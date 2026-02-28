<?php
/**
 * Sistema de Permissões Baseadas em Planos
 * 
 * Este arquivo contém funções para verificar permissões, limites e
 * funcionalidades disponíveis baseadas no plano do usuário.
 * 
 * @package WATS
 * @version 1.0
 * @date 2026-02-25
 */

/**
 * Obtém as features completas do plano do usuário
 * 
 * @param int $userId ID do usuário
 * @return array|false Array com features ou false se não encontrado
 */
function getUserPlanFeatures($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT pf.* 
            FROM users u
            JOIN pricing_plans pp ON u.plan = pp.slug
            JOIN plan_features pf ON pp.id = pf.plan_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $features = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se não encontrou features, retornar features padrão (plano free)
        if (!$features) {
            return getDefaultPlanFeatures();
        }
        
        return $features;
    } catch (Exception $e) {
        error_log("Erro ao buscar features do plano: " . $e->getMessage());
        return getDefaultPlanFeatures();
    }
}

/**
 * Retorna features padrão para plano free/não configurado
 * 
 * @return array Features padrão
 */
function getDefaultPlanFeatures() {
    return [
        'max_messages' => 2000,
        'max_attendants' => 1,
        'max_departments' => 1,
        'max_contacts' => 1000,
        'max_whatsapp_instances' => 1,
        'max_automation_flows' => 5,
        'max_dispatch_campaigns' => 10,
        'max_tags' => 20,
        'max_quick_replies' => 50,
        'max_file_storage_mb' => 100,
        'module_chat' => 1,
        'module_dashboard' => 1,
        'module_dispatch' => 0,
        'module_contacts' => 1,
        'module_kanban' => 0,
        'module_automation' => 0,
        'module_reports' => 0,
        'module_integrations' => 0,
        'module_api' => 0,
        'module_webhooks' => 0,
        'module_ai' => 0,
        'feature_multi_attendant' => 0,
        'feature_departments' => 0,
        'feature_tags' => 1,
        'feature_quick_replies' => 1,
        'feature_file_upload' => 1,
        'feature_media_library' => 0,
        'feature_custom_fields' => 0,
        'feature_export_data' => 0,
        'feature_white_label' => 0,
        'feature_priority_support' => 0,
        'integration_google_sheets' => 0,
        'integration_zapier' => 0,
        'integration_n8n' => 0,
        'integration_make' => 0,
        'integration_crm' => 0,
    ];
}

/**
 * Verifica se usuário tem acesso a um módulo específico
 * 
 * @param int $userId ID do usuário
 * @param string $moduleName Nome do módulo (sem prefixo 'module_')
 * @return bool True se tem acesso, false caso contrário
 */
function userHasModule($userId, $moduleName) {
    $features = getUserPlanFeatures($userId);
    if (!$features) return false;
    
    $moduleKey = 'module_' . $moduleName;
    return isset($features[$moduleKey]) && $features[$moduleKey] == 1;
}

/**
 * Verifica se usuário tem uma funcionalidade específica
 * 
 * @param int $userId ID do usuário
 * @param string $featureName Nome da funcionalidade (sem prefixo 'feature_')
 * @return bool True se tem acesso, false caso contrário
 */
function userHasFeature($userId, $featureName) {
    $features = getUserPlanFeatures($userId);
    if (!$features) return false;
    
    $featureKey = 'feature_' . $featureName;
    return isset($features[$featureKey]) && $features[$featureKey] == 1;
}

/**
 * Verifica se usuário tem acesso a uma integração específica
 * 
 * @param int $userId ID do usuário
 * @param string $integrationName Nome da integração (sem prefixo 'integration_')
 * @return bool True se tem acesso, false caso contrário
 */
function userHasIntegration($userId, $integrationName) {
    $features = getUserPlanFeatures($userId);
    if (!$features) return false;
    
    $integrationKey = 'integration_' . $integrationName;
    return isset($features[$integrationKey]) && $features[$integrationKey] == 1;
}

/**
 * Obtém o limite de um recurso específico
 * 
 * @param int $userId ID do usuário
 * @param string $limitName Nome do limite (sem prefixo 'max_')
 * @return int Limite do recurso (-1 = ilimitado, 0 = desabilitado)
 */
function getUserLimit($userId, $limitName) {
    $features = getUserPlanFeatures($userId);
    if (!$features) return 0;
    
    $limitKey = 'max_' . $limitName;
    return isset($features[$limitKey]) ? intval($features[$limitKey]) : 0;
}

/**
 * Verifica se usuário atingiu o limite de um recurso
 * 
 * @param int $userId ID do usuário
 * @param string $resourceName Nome do recurso (sem prefixo 'max_')
 * @param int $currentCount Contagem atual do recurso
 * @return bool True se atingiu o limite, false caso contrário
 */
function userReachedLimit($userId, $resourceName, $currentCount) {
    $limit = getUserLimit($userId, $resourceName);
    
    // -1 = ilimitado
    if ($limit == -1) return false;
    
    // 0 = desabilitado
    if ($limit == 0) return true;
    
    return $currentCount >= $limit;
}

