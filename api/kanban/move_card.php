<?php
/**
 * API PARA MOVER CARDS NO KANBAN
 * Atualiza posição e coluna do card
 * 
 * @author MAC-IP TECNOLOGIA
 * @version 1.0
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

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

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';

// Determinar o owner_id
if ($userType === 'attendant') {
    $stmt = $pdo->prepare("SELECT supervisor_id FROM supervisor_users WHERE id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $ownerId = $data['supervisor_id'] ?? $userId;
} else {
    $ownerId = $userId;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $cardId = $input['card_id'] ?? null;
    $newColumnId = $input['column_id'] ?? null;
    $newPosition = $input['position'] ?? 0;
    
    if (!$cardId || !$newColumnId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'card_id e column_id são obrigatórios']);
        exit;
    }
    
    // Verificar se o card pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT kc.id, kc.column_id as old_column_id FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kc.id = ? AND kb.user_id = ?
    ");
    $stmt->execute([$cardId, $ownerId]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$card) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Card não encontrado']);
        exit;
    }
    
    // Verificar se a nova coluna pertence ao mesmo quadro
    $stmt = $pdo->prepare("
        SELECT kcol.id, kcol.wip_limit, kb.id as board_id FROM kanban_columns kcol
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kcol.id = ? AND kb.user_id = ?
    ");
    $stmt->execute([$newColumnId, $ownerId]);
    $newColumn = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$newColumn) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Coluna de destino não encontrada']);
        exit;
    }
    
    // Verificar WIP limit
    if ($newColumn['wip_limit'] > 0 && $card['old_column_id'] != $newColumnId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM kanban_cards WHERE column_id = ? AND is_archived = 0");
        $stmt->execute([$newColumnId]);
        $currentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($currentCount >= $newColumn['wip_limit']) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => "Limite WIP atingido ({$newColumn['wip_limit']} cards)"
            ]);
            exit;
        }
    }
    
    $pdo->beginTransaction();
    
    try {
        $oldColumnId = $card['old_column_id'];
        
        // Atualizar o card diretamente
        $stmt = $pdo->prepare("UPDATE kanban_cards SET column_id = ?, position = ? WHERE id = ?");
        $stmt->execute([$newColumnId, $newPosition, $cardId]);
        
        // Reordenar cards na coluna de destino
        $stmt = $pdo->prepare("
            SELECT id FROM kanban_cards 
            WHERE column_id = ? AND is_archived = 0 
            ORDER BY position, id
        ");
        $stmt->execute([$newColumnId]);
        $cardsInColumn = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Atualizar posições sequencialmente
        $pos = 0;
        $updateStmt = $pdo->prepare("UPDATE kanban_cards SET position = ? WHERE id = ?");
        foreach ($cardsInColumn as $cid) {
            $updateStmt->execute([$pos, $cid]);
            $pos++;
        }
        
        // Se mudou de coluna, reordenar a coluna antiga também
        if ($oldColumnId != $newColumnId) {
            $stmt = $pdo->prepare("
                SELECT id FROM kanban_cards 
                WHERE column_id = ? AND is_archived = 0 
                ORDER BY position, id
            ");
            $stmt->execute([$oldColumnId]);
            $cardsInOldColumn = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $pos = 0;
            foreach ($cardsInOldColumn as $cid) {
                $updateStmt->execute([$pos, $cid]);
                $pos++;
            }
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Card movido com sucesso']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
