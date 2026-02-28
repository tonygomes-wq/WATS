<?php
/**
 * Classe para Criptografia de Tokens Sensíveis
 * Utiliza AES-256-GCM para máxima segurança
 */

class TokenEncryption
{
    private string $encryptionKey;
    private string $cipher = 'aes-256-gcm';

    public function __construct()
    {
        $this->encryptionKey = $this->getEncryptionKey();
    }

    /**
     * Obtém a chave de criptografia do .env ou gera uma nova
     */
    private function getEncryptionKey(): string
    {
        // Tentar obter do .env
        $envKey = getenv('ENCRYPTION_KEY') ?: ($_ENV['ENCRYPTION_KEY'] ?? null);
        
        if ($envKey && strpos($envKey, 'base64:') === 0) {
            return base64_decode(substr($envKey, 7));
        }

        // Se não existir, gerar uma nova (deve ser salva no .env)
        $newKey = random_bytes(32);
        error_log('[TOKEN_ENCRYPTION] ATENÇÃO: Chave de criptografia gerada. Adicione ao .env: ENCRYPTION_KEY=base64:' . base64_encode($newKey));
        
        return $newKey;
    }

    /**
     * Criptografa um token
     * 
     * @param string|null $plaintext Token em texto plano
     * @return string|null Token criptografado (formato: iv:tag:ciphertext)
     */
    public function encrypt(?string $plaintext): ?string
    {
        if (empty($plaintext)) {
            return null;
        }

        try {
            $ivLength = openssl_cipher_iv_length($this->cipher);
            $iv = openssl_random_pseudo_bytes($ivLength);
            $tag = '';

            $ciphertext = openssl_encrypt(
                $plaintext,
                $this->cipher,
                $this->encryptionKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                16
            );

            if ($ciphertext === false) {
                error_log('[TOKEN_ENCRYPTION] Erro ao criptografar: ' . openssl_error_string());
                return null;
            }

            // Formato: iv:tag:ciphertext (tudo em base64)
            return base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);
        } catch (Exception $e) {
            error_log('[TOKEN_ENCRYPTION] Exceção ao criptografar: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Descriptografa um token
     * 
     * @param string|null $encrypted Token criptografado
     * @return string|null Token em texto plano
     */
    public function decrypt(?string $encrypted): ?string
    {
        if (empty($encrypted)) {
            return null;
        }

        // Se não contém ':', pode ser um token não criptografado (legacy)
        if (strpos($encrypted, ':') === false) {
            error_log('[TOKEN_ENCRYPTION] Token não criptografado detectado (legacy)');
            return $encrypted;
        }

        try {
            $parts = explode(':', $encrypted, 3);
            
            if (count($parts) !== 3) {
                error_log('[TOKEN_ENCRYPTION] Formato inválido de token criptografado');
                return null;
            }

            list($iv, $tag, $ciphertext) = $parts;
            
            $iv = base64_decode($iv);
            $tag = base64_decode($tag);
            $ciphertext = base64_decode($ciphertext);

            $plaintext = openssl_decrypt(
                $ciphertext,
                $this->cipher,
                $this->encryptionKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($plaintext === false) {
                error_log('[TOKEN_ENCRYPTION] Erro ao descriptografar: ' . openssl_error_string());
                return null;
            }

            return $plaintext;
        } catch (Exception $e) {
            error_log('[TOKEN_ENCRYPTION] Exceção ao descriptografar: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verifica se um token está criptografado
     */
    public function isEncrypted(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        // Tokens criptografados têm o formato iv:tag:ciphertext
        return strpos($token, ':') !== false && substr_count($token, ':') === 2;
    }

    /**
     * Migra tokens não criptografados para criptografados
     * 
     * @param PDO $pdo Conexão com banco de dados
     * @return array Estatísticas da migração
     */
    public function migrateTokens(PDO $pdo): array
    {
        $stats = [
            'total' => 0,
            'migrated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];

        try {
            // Buscar todos os usuários com tokens Meta
            $stmt = $pdo->query("
                SELECT id, meta_permanent_token, meta_app_secret, evolution_token
                FROM users
                WHERE (meta_permanent_token IS NOT NULL AND meta_permanent_token != '')
                   OR (meta_app_secret IS NOT NULL AND meta_app_secret != '')
                   OR (evolution_token IS NOT NULL AND evolution_token != '')
            ");

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stats['total'] = count($users);

            foreach ($users as $user) {
                $updates = [];
                $params = [];

                // Meta Permanent Token
                if (!empty($user['meta_permanent_token']) && !$this->isEncrypted($user['meta_permanent_token'])) {
                    $encrypted = $this->encrypt($user['meta_permanent_token']);
                    if ($encrypted) {
                        $updates[] = 'meta_permanent_token = ?';
                        $params[] = $encrypted;
                        $stats['migrated']++;
                    } else {
                        $stats['errors']++;
                    }
                } else {
                    $stats['skipped']++;
                }

                // Meta App Secret
                if (!empty($user['meta_app_secret']) && !$this->isEncrypted($user['meta_app_secret'])) {
                    $encrypted = $this->encrypt($user['meta_app_secret']);
                    if ($encrypted) {
                        $updates[] = 'meta_app_secret = ?';
                        $params[] = $encrypted;
                        $stats['migrated']++;
                    } else {
                        $stats['errors']++;
                    }
                } else {
                    $stats['skipped']++;
                }

                // Evolution Token
                if (!empty($user['evolution_token']) && !$this->isEncrypted($user['evolution_token'])) {
                    $encrypted = $this->encrypt($user['evolution_token']);
                    if ($encrypted) {
                        $updates[] = 'evolution_token = ?';
                        $params[] = $encrypted;
                        $stats['migrated']++;
                    } else {
                        $stats['errors']++;
                    }
                } else {
                    $stats['skipped']++;
                }

                // Atualizar se houver mudanças
                if (!empty($updates)) {
                    $params[] = $user['id'];
                    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
                    $updateStmt = $pdo->prepare($sql);
                    $updateStmt->execute($params);
                }
            }

            error_log('[TOKEN_ENCRYPTION] Migração concluída: ' . json_encode($stats));
        } catch (Exception $e) {
            error_log('[TOKEN_ENCRYPTION] Erro na migração: ' . $e->getMessage());
            $stats['errors']++;
        }

        return $stats;
    }
}
