<?php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!function_exists('formatMessageDateTime')) {
    function formatMessageDateTime(string $datetime): string
    {
        $time = strtotime($datetime);
        $now = time();

        if (date('Y-m-d', $time) === date('Y-m-d', $now)) {
            return 'Hoje às ' . date('H:i', $time);
        }

function sendMediaViaEvolution(string $phone, string $mediaType, string $binaryContent, string $mime, string $fileName, ?string $caption, string $instance, string $token): array
{
    $number = formatWhatsappNumber($phone);
    $base64 = base64_encode($binaryContent);
    $endpoint = '';
    $payload = [
        'number' => $number
    ];

    switch ($mediaType) {
        case 'image':
            $endpoint = '/message/sendMedia/' . $instance;
            $payload['mediatype'] = 'image';
            $payload['mimetype'] = $mime;
            $payload['media'] = $base64;
            if (!empty($caption)) {
                $payload['caption'] = $caption;
            }
            break;
        case 'audio':
            $endpoint = '/message/sendWhatsAppAudio/' . $instance;
            $payload['audio'] = $base64;
            break;
        case 'video':
            $endpoint = '/message/sendMedia/' . $instance;
            $payload['mediatype'] = 'video';
            $payload['mimetype'] = $mime;
            $payload['media'] = $base64;
            $payload['fileName'] = $fileName;
            if (!empty($caption)) {
                $payload['caption'] = $caption;
            }
            break;
        case 'document':
        default:
            $endpoint = '/message/sendMedia/' . $instance;
            $payload['mediatype'] = 'document';
            $payload['mimetype'] = $mime;
            $payload['media'] = $base64;
            $payload['fileName'] = $fileName;
            if (!empty($caption)) {
                $payload['caption'] = $caption;
            }
            break;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, EVOLUTION_API_URL . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $token
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true];
    }

    $errorMessage = 'Erro ao encaminhar mídia';
    if (!empty($curlError)) {
        $errorMessage = 'Erro de conexão: ' . $curlError;
    } elseif (!empty($response)) {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['message'] ?? $errorData['error'] ?? $errorMessage;
    }

    return ['success' => false, 'error' => $errorMessage];
}

function fetchMediaBinary(string $mediaUrl): string
{
    $content = false;
    if (preg_match('/^https?:\/\//i', $mediaUrl)) {
        $content = @file_get_contents($mediaUrl);
    } else {
        $path = $mediaUrl;
        if (strpos($mediaUrl, BASE_PATH) !== 0) {
            $path = rtrim(BASE_PATH, '/') . '/' . ltrim($mediaUrl, '/');
        }
        $content = @file_get_contents($path);
    }

    if ($content === false) {
        throw new Exception('Não foi possível acessar o arquivo original.');
    }

    return $content;
}

function storeForwardedMedia(string $binary, string $originalName = '', string $mime = ''): array
{
    $uploadDir = BASE_PATH . '/uploads/media/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    if (!$extension && $mime) {
        $extension = guessExtensionFromMime($mime);
    }
    $extension = $extension ? ('.' . ltrim($extension, '.')) : '';

    $uniqueName = uniqid('forward_', true) . $extension;
    $fullPath = $uploadDir . $uniqueName;

    if (file_put_contents($fullPath, $binary) === false) {
        throw new Exception('Não foi possível salvar o arquivo encaminhado.');
    }

    return [
        'media_url' => '/uploads/media/' . $uniqueName,
        'media_filename' => $originalName ?: $uniqueName,
        'media_size' => strlen($binary)
    ];
}

function guessExtensionFromMime(?string $mime): string
{
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov'
    ];

    return $map[$mime] ?? '';
}

function formatWhatsappNumber(string $phone): string
{
    $phoneFormatted = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phoneFormatted) < 12) {
        $phoneFormatted = '55' . $phoneFormatted;
    }
    return $phoneFormatted . '@s.whatsapp.net';
}

        if (date('Y-m-d', $time) === date('Y-m-d', $now - 86400)) {
            return 'Ontem às ' . date('H:i', $time);
        }

        $diff = $now - $time;
        if ($diff < 604800) {
            $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            return $days[date('w', $time)] . ' às ' . date('H:i', $time);
        }

        return date('d/m/Y H:i', $time);
    }
}

