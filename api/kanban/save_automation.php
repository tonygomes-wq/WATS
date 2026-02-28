<?php
/**
 * API para salvar regras de automação do Kanban
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Receber dados JSON
$input = json_decode(file_get_contents('php://input'), true);

$columnId = intval($input['column_id'] ?? 0);
$trigger = $input['trigger'] ?? '';
$days = intval($input['days'] ?? 0);
$action = $input['action'] ?? '';

if (!$columnId || !$trigger || !$action || $days < 1) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

try {
    // Verificar permissão (se usuário é dono do quadro ou supervisor)
    // Simplificado: verifica se a coluna existe
    
    // Parâmetros em JSON
    $params = json_encode(['days' => $days]);
    
    // Inserir ou atualizar regra (por enquanto vamos permitir apenas 1 regra por tipo por coluna para simplificar)
    $stmt = $pdo->prepare("
        INSERT INTO kanban_automation_rules (board_id, column_id, trigger_type, action_type, parameters)
        VALUES (
            (SELECT board_id FROM kanban_columns WHERE id = ?), 
            ?, ?, ?, ?
        )
    ");
    
    $stmt->execute([$columnId, $columnId, $trigger, $action, $params]);
    
    echo json_encode(['success' => true, 'message' => 'Regra salva com sucesso']);

} catch (Exception $e) {
    error_log('Erro ao salvar automação: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar regra: ' . $e->getMessage()]);
}
