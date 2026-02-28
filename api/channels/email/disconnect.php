<?php
/**
 * API para desconectar canal Email
 */

session_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../../includes/functions.php';
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar dependências: ' . $e->getMessage()
    ]);
    exit;
}

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Buscar canal Email
    $stmt = $pdo->prepare("SELECT id FROM channels WHERE channel_type = 'email' LIMIT 1");
    $stmt->execute();
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$channel) {
        echo json_encode([
            'success' => false,
            'error' => 'Canal Email não encontrado'
        ]);
        exit;
    }
    
    $channelId = $channel['id'];
    
    // Deletar mensagens relacionadas ao canal
    $stmt = $pdo->prepare("
        DELETE m FROM messages m
        INNER JOIN conversations c ON m.conversation_id = c.id
        WHERE c.channel_id = ?
    ");
    $stmt->execute([$channelId]);
    
    // Deletar conversas relacionadas ao canal
    $stmt = $pdo->prepare("DELETE FROM conversations WHERE channel_id = ?");
    $stmt->execute([$channelId]);
    
    // Deletar configurações do Email
    $stmt = $pdo->prepare("DELETE FROM channel_email WHERE channel_id = ?");
    $stmt->execute([$channelId]);
    
    // Atualizar status do canal para 'inactive'
    $stmt = $pdo->prepare("UPDATE channels SET status = 'inactive', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$channelId]);
    
    // Commit da transação
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Canal Email desconectado com sucesso. Todos os emails foram removidos.'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao desconectar canal: ' . $e->getMessage()
    ]);
}
