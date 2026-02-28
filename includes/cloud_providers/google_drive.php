<?php
/**
 * Integração Google Drive para Backup
 * 
 * Gerencia autenticação OAuth2 e upload de arquivos para Google Drive
 * 
 * MACIP Tecnologia LTDA
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__DIR__)));
}

class GoogleDriveBackup {
    
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $accessToken;
    private $refreshToken;
    private $tokenExpiry;
    
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';
    const FILES_URL = 'https://www.googleapis.com/drive/v3/files';
    
    const SCOPES = 'https://www.googleapis.com/auth/drive.file';
    
    public function __construct($credentials = []) {
        $this->clientId = $credentials['client_id'] ?? '';
        $this->clientSecret = $credentials['client_secret'] ?? '';
        $this->redirectUri = $credentials['redirect_uri'] ?? '';
        $this->accessToken = $credentials['access_token'] ?? '';
        $this->refreshToken = $credentials['refresh_token'] ?? '';
        $this->tokenExpiry = $credentials['token_expiry'] ?? 0;
    }
    
    /**
     * Gera URL de autorização OAuth2
     */
    public function getAuthUrl($state = '') {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        ];
        
        return self::AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Troca código de autorização por tokens
     */
    public function exchangeCode($code) {
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri
        ];
        
        $response = $this->httpPost(self::TOKEN_URL, $data);
        
        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            $this->refreshToken = $response['refresh_token'] ?? $this->refreshToken;
            $this->tokenExpiry = time() + ($response['expires_in'] ?? 3600);
            
            return [
                'success' => true,
                'access_token' => $this->accessToken,
                'refresh_token' => $this->refreshToken,
                'token_expiry' => $this->tokenExpiry
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error_description'] ?? $response['error'] ?? 'Erro ao obter token'
        ];
    }
    
    /**
     * Renova access token usando refresh token
     */
    public function refreshAccessToken() {
        if (empty($this->refreshToken)) {
            return ['success' => false, 'error' => 'Refresh token não disponível'];
        }
        
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        $response = $this->httpPost(self::TOKEN_URL, $data);
        
        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            $this->tokenExpiry = time() + ($response['expires_in'] ?? 3600);
            
            return [
                'success' => true,
                'access_token' => $this->accessToken,
                'token_expiry' => $this->tokenExpiry
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error_description'] ?? $response['error'] ?? 'Erro ao renovar token'
        ];
    }
    
    /**
     * Verifica se token está válido, renova se necessário
     */
    public function ensureValidToken() {
        if (empty($this->accessToken)) {
            return ['success' => false, 'error' => 'Não autenticado'];
        }
        
        // Renovar se expirar em menos de 5 minutos
        if ($this->tokenExpiry && $this->tokenExpiry < (time() + 300)) {
            return $this->refreshAccessToken();
        }
        
        return ['success' => true];
    }
    
    /**
     * Faz upload de arquivo para Google Drive
     */
    public function uploadFile($filePath, $fileName, $folderId = null, $mimeType = 'application/octet-stream') {
        $tokenCheck = $this->ensureValidToken();
        if (!$tokenCheck['success']) {
            return $tokenCheck;
        }
        
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'Arquivo não encontrado'];
        }
        
        // Metadados do arquivo
        $metadata = [
            'name' => $fileName
        ];
        
        if ($folderId) {
            $metadata['parents'] = [$folderId];
        }
        
        // Criar arquivo com upload resumable
        $boundary = '-------' . uniqid();
        $delimiter = "\r\n--$boundary\r\n";
        $closeDelimiter = "\r\n--$boundary--";
        
        $fileContent = file_get_contents($filePath);
        
        $body = $delimiter;
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= json_encode($metadata);
        $body .= $delimiter;
        $body .= "Content-Type: $mimeType\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= base64_encode($fileContent);
        $body .= $closeDelimiter;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::UPLOAD_URL . '?uploadType=multipart');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . strlen($body)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return ['success' => false, 'error' => 'Erro de conexão: ' . $curlError];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && isset($data['id'])) {
            return [
                'success' => true,
                'file_id' => $data['id'],
                'file_name' => $data['name'],
                'web_view_link' => $data['webViewLink'] ?? null,
                'web_content_link' => $data['webContentLink'] ?? null
            ];
        }
        
        return [
            'success' => false,
            'error' => $data['error']['message'] ?? 'Erro ao fazer upload'
        ];
    }
    
    /**
     * Cria pasta no Google Drive
     */
    public function createFolder($folderName, $parentFolderId = null) {
        $tokenCheck = $this->ensureValidToken();
        if (!$tokenCheck['success']) {
            return $tokenCheck;
        }
        
        $metadata = [
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'
        ];
        
        if ($parentFolderId) {
            $metadata['parents'] = [$parentFolderId];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::FILES_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metadata));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && isset($data['id'])) {
            return [
                'success' => true,
                'folder_id' => $data['id'],
                'folder_name' => $data['name']
            ];
        }
        
        return [
            'success' => false,
            'error' => $data['error']['message'] ?? 'Erro ao criar pasta'
        ];
    }
    
    /**
     * Lista arquivos em uma pasta
     */
    public function listFiles($folderId = null, $pageSize = 100) {
        $tokenCheck = $this->ensureValidToken();
        if (!$tokenCheck['success']) {
            return $tokenCheck;
        }
        
        $query = "trashed = false";
        if ($folderId) {
            $query .= " and '$folderId' in parents";
        }
        
        $params = [
            'q' => $query,
            'pageSize' => $pageSize,
            'fields' => 'files(id, name, mimeType, size, createdTime, webViewLink)'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::FILES_URL . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'files' => $data['files'] ?? []
            ];
        }
        
        return [
            'success' => false,
            'error' => $data['error']['message'] ?? 'Erro ao listar arquivos'
        ];
    }
    
    /**
     * Deleta arquivo do Google Drive
     */
    public function deleteFile($fileId) {
        $tokenCheck = $this->ensureValidToken();
        if (!$tokenCheck['success']) {
            return $tokenCheck;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::FILES_URL . '/' . $fileId);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 204 || $httpCode === 200) {
            return ['success' => true];
        }
        
        $data = json_decode($response, true);
        return [
            'success' => false,
            'error' => $data['error']['message'] ?? 'Erro ao deletar arquivo'
        ];
    }
    
    /**
     * Retorna tokens atuais para salvar
     */
    public function getTokens() {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'token_expiry' => $this->tokenExpiry
        ];
    }
    
    /**
     * HTTP POST helper
     */
    private function httpPost($url, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?? [];
    }
}

