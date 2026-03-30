<?php
/**
 * API: Corrigir Timestamps das Mensagens
 * Converte timestamps de milissegundos para segundos
 */

header('Content-Type: application/json');

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Buscar mensagens com timestamp suspeito (muito grande = milissegundos)
    $stmt = $pdo->prepare("
        SELECT id, timestamp
        FROM chat_messages
        WHERE user_id = ?
        AND timestamp > 9999999999
        ORDER BY id DESC
        LIMIT 1000
    ");
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixed = 0;
    
    foreach ($messages as $msg) {
        $oldTimestamp = $msg['timestamp'];
        $newTimestamp = (int)($oldTimestamp / 1000);
        
        $updateStmt = $pdo->prepare("
            UPDATE chat_messages 
            SET timestamp = ? 
            WHERE id = ?
        ");
        
        if ($updateStmt->execute([$newTimestamp, $msg['id']])) {
            $fixed++;
        }
    }
    
    // Atualizar last_message_time das conversas
    $stmt = $pdo->prepare("
        UPDATE chat_conversations cc
        SET last_message_time = (
            SELECT FROM_UNIXTIME(MAX(cm.timestamp))
            FROM chat_messages cm
            WHERE cm.conversation_id = cc.id
        )
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    
    echo json_encode([
        'success' => true,
        'fixed' => $fixed,
        'total' => count($messages)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
