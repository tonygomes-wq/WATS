<?php
/**
 * API de Gerenciamento de Planos
 * Permite criar, editar e excluir planos (apenas para suporte@macip.com.br)
 * MACIP Tecnologia LTDA
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar se é o usuário autorizado
if ($_SESSION['user_email'] !== 'suporte@macip.com.br') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas suporte@macip.com.br pode gerenciar planos.']);
    exit;
}

// Permitir apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // Listar todos os planos
            $stmt = $pdo->query("
                SELECT * FROM pricing_plans 
                ORDER BY sort_order ASC, id ASC
            ");
            $plans = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'plans' => $plans
            ]);
            break;
            
        case 'create':
            // Criar novo plano
            $slug = sanitize($input['slug'] ?? '');
            $name = sanitize($input['name'] ?? '');
            $price = floatval($input['price'] ?? 0);
            $messageLimit = intval($input['message_limit'] ?? 0);
            $features = $input['features'] ?? [];
            $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;
            $isPopular = isset($input['is_popular']) ? intval($input['is_popular']) : 0;
            $sortOrder = intval($input['sort_order'] ?? 0);
            
            if (empty($slug) || empty($name)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Slug e nome são obrigatórios'
                ]);
                exit;
            }
            
            // Verificar se slug já existe
            $stmt = $pdo->prepare("SELECT id FROM pricing_plans WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Já existe um plano com este slug'
                ]);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO pricing_plans 
                (slug, name, price, message_limit, features, is_active, is_popular, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $slug,
                $name,
                $price,
                $messageLimit,
                json_encode($features),
                $isActive,
                $isPopular,
                $sortOrder
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Plano criado com sucesso',
                'plan_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'update':
            // Atualizar plano existente
            $id = intval($input['id'] ?? 0);
            $slug = sanitize($input['slug'] ?? '');
            $name = sanitize($input['name'] ?? '');
            $price = floatval($input['price'] ?? 0);
            $messageLimit = intval($input['message_limit'] ?? 0);
            $features = $input['features'] ?? [];
            $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;
            $isPopular = isset($input['is_popular']) ? intval($input['is_popular']) : 0;
            $sortOrder = intval($input['sort_order'] ?? 0);
            
            if ($id <= 0 || empty($slug) || empty($name)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID, slug e nome são obrigatórios'
                ]);
                exit;
            }
            
            // Verificar se plano existe
            $stmt = $pdo->prepare("SELECT id FROM pricing_plans WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Plano não encontrado'
                ]);
                exit;
            }
            
            // Verificar se slug já existe em outro plano
            $stmt = $pdo->prepare("SELECT id FROM pricing_plans WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Já existe outro plano com este slug'
                ]);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE pricing_plans 
                SET slug = ?, name = ?, price = ?, message_limit = ?, 
                    features = ?, is_active = ?, is_popular = ?, sort_order = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $slug,
                $name,
                $price,
                $messageLimit,
                json_encode($features),
                $isActive,
                $isPopular,
                $sortOrder,
                $id
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Plano atualizado com sucesso'
            ]);
            break;
            
        case 'delete':
            // Excluir plano
            $id = intval($input['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID inválido'
                ]);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM pricing_plans WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Plano excluído com sucesso'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Plano não encontrado'
                ]);
            }
            break;
            
        case 'toggle_active':
            // Ativar/desativar plano
            $id = intval($input['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID inválido'
                ]);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE pricing_plans 
                SET is_active = NOT is_active 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Status do plano atualizado'
            ]);
            break;
            
        case 'toggle_popular':
            // Marcar/desmarcar como popular
            $id = intval($input['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID inválido'
                ]);
                exit;
            }
            
            // Remover popular de todos
            $pdo->exec("UPDATE pricing_plans SET is_popular = 0");
            
            // Marcar este como popular
            $stmt = $pdo->prepare("UPDATE pricing_plans SET is_popular = 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Plano marcado como popular'
            ]);
            break;
        
        case 'get_features':
            // Obter features de um plano
            $planId = intval($input['plan_id'] ?? $_GET['plan_id'] ?? 0);
            
            if ($planId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID do plano inválido'
                ]);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM plan_features WHERE plan_id = ?");
            $stmt->execute([$planId]);
            $features = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Se não existir, criar features padrão
            if (!$features) {
                $stmt = $pdo->prepare("INSERT INTO plan_features (plan_id) VALUES (?)");
                $stmt->execute([$planId]);
                
                $stmt = $pdo->prepare("SELECT * FROM plan_features WHERE plan_id = ?");
                $stmt->execute([$planId]);
                $features = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            echo json_encode([
                'success' => true,
                'features' => $features
            ]);
            break;
        
        case 'update_features':
            // Atualizar features de um plano
            $planId = intval($input['plan_id'] ?? 0);
            
            if ($planId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID do plano inválido'
                ]);
                exit;
            }
            
            // Verificar se plano existe
            $stmt = $pdo->prepare("SELECT id FROM pricing_plans WHERE id = ?");
            $stmt->execute([$planId]);
            if (!$stmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Plano não encontrado'
                ]);
                exit;
            }
            
            // Preparar dados das features
            $features = [
                // Limites
                'max_messages' => intval($input['max_messages'] ?? 2000),
                'max_attendants' => intval($input['max_attendants'] ?? 1),
                'max_departments' => intval($input['max_departments'] ?? 1),
                'max_contacts' => intval($input['max_contacts'] ?? 1000),
                'max_whatsapp_instances' => intval($input['max_whatsapp_instances'] ?? 1),
                'max_automation_flows' => intval($input['max_automation_flows'] ?? 5),
                'max_dispatch_campaigns' => intval($input['max_dispatch_campaigns'] ?? 10),
                'max_tags' => intval($input['max_tags'] ?? 20),
                'max_quick_replies' => intval($input['max_quick_replies'] ?? 50),
                'max_file_storage_mb' => intval($input['max_file_storage_mb'] ?? 100),
                
                // Módulos
                'module_chat' => 1, // Sempre habilitado
                'module_dashboard' => isset($input['module_dashboard']) ? 1 : 0,
                'module_dispatch' => isset($input['module_dispatch']) ? 1 : 0,
                'module_contacts' => isset($input['module_contacts']) ? 1 : 0,
                'module_kanban' => isset($input['module_kanban']) ? 1 : 0,
                'module_automation' => isset($input['module_automation']) ? 1 : 0,
                'module_reports' => isset($input['module_reports']) ? 1 : 0,
                'module_integrations' => isset($input['module_integrations']) ? 1 : 0,
                'module_api' => isset($input['module_api']) ? 1 : 0,
                'module_webhooks' => isset($input['module_webhooks']) ? 1 : 0,
                'module_ai' => isset($input['module_ai']) ? 1 : 0,
                
                // Funcionalidades
                'feature_multi_attendant' => isset($input['feature_multi_attendant']) ? 1 : 0,
                'feature_departments' => isset($input['feature_departments']) ? 1 : 0,
                'feature_tags' => isset($input['feature_tags']) ? 1 : 0,
                'feature_quick_replies' => isset($input['feature_quick_replies']) ? 1 : 0,
                'feature_file_upload' => isset($input['feature_file_upload']) ? 1 : 0,
                'feature_media_library' => isset($input['feature_media_library']) ? 1 : 0,
                'feature_custom_fields' => isset($input['feature_custom_fields']) ? 1 : 0,
                'feature_export_data' => isset($input['feature_export_data']) ? 1 : 0,
                'feature_white_label' => isset($input['feature_white_label']) ? 1 : 0,
                'feature_priority_support' => isset($input['feature_priority_support']) ? 1 : 0,
                
                // Integrações
                'integration_google_sheets' => isset($input['integration_google_sheets']) ? 1 : 0,
                'integration_zapier' => isset($input['integration_zapier']) ? 1 : 0,
                'integration_n8n' => isset($input['integration_n8n']) ? 1 : 0,
                'integration_make' => isset($input['integration_make']) ? 1 : 0,
                'integration_crm' => isset($input['integration_crm']) ? 1 : 0,
            ];
            
            // Verificar se já existe registro
            $stmt = $pdo->prepare("SELECT id FROM plan_features WHERE plan_id = ?");
            $stmt->execute([$planId]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Update
                $sql = "UPDATE plan_features SET ";
                $updates = [];
                foreach ($features as $key => $value) {
                    $updates[] = "$key = :$key";
                }
                $sql .= implode(', ', $updates);
                $sql .= " WHERE plan_id = :plan_id";
                
                $features['plan_id'] = $planId;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($features);
            } else {
                // Insert
                $features['plan_id'] = $planId;
                $columns = implode(', ', array_keys($features));
                $placeholders = ':' . implode(', :', array_keys($features));
                
                $sql = "INSERT INTO plan_features ($columns) VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($features);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Features atualizadas com sucesso'
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Ação inválida'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro no gerenciamento de planos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição: ' . $e->getMessage()
    ]);
}
