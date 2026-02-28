<?php
require_once __DIR__ . '/../ProviderInterface.php';
require_once __DIR__ . '/../../IdentifierResolver.php';

/**
 * Provider para Z-API
 * Documentação: https://developer.z-api.io/
 */
class ZAPIProvider implements ProviderInterface {
    private $instance;
    private $baseUrl;
    private $resolver;
    private $pdo;
    
    public function __construct($instance, $pdo) {
        $this->instance = $instance;
        $this->pdo = $pdo;
        
        // Z-API usa instance_id e token na URL
        $instanceId = $instance['instance_id'] ?? $instance['name'];
        $token = $instance['token'] ?? $instance['api_key'];
        
        $this->baseUrl = "https://api.z-api.io/instances/{$instanceId}/token/{$token}";
        $this->resolver = new IdentifierResolver($pdo);
    }
    
    public function sendText($identifier, $message) {
        $phone = $this->prepareIdentifier($identifier);
        
        $endpoint = $this->baseUrl . '/send-text';
        $payload = [
            'phone' => $phone,
            'message' => $message
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendImage($identifier, $imageUrl, $caption = '') {
        $phone = $this->prepareIdentifier($identifier);
        
        $endpoint = $this->baseUrl . '/send-image';
        $payload = [
            'phone' => $phone,
            'image' => $imageUrl,
            'caption' => $caption
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendVideo($identifier, $videoUrl, $caption = '') {
        $phone = $this->prepareIdentifier($identifier);
        
        $endpoint = $this->baseUrl . '/send-video';
        $payload = [
            'phone' => $phone,
            'video' => $videoUrl,
            'caption' => $caption
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendAudio($identifier, $audioUrl) {
        $phone = $this->prepareIdentifier($identifier);
        
        $endpoint = $this->baseUrl . '/send-audio';
        $payload = [
            'phone' => $phone,
            'audio' => $audioUrl
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendDocument($identifier, $documentUrl, $filename = '') {
        $phone = $this->prepareIdentifier($identifier);
        
        $endpoint = $this->baseUrl . '/send-document';
        $payload = [
            'phone' => $phone,
            'document' => $documentUrl,
            'fileName' => $filename
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendLocation($identifier, $latitude, $longitude, $name = '', $address = '') {
        $phone = $this->prepareIdentifier($identifier);
        
        $endpoint = $this->baseUrl . '/send-location';
        $payload = [
            'phone' => $phone,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name,
            'address' => $address
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function getStatus() {
        $endpoint = $this->baseUrl . '/status';
        $result = $this->makeRequest($endpoint, null, 'GET');
        
        return [
            'connected' => $result['connected'] ?? false,
            'phone' => $result['phone'] ?? null
        ];
    }
    
    public function checkIdentifier($identifier) {
        $phone = $this->prepareIdentifier($identifier);
        
        $endpoint = $this->baseUrl . '/phone-exists/' . $phone;
        $result = $this->makeRequest($endpoint, null, 'GET');
        
        return [
            'exists' => $result['exists'] ?? false,
            'name' => $result['name'] ?? null
        ];
    }
    
    public function getProfilePicture($identifier) {
        $phone = $this->prepareIdentifier($identifier);
        
        $endpoint = $this->baseUrl . '/profile-picture/' . $phone;
        $result = $this->makeRequest($endpoint, null, 'GET');
        
        return $result['profilePictureUrl'] ?? null;
    }
    
    public function createGroup($name, $participants) {
        $endpoint = $this->baseUrl . '/create-group';
        $payload = [
            'groupName' => $name,
            'phones' => array_map([$this, 'prepareIdentifier'], $participants)
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function supportsLID() {
        // Z-API ainda não suporta LID nativamente (2024)
        return false;
    }
    
    /**
     * Prepara identificador para Z-API (precisa ser phone)
     * Z-API trabalha apenas com números de telefone
     */
    private function prepareIdentifier($identifier) {
        $type = IdentifierResolver::getType($identifier);
        
        if ($type === 'lid') {
            // Tentar resolver LID para phone
            $phone = $this->resolver->resolveToPhone($identifier);
            if ($phone) {
                return $phone;
            }
            throw new Exception("Não foi possível resolver LID para número de telefone");
        }
        
        return IdentifierResolver::normalize($identifier);
    }
    
    /**
     * Faz requisição HTTP para Z-API
     */
    private function makeRequest($endpoint, $payload = null, $method = 'POST') {
        $ch = curl_init($endpoint);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Erro na requisição Z-API: " . $error);
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorMsg = $data['message'] ?? $data['error'] ?? 'Erro desconhecido';
            throw new Exception("Erro Z-API (HTTP {$httpCode}): " . $errorMsg);
        }
        
        return $data;
    }
}
