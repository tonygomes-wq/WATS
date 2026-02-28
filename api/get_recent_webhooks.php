<?php
/**
 * API para buscar webhooks recentes
 * Usado para monitoramento em tempo real
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$last_id = (int)($_GET['last_id'] ?? 0);

try {
    // Buscar instância do usuário
    $stmt = $pdo->prepare("SELECT evolution_instance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || empty($user['evolution_instance'])) {
        echo json_encode(['success' => false, 'error' => 'Instância não configurada']);
        exit;
    }
    
    $instance = $user['evolution_instance'];
    
    // Buscar webhooks recentes desta instância
    $stmt = $pdo->prepare("
        SELECT 
            id,
            event_type,
            instance_name,
            phone,
            processed,
            error_message,
            created_at
        FROM chat_webhook_logs
        WHERE instance_name = ?
        AND id > ?
        ORDER BY id DESC
        LIMIT 20
    ");
    $stmt->execute([$instance, $last_id]);
    $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'webhooks' => $webhooks,
        'count' => count($webhooks)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
