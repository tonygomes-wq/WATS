<?php
require_once __DIR__ . '/ProviderInterface.php';
require_once __DIR__ . '/providers/EvolutionProvider.php';
require_once __DIR__ . '/providers/ZAPIProvider.php';
require_once __DIR__ . '/../IdentifierResolver.php';

/**
 * Classe principal para comunicação WhatsApp
 * Suporta múltiplos providers (Evolution API, Z-API)
 * Usa padrão Factory para criar provider apropriado
 */
class WhatsAppChannel {
    private $pdo;
    private $instance;
    private $provider;
    private $resolver;
    
    /**
     * Construtor
     * 
     * @param int|array $userIdOrData ID do usuário ou array com dados
     * @param PDO|null $pdo Conexão PDO (usa global se não fornecido)
     */
    public function __construct($userIdOrData, $pdo = null) {
        global $pdo as $globalPdo;
        $this->pdo = $pdo ?? $globalPdo;
        
        // Se recebeu ID, carregar dados do usuário
        if (is_numeric($userIdOrData)) {
            $this->loadInstance($userIdOrData);
        } else {
            // Se recebeu array, usar diretamente
            $this->instance = $userIdOrData;
        }
        
        if (!$this->instance) {
            throw new Exception("Configuração WhatsApp não encontrada");
        }
        
        // Criar provider apropriado
        $this->provider = $this->createProvider();
        $this->resolver = new IdentifierResolver($this->pdo);
    }
    
    /**
     * Carrega configuração do usuário do banco de dados
     * Sistema usa tabela 'users' para armazenar configurações de instância
     */
    private function loadInstance($userId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                evolution_instance as instance_id,
                evolution_token as token,
                evolution_api_url as api_url,
                evolution_api_key,
                whatsapp_provider as provider,
                zapi_instance_id,
                zapi_token,
                provider_config,
                supports_lid,
                meta_phone_number_id,
                meta_business_account_id
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $this->instance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Normalizar dados para formato esperado pelos providers
        if ($this->instance) {
            // Se não tem provider definido, detectar
            if (empty($this->instance['provider'])) {
                if (!empty($this->instance['zapi_instance_id'])) {
                    $this->instance['provider'] = 'zapi';
                } elseif (!empty($this->instance['meta_phone_number_id'])) {
                    $this->instance['provider'] = 'meta';
                } else {
                    $this->instance['provider'] = 'evolution';
                }
            }
            
            // Para Z-API, usar campos específicos
            if ($this->instance['provider'] === 'zapi') {
                $this->instance['instance_id'] = $this->instance['zapi_instance_id'];
                $this->instance['token'] = $this->instance['zapi_token'];
            }
            
            // Garantir api_key para Evolution
            if (empty($this->instance['api_key']) && !empty($this->instance['evolution_api_key'])) {
                $this->instance['api_key'] = $this->instance['evolution_api_key'];
            }
            if (empty($this->instance['api_key']) && !empty($this->instance['token'])) {
                $this->instance['api_key'] = $this->instance['token'];
            }
        }
    }
    
    /**
     * Factory para criar provider baseado na configuração
     * Suporta: evolution, zapi, baileys
     */
    private function createProvider() {
        $provider = $this->instance['provider'] ?? 'evolution';
        
        switch ($provider) {
            case 'zapi':
                return new ZAPIProvider($this->instance, $this->pdo);
            
            case 'evolution':
            case 'baileys':
                return new EvolutionProvider($this->instance, $this->pdo);
            
            default:
                throw new Exception("Provider não suportado: " . $provider);
        }
    }
    
