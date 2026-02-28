<?php
/**
 * MICROSOFT TEAMS GRAPH API INTEGRATION
 * Integração completa com Microsoft Teams usando Graph API
 * Permite chat bidirecional, leitura de mensagens, criação de chats, etc.
 * 
 * @author MAC-IP TECNOLOGIA
 * @version 2.0
 * @date 2025-01-23
 */

class TeamsGraphAPI {
    private $pdo;
    private $userId;
    private $clientId;
    private $clientSecret;
    private $tenantId;
    private $accessToken;
    private $graphApiUrl = 'https://graph.microsoft.com/v1.0';
    
    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->loadCredentials();
    }
    
    /**
     * Carregar credenciais do Azure AD
     * Se o usuário for atendente, herda as credenciais do supervisor
     */
    private function loadCredentials() {
        // Verificar se é atendente e buscar supervisor_id
        $stmt = $this->pdo->prepare("
            SELECT supervisor_id FROM supervisor_users WHERE id = ?
        ");
        $stmt->execute([$this->userId]);
        $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se for atendente, usar credenciais do supervisor
        // Caso contrário, usar as próprias credenciais
        $credentialsUserId = $attendant ? $attendant['supervisor_id'] : $this->userId;
        
        // Buscar credenciais do Azure AD (do supervisor se for atendente)
        $stmt = $this->pdo->prepare("
            SELECT 
                teams_client_id,
                teams_client_secret,
                teams_tenant_id
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$credentialsUserId]);
        $credentials = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Buscar tokens OAuth (sempre do próprio usuário)
        $stmt = $this->pdo->prepare("
            SELECT 
                teams_access_token,
                teams_refresh_token,
                teams_token_expires_at
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$this->userId]);
        $tokens = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Combinar credenciais + tokens
        if ($credentials) {
            $this->clientId = $credentials['teams_client_id'];
            $this->clientSecret = $credentials['teams_client_secret'];
            $this->tenantId = $credentials['teams_tenant_id'];
        }
        
        if ($tokens) {
            $this->accessToken = $tokens['teams_access_token'];
            
            // Verificar se token expirou
            if ($tokens['teams_token_expires_at'] && strtotime($tokens['teams_token_expires_at']) < time()) {
                $this->refreshAccessToken($tokens['teams_refresh_token']);
            }
        }
    }
    
    /**
     * Verificar se o usuário atual é um atendente
     */
    public function isAttendant() {
        $stmt = $this->pdo->prepare("
            SELECT supervisor_id FROM supervisor_users WHERE id = ?
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }
    
    /**
     * Obter ID do supervisor (se for atendente)
     */
    public function getSupervisorId() {
        $stmt = $this->pdo->prepare("
            SELECT supervisor_id FROM supervisor_users WHERE id = ?
        ");
        $stmt->execute([$this->userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['supervisor_id'] : null;
    }
    
    /**
     * Obter URL de autorização OAuth
     */
    public function getAuthorizationUrl($redirectUri) {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'response_mode' => 'query',
            'scope' => implode(' ', [
                'offline_access',
                'User.Read.All',
                'Chat.Read',
                'Chat.ReadWrite',
                'ChatMessage.Read',
                'ChatMessage.Send'
            ]),
            'state' => bin2hex(random_bytes(16))
        ];
        
        return "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize?" . http_build_query($params);
    }
    
    /**
     * Obter URL de consentimento de administrador
     */
    public function getAdminConsentUrl($redirectUri) {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'state' => bin2hex(random_bytes(16))
        ];
        
        return "https://login.microsoftonline.com/{$this->tenantId}/adminconsent?" . http_build_query($params);
    }
    
    /**
     * Trocar código de autorização por access token
     */
    public function exchangeCodeForToken($code, $redirectUri) {
        $tokenUrl = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        
        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $this->saveTokens($data);
            return ['success' => true, 'data' => $data];
        }
        
        return ['success' => false, 'error' => 'Erro ao obter token: ' . $response];
    }
    
    /**
     * Renovar access token usando refresh token
     */
    private function refreshAccessToken($refreshToken) {
        $tokenUrl = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        
        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $this->saveTokens($data);
            $this->accessToken = $data['access_token'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Salvar tokens no banco de dados
     */
    private function saveTokens($tokenData) {
        $expiresAt = date('Y-m-d H:i:s', time() + $tokenData['expires_in']);
        
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET 
                teams_access_token = ?,
                teams_refresh_token = ?,
                teams_token_expires_at = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? null,
            $expiresAt,
            $this->userId
        ]);
    }
    
    /**
     * Fazer requisição à Graph API
     */
    private function graphRequest($method, $endpoint, $data = null) {
        if (!$this->accessToken) {
            return ['success' => false, 'error' => 'Token de acesso não disponível'];
        }
        
        $url = $this->graphApiUrl . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        // Log para debug
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("[TeamsGraphAPI] Erro na requisição: $method $endpoint");
            error_log("[TeamsGraphAPI] HTTP Code: $httpCode");
            error_log("[TeamsGraphAPI] cURL Error: $curlError");
            error_log("[TeamsGraphAPI] Response: " . substr($response, 0, 500));
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $result];
        }
        
        $errorMessage = 'Erro desconhecido';
        if (isset($result['error']['message'])) {
            $errorMessage = $result['error']['message'];
        } elseif (isset($result['error'])) {
            $errorMessage = is_string($result['error']) ? $result['error'] : json_encode($result['error']);
        } elseif ($curlError) {
            $errorMessage = "cURL Error: $curlError";
        }
        
        return [
            'success' => false, 
            'error' => $errorMessage, 
            'code' => $httpCode,
            'data' => $result
        ];
    }
    
    /**
     * Enviar mensagem para um chat
     * 
     * @param string $chatId ID do chat
     * @param string $message Conteúdo da mensagem
     * @param string $contentType Tipo de conteúdo ('text' ou 'html')
     * @return array Resultado da operação
     */
    public function sendChatMessage($chatId, $message, $contentType = 'text') {
        $data = [
            'body' => [
                'contentType' => $contentType,
                'content' => $message
            ]
        ];
        
        return $this->graphRequest('POST', "/chats/{$chatId}/messages", $data);
    }
    
    /**
     * Enviar mensagem para um canal
     */
    public function sendChannelMessage($teamId, $channelId, $message) {
        $data = [
            'body' => [
                'content' => $message
            ]
        ];
        
        return $this->graphRequest('POST', "/teams/{$teamId}/channels/{$channelId}/messages", $data);
    }
    
    /**
     * Criar novo chat
     */
    public function createChat($members, $topic = null) {
        $data = [
            'chatType' => count($members) > 2 ? 'group' : 'oneOnOne',
            'members' => array_map(function($userId) {
                return [
                    '@odata.type' => '#microsoft.graph.aadUserConversationMember',
                    'roles' => ['owner'],
                    'user@odata.bind' => "https://graph.microsoft.com/v1.0/users('{$userId}')"
                ];
            }, $members)
        ];
        
        if ($topic && count($members) > 2) {
            $data['topic'] = $topic;
        }
        
        return $this->graphRequest('POST', '/chats', $data);
    }
    
    /**
     * Listar chats do usuário
     */
    public function listChats() {
        return $this->graphRequest('GET', '/me/chats');
    }
    
    /**
     * Obter mensagens de um chat
     */
    public function getChatMessages($chatId, $top = 50) {
        // Usar URL simples sem orderby para evitar problemas de encoding
        return $this->graphRequest('GET', "/chats/{$chatId}/messages?\$top={$top}");
    }
    
    /**
     * Obter mensagens de um canal
     */
    public function getChannelMessages($teamId, $channelId, $top = 50) {
        return $this->graphRequest('GET', "/teams/{$teamId}/channels/{$channelId}/messages?\$top={$top}");
    }
    
    /**
     * Listar times do usuário
     */
    public function listTeams() {
        return $this->graphRequest('GET', '/me/joinedTeams');
    }
    
    /**
     * Listar canais de um time
     */
    public function listChannels($teamId) {
        return $this->graphRequest('GET', "/teams/{$teamId}/channels");
    }
    
    /**
     * Obter informações do usuário autenticado
     */
    public function getMe() {
        return $this->graphRequest('GET', '/me');
    }
    
    /**
     * Verificar se está autenticado
     */
    public function isAuthenticated() {
        return !empty($this->accessToken);
    }
    
    /**
     * Salvar credenciais do Azure AD
     */
    public function saveCredentials($clientId, $clientSecret, $tenantId) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET 
                teams_client_id = ?,
                teams_client_secret = ?,
                teams_tenant_id = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([$clientId, $clientSecret, $tenantId, $this->userId]);
    }
    
    /**
     * Desconectar (remover tokens)
     */
    public function disconnect() {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET 
                teams_access_token = NULL,
                teams_refresh_token = NULL,
                teams_token_expires_at = NULL
            WHERE id = ?
        ");
        
        return $stmt->execute([$this->userId]);
    }

    /**
     * Buscar informações de um usuário pelo ID
     * @param string $userId ID do usuário no Azure AD
     * @return array Informações do usuário
     */
    public function getUserInfo($userId) {
        $response = $this->graphRequest('GET', "/users/{$userId}");
        
        if ($response['success']) {
            return [
                'success' => true,
                'data' => [
                    'id' => $response['data']['id'] ?? null,
                    'displayName' => $response['data']['displayName'] ?? null,
                    'mail' => $response['data']['mail'] ?? $response['data']['userPrincipalName'] ?? null,
                    'jobTitle' => $response['data']['jobTitle'] ?? null,
                    'officeLocation' => $response['data']['officeLocation'] ?? null
                ]
            ];
        }
        
        return $response;
    }
    
    /**
     * Buscar foto de perfil de um usuário
     * @param string $userId ID do usuário no Azure AD
     * @return array URL da foto ou dados binários
     */
    public function getUserPhoto($userId) {
        // ✅ CACHE: Verificar se foto já foi buscada recentemente (últimas 24h)
        $cacheKey = "teams_photo_{$userId}";
        $cacheFile = __DIR__ . '/../../storage/cache/' . $cacheKey . '.cache';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && isset($cached['photo_base64'])) {
                return [
                    'success' => true,
                    'data' => $cached,
                    'cached' => true
                ];
            }
        }
        
        // ✅ RATE LIMITING: Verificar se não estamos fazendo muitas requisições
        $rateLimitFile = __DIR__ . '/../../storage/cache/teams_rate_limit.txt';
        if (file_exists($rateLimitFile)) {
            $lastRequest = (int)file_get_contents($rateLimitFile);
            $timeSinceLastRequest = time() - $lastRequest;
            
            // Aguardar pelo menos 1 segundo entre requisições
            if ($timeSinceLastRequest < 1) {
                sleep(1 - $timeSinceLastRequest);
            }
        }
        
        $url = "{$this->graphApiUrl}/users/{$userId}/photo/\$value";
        
        // ✅ RETRY COM BACKOFF: Tentar até 3 vezes com delays crescentes
        $maxRetries = 3;
        $retryDelay = 2; // segundos
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->accessToken}",
                "Content-Type: image/jpeg"
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 segundos
            
            $photoData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Atualizar timestamp da última requisição
            file_put_contents($rateLimitFile, time());
            
            // Sucesso
            if ($httpCode === 200 && $photoData) {
                // Converter para base64
                $base64 = base64_encode($photoData);
                $result = [
                    'photo_base64' => $base64,
                    'photo_url' => "data:image/jpeg;base64,{$base64}"
                ];
                
                // Salvar no cache
                $cacheDir = dirname($cacheFile);
                if (!file_exists($cacheDir)) {
                    mkdir($cacheDir, 0755, true);
                }
                file_put_contents($cacheFile, json_encode($result));
                
                return [
                    'success' => true,
                    'data' => $result
                ];
            }
            
            // Erro 429 (Too Many Requests) - aguardar e tentar novamente
            if ($httpCode === 429) {
                if ($attempt < $maxRetries) {
                    error_log("[TeamsGraphAPI] Rate limit atingido (429), aguardando {$retryDelay}s antes de tentar novamente (tentativa {$attempt}/{$maxRetries})");
                    sleep($retryDelay);
                    $retryDelay *= 2; // Backoff exponencial
                    continue;
                }
            }
            
            // Erro 404 (Not Found) - usuário não tem foto
            if ($httpCode === 404) {
                return [
                    'success' => false,
                    'error' => 'Foto não encontrada',
                    'code' => 404
                ];
            }
            
            // Outros erros - não tentar novamente
            break;
        }
        
        return [
            'success' => false,
            'error' => 'Foto não encontrada ou não disponível',
            'code' => $httpCode ?? 0
        ];
    }
    
    /**
     * Salvar foto de perfil localmente
     * @param string $userId ID do usuário no Azure AD
     * @param string $displayName Nome do usuário
     * @return array Caminho da foto salva
     */
    public function saveUserPhotoLocally($userId, $displayName) {
        $photoResult = $this->getUserPhoto($userId);
        
        if (!$photoResult['success']) {
            return $photoResult;
        }
        
        // Criar diretório se não existir
        $uploadDir = __DIR__ . '/../../uploads/profile_pictures/teams/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Nome do arquivo
        $filename = preg_replace('/[^a-z0-9_-]/i', '_', $userId) . '.jpg';
        $filepath = $uploadDir . $filename;
        
        // Salvar arquivo
        $photoData = base64_decode($photoResult['data']['photo_base64']);
        file_put_contents($filepath, $photoData);
        
        // Retornar caminho relativo
        $relativePath = '/uploads/profile_pictures/teams/' . $filename;
        
        return [
            'success' => true,
            'data' => [
                'local_path' => $relativePath,
                'full_path' => $filepath
            ]
        ];
    }

    /**
     * Buscar membros de um chat
     * @param string $chatId ID do chat
     * @return array Lista de membros
     */
    public function getChatMembers($chatId) {
        $response = $this->graphRequest('GET', "/chats/{$chatId}/members");
        
        if ($response['success']) {
            return [
                'success' => true,
                'data' => $response['data']['value'] ?? []
            ];
        }
        
        return $response;
    }

    /**
     * Buscar informações do usuário logado
     * @return array Informações do usuário
     */
    public function getMyInfo() {
        $response = $this->graphRequest('GET', '/me');
        
        if ($response['success']) {
            return [
                'success' => true,
                'data' => [
                    'id' => $response['data']['id'] ?? null,
                    'displayName' => $response['data']['displayName'] ?? null,
                    'mail' => $response['data']['mail'] ?? $response['data']['userPrincipalName'] ?? null,
                    'userPrincipalName' => $response['data']['userPrincipalName'] ?? null
                ]
            ];
        }
        
        return $response;
    }
    
    /**
     * Enviar mensagem com anexo para um chat
     * 
     * Envia uma mensagem de texto com um arquivo anexado via Microsoft Teams.
     * Suporta inline attachments (base64) para arquivos pequenos.
     * 
     * @param string $chatId ID do chat no Teams
     * @param string $message Texto da mensagem (pode ser vazio)
     * @param array $attachment Dados do anexo com estrutura:
     *   - id: ID único do anexo
     *   - contentType: MIME type do arquivo
     *   - contentUrl: URL do conteúdo (opcional, para hosted content)
     *   - name: Nome do arquivo
     *   - contentBytes: Conteúdo em base64 (para inline attachments)
     * @return array ['success' => bool, 'data' => array, 'error' => string|null]
     * 
     * @example
     * $attachment = [
     *     'id' => uniqid('attachment_'),
     *     'contentType' => 'image/jpeg',
     *     'name' => 'foto.jpg',
     *     'contentBytes' => base64_encode($fileContent)
     * ];
     * $result = $api->sendChatMessageWithAttachment($chatId, 'Veja esta foto', $attachment);
     */
    public function sendChatMessageWithAttachment($chatId, $message, $attachment) {
        // Preparar payload da mensagem
        $data = [
            'body' => [
                'contentType' => 'html',
                'content' => $message ?: '<attachment id="' . $attachment['id'] . '"></attachment>'
            ],
            'attachments' => [$attachment]
        ];
        
        // Log para debug
        error_log("[TeamsGraphAPI] Enviando mensagem com anexo para chat: {$chatId}");
        error_log("[TeamsGraphAPI] Attachment ID: " . $attachment['id']);
        error_log("[TeamsGraphAPI] Attachment Type: " . $attachment['contentType']);
        error_log("[TeamsGraphAPI] Attachment Name: " . $attachment['name']);
        
        // Enviar via Graph API
        $result = $this->graphRequest('POST', "/chats/{$chatId}/messages", $data);
        
        if ($result['success']) {
            error_log("[TeamsGraphAPI] Mensagem com anexo enviada com sucesso");
        } else {
            error_log("[TeamsGraphAPI] Erro ao enviar mensagem com anexo: " . ($result['error'] ?? 'Erro desconhecido'));
        }
        
        return $result;
    }
    
    /**
     * Upload de arquivo como hosted content
     * 
     * Faz upload de um arquivo para o Teams como "hosted content" e retorna
     * a URL do conteúdo que pode ser usada em attachments.
     * 
     * Nota: Este método é útil para arquivos maiores (> 3MB) que não podem
     * ser enviados como inline attachments.
     * 
     * @param string $chatId ID do chat no Teams
     * @param string $filePath Caminho completo do arquivo no servidor
     * @param string $fileName Nome do arquivo a ser exibido
     * @return array ['success' => bool, 'contentUrl' => string, 'error' => string|null]
     * 
     * @example
     * $result = $api->uploadHostedContent($chatId, '/path/to/file.pdf', 'documento.pdf');
     * if ($result['success']) {
     *     $contentUrl = $result['contentUrl'];
     *     // Usar contentUrl no attachment
     * }
     */
    public function uploadHostedContent($chatId, $filePath, $fileName) {
        // Verificar se arquivo existe
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'Arquivo não encontrado: ' . $filePath
            ];
        }
        
        // Verificar tamanho do arquivo (limite da API: 4MB para hosted content)
        $fileSize = filesize($filePath);
        $maxSize = 4 * 1024 * 1024; // 4MB
        
        if ($fileSize > $maxSize) {
            return [
                'success' => false,
                'error' => 'Arquivo muito grande para hosted content. Máximo: 4MB, Arquivo: ' . round($fileSize / 1024 / 1024, 2) . 'MB'
            ];
        }
        
        // Ler arquivo e converter para base64
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return [
                'success' => false,
                'error' => 'Erro ao ler arquivo: ' . $filePath
            ];
        }
        
        $base64 = base64_encode($fileContent);
        $mimeType = mime_content_type($filePath);
        
        // Preparar payload
        $data = [
            '@microsoft.graph.temporaryId' => uniqid('temp_'),
            'contentBytes' => $base64,
            'contentType' => $mimeType
        ];
        
        // Log para debug
        error_log("[TeamsGraphAPI] Fazendo upload de hosted content para chat: {$chatId}");
        error_log("[TeamsGraphAPI] Arquivo: {$fileName}");
        error_log("[TeamsGraphAPI] Tamanho: " . round($fileSize / 1024, 2) . " KB");
        error_log("[TeamsGraphAPI] MIME Type: {$mimeType}");
        error_log("[TeamsGraphAPI] Base64 length: " . strlen($base64));
        
        // Upload via Graph API
        $result = $this->graphRequest('POST', "/chats/{$chatId}/messages/hostedContents", $data);
        
        if ($result['success']) {
            // A API retorna o ID do hosted content
            // Precisamos construir a URL completa
            $hostedContentId = $result['data']['id'] ?? null;
            
            if ($hostedContentId) {
                // Construir URL completa do hosted content
                $contentUrl = $this->graphApiUrl . "/chats/{$chatId}/messages/hostedContents/{$hostedContentId}/\$value";
                
                error_log("[TeamsGraphAPI] Hosted content enviado com sucesso");
                error_log("[TeamsGraphAPI] Hosted Content ID: {$hostedContentId}");
                error_log("[TeamsGraphAPI] Content URL: {$contentUrl}");
                
                return [
                    'success' => true,
                    'contentUrl' => $contentUrl,
                    'hostedContentId' => $hostedContentId,
                    'data' => $result['data']
                ];
            } else {
                error_log("[TeamsGraphAPI] ERRO: ID do hosted content não retornado");
                error_log("[TeamsGraphAPI] Resposta da API: " . json_encode($result['data']));
                return [
                    'success' => false,
                    'error' => 'ID do hosted content não retornado pela API'
                ];
            }
        }
        
        error_log("[TeamsGraphAPI] Erro ao fazer upload de hosted content: " . ($result['error'] ?? 'Erro desconhecido'));
        error_log("[TeamsGraphAPI] Detalhes do erro: " . json_encode($result));
        return $result;
    }
}
