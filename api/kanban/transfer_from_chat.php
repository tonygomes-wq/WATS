<?php
/**
 * API PARA TRANSFERIR CONVERSA DO CHAT PARA O KANBAN
 * Cria um card vinculado à conversa
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
    
    $conversationId = $input['conversation_id'] ?? null;
    $boardId = $input['board_id'] ?? null;
    $columnId = $input['column_id'] ?? null;
    $title = trim($input['title'] ?? '');
    
    // Validações
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'conversation_id é obrigatório']);
        exit;
    }
    
    // Buscar dados da conversa
    $stmt = $pdo->prepare("
        SELECT cc.*, 
               COALESCE(cc.contact_name, c.name, cc.phone) as display_name,
               cc.channel_type as channel_source
        FROM chat_conversations cc
        LEFT JOIN contacts c ON cc.contact_id = c.id
        WHERE cc.id = ? AND cc.user_id = ?
    ");
    $stmt->execute([$conversationId, $ownerId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
        exit;
    }
    
    // Verificar se já existe card para esta conversa
    $stmt = $pdo->prepare("
        SELECT kc.id, kcol.name as column_name, kb.name as board_name
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kc.conversation_id = ? AND kb.user_id = ? AND kc.is_archived = 0
    ");
    $stmt->execute([$conversationId, $ownerId]);
    $existingCard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingCard) {
        echo json_encode([
            'success' => false, 
            'error' => "Esta conversa já está no Kanban: {$existingCard['board_name']} > {$existingCard['column_name']}",
            'existing_card_id' => $existingCard['id']
        ]);
        exit;
    }
    
    // Se não especificou board_id, usar o padrão
    if (!$boardId) {
        $stmt = $pdo->prepare("SELECT id FROM kanban_boards WHERE user_id = ? AND is_default = 1 LIMIT 1");
        $stmt->execute([$ownerId]);
        $defaultBoard = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$defaultBoard) {
            // Buscar qualquer quadro
            $stmt = $pdo->prepare("SELECT id FROM kanban_boards WHERE user_id = ? LIMIT 1");
            $stmt->execute([$ownerId]);
            $defaultBoard = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$defaultBoard) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nenhum quadro Kanban encontrado. Acesse o Kanban primeiro para criar um.']);
            exit;
        }
        
        $boardId = $defaultBoard['id'];
    }
    
    // Verificar se o quadro pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM kanban_boards WHERE id = ? AND user_id = ?");
    $stmt->execute([$boardId, $ownerId]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Quadro não encontrado']);
        exit;
    }
    
    // Se não especificou column_id, usar a primeira coluna
    if (!$columnId) {
        $stmt = $pdo->prepare("SELECT id FROM kanban_columns WHERE board_id = ? ORDER BY position ASC LIMIT 1");
        $stmt->execute([$boardId]);
        $firstColumn = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$firstColumn) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nenhuma coluna encontrada no quadro']);
            exit;
        }
        
        $columnId = $firstColumn['id'];
    } else {
        // Verificar se a coluna pertence ao quadro
        $stmt = $pdo->prepare("SELECT id FROM kanban_columns WHERE id = ? AND board_id = ?");
        $stmt->execute([$columnId, $boardId]);
        
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Coluna não pertence ao quadro selecionado']);
            exit;
        }
    }
    
    // Gerar título se não fornecido
    if (empty($title)) {
        $title = $conversation['display_name'] ?: $conversation['phone'];
        
        // Adicionar última mensagem como contexto
        if (!empty($conversation['last_message_text'])) {
            $preview = substr($conversation['last_message_text'], 0, 50);
            if (strlen($conversation['last_message_text']) > 50) {
                $preview .= '...';
            }
            $title .= " - " . $preview;
        }
    }
    
    // Obter próxima posição
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 as next_pos FROM kanban_cards WHERE column_id = ?");
    $stmt->execute([$columnId]);
    $nextPos = $stmt->fetch(PDO::FETCH_ASSOC)['next_pos'];
    
    // Criar o card
    $stmt = $pdo->prepare("
        INSERT INTO kanban_cards (
            column_id, conversation_id, title, description,
            contact_name, contact_phone, assigned_to, assigned_type,
            priority, due_date, value, position, created_by, source_channel
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $assignedTo = $input['assigned_to'] ?? null;
    $assignedType = $assignedTo ? 'attendant' : null;
    
    // Descrição com contexto da conversa
    $description = $input['description'] ?? '';
    if (empty($description) && !empty($conversation['last_message_text'])) {
        $description = "Última mensagem: " . $conversation['last_message_text'];
    }
    
    // Normalizar o canal de origem
    $sourceChannel = strtolower($conversation['channel_source'] ?? 'whatsapp');
    
    $stmt->execute([
        $columnId,
        $conversationId,
        $title,
        $description,
        $conversation['display_name'] ?: $conversation['contact_name'],
        $conversation['phone'],
        $assignedTo,
        $assignedType,
        $input['priority'] ?? 'normal',
        $input['due_date'] ?? null,
        $input['value'] ?? null,
        $nextPos,
        $userId,
        $sourceChannel
    ]);
    
    $cardId = $pdo->lastInsertId();
    
    // Adicionar labels se fornecidas
    if (!empty($input['labels'])) {
        $stmtLabel = $pdo->prepare("INSERT INTO kanban_card_labels (card_id, label_id) VALUES (?, ?)");
        foreach ($input['labels'] as $labelId) {
            $stmtLabel->execute([$cardId, $labelId]);
        }
    }
    
    // Copiar notas internas da conversa se solicitado
    if (!empty($input['copy_notes'])) {
        $stmt = $pdo->prepare("SELECT note, created_at FROM conversation_notes WHERE conversation_id = ? ORDER BY created_at ASC");
        $stmt->execute([$conversationId]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($notes)) {
            $stmtComment = $pdo->prepare("
                INSERT INTO kanban_card_comments (card_id, user_id, user_type, comment, is_internal, created_at)
                VALUES (?, ?, ?, ?, 1, ?)
            ");
            
            foreach ($notes as $note) {
                $stmtComment->execute([
                    $cardId,
                    $userId,
                    $userType,
                    "[Nota importada do chat] " . $note['note'],
                    $note['created_at']
                ]);
            }
        }
    }
    
    // Registrar no histórico
    $stmt = $pdo->prepare("
        INSERT INTO kanban_card_history (card_id, user_id, user_type, action, to_column_id, changes)
        VALUES (?, ?, ?, 'created', ?, ?)
    ");
    $stmt->execute([
        $cardId, 
        $userId, 
        $userType, 
        $columnId,
        json_encode(['source' => 'chat', 'conversation_id' => $conversationId])
    ]);
    
    // Buscar informações do card criado
    $stmt = $pdo->prepare("
        SELECT kc.*, kcol.name as column_name, kb.name as board_name
        FROM kanban_cards kc
        INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
        INNER JOIN kanban_boards kb ON kcol.board_id = kb.id
        WHERE kc.id = ?
    ");
    $stmt->execute([$cardId]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Conversa transferida para o Kanban com sucesso!',
        'card_id' => $cardId,
        'card' => $card,
        'kanban_url' => "kanban.php?board={$boardId}"
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