/**
 * Função helper para fazer upload de backup para Google Drive
 */
function uploadBackupToGoogleDrive($backupId, $userId) {
    global $pdo;
    
    // Buscar backup
    $stmt = $pdo->prepare('SELECT * FROM backups WHERE id = ? AND user_id = ?');
    $stmt->execute([$backupId, $userId]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$backup || empty($backup['local_path'])) {
        return ['success' => false, 'error' => 'Backup não encontrado'];
    }
    
    // Buscar configuração com credenciais
    $stmt = $pdo->prepare('SELECT * FROM backup_configs WHERE user_id = ? AND destination = "google_drive"');
    $stmt->execute([$userId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['credentials'])) {
        return ['success' => false, 'error' => 'Google Drive não configurado'];
    }
    
    // Descriptografar credenciais
    $credentials = json_decode($config['credentials'], true);
    if (!$credentials) {
        return ['success' => false, 'error' => 'Credenciais inválidas'];
    }
    
    // Inicializar cliente
    $drive = new GoogleDriveBackup($credentials);
    
    // Determinar mime type
    $ext = pathinfo($backup['filename'], PATHINFO_EXTENSION);
    $mimeTypes = [
        'json' => 'application/json',
        'csv' => 'text/csv',
        'pdf' => 'application/pdf'
    ];
    $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
    
    // Fazer upload
    $result = $drive->uploadFile(
        $backup['local_path'],
        $backup['filename'],
        $credentials['folder_id'] ?? null,
        $mimeType
    );
    
    if ($result['success']) {
        // Atualizar backup com informações do Drive
        $stmt = $pdo->prepare('
            UPDATE backups SET
                destination = "google_drive",
                remote_path = ?,
                remote_url = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $result['file_id'],
            $result['web_view_link'] ?? $result['web_content_link'],
            $backupId
        ]);
        
        // Atualizar tokens se renovados
        $newTokens = $drive->getTokens();
        if ($newTokens['access_token'] !== $credentials['access_token']) {
            $credentials = array_merge($credentials, $newTokens);
            $stmt = $pdo->prepare('UPDATE backup_configs SET credentials = ? WHERE id = ?');
            $stmt->execute([json_encode($credentials), $config['id']]);
        }
        
        // Log
        logBackupAction($backupId, $userId, 'upload_cloud', [
            'provider' => 'google_drive',
            'file_id' => $result['file_id']
        ]);
    }
    
    return $result;
}
