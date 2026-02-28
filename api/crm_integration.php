<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/crm_integration.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$crm = new CRMIntegration($pdo, $userId);

try {
    switch ($action) {
        case 'configure':
            $data = [
                'crm_type' => $_POST['crm_type'] ?? '',
                'api_key' => $_POST['api_key'] ?? '',
                'api_secret' => $_POST['api_secret'] ?? null,
                'webhook_url' => $_POST['webhook_url'] ?? null,
                'sync_contacts' => isset($_POST['sync_contacts']) ? (bool)$_POST['sync_contacts'] : true,
                'sync_responses' => isset($_POST['sync_responses']) ? (bool)$_POST['sync_responses'] : true
            ];
            
            if (empty($data['crm_type']) || empty($data['api_key'])) {
                throw new Exception('Tipo de CRM e API Key são obrigatórios');
            }
            
            $integrationId = $crm->configure($data);
            
            echo json_encode([
                'success' => true,
                'integration_id' => $integrationId,
                'message' => 'Integração configurada com sucesso'
            ]);
            break;
            
        case 'get':
            $integration = $crm->getIntegration();
            
            // Ocultar API key parcialmente
            if ($integration && $integration['api_key']) {
                $integration['api_key_masked'] = substr($integration['api_key'], 0, 8) . '********';
                unset($integration['api_key']);
            }
            
            echo json_encode([
                'success' => true,
                'integration' => $integration
            ]);
            break;
            
        case 'test':
            $result = $crm->testConnection();
            
            echo json_encode([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Conexão bem sucedida' : ($result['error'] ?? 'Falha na conexão'),
                'details' => $result
            ]);
            break;
            
        case 'sync_contact':
            $contact = [
                'name' => $_POST['name'] ?? '',
                'phone' => $_POST['phone'] ?? ''
            ];
            
            if (empty($contact['phone'])) {
                throw new Exception('Telefone é obrigatório');
            }
            
            $result = $crm->syncContact($contact);
            
            echo json_encode([
                'success' => $result['success'],
                'result' => $result
            ]);
            break;
            
        case 'sync_response':
            $response = [
                'phone' => $_POST['phone'] ?? '',
                'message_text' => $_POST['message_text'] ?? '',
                'sentiment' => $_POST['sentiment'] ?? 'unknown'
            ];
            
            if (empty($response['phone'])) {
                throw new Exception('Telefone é obrigatório');
            }
            
            $result = $crm->syncResponse($response);
            
            echo json_encode([
                'success' => $result['success'],
                'result' => $result
            ]);
            break;
            
        case 'logs':
            $limit = (int)($_GET['limit'] ?? 50);
            $logs = $crm->getSyncLogs($limit);
            
            echo json_encode([
                'success' => true,
                'logs' => $logs
            ]);
            break;
            
        case 'disable':
            $success = $crm->disable();
            
            echo json_encode([
                'success' => $success,
                'message' => 'Integração desabilitada'
            ]);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
