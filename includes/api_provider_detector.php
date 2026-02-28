<?php
/**
 * Sistema de Detecção Automática de API Provider
 * Detecta se o usuário está usando Evolution API ou Meta API
 */

class ApiProviderDetector {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Detecta qual API o usuário está usando
     * @return array ['provider' => 'evolution'|'meta', 'config' => [...]]
     */
    public function detectProvider($userId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                whatsapp_provider,
                evolution_instance,
                evolution_token,
                evolution_api_url,
                meta_phone_number_id,
                meta_business_account_id,
                meta_app_id,
                meta_app_secret,
                meta_permanent_token,
                meta_webhook_verify_token,
                meta_api_version
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [
                'provider' => null,
                'config' => null,
                'error' => 'Usuário não encontrado'
            ];
        }
        
        // Verificar qual provider está configurado
        $provider = $user['whatsapp_provider'] ?? 'evolution';
        
        // Validar se o provider escolhido está realmente configurado
        if ($provider === 'meta') {
            $isMetaConfigured = $this->isMetaConfigured($user);
            if (!$isMetaConfigured) {
                // Se Meta não está configurado, verificar se Evolution está
                $isEvolutionConfigured = $this->isEvolutionConfigured($user);
                if ($isEvolutionConfigured) {
                    // Fallback para Evolution
                    $provider = 'evolution';
                } else {
                    return [
                        'provider' => null,
                        'config' => null,
                        'error' => 'Nenhuma API configurada'
                    ];
                }
            }
        } else {
            // Provider é Evolution
            $isEvolutionConfigured = $this->isEvolutionConfigured($user);
            if (!$isEvolutionConfigured) {
                // Verificar se Meta está configurado como fallback
                $isMetaConfigured = $this->isMetaConfigured($user);
                if ($isMetaConfigured) {
                    $provider = 'meta';
                } else {
                    return [
                        'provider' => null,
                        'config' => null,
                        'error' => 'Nenhuma API configurada'
                    ];
                }
            }
        }
        
        // Retornar configuração do provider detectado
        if ($provider === 'meta') {
            return [
                'provider' => 'meta',
                'config' => [
                    'meta_phone_number_id' => $user['meta_phone_number_id'],
                    'meta_business_account_id' => $user['meta_business_account_id'],
                    'meta_app_id' => $user['meta_app_id'],
                    'meta_app_secret' => $user['meta_app_secret'],
                    'meta_permanent_token' => $user['meta_permanent_token'],
                    'meta_webhook_verify_token' => $user['meta_webhook_verify_token'],
                    'meta_api_version' => $user['meta_api_version'] ?? 'v19.0'
                ]
            ];
        } else {
            return [
                'provider' => 'evolution',
                'config' => [
                    'evolution_instance' => $user['evolution_instance'],
                    'evolution_token' => $user['evolution_token'],
                    'evolution_api_url' => $user['evolution_api_url'] ?? EVOLUTION_API_URL
                ]
            ];
        }
    }
    
    /**
     * Verifica se Meta API está configurada
     */
    private function isMetaConfigured($user) {
        return !empty($user['meta_phone_number_id']) 
            && !empty($user['meta_permanent_token']);
    }
    
    /**
     * Verifica se Evolution API está configurada
     */
    private function isEvolutionConfigured($user) {
        return !empty($user['evolution_instance']) 
            && !empty($user['evolution_token']);
    }
    
    /**
     * Retorna informações sobre o provider em uso
     */
    public function getProviderInfo($userId) {
        $detection = $this->detectProvider($userId);
        
        if (!$detection['provider']) {
            return [
                'configured' => false,
                'provider' => null,
                'name' => 'Nenhuma',
                'icon' => '❌',
                'description' => 'Configure uma API em Minha Instância'
            ];
        }
        
        if ($detection['provider'] === 'meta') {
            return [
                'configured' => true,
                'provider' => 'meta',
                'name' => 'WhatsApp Business API (Meta)',
                'icon' => '🏢',
                'description' => 'API Oficial da Meta',
                'features' => [
                    'Oficial e estável',
                    'Requer templates aprovados',
                    'Janela de 24h para mensagens'
                ]
            ];
        } else {
            return [
                'configured' => true,
                'provider' => 'evolution',
                'name' => 'Evolution API',
                'icon' => '🚀',
                'description' => 'API via Baileys (WhatsApp Web)',
                'features' => [
                    'Envio livre sem restrições',
                    'Ideal para disparo em massa',
                    'Chat em tempo real'
                ]
            ];
        }
    }
    
    /**
     * Sincroniza provider ao trocar de API
     */
    public function syncProviderChange($userId, $newProvider) {
        // Validar provider
        if (!in_array($newProvider, ['evolution', 'meta'])) {
            return [
                'success' => false,
                'error' => 'Provider inválido'
            ];
        }
        
        // Atualizar no banco
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET whatsapp_provider = ? 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$newProvider, $userId])) {
            // Log da mudança
            error_log("[API_PROVIDER] Usuário $userId trocou para: $newProvider");
            
            return [
                'success' => true,
                'provider' => $newProvider,
                'message' => 'Provider atualizado com sucesso'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Erro ao atualizar provider'
            ];
        }
    }
}

/**
 * Função helper para uso rápido
 */
function detectUserApiProvider($userId) {
    global $pdo;
    $detector = new ApiProviderDetector($pdo);
    return $detector->detectProvider($userId);
}

function getUserProviderInfo($userId) {
    global $pdo;
    $detector = new ApiProviderDetector($pdo);
    return $detector->getProviderInfo($userId);
}
