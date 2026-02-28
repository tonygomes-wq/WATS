<?php
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/chat_service.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = $_SESSION['user_type'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$sourceMessageId = (int) ($input['source_message_id'] ?? 0);
$targetConversationIds = $input['target_conversation_ids'] ?? [];

if (!is_array($targetConversationIds)) {
    $targetConversationIds = [$targetConversationIds];
}

$targetConversationIds = array_values(array_unique(array_filter(array_map('intval', $targetConversationIds))));

if (!$sourceMessageId || empty($targetConversationIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Informe a mensagem e ao menos uma conversa de destino.']);
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT m.*, c.user_id AS owner_user_id
        FROM chat_messages m
        INNER JOIN chat_conversations c ON m.conversation_id = c.id
        WHERE m.id = ?
        LIMIT 1
    ');
    $stmt->execute([$sourceMessageId]);
    $sourceMessage = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sourceMessage) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Mensagem original não encontrada.']);
        exit;
    }

    if (!canAccessConversation((int) $sourceMessage['owner_user_id'], $userId, $userType)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Você não tem permissão para encaminhar esta mensagem.']);
        exit;
    }

    $results = [];
    foreach ($targetConversationIds as $conversationId) {
        $stmt = $pdo->prepare('SELECT id, user_id FROM chat_conversations WHERE id = ? LIMIT 1');
        $stmt->execute([$conversationId]);
        $targetConversation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetConversation) {
            $results[] = [
                'conversation_id' => $conversationId,
                'success' => false,
                'error' => 'Conversa não encontrada'
            ];
            continue;
        }

        if (!canAccessConversation((int) $targetConversation['user_id'], $userId, $userType)) {
            $results[] = [
                'conversation_id' => $conversationId,
                'success' => false,
                'error' => 'Sem permissão para enviar nesta conversa'
            ];
            continue;
        }

        try {
            if ($sourceMessage['message_type'] === 'text') {
                $sendResult = sendTextMessageToConversation(
                    (int) $conversationId,
                    $userId,
                    $userType,
                    $sourceMessage['message_text'],
                    null
                );
            } else {
                $sendResult = sendMediaMessageToConversation(
                    (int) $conversationId,
                    $userId,
                    $userType,
                    $sourceMessage
                );
            }

            if (!empty($sendResult['success'])) {
                $results[] = [
                    'conversation_id' => $conversationId,
                    'success' => true
                ];
            } else {
                $results[] = [
                    'conversation_id' => $conversationId,
                    'success' => false,
                    'error' => $sendResult['error'] ?? 'Erro ao enviar'
                ];
            }
        } catch (Exception $e) {
            $results[] = [
                'conversation_id' => $conversationId,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
        }
    }

    $overallSuccess = !empty($results) && !in_array(false, array_column($results, 'success'), true);

    echo json_encode([
        'success' => $overallSuccess,
        'results' => $results
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function canAccessConversation(int $conversationOwnerId, int $userId, string $userType): bool
{
    if ($userType === 'admin') {
        return true;
    }

    if (in_array($userType, ['user', 'supervisor'])) {
        return $conversationOwnerId === $userId;
    }

    if ($userType === 'attendant') {
        $supervisorId = getSupervisorIdForAttendant($userId);
        return $supervisorId && (int) $conversationOwnerId === (int) $supervisorId;
    }

    return false;
}
