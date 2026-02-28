<?php
/**
 * Teams Media Handler
 * 
 * Processa envio de mídias para Microsoft Teams via Graph API.
 * 
 * @package MediaHandlers
 * @version 1.0
 * @since 2026-01-29
 */

class TeamsMediaHandler {
    
    /**
     * Enviar mídia via Teams
     * 
     * Envia arquivo de mídia através da Microsoft Graph API.
     * Utiliza attachments inline (base64) para arquivos pequenos.
     * 
     * @param array $file Dados do arquivo ($_FILES)
     * @param string $teamsChatId ID do chat no Teams (ex: "19:abc123@thread.v2")
     * @param string $mediaType Tipo de mídia ('image', 'audio', 'document')
     * @param string $caption Legenda/caption (opcional)
     * @param TeamsGraphAPI $teamsAPI Instância da API do Teams
     * @param string|null $localPath Caminho do arquivo salvo localmente (após MediaStorageManager::save)
     * @return array ['success' => bool, 'media_url' => string, 'error' => string|null]
     * 
     * @example
     * $teamsAPI = new TeamsGraphAPI($pdo, $userId);
     * $result = TeamsMediaHandler::send(
     *     $_FILES['file'],
     *     '19:abc123@thread.v2',
     *     'image',
     *     'Minha foto',
     *     $teamsAPI,
     *     '/path/to/saved/file.jpg'
     * );
     */
    public static function send(
        array $file,
        string $teamsChatId,
        string $mediaType,
        string $caption,
        $teamsAPI,
        ?string $localPath = null
    ): array {
        try {
            // ========================================
            // 1. VALIDAR PARÂMETROS
            // ========================================
            
            if (!$file) {
                throw new Exception('Arquivo inválido');
            }
            
            if (empty($teamsChatId)) {
                throw new Exception('Chat ID do Teams não fornecido');
            }
            
            if (!$teamsAPI || !method_exists($teamsAPI, 'sendChatMessageWithAttachment')) {
                throw new Exception('Instância TeamsGraphAPI inválida');
            }
            
            // ========================================
            // 2. VALIDAR E PREPARAR CAMINHO DO ARQUIVO
            // ========================================
            
            // Usar $localPath se fornecido, senão usar tmp_name
            $filePath = $localPath ?: $file['tmp_name'];
            
            if (!file_exists($filePath)) {
                throw new Exception('Arquivo não encontrado: ' . $filePath);
            }
            
            // Detectar MIME type
            $mimeType = mime_content_type($filePath);
            if (!$mimeType) {
                $mimeType = $file['type']; // Fallback para o tipo enviado pelo navegador
            }
            
            $fileName = $file['name'];
            $fileSize = $file['size'];
            
            // Debug logs
            error_log("[TeamsMediaHandler] Preparando envio de mídia");
            error_log("  - Arquivo: " . $fileName);
            error_log("  - Tamanho: " . $fileSize . " bytes");
            error_log("  - MIME Type: " . $mimeType);
            error_log("  - Chat ID: " . $teamsChatId);
            error_log("  - Media Type: " . $mediaType);
            
            // ========================================
            // 3. PREPARAR URL PÚBLICA DO ARQUIVO
            // ========================================
            
            // A API do Teams para chats 1:1 não suporta hosted content
            // Solução: usar URL pública do arquivo salvo localmente
            
            // Construir URL pública completa
            // O MediaStorageManager já retornou o caminho relativo em $storage['media_url']
            // Precisamos apenas adicionar o protocolo e domínio
            
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            
            // Buscar media_url do storage (já foi salvo anteriormente em send_media.php)
            // Como não temos acesso direto aqui, vamos construir a partir do localPath
            $documentRoot = $_SERVER['DOCUMENT_ROOT'];
            $relativePath = str_replace($documentRoot, '', $filePath);
            $relativePath = str_replace('\\', '/', $relativePath); // Normalizar barras (Windows)
            
            // Garantir que começa com /
            if ($relativePath[0] !== '/') {
                $relativePath = '/' . $relativePath;
            }
            
            $publicUrl = $protocol . '://' . $host . $relativePath;
            
            error_log("[TeamsMediaHandler] Construindo URL pública:");
            error_log("  - Protocol: " . $protocol);
            error_log("  - Host: " . $host);
            error_log("  - Document Root: " . $documentRoot);
            error_log("  - File Path: " . $filePath);
            error_log("  - Relative Path: " . $relativePath);
            error_log("  - Public URL: " . $publicUrl);
            
            // ========================================
            // 4. PREPARAR ATTACHMENT
            // ========================================
            
            // Gerar GUID válido para o attachment (formato: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
            // Teams requer GUID no formato RFC 4122
            $attachmentId = sprintf(
                '%08x-%04x-%04x-%04x-%012x',
                mt_rand(0, 0xffffffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffffffffffff)
            );
            
            error_log("[TeamsMediaHandler] Attachment ID (GUID): " . $attachmentId);
            
            // Criar estrutura do attachment
            // Para que o Teams renderize como imagem, precisamos usar o MIME type correto
            // e não usar 'reference' como contentType
            $attachment = [
                'id' => $attachmentId,
                'contentType' => $mimeType,  // Usar MIME type real (image/jpeg, etc.)
                'contentUrl' => $publicUrl,
                'name' => $fileName
            ];
            
            // ========================================
            // 5. ENVIAR MENSAGEM VIA GRAPH API
            // ========================================
            
            // Para chats 1:1, o Teams não suporta attachments com URL externa
            // Solução: enviar como HTML com tag <img>
            
            // Preparar mensagem HTML com imagem inline
            $messageHtml = '<div>';
            
            if ($caption) {
                $messageHtml .= '<p>' . htmlspecialchars($caption) . '</p>';
            }
            
            $messageHtml .= '<img src="' . htmlspecialchars($publicUrl) . '" alt="' . htmlspecialchars($fileName) . '" style="max-width: 100%; height: auto;" />';
            $messageHtml .= '</div>';
            
            error_log("[TeamsMediaHandler] Enviando mensagem HTML");
            error_log("  - HTML: " . $messageHtml);
            
            // Enviar via Graph API como mensagem HTML simples (sem attachment)
            $result = $teamsAPI->sendChatMessage($teamsChatId, $messageHtml, 'html');
            
            // ========================================
            // 6. TRATAR RESPOSTA
            // ========================================
            
            if ($result['success']) {
                error_log("[TeamsMediaHandler] Mídia enviada com sucesso via Teams");
                
                // ✅ EXTRAIR ID DA MENSAGEM DO TEAMS
                $teamsMessageId = $result['data']['id'] ?? null;
                error_log("[TeamsMediaHandler] Teams Message ID: " . ($teamsMessageId ?? 'NULL'));
                
                return [
                    'success' => true,
                    'media_url' => null, // Teams não retorna URL direta
                    'message_id' => $teamsMessageId,  // ← ID real do Teams
                    'error' => null
                ];
            } else {
                $errorMsg = $result['error'] ?? 'Erro desconhecido ao enviar mídia';
                error_log("[TeamsMediaHandler] Erro ao enviar mídia: " . $errorMsg);
                
                throw new Exception($errorMsg);
            }
            
        } catch (Exception $e) {
            error_log("[TeamsMediaHandler] Exceção: " . $e->getMessage());
            
            return [
                'success' => false,
                'media_url' => null,
                'error' => $e->getMessage()
            ];
        }
    }
}