function getSupervisorIdForAttendant(int $attendantId)
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT supervisor_id FROM supervisor_users WHERE id = ?');
    $stmt->execute([$attendantId]);
    $attendant = $stmt->fetch(PDO::FETCH_ASSOC);

    return $attendant['supervisor_id'] ?? null;
}

function getConversationContext(int $conversationId, int $executingUserId, string $executingUserType): array
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT * FROM chat_conversations WHERE id = ? LIMIT 1');
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conversation) {
        throw new Exception('Conversa não encontrada.');
    }

    $instanceUserId = (int) $conversation['user_id'];
    $messageUserId = $executingUserId;

    if ($executingUserType === 'attendant') {
        $supervisorId = getSupervisorIdForAttendant($executingUserId);
        if (!$supervisorId) {
            throw new Exception('Supervisor do atendente não encontrado.');
        }
        $instanceUserId = (int) $supervisorId;
        $messageUserId = (int) $supervisorId;
    }

    $stmt = $pdo->prepare('SELECT evolution_instance, evolution_token FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$instanceUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['evolution_instance']) || empty($user['evolution_token'])) {
        throw new Exception('Instância Evolution API não configurada. Configure em "Minha Instância".');
    }

    return [
        'conversation' => $conversation,
        'instance_user_id' => $instanceUserId,
        'message_user_id' => $messageUserId,
        'instance' => $user['evolution_instance'],
        'token' => $user['evolution_token']
    ];
}