    /**
     * Enviar mensagem (detecta tipo automaticamente)
     * 
     * @param string $identifier Identificador do destinatário
     * @param string $message Mensagem ou caption
     * @param array $options Opções adicionais (type, media_url, etc)
     * @return array Resultado com success, messageId, error
     */
    public function sendMessage($identifier, $message, $options = []) {
        $type = $options['type'] ?? 'text';
        
        try {
            switch ($type) {
                case 'text':
                    $result = $this->provider->sendText($identifier, $message);
                    break;
                
                case 'image':
                    $result = $this->provider->sendImage(
                        $identifier,
                        $options['media_url'],
                        $options['caption'] ?? $message
                    );
                    break;
                
                case 'video':
                    $result = $this->provider->sendVideo(
                        $identifier,
                        $options['media_url'],
                        $options['caption'] ?? $message
                    );
                    break;
                
                case 'audio':
                    $result = $this->provider->sendAudio(
                        $identifier,
                        $options['media_url']
                    );
                    break;
                
                case 'document':
                    $result = $this->provider->sendDocument(
                        $identifier,
                        $options['media_url'],
                        $options['filename'] ?? ''
                    );
                    break;
                
                case 'location':
                    $result = $this->provider->sendLocation(
                        $identifier,
                        $options['latitude'],
                        $options['longitude'],
                        $options['name'] ?? '',
                        $options['address'] ?? ''
                    );
                    break;
                
                default:
                    throw new Exception("Tipo de mensagem não suportado: " . $type);
            }
            
            return [
                'success' => true,
                'messageId' => $result['messageId'] ?? $result['key']['id'] ?? null,
                'data' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar status da instância
     * 
     * @return array Status com connected e phone
     */
    public function getStatus() {
        try {
            return $this->provider->getStatus();
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar se identificador existe no WhatsApp
     * 
     * @param string $identifier Identificador a verificar
     * @return array Resultado com exists e name
     */
    public function checkIdentifier($identifier) {
        try {
            return $this->provider->checkIdentifier($identifier);
        } catch (Exception $e) {
            return [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Alias para checkIdentifier (compatibilidade)
     */
    public function checkPhone($phone) {
        return $this->checkIdentifier($phone);
    }
    
    /**
     * Obter foto de perfil
     * 
     * @param string $identifier Identificador
     * @return string|null URL da foto ou null
     */
    public function getProfilePicture($identifier) {
        try {
            return $this->provider->getProfilePicture($identifier);
        } catch (Exception $e) {
            error_log("[WhatsAppChannel] Erro ao obter foto de perfil: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Criar grupo
     * 
     * @param string $name Nome do grupo
     * @param array $participants Lista de participantes
     * @return array Resultado
     */
    public function createGroup($name, $participants) {
        try {
            return [
                'success' => true,
                'data' => $this->provider->createGroup($name, $participants)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar se provider suporta LID
     * 
     * @return bool True se suporta LID
     */
    public function supportsLID() {
        return $this->provider->supportsLID();
    }
    
    /**
     * Resolver identificador (LID → Phone ou vice-versa)
     * 
     * @param string $identifier Identificador a resolver
     * @return array Resultado com type, original, resolved, success
     */
    public function resolveIdentifier($identifier) {
        $type = IdentifierResolver::getType($identifier);
        
        if ($type === 'lid') {
            $phone = $this->resolver->resolveToPhone($identifier);
            return [
                'type' => 'lid',
                'original' => $identifier,
                'resolved' => $phone,
                'success' => !empty($phone)
            ];
        } elseif ($type === 'phone') {
            $lid = $this->resolver->resolveToLID($identifier);
            return [
                'type' => 'phone',
                'original' => $identifier,
                'resolved' => $lid,
                'success' => !empty($lid)
            ];
        }
        
        return [
            'type' => $type,
            'original' => $identifier,
            'resolved' => null,
            'success' => false
        ];
    }
    
    /**
     * Obter dados da instância
     * 
     * @return array Dados da instância
     */
    public function getInstance() {
        return $this->instance;
    }
    
    /**
     * Obter provider atual
     * 
     * @return string Nome do provider (evolution, zapi, etc)
     */
    public function getProviderName() {
        return $this->instance['provider'] ?? 'evolution';
    }
}
