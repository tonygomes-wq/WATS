<?php
/**
 * Sistema de Integração com CRMs
 * Suporta: Pipedrive, HubSpot, RD Station, Custom Webhook
 */

class CRMIntegration
{
    private PDO $pdo;
    private int $userId;
    private ?array $integration = null;
    
    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    /**
     * Configura uma integração CRM
     */
    public function configure(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO crm_integrations 
            (user_id, crm_type, api_key, api_secret, webhook_url, sync_contacts, sync_responses)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                api_key = VALUES(api_key),
                api_secret = VALUES(api_secret),
                webhook_url = VALUES(webhook_url),
                sync_contacts = VALUES(sync_contacts),
                sync_responses = VALUES(sync_responses)
        ");
        
        $stmt->execute([
            $this->userId,
            $data['crm_type'],
            $data['api_key'],
            $data['api_secret'] ?? null,
            $data['webhook_url'] ?? null,
            $data['sync_contacts'] ?? true,
            $data['sync_responses'] ?? true
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Obtém integração ativa
     */
    public function getIntegration(): ?array
    {
        if ($this->integration) {
            return $this->integration;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM crm_integrations
            WHERE user_id = ? AND sync_enabled = TRUE
            LIMIT 1
        ");
        
        $stmt->execute([$this->userId]);
        $this->integration = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
        return $this->integration;
    }
    
    /**
     * Sincroniza contato com CRM
     */
    public function syncContact(array $contact): array
    {
        $integration = $this->getIntegration();
        if (!$integration || !$integration['sync_contacts']) {
            return ['success' => false, 'error' => 'Integração não configurada'];
        }
        
        $result = [];
        
        switch ($integration['crm_type']) {
            case 'pipedrive':
                $result = $this->syncToPipedrive($contact, 'contact');
                break;
            case 'hubspot':
                $result = $this->syncToHubspot($contact, 'contact');
                break;
            case 'rd_station':
                $result = $this->syncToRDStation($contact, 'contact');
                break;
            case 'custom':
                $result = $this->syncToCustomWebhook($contact, 'contact');
                break;
            default:
                $result = ['success' => false, 'error' => 'CRM não suportado'];
        }
        
        $this->logSync($integration['id'], 'contact', 'to_crm', $result);
        
        return $result;
    }
    
    /**
     * Sincroniza resposta com CRM
     */
    public function syncResponse(array $response): array
    {
        $integration = $this->getIntegration();
        if (!$integration || !$integration['sync_responses']) {
            return ['success' => false, 'error' => 'Integração não configurada'];
        }
        
        $result = [];
        
        switch ($integration['crm_type']) {
            case 'pipedrive':
                $result = $this->syncToPipedrive($response, 'response');
                break;
            case 'hubspot':
                $result = $this->syncToHubspot($response, 'response');
                break;
            case 'rd_station':
                $result = $this->syncToRDStation($response, 'response');
                break;
            case 'custom':
                $result = $this->syncToCustomWebhook($response, 'response');
                break;
            default:
                $result = ['success' => false, 'error' => 'CRM não suportado'];
        }
        
        $this->logSync($integration['id'], 'response', 'to_crm', $result);
        
        return $result;
    }
    
    /**
     * Sincroniza com Pipedrive
     */
    private function syncToPipedrive(array $data, string $type): array
    {
        $integration = $this->getIntegration();
        $apiKey = $integration['api_key'];
        $baseUrl = 'https://api.pipedrive.com/v1';
        
        if ($type === 'contact') {
            // Criar/atualizar pessoa no Pipedrive
            $personData = [
                'name' => $data['name'] ?? 'Contato WhatsApp',
                'phone' => [['value' => $data['phone'], 'primary' => true]],
                'visible_to' => 3 // Visível para todos
            ];
            
            $response = $this->makeRequest(
                "{$baseUrl}/persons?api_token={$apiKey}",
                'POST',
                $personData
            );
            
            return $response;
            
        } elseif ($type === 'response') {
            // Criar nota/atividade no Pipedrive
            $noteData = [
                'content' => "Resposta WhatsApp: {$data['message_text']}\nSentimento: {$data['sentiment']}",
                'pinned_to_person_flag' => 1
            ];
            
            // Buscar pessoa pelo telefone
            $searchResponse = $this->makeRequest(
                "{$baseUrl}/persons/search?term={$data['phone']}&api_token={$apiKey}",
                'GET'
            );
            
            if ($searchResponse['success'] && !empty($searchResponse['data']['items'])) {
                $personId = $searchResponse['data']['items'][0]['item']['id'];
                $noteData['person_id'] = $personId;
                
                return $this->makeRequest(
                    "{$baseUrl}/notes?api_token={$apiKey}",
                    'POST',
                    $noteData
                );
            }
            
            return ['success' => false, 'error' => 'Pessoa não encontrada no Pipedrive'];
        }
        
        return ['success' => false, 'error' => 'Tipo não suportado'];
    }
    
    /**
     * Sincroniza com HubSpot
     */
    private function syncToHubspot(array $data, string $type): array
    {
        $integration = $this->getIntegration();
        $apiKey = $integration['api_key'];
        $baseUrl = 'https://api.hubapi.com';
        
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];
        
        if ($type === 'contact') {
            $contactData = [
                'properties' => [
                    'firstname' => $data['name'] ?? 'Contato',
                    'phone' => $data['phone'],
                    'hs_lead_status' => 'NEW'
                ]
            ];
            
            return $this->makeRequest(
                "{$baseUrl}/crm/v3/objects/contacts",
                'POST',
                $contactData,
                $headers
            );
            
        } elseif ($type === 'response') {
            // Criar nota no HubSpot
            $noteData = [
                'properties' => [
                    'hs_note_body' => "Resposta WhatsApp: {$data['message_text']}\nSentimento: {$data['sentiment']}",
                    'hs_timestamp' => time() * 1000
                ]
            ];
            
            return $this->makeRequest(
                "{$baseUrl}/crm/v3/objects/notes",
                'POST',
                $noteData,
                $headers
            );
        }
        
        return ['success' => false, 'error' => 'Tipo não suportado'];
    }
    
    /**
     * Sincroniza com RD Station
     */
    private function syncToRDStation(array $data, string $type): array
    {
        $integration = $this->getIntegration();
        $apiKey = $integration['api_key'];
        $baseUrl = 'https://api.rd.services';
        
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];
        
        if ($type === 'contact') {
            $contactData = [
                'event_type' => 'CONVERSION',
                'event_family' => 'CDP',
                'payload' => [
                    'conversion_identifier' => 'whatsapp-contact',
                    'name' => $data['name'] ?? 'Contato WhatsApp',
                    'mobile_phone' => $data['phone'],
                    'cf_origem' => 'WhatsApp WATS'
                ]
            ];
            
            return $this->makeRequest(
                "{$baseUrl}/platform/events",
                'POST',
                $contactData,
                $headers
            );
            
        } elseif ($type === 'response') {
            $eventData = [
                'event_type' => 'CONVERSION',
                'event_family' => 'CDP',
                'payload' => [
                    'conversion_identifier' => 'whatsapp-response',
                    'mobile_phone' => $data['phone'],
                    'cf_mensagem' => $data['message_text'],
                    'cf_sentimento' => $data['sentiment']
                ]
            ];
            
            return $this->makeRequest(
                "{$baseUrl}/platform/events",
                'POST',
                $eventData,
                $headers
            );
        }
        
        return ['success' => false, 'error' => 'Tipo não suportado'];
    }
    
    /**
     * Sincroniza com Webhook customizado
     */
    private function syncToCustomWebhook(array $data, string $type): array
    {
        $integration = $this->getIntegration();
        $webhookUrl = $integration['webhook_url'];
        
        if (empty($webhookUrl)) {
            return ['success' => false, 'error' => 'URL do webhook não configurada'];
        }
        
        $payload = [
            'type' => $type,
            'timestamp' => date('c'),
            'data' => $data
        ];
        
        return $this->makeRequest($webhookUrl, 'POST', $payload);
    }
    
    /**
     * Faz requisição HTTP
     */
    private function makeRequest(string $url, string $method, array $data = null, array $headers = []): array
    {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $defaultHeaders = ['Content-Type: application/json'];
        $allHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        
        $responseData = json_decode($response, true);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $responseData
        ];
    }
    
    /**
     * Registra log de sincronização
     */
    private function logSync(int $integrationId, string $type, string $direction, array $result): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO crm_sync_log 
            (integration_id, sync_type, direction, status, records_processed, error_message)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $integrationId,
            $type,
            $direction,
            $result['success'] ? 'success' : 'failed',
            $result['success'] ? 1 : 0,
            $result['error'] ?? null
        ]);
    }
    
    /**
     * Obtém logs de sincronização
     */
    public function getSyncLogs(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT csl.*, ci.crm_type
            FROM crm_sync_log csl
            JOIN crm_integrations ci ON csl.integration_id = ci.id
            WHERE ci.user_id = ?
            ORDER BY csl.synced_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$this->userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Testa conexão com CRM
     */
    public function testConnection(): array
    {
        $integration = $this->getIntegration();
        if (!$integration) {
            return ['success' => false, 'error' => 'Nenhuma integração configurada'];
        }
        
        switch ($integration['crm_type']) {
            case 'pipedrive':
                $result = $this->makeRequest(
                    "https://api.pipedrive.com/v1/users/me?api_token={$integration['api_key']}",
                    'GET'
                );
                break;
            case 'hubspot':
                $result = $this->makeRequest(
                    'https://api.hubapi.com/crm/v3/objects/contacts?limit=1',
                    'GET',
                    null,
                    ['Authorization: Bearer ' . $integration['api_key']]
                );
                break;
            case 'custom':
                $result = ['success' => true, 'message' => 'Webhook configurado'];
                break;
            default:
                $result = ['success' => false, 'error' => 'CRM não suportado'];
        }
        
        return $result;
    }
    
    /**
     * Desabilita integração
     */
    public function disable(): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE crm_integrations SET sync_enabled = FALSE
            WHERE user_id = ?
        ");
        
        return $stmt->execute([$this->userId]);
    }
}
