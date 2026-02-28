<?php
/**
 * Integração OneDrive para Backup
 * 
 * Gerencia autenticação OAuth2 e upload de arquivos para OneDrive
 * 
 * MACIP Tecnologia LTDA
 */

class OneDriveBackup {
    
    private $clientId;
    private $clientSecret;
    private $tenantId;
    private $redirectUri;
    private $accessToken;
    private $refreshToken;
    private $tokenExpiry;
    
    const TOKEN_URL_TEMPLATE = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';
    const AUTH_URL_TEMPLATE = 'https://login.microsoftonline.com/%s/oauth2/v2.0/authorize';
    const GRAPH_URL = 'https://graph.microsoft.com/v1.0';
    
    const SCOPES = 'offline_access Files.ReadWrite';
    
    public function __construct($credentials = []) {
        $this->clientId = $credentials['client_id'] ?? '';
        $this->clientSecret = $credentials['client_secret'] ?? '';
        $this->tenantId = $credentials['tenant_id'] ?? 'common';
        $this->redirectUri = $credentials['redirect_uri'] ?? '';
        $this->accessToken = $credentials['access_token'] ?? '';
        $this->refreshToken = $credentials['refresh_token'] ?? '';
        $this->tokenExpiry = $credentials['token_expiry'] ?? 0;
    }
    
    /**
     * Gera URL de autorização OAuth2
     */
    public function getAuthUrl($state = '') {
        $authUrl = sprintf(self::AUTH_URL_TEMPLATE, $this->tenantId);
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'response_mode' => 'query',
            'state' => $state
        ];
        
        return $authUrl . '?' . http_build_query($params);
    }
    
    /**
     * Troca código de autorização por tokens
     */
    public function exchangeCode($code) {
        $tokenUrl = sprintf(self::TOKEN_URL_TEMPLATE, $this->tenantId);
        
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
            'scope' => self::SCOPES
        ];
        
        $response = $this->httpPost($tokenUrl, $data);
        
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
        
        $tokenUrl = sprintf(self::TOKEN_URL_TEMPLATE, $this->tenantId);
        
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
            'scope' => self::SCOPES
        ];
        
        $response = $this->httpPost($tokenUrl, $data);
        
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
     * Faz upload de arquivo para OneDrive (streaming direto, sem salvar no servidor)
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
        
        // Para arquivos pequenos (< 4MB), usar upload simples
        if ($fileSize < 4 * 1024 * 1024) {
            return $this->simpleUpload($filePath, $fileName, $folderPath);
        }
        
        // Para arquivos grandes, usar upload em sessão
        return $this->resumableUpload($filePath, $fileName, $folderPath);
    }
    
    /**
     * Upload direto de conteúdo (sem arquivo local)
     */
    public function uploadContent($content, $fileName, $folderPath = '/WATS_Backups') {
        $tokenCheck = $this->ensureValidToken();
        if (!$tokenCheck['success']) {
            return $tokenCheck;
        }
        
        $encodedPath = rawurlencode($folderPath . '/' . $fileName);
        $url = self::GRAPH_URL . '/me/drive/root:' . $encodedPath . ':/content';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
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
                'web_url' => $data['webUrl'] ?? null
            ];
        }
        
        return [
            'success' => false,
            'error' => $data['error']['message'] ?? 'Erro ao fazer upload'
        ];
    }
    
    private function simpleUpload($filePath, $fileName, $folderPath) {
        $content = file_get_contents($filePath);
        return $this->uploadContent($content, $fileName, $folderPath);
    }
    
    private function resumableUpload($filePath, $fileName, $folderPath) {
        // Criar sessão de upload
        $encodedPath = rawurlencode($folderPath . '/' . $fileName);
        $url = self::GRAPH_URL . '/me/drive/root:' . $encodedPath . ':/createUploadSession';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'item' => ['@microsoft.graph.conflictBehavior' => 'replace']
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $session = json_decode($response, true);
        
        if (!isset($session['uploadUrl'])) {
            return ['success' => false, 'error' => 'Erro ao criar sessão de upload'];
        }
        
        // Upload em chunks
        $uploadUrl = $session['uploadUrl'];
        $fileSize = filesize($filePath);
        $chunkSize = 10 * 1024 * 1024; // 10MB chunks
        $handle = fopen($filePath, 'rb');
        $offset = 0;
        
        while ($offset < $fileSize) {
            $chunk = fread($handle, $chunkSize);
            $chunkLength = strlen($chunk);
            $endByte = $offset + $chunkLength - 1;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $uploadUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $chunk);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Length: ' . $chunkLength,
                'Content-Range: bytes ' . $offset . '-' . $endByte . '/' . $fileSize
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 400) {
                fclose($handle);
                return ['success' => false, 'error' => 'Erro durante upload: HTTP ' . $httpCode];
            }
            
            $offset += $chunkLength;
        }
        
        fclose($handle);
        
        $data = json_decode($response, true);
        
        return [
            'success' => true,
            'file_id' => $data['id'] ?? null,
            'file_name' => $data['name'] ?? $fileName,
            'web_url' => $data['webUrl'] ?? null
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
