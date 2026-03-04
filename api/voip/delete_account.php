<?php
/**
 * API: Excluir Conta VoIP
 * Remove conta SIP do usuário
 */

header('Content-Type: application/json');

// Iniciar sessão
if (!isset($_SESSION)) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];

try {
    // Obter dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $accountId = $input['account_id'] ?? null;
    
    if (!$accountId) {
        throw new Exception('ID da conta não fornecido');
    }
    
    // Verificar se a conta pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT extension, sip_domain 
        FROM voip_users 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$accountId, $userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        throw new Exception('Conta não encontrada ou sem permissão');
    }
    
    $pdo->beginTransaction();
    
    // Excluir conta
    $stmt = $pdo->prepare("DELETE FROM voip_users WHERE id = ? AND user_id = ?");
    $stmt->execute([$accountId, $userId]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Conta excluída com sucesso'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
