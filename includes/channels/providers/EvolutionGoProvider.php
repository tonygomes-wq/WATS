<?php
require_once __DIR__ . '/../ProviderInterface.php';
require_once __DIR__ . '/../../IdentifierResolver.php';

/**
 * Provider para Evolution Go API
 * Documentação: https://docs.evolutionfoundation.com.br/evolution-go
 * 
 * Evolution Go é uma versão em Go da Evolution API com melhor performance
 * e compatibilidade com a API original
 */
class EvolutionGoProvider implements ProviderInterface {
    private $instance;
    private $baseUrl;
    private $apiKey;
    private $resolver;
    private $pdo;
    
    public function __construct($instance, $pdo) {
        $this->instance = $instance;
        $this->pdo = $pdo;
        
        // Evolution Go usa a mesma estrutura de URL
        $this->baseUrl = rtrim($instance['api_url'] ?? (defined('EVOLUTION_GO_API_URL') ? EVOLUTION_GO_API_URL : 'http://localhost:8090'), '/');
        $this->apiKey = $instance['api_key'] ?? $instance['token'] ?? '';
        $this->resolver = new IdentifierResolver($pdo);
        
        error_log("[EVOLUTION_GO] Inicializado - URL: {$this->baseUrl}, Instance: {$instance['instance_id']}");
    }
    
