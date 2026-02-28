<?php
/**
 * API para listar chats do Teams
 * Retorna todos os chats disponíveis na conta do usuário
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/channels/TeamsGraphAPI.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $teamsAPI = new TeamsGraphAPI($pdo, $userId);
    
    if (!$teamsAPI->isAuthenticated()) {
        echo json_encode(['success' => false, 'error' => 'Teams não autenticado']);
        exit;
    }
    
    // Listar todos os chats
    $chatsResult = $teamsAPI->listChats();
    
    if (!$chatsResult['success']) {
        echo json_encode([
            'success' => false, 
            'error' => $chatsResult['error'] ?? 'Erro ao listar chats'
        ]);
        exit;
    }
    
    $chats = $chatsResult['data']['value'] ?? [];
    
    // Formatar resposta
    $formattedChats = [];
    foreach ($chats as $chat) {
        $formattedChats[] = [
            'id' => $chat['id'] ?? null,
            'chatType' => $chat['chatType'] ?? 'unknown',
            'topic' => $chat['topic'] ?? null,
            'createdDateTime' => $chat['createdDateTime'] ?? null,
            'lastUpdatedDateTime' => $chat['lastUpdatedDateTime'] ?? null,
            'webUrl' => $chat['webUrl'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'total' => count($formattedChats),
        'chats' => $formattedChats
    ]);
    
} catch (Exception $e) {
    error_log("[Teams List Chats] Erro: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
