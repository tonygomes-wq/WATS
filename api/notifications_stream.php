<?php
/**
 * Server-Sent Events (SSE) para Notificações em Tempo Real
 * Endpoint: /api/notifications_stream.php
 * 
 * Este endpoint mantém uma conexão aberta e envia notificações
 * quando há novas mensagens ou eventos para o usuário.
 */

// Desabilitar buffer de saída
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);

// Limpar qualquer buffer existente
while (ob_get_level()) {
    ob_end_clean();
}

// Headers SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx

// Iniciar sessão para obter user_id
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "event: error\n";
    echo "data: {\"error\": \"Não autenticado\"}\n\n";
    flush();
    exit;
}

$userId = $_SESSION['user_id'];

require_once '../config/database.php';

// Função para enviar evento SSE
function sendSSE($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

// Enviar heartbeat inicial
sendSSE('connected', ['status' => 'ok', 'user_id' => $userId, 'time' => date('H:i:s')]);

// Buscar último ID de mensagem conhecido
$stmt = $pdo->prepare("
    SELECT MAX(cm.id) as last_id 
    FROM chat_messages cm
    JOIN chat_conversations cc ON cm.conversation_id = cc.id
    WHERE cc.user_id = ?
");
$stmt->execute([$userId]);
$lastMessageId = (int)($stmt->fetchColumn() ?? 0);

// Buscar última contagem de não lidas
$stmt = $pdo->prepare("
    SELECT SUM(unread_count) as total 
    FROM chat_conversations 
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$lastUnreadCount = (int)($stmt->fetchColumn() ?? 0);

// Loop principal - verificar novas mensagens a cada 2 segundos
$maxExecutionTime = 30; // Reconectar a cada 30 segundos
$startTime = time();
$checkInterval = 2; // Segundos entre verificações

while (true) {
    // Verificar se conexão ainda está ativa
    if (connection_aborted()) {
        break;
    }
    
    // Verificar tempo máximo de execução
    if ((time() - $startTime) >= $maxExecutionTime) {
        sendSSE('reconnect', ['message' => 'Reconectando...']);
        break;
    }
    
    try {
        // Verificar novas mensagens
        $stmt = $pdo->prepare("
            SELECT 
                cm.id,
                cm.conversation_id,
                cm.message_text,
                cm.message_type,
                cm.from_me,
                cm.created_at,
                cc.contact_name,
                cc.phone,
                cc.profile_pic_url
            FROM chat_messages cm
            JOIN chat_conversations cc ON cm.conversation_id = cc.id
            WHERE cc.user_id = ? 
            AND cm.id > ?
            AND cm.from_me = 0
            ORDER BY cm.id ASC
            LIMIT 10
        ");
        $stmt->execute([$userId, $lastMessageId]);
        $newMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($newMessages as $msg) {
            $lastMessageId = max($lastMessageId, (int)$msg['id']);
            
            // Enviar notificação de nova mensagem
            sendSSE('new_message', [
                'conversation_id' => (int)$msg['conversation_id'],
                'contact_name' => $msg['contact_name'] ?: $msg['phone'],
                'phone' => $msg['phone'],
                'message' => $msg['message_text'] ?: '[' . ucfirst($msg['message_type']) . ']',
                'message_type' => $msg['message_type'],
                'profile_pic' => $msg['profile_pic_url'],
                'time' => $msg['created_at']
            ]);
        }
        
        // Verificar mudança na contagem de não lidas
        $stmt = $pdo->prepare("
            SELECT SUM(unread_count) as total 
            FROM chat_conversations 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $currentUnreadCount = (int)($stmt->fetchColumn() ?? 0);
        
        if ($currentUnreadCount !== $lastUnreadCount) {
            sendSSE('unread_count', [
                'count' => $currentUnreadCount,
                'previous' => $lastUnreadCount
            ]);
            $lastUnreadCount = $currentUnreadCount;
        }
        
    } catch (Exception $e) {
        sendSSE('error', ['message' => 'Erro ao verificar mensagens']);
    }
    
    // Enviar heartbeat para manter conexão
    sendSSE('heartbeat', ['time' => date('H:i:s')]);
    
    // Aguardar antes da próxima verificação
    sleep($checkInterval);
}

// Fechar conexão
sendSSE('close', ['message' => 'Conexão encerrada']);
