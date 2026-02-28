<?php
/**
 * Integração Amazon S3 para Backup
 * 
 * Upload de arquivos para Amazon S3 usando AWS Signature V4
 * Não requer SDK AWS - implementação pura PHP
 * 
 * MACIP Tecnologia LTDA
 */

class S3Backup {
    
    private $accessKey;
    private $secretKey;
    private $bucket;
    private $region;
    private $endpoint;
    
    public function __construct($credentials = []) {
        $this->accessKey = $credentials['access_key'] ?? '';
        $this->secretKey = $credentials['secret_key'] ?? '';
        $this->bucket = $credentials['bucket'] ?? '';
        $this->region = $credentials['region'] ?? 'us-east-1';
        $this->endpoint = "https://s3.{$this->region}.amazonaws.com";
    }
    
    /**
     * Testa conexão com S3
     */
    public function testConnection() {
        if (empty($this->accessKey) || empty($this->secretKey) || empty($this->bucket)) {
            return ['success' => false, 'error' => 'Credenciais incompletas'];
        }
        
        // Tentar listar objetos (HEAD bucket)
        $uri = "/{$this->bucket}";
        $headers = $this->signRequest('HEAD', $uri);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint . $uri);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => 'Erro de conexão: ' . $error];
        }
        
        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'Conexão estabelecida com sucesso'];
        } elseif ($httpCode === 403) {
            return ['success' => false, 'error' => 'Acesso negado - verifique as credenciais'];
        } elseif ($httpCode === 404) {
            return ['success' => false, 'error' => 'Bucket não encontrado'];
        }
        
        return ['success' => false, 'error' => 'Erro HTTP: ' . $httpCode];
    }
    
    /**
     * Faz upload de arquivo para S3
     */
    public function uploadFile($filePath, $fileName, $folderPath = 'wats-backups') {
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'Arquivo não encontrado'];
        }
        
        $content = file_get_contents($filePath);
        return $this->uploadContent($content, $fileName, $folderPath);
    }
    
    /**
     * Upload direto de conteúdo (sem arquivo local)
     */
    public function uploadContent($content, $fileName, $folderPath = 'wats-backups') {
        if (empty($this->accessKey) || empty($this->secretKey) || empty($this->bucket)) {
            return ['success' => false, 'error' => 'Credenciais não configuradas'];
        }
        
        $key = trim($folderPath, '/') . '/' . $fileName;
        $uri = "/{$this->bucket}/{$key}";
        
        $contentHash = hash('sha256', $content);
        $contentType = $this->getMimeType($fileName);
        
        $headers = $this->signRequest('PUT', $uri, $contentHash, $contentType);
        $headers[] = 'Content-Type: ' . $contentType;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint . $uri);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => 'Erro de conexão: ' . $error];
        }
        
        if ($httpCode === 200) {
            $s3Url = "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$key}";
            return [
                'success' => true,
                'file_name' => $fileName,
                'key' => $key,
                's3_url' => $s3Url
            ];
        }
        
        // Tentar extrair erro do XML
        $errorMsg = 'Erro HTTP: ' . $httpCode;
        if ($response && strpos($response, '<Error>') !== false) {
            if (preg_match('/<Message>(.*?)<\/Message>/s', $response, $matches)) {
                $errorMsg = $matches[1];
            }
        }
        
        return ['success' => false, 'error' => $errorMsg];
    }
    
    /**
     * Assina requisição usando AWS Signature V4
     */
    private function signRequest($method, $uri, $payloadHash = null, $contentType = null) {
        $service = 's3';
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        
        if ($payloadHash === null) {
            $payloadHash = hash('sha256', '');
        }
        
        // Headers canônicos
        $host = "s3.{$this->region}.amazonaws.com";
        $canonicalHeaders = "host:{$host}\n";
        $canonicalHeaders .= "x-amz-content-sha256:{$payloadHash}\n";
        $canonicalHeaders .= "x-amz-date:{$timestamp}\n";
        
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        
        // Requisição canônica
        $canonicalRequest = "{$method}\n";
        $canonicalRequest .= "{$uri}\n";
        $canonicalRequest .= "\n"; // Query string vazia
        $canonicalRequest .= $canonicalHeaders;
        $canonicalRequest .= "\n";
        $canonicalRequest .= $signedHeaders;
        $canonicalRequest .= "\n";
        $canonicalRequest .= $payloadHash;
        
        // String to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$date}/{$this->region}/{$service}/aws4_request";
        $stringToSign = "{$algorithm}\n{$timestamp}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        
        // Signing key
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        
        // Signature
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        // Authorization header
        $authorization = "{$algorithm} Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        
        return [
            'Host: ' . $host,
            'x-amz-content-sha256: ' . $payloadHash,
            'x-amz-date: ' . $timestamp,
            'Authorization: ' . $authorization
        ];
    }
    
    private function getMimeType($fileName) {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeTypes = [
            'json' => 'application/json',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
            'enc' => 'application/octet-stream',
            'pdf' => 'application/pdf'
        ];
        return $mimeTypes[$ext] ?? 'application/octet-stream';
    }
}
