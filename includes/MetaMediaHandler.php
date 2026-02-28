<?php
/**
 * Gerenciador de Download e Armazenamento de Mídias da Meta API
 */

class MetaMediaHandler
{
    private PDO $pdo;
    private string $uploadDir;
    private array $allowedMimeTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/3gpp',
        'audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg',
        'application/pdf', 'application/vnd.ms-powerpoint', 'application/msword',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

    public function __construct(PDO $pdo, string $uploadDir = null)
    {
        $this->pdo = $pdo;
        $this->uploadDir = $uploadDir ?? __DIR__ . '/../uploads/meta_media/';
        
        // Criar diretório se não existir
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Processa mídia recebida de mensagem da Meta
     * 
     * @param array $message Mensagem do webhook
     * @param array $userConfig Configurações do usuário
     * @return array ['success' => bool, 'media_path' => string|null, 'media_type' => string|null]
     */
    public function processIncomingMedia(array $message, array $userConfig): array
    {
        $type = $message['type'] ?? 'text';
        
        if ($type === 'text') {
            return ['success' => true, 'media_path' => null, 'media_type' => null];
        }

        $mediaId = null;
        $caption = null;
        $mimeType = null;
        $filename = null;

        // Extrair informações baseado no tipo
        switch ($type) {
            case 'image':
                $mediaId = $message['image']['id'] ?? null;
                $caption = $message['image']['caption'] ?? null;
                $mimeType = $message['image']['mime_type'] ?? 'image/jpeg';
                break;
            
            case 'video':
                $mediaId = $message['video']['id'] ?? null;
                $caption = $message['video']['caption'] ?? null;
                $mimeType = $message['video']['mime_type'] ?? 'video/mp4';
                break;
            
            case 'audio':
                $mediaId = $message['audio']['id'] ?? null;
                $mimeType = $message['audio']['mime_type'] ?? 'audio/ogg';
                break;
            
            case 'voice':
                $mediaId = $message['voice']['id'] ?? null;
                $mimeType = $message['voice']['mime_type'] ?? 'audio/ogg';
                break;
            
            case 'document':
                $mediaId = $message['document']['id'] ?? null;
                $caption = $message['document']['caption'] ?? null;
                $mimeType = $message['document']['mime_type'] ?? 'application/pdf';
                $filename = $message['document']['filename'] ?? null;
                break;
            
            case 'sticker':
                $mediaId = $message['sticker']['id'] ?? null;
                $mimeType = $message['sticker']['mime_type'] ?? 'image/webp';
                break;
            
            default:
                error_log('[META_MEDIA] Tipo de mídia não suportado: ' . $type);
                return ['success' => false, 'media_path' => null, 'media_type' => $type];
        }

        if (!$mediaId) {
            error_log('[META_MEDIA] Media ID não encontrado');
            return ['success' => false, 'media_path' => null, 'media_type' => $type];
        }

        // Baixar mídia
        $downloadResult = $this->downloadMedia($mediaId, $userConfig, $mimeType, $filename);
        
        if (!$downloadResult['success']) {
            return [
                'success' => false,
                'media_path' => null,
                'media_type' => $type,
                'error' => $downloadResult['error'] ?? 'Erro ao baixar mídia'
            ];
        }

        return [
            'success' => true,
            'media_path' => $downloadResult['path'],
            'media_type' => $type,
            'mime_type' => $mimeType,
            'caption' => $caption,
            'filename' => $filename ?? basename($downloadResult['path'])
        ];
    }

    /**
     * Baixa mídia da Meta API
     * 
     * @param string $mediaId ID da mídia
     * @param array $userConfig Configurações do usuário
     * @param string $mimeType Tipo MIME
     * @param string|null $filename Nome do arquivo original
     * @return array ['success' => bool, 'path' => string|null]
     */
    private function downloadMedia(string $mediaId, array $userConfig, string $mimeType, ?string $filename = null): array
    {
        try {
            // Passo 1: Obter URL da mídia
            $mediaUrlResult = $this->getMediaUrl($mediaId, $userConfig);
            
            if (!$mediaUrlResult['success']) {
                return ['success' => false, 'error' => $mediaUrlResult['error']];
            }

            $mediaUrl = $mediaUrlResult['url'];

            // Passo 2: Baixar arquivo
            $ch = curl_init($mediaUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $userConfig['meta_permanent_token']
            ]);

            $fileContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200) {
                error_log('[META_MEDIA] Erro ao baixar mídia: ' . ($error ?: "HTTP $httpCode"));
                return ['success' => false, 'error' => 'Erro ao baixar arquivo'];
            }

            // Passo 3: Salvar arquivo
            $extension = $this->getExtensionFromMimeType($mimeType);
            $safeFilename = $filename ? $this->sanitizeFilename($filename) : (uniqid('meta_') . $extension);
            $filePath = $this->uploadDir . $safeFilename;

            // Evitar sobrescrever
            $counter = 1;
            while (file_exists($filePath)) {
                $safeFilename = pathinfo($safeFilename, PATHINFO_FILENAME) . "_{$counter}" . $extension;
                $filePath = $this->uploadDir . $safeFilename;
                $counter++;
            }

            if (file_put_contents($filePath, $fileContent) === false) {
                error_log('[META_MEDIA] Erro ao salvar arquivo: ' . $filePath);
                return ['success' => false, 'error' => 'Erro ao salvar arquivo'];
            }

            // Retornar caminho relativo
            $relativePath = 'uploads/meta_media/' . $safeFilename;

            error_log('[META_MEDIA] Mídia baixada com sucesso: ' . $relativePath);

            return [
                'success' => true,
                'path' => $relativePath,
                'full_path' => $filePath,
                'size' => filesize($filePath)
            ];

        } catch (Exception $e) {
            error_log('[META_MEDIA] Exceção ao baixar mídia: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtém URL da mídia via Graph API
     * 
     * @param string $mediaId ID da mídia
     * @param array $userConfig Configurações do usuário
     * @return array ['success' => bool, 'url' => string|null]
     */
    private function getMediaUrl(string $mediaId, array $userConfig): array
    {
        $apiVersion = $userConfig['meta_api_version'] ?? 'v19.0';
        $endpoint = "https://graph.facebook.com/{$apiVersion}/{$mediaId}";

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $userConfig['meta_permanent_token']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            error_log('[META_MEDIA] Erro ao obter URL da mídia: ' . ($error ?: "HTTP $httpCode"));
            return ['success' => false, 'error' => 'Erro ao obter URL da mídia'];
        }

        $data = json_decode($response, true);
        
        if (!isset($data['url'])) {
            error_log('[META_MEDIA] URL não encontrada na resposta: ' . $response);
            return ['success' => false, 'error' => 'URL não encontrada'];
        }

        return ['success' => true, 'url' => $data['url']];
    }

    /**
     * Obtém extensão baseada no MIME type
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeMap = [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'video/mp4' => '.mp4',
            'video/3gpp' => '.3gp',
            'audio/aac' => '.aac',
            'audio/mp4' => '.m4a',
            'audio/mpeg' => '.mp3',
            'audio/amr' => '.amr',
            'audio/ogg' => '.ogg',
            'application/pdf' => '.pdf',
            'application/msword' => '.doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
            'application/vnd.ms-excel' => '.xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
            'application/vnd.ms-powerpoint' => '.ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '.pptx'
        ];

        return $mimeMap[$mimeType] ?? '.bin';
    }

    /**
     * Sanitiza nome de arquivo
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remover caracteres perigosos
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Limitar tamanho
        if (strlen($filename) > 200) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 190);
            $filename = $name . '.' . $ext;
        }

        return $filename;
    }

    /**
     * Limpa mídias antigas (opcional - para economizar espaço)
     * 
     * @param int $daysOld Dias de antiguidade
     * @return int Número de arquivos removidos
     */
    public function cleanOldMedia(int $daysOld = 30): int
    {
        $count = 0;
        $cutoffTime = time() - ($daysOld * 86400);

        $files = glob($this->uploadDir . '*');
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }

        error_log('[META_MEDIA] Limpeza concluída: ' . $count . ' arquivos removidos');
        return $count;
    }
}
