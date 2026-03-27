<?php
/**
 * WhatsApp Media Handler
 * 
 * Processa envio de mídias para WhatsApp via Evolution API.
 * Este handler contém o código extraído do send_media.php original.
 * 
 * @package MediaHandlers
 * @version 1.0
 * @since 2026-01-29
 */

class WhatsAppMediaHandler {
    
    /**
     * Enviar mídia via WhatsApp
     * 
     * Envia arquivo de mídia através da Evolution API.
     * Mantém a mesma lógica do código original para garantir
     * compatibilidade total.
     * 
     * @param array $file Dados do arquivo ($_FILES)
     * @param string $phone Número de telefone (formato: +5511999999999)
     * @param string $mediaType Tipo de mídia ('image', 'audio', 'document')
     * @param string $caption Legenda/caption (opcional)
     * @param array $config Configuração Evolution API ['instance' => string, 'token' => string, 'url' => string]
     * @return array ['success' => bool, 'media_url' => string, 'error' => string|null]
     * 
     * @example
     * $result = WhatsAppMediaHandler::send(
     *     $_FILES['file'],
     *     '+5511999999999',
     *     'image',
     *     'Minha foto',
     *     ['instance' => 'inst1', 'token' => 'abc123', 'url' => 'https://api.example.com']
     * );
     */
    public static function send(
        array $file,
        string $phone,
        string $mediaType,
        string $caption,
        array $config
    ): array {
        try {
            // Validar configuração
            if (!isset($config['instance']) || !isset($config['token'])) {
                throw new Exception('Configuração incompleta');
            }
            
            $provider = $config['provider'] ?? 'evolution';
            $instance = $config['instance'];
            $token = $config['token'];
            $clientToken = $config['client_token'] ?? '';
            
            error_log("WhatsAppMediaHandler: Provider = $provider, Instance = $instance");
            
            // Verificar se arquivo existe
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                throw new Exception('Arquivo temporário não definido');
            }
            
            if (!file_exists($file['tmp_name'])) {
                error_log("ERROR - Arquivo temporário não existe: " . $file['tmp_name']);
                throw new Exception('Arquivo temporário não encontrado no servidor');
            }
            
            if (!is_readable($file['tmp_name'])) {
                error_log("ERROR - Arquivo temporário sem permissão de leitura: " . $file['tmp_name']);
                throw new Exception('Sem permissão para ler arquivo temporário');
            }
            
            error_log("DEBUG - Lendo arquivo: " . $file['tmp_name'] . " (tamanho: " . filesize($file['tmp_name']) . " bytes)");
            
            // Enviar baseado no provider
            if ($provider === 'zapi') {
                return self::sendViaZAPI($file, $phone, $mediaType, $caption, $instance, $token, $clientToken);
            } else {
                return self::sendViaEvolution($file, $phone, $mediaType, $caption, $instance, $token, $config['url'] ?? EVOLUTION_API_URL);
            }
            
        } catch (Exception $e) {
            error_log("WhatsAppMediaHandler Error: " . $e->getMessage());
            return [
                'success' => false,
                'media_url' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar mídia via Z-API
     */
    private static function sendViaZAPI(
        array $file,
        string $phone,
        string $mediaType,
        string $caption,
        string $instanceId,
        string $token,
        string $clientToken
    ): array {
        error_log("SEND_MEDIA_ZAPI: Iniciando envio via Z-API");
        
        // Ler arquivo e converter para base64
        $fileContent = file_get_contents($file['tmp_name']);
        if ($fileContent === false) {
            throw new Exception('Erro ao ler o arquivo temporário');
        }
        
        $base64 = base64_encode($fileContent);
        if (empty($base64)) {
            throw new Exception('Erro ao converter arquivo para base64');
        }
        
        $mimeType = mime_content_type($file['tmp_name']) ?: $file['type'];
        $fileName = $file['name'];
        
        error_log("SEND_MEDIA_ZAPI: Arquivo: $fileName, MIME: $mimeType, Tamanho: " . strlen($fileContent) . " bytes");
        
        // Formatar número
        $phoneFormatted = preg_replace('/[^0-9]/', '', $phone);
        
        // Preparar endpoint e payload baseado no tipo de mídia
        $endpoint = '';
        $payload = ['phone' => $phoneFormatted];
        
        switch ($mediaType) {
            case 'image':
                $endpoint = '/send-image';
                $payload['image'] = $base64;
                if ($caption) {
                    $payload['caption'] = $caption;
                }
                break;
                
            case 'audio':
                $endpoint = '/send-audio';
                $payload['audio'] = $base64;
                break;
                
            case 'video':
                $endpoint = '/send-video';
                $payload['video'] = $base64;
                if ($caption) {
                    $payload['caption'] = $caption;
                }
                break;
                
            case 'document':
            default:
                $endpoint = '/send-document';
                $payload['document'] = $base64;
                $payload['fileName'] = $fileName;
                if ($caption) {
                    $payload['caption'] = $caption;
                }
                break;
        }
        
        $url = "https://api.z-api.io/instances/{$instanceId}/token/{$token}{$endpoint}";
        
        error_log("SEND_MEDIA_ZAPI: URL: $url");
        error_log("SEND_MEDIA_ZAPI: Payload keys: " . implode(', ', array_keys($payload)));
        
        // Enviar para Z-API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = ['Content-Type: application/json'];
        if (!empty($clientToken)) {
            $headers[] = 'Client-Token: ' . $clientToken;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        error_log("SEND_MEDIA_ZAPI: HTTP Code: $httpCode");
        error_log("SEND_MEDIA_ZAPI: Response: $response");
        
        if ($curlError) {
            error_log("SEND_MEDIA_ZAPI: CURL Error: $curlError");
            throw new Exception('Erro Z-API: ' . $curlError);
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            $responseData = json_decode($response, true);
            $errorMsg = $responseData['message'] ?? $responseData['error'] ?? 'Erro desconhecido';
            throw new Exception("Erro Z-API (HTTP {$httpCode}): {$errorMsg}");
        }
        
        error_log("SEND_MEDIA_ZAPI: ✅ Mídia enviada com sucesso!");
        
        return [
            'success' => true,
            'media_url' => null,
            'error' => null
        ];
    }
    
    /**
     * Enviar mídia via Evolution API
     */
    private static function sendViaEvolution(
        array $file,
        string $phone,
        string $mediaType,
        string $caption,
        string $instance,
        string $token,
        string $apiUrl
    ): array {
            
            // Ler arquivo e converter para base64
            // ✅ CORREÇÃO: Verificar se arquivo existe antes de ler
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                throw new Exception('Arquivo temporário não definido');
            }
            
            if (!file_exists($file['tmp_name'])) {
                error_log("ERROR - Arquivo temporário não existe: " . $file['tmp_name']);
                throw new Exception('Arquivo temporário não encontrado no servidor');
            }
            
            if (!is_readable($file['tmp_name'])) {
                error_log("ERROR - Arquivo temporário sem permissão de leitura: " . $file['tmp_name']);
                throw new Exception('Sem permissão para ler arquivo temporário');
            }
            
            error_log("DEBUG - Lendo arquivo: " . $file['tmp_name'] . " (tamanho: " . filesize($file['tmp_name']) . " bytes)");
            
            $fileContent = file_get_contents($file['tmp_name']);
            if ($fileContent === false) {
                error_log("ERROR - file_get_contents retornou false para: " . $file['tmp_name']);
                throw new Exception('Erro ao ler o arquivo temporário');
            }
            
            $base64 = base64_encode($fileContent);
            if (empty($base64)) {
                throw new Exception('Erro ao converter arquivo para base64');
            }
            
            $mimeType = mime_content_type($file['tmp_name']);
            if (!$mimeType) {
                $mimeType = $file['type']; // Fallback para o tipo enviado pelo navegador
            }
            
            $fileName = $file['name'];
            
            // Debug logs
            error_log("DEBUG - Arquivo: " . $fileName);
            error_log("DEBUG - Tamanho original: " . strlen($fileContent) . " bytes");
            error_log("DEBUG - Tamanho base64: " . strlen($base64) . " chars");
            error_log("DEBUG - MIME Type: " . $mimeType);
            
            // Formatar número
            error_log("DEBUG - Phone recebido: " . $phone);
            $phoneFormatted = preg_replace('/[^0-9]/', '', $phone);
            error_log("DEBUG - Phone após preg_replace: " . $phoneFormatted);
            if (!str_starts_with($phoneFormatted, '55')) {
                $phoneFormatted = '55' . $phoneFormatted;
            }
            error_log("DEBUG - Phone após adicionar 55: " . $phoneFormatted);
            $phoneFormatted .= '@s.whatsapp.net';
            error_log("DEBUG - Phone final formatado: " . $phoneFormatted);
            
            // Preparar payload baseado no tipo de mídia
            $endpoint = '';
            $payload = [
                'number' => $phoneFormatted
            ];
            
            switch ($mediaType) {
                case 'image':
                    $endpoint = '/message/sendMedia/' . $instance;
                    
                    // GIFs animados - tentar enviar mantendo o formato original
                    if ($mimeType === 'image/gif') {
                        $payload['mediatype'] = 'image';
                        $payload['mimetype'] = 'image/gif';
                        $payload['media'] = $base64;
                        $payload['gifPlayback'] = true;
                        error_log("DEBUG - Enviando GIF com mimetype image/gif e gifPlayback=true");
                    } else {
                        $payload['mediatype'] = 'image';
                        $payload['mimetype'] = $mimeType;
                        $payload['media'] = $base64;
                    }
                    
                    if ($caption) {
                        $payload['caption'] = $caption;
                    }
                    break;
                    
                case 'audio':
                    $endpoint = '/message/sendWhatsAppAudio/' . $instance;
                    $payload['audio'] = $base64; // Apenas base64, sem prefixo data URI
                    break;
                    
                case 'document':
                default:
                    $endpoint = '/message/sendMedia/' . $instance;
                    $payload['mediatype'] = 'document';
                    $payload['mimetype'] = $mimeType;
                    $payload['media'] = $base64; // Apenas base64, sem prefixo data URI
                    $payload['fileName'] = $fileName;
                    if ($caption) {
                        $payload['caption'] = $caption;
                    }
                    break;
            }
            
            // Debug payload
            error_log("DEBUG - Endpoint: " . $apiUrl . $endpoint);
            error_log("DEBUG - Payload keys: " . implode(', ', array_keys($payload)));
            error_log("DEBUG - Media field length: " . strlen($payload['media'] ?? $payload['audio'] ?? 'N/A'));
            
            // Enviar para Evolution API
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl . $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);           // ✅ NOVO: Timeout 90s
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);    // ✅ NOVO: Conexão 15s
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . $token
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Debug response
            error_log("DEBUG - HTTP Code: " . $httpCode);
            error_log("DEBUG - Response: " . $response);
            if ($curlError) {
                error_log("DEBUG - CURL Error: " . $curlError);
            }
            
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new Exception('Erro ao enviar mídia: ' . $response);
            }
            
            // Sucesso
            return [
                'success' => true,
                'media_url' => null, // Evolution API não retorna URL direta
                'error' => null
            ];
        }
    }
}
