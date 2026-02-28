<?php
/**
 * Classe base abstrata para todos os canais
 * Implementa funcionalidades comuns e padrões
 */

require_once __DIR__ . '/ChannelInterface.php';

abstract class BaseChannel implements ChannelInterface
{
    protected PDO $pdo;
    protected int $channelId;
    protected int $userId;
    protected array $config;
    protected string $channelType;
    
    public function __construct(PDO $pdo, int $channelId)
    {
        $this->pdo = $pdo;
        $this->channelId = $channelId;
        $this->loadConfig();
    }
    
    /**
     * Carrega configuração do canal do banco de dados
     */
    protected function loadConfig(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM channels 
            WHERE id = ?
        ");
        $stmt->execute([$this->channelId]);
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->config) {
            throw new Exception("Canal não encontrado: {$this->channelId}");
        }
        
        $this->userId = $this->config['user_id'];
        $this->channelType = $this->config['channel_type'];
    }
    
    /**
     * Registra atividade do canal
     */
    protected function logActivity(string $action, array $data, string $status = 'success', ?string $errorMessage = null): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO channel_activity_logs 
            (channel_id, action, data, status, error_message, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $this->channelId,
            $action,
            json_encode($data),
            $status,
            $errorMessage
        ]);
    }
    
    /**
     * Cria ou atualiza contato
     */
    protected function createOrUpdateContact(array $contactData): int
    {
        $source = $contactData['source'] ?? $this->channelType;
        $sourceId = $contactData['source_id'];
        $name = $contactData['name'] ?? 'Sem nome';
        $phone = $contactData['phone'] ?? $sourceId;
        
        // Verificar se contato já existe
        $stmt = $this->pdo->prepare("
            SELECT id FROM contacts 
            WHERE source = ? AND source_id = ? AND user_id = ?
        ");
        $stmt->execute([$source, $sourceId, $this->userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Atualizar contato existente
            $stmt = $this->pdo->prepare("
                UPDATE contacts 
                SET name = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $existing['id']]);
            return $existing['id'];
        }
        
        // Criar novo contato
        $stmt = $this->pdo->prepare("
            INSERT INTO contacts 
            (user_id, name, phone, source, source_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $this->userId,
            $name,
            $phone,
            $source,
            $sourceId
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Cria mensagem no banco de dados
     */
    protected function createMessage(array $messageData): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO messages 
            (user_id, contact_id, channel_id, channel_type, external_id, 
             message_text, from_me, message_type, status, additional_data, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $this->userId,
            $messageData['contact_id'],
            $this->channelId,
            $this->channelType,
            $messageData['external_id'] ?? null,
            $messageData['message_text'] ?? '',
            $messageData['from_me'] ? 1 : 0,
            $messageData['message_type'] ?? 'text',
            $messageData['status'] ?? 'sent',
            json_encode($messageData['additional_data'] ?? [])
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Atualiza status do canal
     */
    protected function updateChannelStatus(string $status, ?string $errorMessage = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE channels 
            SET status = ?, error_message = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $errorMessage, $this->channelId]);
    }
    
    /**
     * Atualiza última sincronização
     */
    protected function updateLastSync(): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE channels 
            SET last_sync_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$this->channelId]);
    }
    
    /**
     * Faz requisição HTTP
     */
    protected function makeHttpRequest(string $url, string $method = 'GET', ?array $data = null, ?array $headers = []): array
    {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $response ? json_decode($response, true) : null,
            'error' => $error
        ];
    }
    
    /**
     * Implementação padrão para envio de anexo
     */
    public function sendAttachment(array $attachment): array
    {
        return [
            'success' => false,
            'error' => 'Anexos não suportados neste canal'
        ];
    }
    
    /**
     * Implementação padrão para marcar como lido
     */
    public function markAsRead(string $externalId): bool
    {
        return false;
    }
}
