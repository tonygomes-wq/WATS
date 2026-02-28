<?php
/**
 * Integração Dropbox para Backup
 * 
 * Gerencia autenticação OAuth2 e upload de arquivos para Dropbox
 * 
 * MACIP Tecnologia LTDA
 */

class DropboxBackup {
    
    private $appKey;
    private $appSecret;
    private $redirectUri;
    private $accessToken;
    private $refreshToken;
    private $tokenExpiry;
    
    const AUTH_URL = 'https://www.dropbox.com/oauth2/authorize';
    const TOKEN_URL = 'https://api.dropboxapi.com/oauth2/token';
    const UPLOAD_URL = 'https://content.dropboxapi.com/2/files/upload';
    const UPLOAD_SESSION_START = 'https://content.dropboxapi.com/2/files/upload_session/start';
    const UPLOAD_SESSION_APPEND = 'https://content.dropboxapi.com/2/files/upload_session/append_v2';
    const UPLOAD_SESSION_FINISH = 'https://content.dropboxapi.com/2/files/upload_session/finish';
    
    public function __construct($credentials = []) {
        $this->appKey = $credentials['app_key'] ?? '';
        $this->appSecret = $credentials['app_secret'] ?? '';
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
            'client_id' => $this->appKey,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'token_access_type' => 'offline',
            'state' => $state
        ];
        
        return self::AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Troca código de autorização por tokens
     */
    public function exchangeCode($code) {
        $data = [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri
        ];
        
        $response = $this->httpPostWithAuth(self::TOKEN_URL, $data);
        
        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            $this->refreshToken = $response['refresh_token'] ?? null;
            $this->tokenExpiry = $response['expires_in'] ? time() + $response['expires_in'] : null;
            
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
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        $response = $this->httpPostWithAuth(self::TOKEN_URL, $data);
        
        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            $this->tokenExpiry = $response['expires_in'] ? time() + $response['expires_in'] : null;
            
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
        
        if ($this->tokenExpiry && $this->tokenExpiry < (time() + 300)) {
            return $this->refreshAccessToken();
        }
        
        return ['success' => true];
    }
    
    /**
     * Faz upload de arquivo para Dropbox
     */
    public function uploadFile($filePath, $fileName, $folderPath = '/WATS_Backups') {
        $tokenCheck = $this->ensureValidToken();
        if (!$tokenCheck['success']) {
            return $tokenCheck;
        }
        
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'Arquivo não encontrado'];
        }
        
        $fileSize = filesize($filePath);
        $dropboxPath = $folderPath . '/' . $fileName;
        
        // Para arquivos pequenos (< 150MB), usar upload simples
        if ($fileSize < 150 * 1024 * 1024) {
            return $this->simpleUpload($filePath, $dropboxPath);
        }
        
        // Para arquivos grandes, usar upload em sessão
        return $this->sessionUpload($filePath, $dropboxPath);
    }
    
    /**
     * Upload direto de conteúdo (sem arquivo local)
     */
    public function uploadContent($content, $fileName, $folderPath = '/WATS_Backups') {
        $tokenCheck = $this->ensureValidToken();
        if (!$tokenCheck['success']) {
            return $tokenCheck;
        }
        
        $dropboxPath = $folderPath . '/' . $fileName;
        
        $args = json_encode([
            'path' => $dropboxPath,
            'mode' => 'overwrite',
            'autorename' => false,
            'mute' => false
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::UPLOAD_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Dropbox-API-Arg: ' . $args,
            'Content-Type: application/octet-stream'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && isset($data['id'])) {
            return [
                'success' => true,
                'file_id' => $data['id'],
                'file_name' => $data['name'],
                'path' => $data['path_display']
            ];
        }
        
        return [
            'success' => false,
            'error' => $data['error_summary'] ?? $data['error']['.tag'] ?? 'Erro ao fazer upload'
        ];
    }
    
    private function simpleUpload($filePath, $dropboxPath) {
        $content = file_get_contents($filePath);
        $fileName = basename($dropboxPath);
        $folderPath = dirname($dropboxPath);
        return $this->uploadContent($content, $fileName, $folderPath);
    }
    
    private function sessionUpload($filePath, $dropboxPath) {
        $chunkSize = 8 * 1024 * 1024; // 8MB chunks
        $handle = fopen($filePath, 'rb');
        $fileSize = filesize($filePath);
        $offset = 0;
        $sessionId = null;
        
        // Iniciar sessão
        $chunk = fread($handle, $chunkSize);
        $chunkLength = strlen($chunk);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::UPLOAD_SESSION_START);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $chunk);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Dropbox-API-Arg: {"close": false}',
            'Content-Type: application/octet-stream'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if (!isset($data['session_id'])) {
            fclose($handle);
            return ['success' => false, 'error' => 'Erro ao iniciar sessão de upload'];
        }
        
        $sessionId = $data['session_id'];
        $offset = $chunkLength;
        
        // Continuar upload em chunks
        while ($offset < $fileSize) {
            $chunk = fread($handle, $chunkSize);
            $chunkLength = strlen($chunk);
            $isLast = ($offset + $chunkLength >= $fileSize);
            
            if ($isLast) {
                // Finalizar sessão
                $args = json_encode([
                    'cursor' => [
                        'session_id' => $sessionId,
                        'offset' => $offset
                    ],
                    'commit' => [
                        'path' => $dropboxPath,
                        'mode' => 'overwrite',
                        'autorename' => false,
                        'mute' => false
                    ]
                ]);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, self::UPLOAD_SESSION_FINISH);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $chunk);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $this->accessToken,
                    'Dropbox-API-Arg: ' . $args,
                    'Content-Type: application/octet-stream'
                ]);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                $data = json_decode($response, true);
                fclose($handle);
                
                if (isset($data['id'])) {
                    return [
                        'success' => true,
                        'file_id' => $data['id'],
                        'file_name' => $data['name'],
                        'path' => $data['path_display']
                    ];
                }
                
                return ['success' => false, 'error' => 'Erro ao finalizar upload'];
            } else {
                // Append chunk
                $args = json_encode([
                    'cursor' => [
                        'session_id' => $sessionId,
                        'offset' => $offset
                    ],
                    'close' => false
                ]);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, self::UPLOAD_SESSION_APPEND);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $chunk);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $this->accessToken,
                    'Dropbox-API-Arg: ' . $args,
                    'Content-Type: application/octet-stream'
                ]);
                
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode >= 400) {
                    fclose($handle);
                    return ['success' => false, 'error' => 'Erro durante upload: HTTP ' . $httpCode];
                }
            }
            
            $offset += $chunkLength;
        }
        
        fclose($handle);
        return ['success' => false, 'error' => 'Erro inesperado no upload'];
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
    
    private function httpPostWithAuth($url, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->appKey . ':' . $this->appSecret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?? [];
    }
}
