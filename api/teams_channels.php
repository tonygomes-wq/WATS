<?php
/**
 * API DE GERENCIAMENTO DE CANAIS DO MICROSOFT TEAMS
 * CRUD de canais do Teams
 * 
 * @author MAC-IP TECNOLOGIA
 * @version 1.0
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/channels/TeamsChannel.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$teams = new TeamsChannel($pdo, $userId);

try {
    switch ($method) {
        case 'GET':
            handleGet($teams);
            break;
            
        case 'POST':
            handlePost($teams);
            break;
            
        case 'PUT':
            handlePut($teams);
            break;
            
        case 'DELETE':
            handleDelete($teams);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * GET - Listar canais ou testar webhook
 */
function handleGet($teams) {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'test') {
        $webhookUrl = $_GET['webhook_url'] ?? '';
        
        if (empty($webhookUrl)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'webhook_url é obrigatório']);
            return;
        }
        
        $result = $teams->testWebhook($webhookUrl);
        echo json_encode($result);
        return;
    }
    
    // Listar canais
    $channels = $teams->listChannels();
    echo json_encode(['success' => true, 'channels' => $channels]);
}

/**
 * POST - Adicionar novo canal
 */
function handlePost($teams) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $channelName = trim($input['channel_name'] ?? '');
    $webhookUrl = trim($input['webhook_url'] ?? '');
    $teamName = trim($input['team_name'] ?? '');
    
    if (empty($channelName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nome do canal é obrigatório']);
        return;
    }
    
    if (empty($webhookUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'URL do webhook é obrigatória']);
        return;
    }
    
    // Validar URL
    if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'URL do webhook inválida']);
        return;
    }
    
    // Testar webhook antes de salvar
    $testResult = $teams->testWebhook($webhookUrl);
    
    if (!$testResult['success']) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Falha ao testar webhook: ' . $testResult['message']
        ]);
        return;
    }
    
    // Adicionar canal
    $channelId = $teams->addChannel($channelName, $webhookUrl, $teamName);
    
    echo json_encode([
        'success' => true,
        'channel_id' => $channelId,
        'message' => 'Canal adicionado com sucesso'
    ]);
}

/**
 * PUT - Atualizar canal
 */
function handlePut($teams) {
    $channelId = $_GET['id'] ?? null;
    
    if (!$channelId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do canal é obrigatório']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $result = $teams->updateChannel($channelId, $input);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Canal atualizado com sucesso']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Falha ao atualizar canal']);
    }
}

/**
 * DELETE - Deletar canal
 */
function handleDelete($teams) {
    $channelId = $_GET['id'] ?? null;
    
    if (!$channelId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do canal é obrigatório']);
        return;
    }
    
    $result = $teams->deleteChannel($channelId);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Canal deletado com sucesso']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Falha ao deletar canal']);
    }
}