function sendTextMessageToConversation(int $conversationId, int $executingUserId, string $executingUserType, string $messageText, ?int $quotedMessageId = null): array
{
    global $pdo;

    $messageText = trim($messageText);
    if ($messageText === '') {
        throw new Exception('Mensagem não pode estar vazia.');
    }

    $context = getConversationContext($conversationId, $executingUserId, $executingUserType);
    $conversation = $context['conversation'];

    $result = sendTextMessageViaEvolution(
        $conversation['phone'],
        $messageText,
        $context['instance'],
        $context['token']
    );

    if (!$result['success']) {
        throw new Exception($result['error'] ?? 'Erro ao enviar mensagem.');
    }

    $stmt = $pdo->prepare('
        INSERT INTO chat_messages (
            conversation_id, user_id, message_id, from_me,
            message_type, message_text, quoted_message_id,
            status, timestamp
        ) VALUES (?, ?, ?, 1, "text", ?, ?, "sent", ?)
    ');

    $stmt->execute([
        $conversationId,
        $context['message_user_id'],
        $result['message_id'] ?? null,
        $messageText,
        $quotedMessageId,
        $result['timestamp'] ?? time()
    ]);

    $insertedId = $pdo->lastInsertId();

    $stmt = $pdo->prepare('
        UPDATE chat_conversations
        SET last_message_text = ?, last_message_time = NOW(), updated_at = NOW()
        WHERE id = ?
    ');
    $stmt->execute([$messageText, $conversationId]);

    $stmt = $pdo->prepare('
        SELECT 
            m.id, m.message_id, m.from_me, m.message_type,
            m.message_text, m.status, m.timestamp, m.created_at,
            m.quoted_message_id, u.name AS sender_name
        FROM chat_messages m
        LEFT JOIN users u ON m.user_id = u.id
        WHERE m.id = ?
    ');
    $stmt->execute([$insertedId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    $message['id'] = (int) $message['id'];
    $message['from_me'] = (bool) $message['from_me'];
    $message['timestamp'] = (int) $message['timestamp'];
    $message['time_formatted'] = date('H:i', strtotime($message['created_at']));
    $message['created_at_formatted'] = formatMessageDateTime($message['created_at']);
    $message['sender_name'] = $message['sender_name'] ?? null;

    return [
        'success' => true,
        'message' => $message
    ];
}

function sendMediaMessageToConversation(int $conversationId, int $executingUserId, string $executingUserType, array $sourceMessage): array
{
    global $pdo;

    $context = getConversationContext($conversationId, $executingUserId, $executingUserType);
    $messageType = $sourceMessage['message_type'] ?? 'document';
    $supportedTypes = ['image', 'document', 'audio', 'video'];

    if (!in_array($messageType, $supportedTypes, true)) {
        throw new Exception('Tipo de mídia não suportado para encaminhamento.');
    }

    $mediaUrl = $sourceMessage['media_url'] ?? '';
    if (empty($mediaUrl)) {
        throw new Exception('Arquivo original não disponível.');
    }

    $binary = fetchMediaBinary($mediaUrl);
    $mime = $sourceMessage['media_mimetype'] ?? null;
    if (!$mime) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = @$finfo->buffer($binary);
        if ($detected) {
            $mime = $detected;
        }
    }
    if (!$mime) {
        $mime = 'application/octet-stream';
    }

    $fileName = $sourceMessage['media_filename'] ?? ($messageType . '_' . time());
    $caption = $sourceMessage['caption'] ?? '';

    $mediaResult = sendMediaViaEvolution(
        $context['conversation']['phone'],
        $messageType,
        $binary,
        $mime,
        $fileName,
        $caption,
        $context['instance'],
        $context['token']
    );

    if (!$mediaResult['success']) {
        throw new Exception($mediaResult['error'] ?? 'Erro ao encaminhar mídia.');
    }

    $storedMedia = storeForwardedMedia($binary, $fileName, $mime);
    $messageText = $caption ?: '';
    $mediaSize = $storedMedia['media_size'];

    $stmt = $pdo->prepare('
        INSERT INTO chat_messages 
        (conversation_id, user_id, message_type, message_text, media_url, media_filename, media_mimetype, media_size, caption, from_me, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ');

    $stmt->execute([
        $conversationId,
        $context['message_user_id'],
        $messageType,
        $messageText,
        $storedMedia['media_url'],
        $storedMedia['media_filename'],
        $mime,
        $mediaSize,
        $caption
    ]);

    $lastMessageText = $messageText ?: sprintf('[%s encaminhado]', ucfirst($messageType));
    $stmt = $pdo->prepare('
        UPDATE chat_conversations
        SET last_message_text = ?, last_message_time = NOW(), updated_at = NOW()
        WHERE id = ?
    ');
    $stmt->execute([$lastMessageText, $conversationId]);

    return ['success' => true];
}

function userCanAccessConversation(int $conversationOwnerId, int $userId, string $userType): bool
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

function deleteMessageForUser(int $messageId, int $userId, string $userType): array
{
    global $pdo;

    $stmt = $pdo->prepare('
        SELECT m.id, m.conversation_id, m.from_me, m.user_id, c.user_id AS conversation_owner
        FROM chat_messages m
        INNER JOIN chat_conversations c ON m.conversation_id = c.id
        WHERE m.id = ?
        LIMIT 1
    ');
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        throw new Exception('Mensagem não encontrada.');
    }

    if (!userCanAccessConversation((int) $message['conversation_owner'], $userId, $userType)) {
        throw new Exception('Você não tem permissão para apagar esta mensagem.');
    }

    if (!$message['from_me'] && $userType !== 'admin') {
        throw new Exception('É possível apagar apenas mensagens enviadas por você.');
    }

    $stmt = $pdo->prepare('
        UPDATE chat_messages
        SET message_text = "[Mensagem apagada]",
            message_type = "text",
            media_url = NULL,
            media_filename = NULL,
            media_mimetype = NULL,
            media_size = NULL,
            caption = NULL,
            status = "deleted"
        WHERE id = ?
    ');
    $stmt->execute([$messageId]);

    return ['success' => true];
}

function sendTextMessageViaEvolution(string $phone, string $message, string $instance, string $token): array
{
    $phoneFormatted = formatWhatsappNumber($phone);

    $data = [
        'number' => $phoneFormatted,
        'text' => $message
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, EVOLUTION_API_URL . '/message/sendText/' . $instance);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $token
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($response, true);

        return [
            'success' => true,
            'message_id' => $responseData['key']['id'] ?? null,
            'timestamp' => $responseData['messageTimestamp'] ?? time()
        ];
    }

    $errorMessage = 'Erro ao enviar mensagem';

    if (!empty($curlError)) {
        $errorMessage = 'Erro de conexão: ' . $curlError;
    } elseif (!empty($response)) {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['message'] ?? $errorData['error'] ?? $errorMessage;
    }

    return [
        'success' => false,
        'error' => $errorMessage
    ];
}
