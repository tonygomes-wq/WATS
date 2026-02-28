<?php
/**
 * Classe para gerenciar canal de Email
 * Integração com Microsoft Graph API e IMAP/SMTP
 */

class EmailChannel {
    private $pdo;
    private $channelId;
    private $config;
    
    public function __construct($pdo, $channelId) {
        $this->pdo = $pdo;
        $this->channelId = $channelId;
        $this->loadConfig();
    }
    
    /**
     * Carregar configuração do canal
     */
    private function loadConfig() {
        $stmt = $this->pdo->prepare("
            SELECT c.*, ce.*
            FROM channels c
            INNER JOIN channel_email ce ON c.id = ce.channel_id
            WHERE c.id = ?
        ");
        $stmt->execute([$this->channelId]);
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->config) {
            throw new Exception('Canal de email não encontrado');
        }
    }
    
    /**
     * Buscar novos emails
     */
    public function fetchNewEmails($limit = 50) {
        if ($this->config['auth_method'] === 'oauth') {
            return $this->fetchEmailsViaGraph($limit);
        } else {
            return $this->fetchEmailsViaIMAP($limit);
        }
    }
    
    /**
     * Buscar emails via Microsoft Graph API
     */
    private function fetchEmailsViaGraph($limit = 50) {
        // Verificar e renovar token se necessário
        $accessToken = $this->getValidAccessToken();
        
        if (!$accessToken) {
            throw new Exception('Token de acesso inválido. Reconecte sua conta Microsoft.');
        }
        
        // Buscar emails não lidos
        $url = 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages';
        $params = [
            '$top' => (string)$limit,
            '$filter' => 'isRead eq false',
            '$orderby' => 'receivedDateTime desc'
        ];
        $url .= '?' . http_build_query($params);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Erro ao buscar emails: HTTP ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        return $data['value'] ?? [];
    }
    
    /**
     * Buscar emails via IMAP
     */
    private function fetchEmailsViaIMAP($limit = 50) {
        if (!function_exists('imap_open')) {
            throw new Exception('Extensão IMAP não está instalada no PHP');
        }
        
        $mailbox = sprintf(
            '{%s:%d/imap/%s}INBOX',
            $this->config['imap_host'],
            $this->config['imap_port'],
            $this->config['imap_encryption']
        );
        
        $imap = @imap_open(
            $mailbox,
            $this->config['email'],
            $this->config['password']
        );
        
        if (!$imap) {
            throw new Exception('Erro ao conectar ao servidor IMAP: ' . imap_last_error());
        }
        
        // Buscar emails não lidos
        $emails = [];
        $unreadEmails = imap_search($imap, 'UNSEEN');
        
        if ($unreadEmails) {
            rsort($unreadEmails); // Mais recentes primeiro
            $unreadEmails = array_slice($unreadEmails, 0, $limit);
            
            foreach ($unreadEmails as $emailNumber) {
                $header = imap_headerinfo($imap, $emailNumber);
                $body = imap_fetchbody($imap, $emailNumber, 1);
                
                $emails[] = [
                    'id' => $header->message_id ?? uniqid('email_'),
                    'subject' => $header->subject ?? 'Sem assunto',
                    'from' => [
                        'emailAddress' => [
                            'address' => $header->from[0]->mailbox . '@' . $header->from[0]->host,
                            'name' => $header->from[0]->personal ?? ''
                        ]
                    ],
                    'receivedDateTime' => date('Y-m-d H:i:s', $header->udate),
                    'bodyPreview' => substr(strip_tags($body), 0, 200),
                    'body' => [
                        'content' => $body
                    ]
                ];
            }
        }
        
        imap_close($imap);
        return $emails;
    }
    
    /**
     * Enviar email
     */
    public function sendEmail($to, $subject, $body, $attachments = []) {
        if ($this->config['auth_method'] === 'oauth') {
            return $this->sendEmailViaGraph($to, $subject, $body, $attachments);
        } else {
            return $this->sendEmailViaSMTP($to, $subject, $body, $attachments);
        }
    }
    
    /**
     * Enviar email via Microsoft Graph API
     */
    private function sendEmailViaGraph($to, $subject, $body, $attachments = []) {
        $accessToken = $this->getValidAccessToken();
        
        if (!$accessToken) {
            throw new Exception('Token de acesso inválido');
        }
        
        $message = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $body
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $to
                        ]
                    ]
                ]
            ]
        ];
        
        $url = 'https://graph.microsoft.com/v1.0/me/sendMail';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 202) {
            throw new Exception('Erro ao enviar email: HTTP ' . $httpCode);
        }
        
        return true;
    }
    
    /**
     * Enviar email via SMTP
     */
    private function sendEmailViaSMTP($to, $subject, $body, $attachments = []) {
        require_once __DIR__ . '/../email_sender.php';
        
        $emailSender = new EmailSender($this->pdo, $this->config['user_id']);
        $result = $emailSender->send($to, $subject, $body, true);
        
        if (!$result['success']) {
            throw new Exception($result['error']);
        }
        
        return true;
    }
    
    /**
     * Obter token de acesso válido (renovar se necessário)
     */
    private function getValidAccessToken() {
        $expiresAt = strtotime($this->config['oauth_token_expires_at']);
        
        // Se token ainda é válido (com 5 minutos de margem)
        if ($expiresAt - time() > 300) {
            return $this->config['oauth_access_token'];
        }
        
        // Token expirado, renovar
        return $this->refreshAccessToken();
    }
    
    /**
     * Renovar token de acesso
     */
    private function refreshAccessToken() {
        if (empty($this->config['oauth_refresh_token'])) {
            return null;
        }
        
        $tokenUrl = sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
            $this->config['oauth_tenant_id'] ?? 'common'
        );
        
        $data = [
            'client_id' => $this->config['oauth_client_id'],
            'client_secret' => $this->config['oauth_client_secret'],
            'refresh_token' => $this->config['oauth_refresh_token'],
            'grant_type' => 'refresh_token',
            'scope' => 'https://graph.microsoft.com/Mail.Read https://graph.microsoft.com/Mail.Send offline_access'
        ];
        
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (!isset($result['access_token'])) {
            error_log('[EmailChannel] Erro ao renovar token: ' . ($result['error_description'] ?? 'Unknown error'));
            return null;
        }
        
        // Atualizar tokens no banco
        $stmt = $this->pdo->prepare("
            UPDATE channel_email 
            SET oauth_access_token = ?, 
                oauth_refresh_token = ?, 
                oauth_token_expires_at = ?
            WHERE channel_id = ?
        ");
        
        $newExpiresAt = date('Y-m-d H:i:s', time() + ($result['expires_in'] ?? 3600));
        $stmt->execute([
            $result['access_token'],
            $result['refresh_token'] ?? $this->config['oauth_refresh_token'],
            $newExpiresAt,
            $this->channelId
        ]);
        
        // Atualizar config local
        $this->config['oauth_access_token'] = $result['access_token'];
        $this->config['oauth_token_expires_at'] = $newExpiresAt;
        
        return $result['access_token'];
    }
    
    /**
     * Validar credenciais
     */
    public function validateCredentials() {
        if ($this->config['auth_method'] === 'oauth') {
            $accessToken = $this->getValidAccessToken();
            return ['success' => !empty($accessToken), 'email' => $this->config['email']];
        } else {
            // Testar conexão IMAP
            try {
                $this->fetchEmailsViaIMAP(1);
                return ['success' => true, 'email' => $this->config['email']];
            } catch (Exception $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }
    }
}
