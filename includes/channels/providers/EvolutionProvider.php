<?php
require_once __DIR__ . '/../ProviderInterface.php';
require_once __DIR__ . '/../../IdentifierResolver.php';

/**
 * Provider para Evolution API
 * Documentação: https://doc.evolution-api.com/
 */
class EvolutionProvider implements ProviderInterface {
    private $instance;
    private $baseUrl;
    private $apiKey;
    private $resolver;
    private $pdo;
    
    public function __construct($instance, $pdo) {
        $this->instance = $instance;
        $this->pdo = $pdo;
        $this->baseUrl = rtrim($instance['api_url'] ?? $instance['evolution_api_url'], '/');
        $this->apiKey = $instance['api_key'] ?? $instance['token'] ?? $instance['evolution_api_key'];
        $this->resolver = new IdentifierResolver($pdo);
    }
    
    public function sendText($identifier, $message) {
        $jid = IdentifierResolver::toJID($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendText/' . $this->instance['instance_id'];
        $payload = [
            'number' => $jid,
            'textMessage' => [
                'text' => $message
            ]
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendImage($identifier, $imageUrl, $caption = '') {
        $jid = IdentifierResolver::toJID($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendMedia/' . $this->instance['instance_id'];
        $payload = [
            'number' => $jid,
            'mediatype' => 'image',
            'media' => $imageUrl,
            'caption' => $caption
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendVideo($identifier, $videoUrl, $caption = '') {
        $jid = IdentifierResolver::toJID($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendMedia/' . $this->instance['instance_id'];
        $payload = [
            'number' => $jid,
            'mediatype' => 'video',
            'media' => $videoUrl,
            'caption' => $caption
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendAudio($identifier, $audioUrl) {
        $jid = IdentifierResolver::toJID($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendMedia/' . $this->instance['instance_id'];
        $payload = [
            'number' => $jid,
            'mediatype' => 'audio',
            'media' => $audioUrl
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendDocument($identifier, $documentUrl, $filename = '') {
        $jid = IdentifierResolver::toJID($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendMedia/' . $this->instance['instance_id'];
        $payload = [
            'number' => $jid,
            'mediatype' => 'document',
            'media' => $documentUrl,
            'fileName' => $filename
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendLocation($identifier, $latitude, $longitude, $name = '', $address = '') {
        $jid = IdentifierResolver::toJID($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendLocation/' . $this->instance['instance_id'];
        $payload = [
            'number' => $jid,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name,
            'address' => $address
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function getStatus() {
        $endpoint = $this->baseUrl . '/instance/connectionState/' . $this->instance['instance_id'];
        $result = $this->makeRequest($endpoint, null, 'GET');
        
        return [
            'connected' => ($result['state'] ?? '') === 'open',
            'phone' => $result['number'] ?? null
        ];
    }
    
    public function checkIdentifier($identifier) {
        $jid = IdentifierResolver::toJID($identifier);
        
        $endpoint = $this->baseUrl . '/chat/whatsappNumbers/' . $this->instance['instance_id'];
        $payload = [
            'numbers' => [$jid]
        ];
        
        $result = $this->makeRequest($endpoint, $payload);
        
        return [
            'exists' => !empty($result) && isset($result[0]['exists']) && $result[0]['exists'],
            'name' => $result[0]['name'] ?? null
        ];
    }
    
    public function getProfilePicture($identifier) {
        $jid = IdentifierResolver::toJID($identifier);
        
        $endpoint = $this->baseUrl . '/chat/fetchProfilePictureUrl/' . $this->instance['instance_id'];
        $payload = [
            'number' => $jid
        ];
        
        $result = $this->makeRequest($endpoint, $payload);
        
        return $result['profilePictureUrl'] ?? null;
    }
    
    public function createGroup($name, $participants) {
        $endpoint = $this->baseUrl . '/group/create/' . $this->instance['instance_id'];
        $payload = [
            'subject' => $name,
            'participants' => array_map(function($p) {
                return IdentifierResolver::toJID($p);
            }, $participants)
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function supportsLID() {
        // Evolution API suporta LID via Baileys
        return true;
    }
    
    /**
     * Faz requisição HTTP para Evolution API
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
            'Content-Type: application/json',
            'apikey: ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Erro na requisição Evolution API: " . $error);
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorMsg = $data['message'] ?? $data['error'] ?? 'Erro desconhecido';
            throw new Exception("Erro Evolution API (HTTP {$httpCode}): " . $errorMsg);
        }
        
        return $data;
    }
}