    public function sendText($identifier, $message) {
        $number = $this->prepareNumber($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendText/' . $this->instance['instance_id'];
        $payload = [
            'number' => $number,
            'text' => $message
        ];
        
        error_log("[EVOLUTION_GO] Enviando texto para: $number");
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendImage($identifier, $imageUrl, $caption = '') {
        $number = $this->prepareNumber($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendMedia/' . $this->instance['instance_id'];
        $payload = [
            'number' => $number,
            'mediatype' => 'image',
            'media' => $imageUrl
        ];
        
        if (!empty($caption)) {
            $payload['caption'] = $caption;
        }
        
        error_log("[EVOLUTION_GO] Enviando imagem para: $number");
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendVideo($identifier, $videoUrl, $caption = '') {
        $number = $this->prepareNumber($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendMedia/' . $this->instance['instance_id'];
        $payload = [
            'number' => $number,
            'mediatype' => 'video',
            'media' => $videoUrl
        ];
        
        if (!empty($caption)) {
            $payload['caption'] = $caption;
        }
        
        error_log("[EVOLUTION_GO] Enviando vídeo para: $number");
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendAudio($identifier, $audioUrl) {
        $number = $this->prepareNumber($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendWhatsAppAudio/' . $this->instance['instance_id'];
        $payload = [
            'number' => $number,
            'audio' => $audioUrl
        ];
        
        error_log("[EVOLUTION_GO] Enviando áudio para: $number");
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendDocument($identifier, $documentUrl, $filename = '') {
        $number = $this->prepareNumber($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendMedia/' . $this->instance['instance_id'];
        $payload = [
            'number' => $number,
            'mediatype' => 'document',
            'media' => $documentUrl
        ];
        
        if (!empty($filename)) {
            $payload['fileName'] = $filename;
        }
        
        error_log("[EVOLUTION_GO] Enviando documento para: $number");
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function sendLocation($identifier, $latitude, $longitude, $name = '', $address = '') {
        $number = $this->prepareNumber($identifier);
        
        $endpoint = $this->baseUrl . '/message/sendLocation/' . $this->instance['instance_id'];
        $payload = [
            'number' => $number,
            'latitude' => (float)$latitude,
            'longitude' => (float)$longitude
        ];
        
        if (!empty($name)) {
            $payload['name'] = $name;
        }
        
        if (!empty($address)) {
            $payload['address'] = $address;
        }
        
        error_log("[EVOLUTION_GO] Enviando localização para: $number");
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function getStatus() {
        $endpoint = $this->baseUrl . '/instance/connectionState/' . $this->instance['instance_id'];
        
        try {
            $result = $this->makeRequest($endpoint, null, 'GET');
            
            $state = $result['state'] ?? $result['instance']['state'] ?? '';
            $connected = in_array($state, ['open', 'connected']);
            
            return [
                'connected' => $connected,
                'phone' => $result['instance']['owner'] ?? $result['number'] ?? null,
                'state' => $state
            ];
        } catch (Exception $e) {
            error_log("[EVOLUTION_GO] Erro ao obter status: " . $e->getMessage());
            return [
                'connected' => false,
                'phone' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function checkIdentifier($identifier) {
        $number = $this->prepareNumber($identifier);
        
        $endpoint = $this->baseUrl . '/chat/whatsappNumbers/' . $this->instance['instance_id'];
        $payload = [
            'numbers' => [$number]
        ];
        
        try {
            $result = $this->makeRequest($endpoint, $payload);
            
            $exists = false;
            $name = null;
            
            if (is_array($result) && !empty($result)) {
                $first = $result[0] ?? [];
                $exists = $first['exists'] ?? false;
                $name = $first['name'] ?? null;
            }
            
            return [
                'exists' => $exists,
                'name' => $name
            ];
        } catch (Exception $e) {
            error_log("[EVOLUTION_GO] Erro ao verificar número: " . $e->getMessage());
            return [
                'exists' => false,
                'name' => null
            ];
        }
    }
    
    public function getProfilePicture($identifier) {
        $number = $this->prepareNumber($identifier);
        
        $endpoint = $this->baseUrl . '/chat/fetchProfilePictureUrl/' . $this->instance['instance_id'];
        $payload = [
            'number' => $number
        ];
        
        try {
            $result = $this->makeRequest($endpoint, $payload);
            return $result['profilePictureUrl'] ?? $result['url'] ?? null;
        } catch (Exception $e) {
            error_log("[EVOLUTION_GO] Erro ao buscar foto de perfil: " . $e->getMessage());
            return null;
        }
    }
    
    public function createGroup($name, $participants) {
        $endpoint = $this->baseUrl . '/group/create/' . $this->instance['instance_id'];
        
        $formattedParticipants = array_map(function($p) {
            return $this->prepareNumber($p);
        }, $participants);
        
        $payload = [
            'subject' => $name,
            'participants' => $formattedParticipants
        ];
        
        error_log("[EVOLUTION_GO] Criando grupo: $name");
        return $this->makeRequest($endpoint, $payload);
    }
    
    public function supportsLID() {
        // Evolution Go suporta LID via Baileys
        return true;
    }
    
    /**
     * Prepara número para formato aceito pela Evolution Go
     * Remove caracteres especiais e garante formato correto
     */
    private function prepareNumber($identifier) {
        // Remover caracteres especiais
        $number = preg_replace('/[^0-9]/', '', $identifier);
        
        // Se não começar com código do país, adicionar 55 (Brasil)
        if (strlen($number) < 12) {
            $number = '55' . $number;
        }
        
        return $number;
    }
    
    /**
     * Faz requisição HTTP para Evolution Go API
     */
    private function makeRequest($endpoint, $payload = null, $method = 'POST') {
        $ch = curl_init($endpoint);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload) {
                $jsonPayload = json_encode($payload);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
                error_log("[EVOLUTION_GO] Request payload: " . $jsonPayload);
            }
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("[EVOLUTION_GO] Response HTTP $httpCode: " . substr($response, 0, 500));
        
        if ($error) {
            error_log("[EVOLUTION_GO] CURL Error: " . $error);
            throw new Exception("Erro na requisição Evolution Go API: " . $error);
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorMsg = $data['message'] ?? $data['error'] ?? $data['response']['message'] ?? 'Erro desconhecido';
            error_log("[EVOLUTION_GO] API Error: HTTP $httpCode - $errorMsg");
            throw new Exception("Erro Evolution Go API (HTTP {$httpCode}): " . $errorMsg);
        }
        
        return $data;
    }
}
