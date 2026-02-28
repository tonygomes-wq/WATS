<?php
/**
 * Validador de Webhooks da Meta API
 * Implementa validação HMAC SHA-256 para segurança
 */

class MetaWebhookValidator
{
    /**
     * Valida a assinatura HMAC do webhook da Meta
     * 
     * @param string $payload Payload bruto da requisição
     * @param string $signature Assinatura do header X-Hub-Signature-256
     * @param string $appSecret App Secret da Meta
     * @return bool True se válido, False caso contrário
     */
    public static function validateSignature(string $payload, string $signature, string $appSecret): bool
    {
        if (empty($signature) || empty($appSecret)) {
            error_log('[META_WEBHOOK] Assinatura ou App Secret vazio');
            return false;
        }

        // Meta envia no formato: sha256=<hash>
        if (strpos($signature, 'sha256=') !== 0) {
            error_log('[META_WEBHOOK] Formato de assinatura inválido');
            return false;
        }

        // Remover prefixo 'sha256='
        $receivedHash = substr($signature, 7);

        // Calcular hash esperado
        $expectedHash = hash_hmac('sha256', $payload, $appSecret);

        // Comparação segura contra timing attacks
        $isValid = hash_equals($expectedHash, $receivedHash);

        if (!$isValid) {
            error_log('[META_WEBHOOK] Assinatura HMAC inválida');
            error_log('[META_WEBHOOK] Recebido: ' . substr($receivedHash, 0, 10) . '...');
            error_log('[META_WEBHOOK] Esperado: ' . substr($expectedHash, 0, 10) . '...');
        }

        return $isValid;
    }

    /**
     * Valida token de verificação do webhook (GET request)
     * 
     * @param string $mode hub.mode da requisição
     * @param string $token hub.verify_token da requisição
     * @param string $challenge hub.challenge da requisição
     * @param PDO $pdo Conexão com banco de dados
     * @return array ['valid' => bool, 'challenge' => string|null, 'user_id' => int|null]
     */
    public static function validateVerification(string $mode, string $token, string $challenge, PDO $pdo): array
    {
        if ($mode !== 'subscribe') {
            return ['valid' => false, 'challenge' => null, 'user_id' => null];
        }

        if (empty($token) || empty($challenge)) {
            return ['valid' => false, 'challenge' => null, 'user_id' => null];
        }

        // Buscar usuário com este verify token
        $stmt = $pdo->prepare("
            SELECT id 
            FROM users 
            WHERE meta_webhook_verify_token = ? 
            AND whatsapp_provider = 'meta'
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log('[META_WEBHOOK] Token de verificação não encontrado: ' . substr($token, 0, 10) . '...');
            return ['valid' => false, 'challenge' => null, 'user_id' => null];
        }

        return [
            'valid' => true,
            'challenge' => $challenge,
            'user_id' => $user['id']
        ];
    }

    /**
     * Extrai App Secret do usuário baseado no phone_number_id
     * 
     * @param array $payload Payload do webhook
     * @param PDO $pdo Conexão com banco de dados
     * @return string|null App Secret ou null se não encontrado
     */
    public static function getAppSecretFromPayload(array $payload, PDO $pdo): ?string
    {
        // Tentar extrair phone_number_id do payload
        $phoneNumberId = null;

        if (isset($payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'])) {
            $phoneNumberId = $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'];
        }

        if (!$phoneNumberId) {
            error_log('[META_WEBHOOK] Phone number ID não encontrado no payload');
            return null;
        }

        // Buscar usuário com este phone_number_id
        $stmt = $pdo->prepare("
            SELECT meta_app_secret 
            FROM users 
            WHERE meta_phone_number_id = ? 
            AND whatsapp_provider = 'meta'
            LIMIT 1
        ");
        $stmt->execute([$phoneNumberId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['meta_app_secret'])) {
            error_log('[META_WEBHOOK] App Secret não encontrado para phone_number_id: ' . $phoneNumberId);
            return null;
        }

        return $user['meta_app_secret'];
    }

    /**
     * Registra tentativa de webhook inválido para monitoramento
     * 
     * @param PDO $pdo Conexão com banco de dados
     * @param string $reason Razão da falha
     * @param array $metadata Metadados adicionais
     */
    public static function logInvalidWebhook(PDO $pdo, string $reason, array $metadata = []): void
    {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO webhook_security_logs 
                (event_type, reason, ip_address, user_agent, metadata, created_at)
                VALUES ('invalid_webhook', ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $reason,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                json_encode($metadata)
            ]);
        } catch (Exception $e) {
            error_log('[META_WEBHOOK] Erro ao registrar webhook inválido: ' . $e->getMessage());
        }
    }
}