/**
 * Verifica se usuário pode adicionar mais de um recurso
 * 
 * @param int $userId ID do usuário
 * @param string $resourceName Nome do recurso
 * @param int $currentCount Contagem atual
 * @return array ['allowed' => bool, 'limit' => int, 'remaining' => int, 'message' => string]
 */
function canUserAddResource($userId, $resourceName, $currentCount) {
    $limit = getUserLimit($userId, $resourceName);
    
    // Ilimitado
    if ($limit == -1) {
        return [
            'allowed' => true,
            'limit' => -1,
            'remaining' => -1,
            'message' => 'Recurso ilimitado'
        ];
    }
    
    // Desabilitado
    if ($limit == 0) {
        return [
            'allowed' => false,
            'limit' => 0,
            'remaining' => 0,
            'message' => 'Este recurso não está disponível no seu plano'
        ];
    }
    
    // Verificar se atingiu o limite
    if ($currentCount >= $limit) {
        return [
            'allowed' => false,
            'limit' => $limit,
            'remaining' => 0,
            'message' => "Você atingiu o limite de {$limit} " . getResourceLabel($resourceName) . " do seu plano"
        ];
    }
    
    return [
        'allowed' => true,
        'limit' => $limit,
        'remaining' => $limit - $currentCount,
        'message' => 'Você pode adicionar mais ' . ($limit - $currentCount) . ' ' . getResourceLabel($resourceName)
    ];
}

/**
 * Obtém label amigável para um recurso
 * 
 * @param string $resourceName Nome do recurso
 * @return string Label amigável
 */
function getResourceLabel($resourceName) {
    $labels = [
        'messages' => 'mensagens',
        'attendants' => 'atendentes',
        'departments' => 'setores',
        'contacts' => 'contatos',
        'whatsapp_instances' => 'instâncias WhatsApp',
        'automation_flows' => 'fluxos de automação',
        'dispatch_campaigns' => 'campanhas de disparo',
        'tags' => 'tags',
        'quick_replies' => 'respostas rápidas',
        'file_storage_mb' => 'MB de armazenamento',
    ];
    
    return $labels[$resourceName] ?? $resourceName;
}

/**
 * Obtém mensagem de upgrade para recurso bloqueado
 * 
 * @param string $resourceName Nome do recurso ou módulo
 * @param string $type Tipo: 'module', 'feature', 'integration', 'limit'
 * @return string Mensagem HTML formatada
 */
function getUpgradeMessage($resourceName, $type = 'module') {
    $messages = [
        'module' => "O módulo <strong>{$resourceName}</strong> não está disponível no seu plano atual.",
        'feature' => "A funcionalidade <strong>{$resourceName}</strong> não está disponível no seu plano atual.",
        'integration' => "A integração com <strong>{$resourceName}</strong> não está disponível no seu plano atual.",
        'limit' => "Você atingiu o limite de <strong>{$resourceName}</strong> do seu plano atual."
    ];
    
    $message = $messages[$type] ?? "Este recurso não está disponível no seu plano atual.";
    
    return $message . ' <a href="upgrade.php" class="font-bold text-green-600 hover:text-green-700">Faça upgrade agora!</a>';
}

/**
 * Renderiza alerta de upgrade
 * 
 * @param string $resourceName Nome do recurso
 * @param string $type Tipo do recurso
 * @param string $icon Ícone FontAwesome
 * @return string HTML do alerta
 */
function renderUpgradeAlert($resourceName, $type = 'module', $icon = 'fa-lock') {
    $message = getUpgradeMessage($resourceName, $type);
    
    return '
    <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-500 p-6 rounded-lg shadow-lg mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas ' . $icon . ' text-yellow-600 text-3xl"></i>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-lg font-bold text-yellow-900 dark:text-yellow-100 mb-2">
                    Recurso Bloqueado
                </h3>
                <p class="text-yellow-800 dark:text-yellow-200">
                    ' . $message . '
                </p>
            </div>
        </div>
    </div>';
}

/**
 * Obtém informações completas do plano do usuário
 * 
 * @param int $userId ID do usuário
 * @return array Informações do plano
 */
function getUserPlanInfo($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                pp.id,
                pp.slug,
                pp.name,
                pp.price,
                pp.message_limit,
                pf.*
            FROM users u
            JOIN pricing_plans pp ON u.plan = pp.slug
            LEFT JOIN plan_features pf ON pp.id = pf.plan_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao buscar info do plano: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica múltiplas permissões de uma vez
 * 
 * @param int $userId ID do usuário
 * @param array $checks Array de verificações ['type' => 'module|feature|integration', 'name' => 'nome']
 * @return array Resultado das verificações
 */
function checkMultiplePermissions($userId, $checks) {
    $results = [];
    
    foreach ($checks as $check) {
        $type = $check['type'] ?? 'module';
        $name = $check['name'] ?? '';
        
        switch ($type) {
            case 'module':
                $results[$name] = userHasModule($userId, $name);
                break;
            case 'feature':
                $results[$name] = userHasFeature($userId, $name);
                break;
            case 'integration':
                $results[$name] = userHasIntegration($userId, $name);
                break;
        }
    }
    
    return $results;
}
