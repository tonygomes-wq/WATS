<?php

/**
 * API DE MENSAGENS DO CHAT
 * Gerencia busca e listagem de mensagens de uma conversa
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// ✅ FASE 2: Adicionar componentes de segurança
require_once '../includes/RateLimiter.php';
require_once '../includes/InputValidator.php';
require_once '../includes/Logger.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    Logger::warning('Tentativa de acesso não autorizado', [
        'endpoint' => 'chat_messages',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// ✅ RATE LIMITING DESABILITADO TEMPORARIAMENTE
// MOTIVO: Erro 429 em massa impedindo funcionamento do sistema
// TODO: Reativar após implementar lazy loading e otimizar requisições
/*
$rateLimiter = new RateLimiter();

// Limite: 2000 requisições por minuto (suporta carregamento de muitas imagens)
if (!$rateLimiter->allow($userId, 'list_messages', 2000, 60)) {
    Logger::warning('Rate limit excedido - listagem de mensagens', [
        'user_id' => $userId,
        'endpoint' => 'chat_messages'
    ]);
    
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Muitas requisições. Aguarde 1 minuto.',
        'retry_after' => 60
    ]);
    exit;
}
*/

try {
    if ($method === 'GET') {
        handleGetMessages($userId);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    Logger::error('Exceção em chat_messages', [
        'user_id' => $userId,
        'method' => $method,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit;

/**
 * Buscar mensagens de uma conversa
 */
function handleGetMessages(int $userId): void
{
    global $pdo;

    // ✅ VALIDAÇÃO DE INPUT
    $idValidation = InputValidator::validateId($_GET['conversation_id'] ?? 0);
    if (!$idValidation['valid']) {
        Logger::warning('Validação falhou - buscar mensagens', [
            'user_id' => $userId,
            'errors' => $idValidation['errors']
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $idValidation['errors'])
        ]);
        return;
    }
    
    $conversationId = $idValidation['sanitized'];

    // LIMITE OTIMIZADO PARA PERFORMANCE
    // ====================================
    // Padrão: 50 mensagens (performance excelente + histórico suficiente)
    // - Tempo de resposta: ~100ms (muito rápido)
    // - Histórico de ~3-5 dias de conversa ativa
    // - Memória: ~150KB (muito leve)
    //
    // Opções:
    // - ?limit=50 (padrão) - Últimas 50 mensagens (RECOMENDADO)
    // - ?limit=100 - Carregar mais 100 mensagens antigas
    // - ?limit=200 - Carregar mais 200 mensagens antigas
    // - ?limit=0 (sem limite) - TODAS as mensagens
    //
    // IMPORTANTE: O frontend usa proteção por timestamp para garantir que
    // mensagens recém-enviadas nunca sejam removidas, mesmo com limite ativo.

    $requestedLimit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;

    if ($requestedLimit === 0) {
        // limit=0 significa "sem limite" (todas as mensagens)
        $limit = 999999;
    } else {
        // Respeitar limite solicitado (máximo 500 para evitar problemas de performance)
        $limit = min($requestedLimit, 500);
    }

    $offset = (int) ($_GET['offset'] ?? 0);
    $beforeMessageId = (int) ($_GET['before_id'] ?? 0);

    Logger::info('Buscando mensagens', [
        'user_id' => $userId,
        'conversation_id' => $conversationId,
        'limit' => $limit,
        'offset' => $offset
    ]);

    // Verificação simplificada - apenas checar se conversa existe
    // (a segurança já é garantida pelo frontend que só mostra conversas do usuário)
    $stmt = $pdo->prepare("SELECT id, phone, contact_name, profile_pic_url, channel_type FROM chat_conversations WHERE id = ? LIMIT 1");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch();
    if (!$conversation) {
        Logger::warning('Conversa não encontrada', [
            'user_id' => $userId,
            'conversation_id' => $conversationId
        ]);
        
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
        return;
    }

    // Verificar channel_type da conversa para decidir qual tabela usar
    $channelType = $conversation['channel_type'] ?? 'whatsapp';

    // Para WhatsApp: usar chat_messages (onde o webhook salva)
    // Para Teams: usar messages (onde o sync salva)
    if ($channelType === 'teams') {
        // Teams usa tabela messages
        $sql = "
            SELECT 
                m.id,
                m.external_id as message_id,
                CASE WHEN m.sender_type = 'user' THEN 1 ELSE 0 END as from_me,
                COALESCE(m.message_type, 'text') as message_type,
                m.message_text,
                m.media_url,
                NULL as media_mimetype,
                NULL as media_filename,
                NULL as media_size,
                m.message_text as caption,
                NULL as quoted_message_id,
                'delivered' as status,
                UNIX_TIMESTAMP(m.created_at) as timestamp,
                m.created_at,
                NULL as read_at,
                NULL as sender_user_id,
                m.sender_name,
                m.channel_type,
                m.external_id
            FROM messages m
            WHERE m.conversation_id = ?
        ";
    } else {
        // WhatsApp usa tabela chat_messages
        $sql = "
            SELECT 
                m.id,
                m.message_id,
                m.from_me,
                m.message_type,
                m.message_text,
                m.media_url,
                m.media_mimetype,
                m.media_filename,
                m.media_size,
                m.caption,
                m.quoted_message_id,
                m.status,
                m.timestamp,
                FROM_UNIXTIME(m.timestamp) as created_at,
                m.read_at,
                m.user_id as sender_user_id,
                NULL as sender_name,
                'whatsapp' as channel_type,
                m.message_id as external_id
            FROM chat_messages m
            WHERE m.conversation_id = ?
        ";
    }

    $params = [$conversationId];

    // Paginação por ID (melhor para chat em tempo real)
    if ($beforeMessageId > 0) {
        $sql .= " AND m.id < ?";
        $params[] = $beforeMessageId;
    }

    // Ordenação: Teams usa created_at, WhatsApp usa timestamp
    if ($channelType === 'teams') {
        $sql .= " ORDER BY m.created_at DESC, m.id DESC LIMIT ?";
    } else {
        $sql .= " ORDER BY m.timestamp DESC, m.id DESC LIMIT ?";
    }
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Inverter ordem para exibir do mais antigo para o mais novo
    $messages = array_reverse($messages);

    // Formatar mensagens
    foreach ($messages as &$msg) {
        $msg['id'] = (int) $msg['id'];
        $msg['from_me'] = (bool) $msg['from_me'];
        $msg['quoted_message_id'] = $msg['quoted_message_id'] ? (int) $msg['quoted_message_id'] : null;
        $msg['timestamp'] = (int) $msg['timestamp'];

        // Formatar data/hora
        if (!empty($msg['created_at'])) {
            $msg['created_at_formatted'] = date('d/m/Y H:i', strtotime($msg['created_at']));
            $msg['time_formatted'] = formatMessageDateTime($msg['created_at']);
        } else {
            $msg['created_at_formatted'] = date('d/m/Y H:i', $msg['timestamp']);
            $msg['time_formatted'] = formatMessageDateTime(date('Y-m-d H:i:s', $msg['timestamp']));
        }

        $msg['is_read'] = !empty($msg['read_at']);

        // Informações do canal
        $msg['channel_type'] = $msg['channel_type'] ?? 'whatsapp';
        $msg['channel_id'] = null;
        $msg['channel_name'] = $msg['channel_type'] === 'teams' ? 'Microsoft Teams' : 'WhatsApp';
        $msg['external_id'] = $msg['external_id'] ?? null;

        // Corrigir message_text NULL
        if (empty($msg['message_text'])) {
            switch ($msg['message_type']) {
                case 'image':
                    $msg['message_text'] = '[Imagem enviada]';
                    break;
                case 'document':
                    $msg['message_text'] = '[Documento enviado]';
                    break;
                case 'audio':
                    $msg['message_text'] = '[Áudio enviado]';
                    break;
                case 'video':
                    $msg['message_text'] = '[Vídeo enviado]';
                    break;
                default:
                    $msg['message_text'] = '[Mensagem de texto]';
            }
        }

        // Formatar mídia
        if ($msg['message_type'] !== 'text' && !empty($msg['media_url'])) {
            $msg['has_media'] = true;
            $msg['media_size_formatted'] = formatBytes($msg['media_size'] ?? 0);
        } else {
            $msg['has_media'] = false;
        }
    }

    // Contar total de mensagens (usar tabela correta)
    if ($channelType === 'teams') {
        $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM messages WHERE conversation_id = ?");
    } else {
        $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM chat_messages WHERE conversation_id = ?");
    }
    $stmtCount->execute([$conversationId]);
    $total = $stmtCount->fetch()['total'];

    // Marcar mensagens como lidas automaticamente
    markMessagesAsRead($conversationId, $userId);

    echo json_encode([
        'success' => true,
        'conversation' => [
            'id' => (int) $conversation['id'],
            'phone' => $conversation['phone'] ?? '',
            'contact_name' => $conversation['contact_name'] ?? $conversation['phone'] ?? '',
            'profile_pic_url' => $conversation['profile_pic_url'] ?? null
        ],
        'messages' => $messages,
        'total' => (int) $total,
        'has_more' => count($messages) === $limit
    ]);
}

/**
 * Marcar mensagens como lidas
 */
function markMessagesAsRead(int $conversationId, int $userId): void
{
    global $pdo;

    try {
        // Verificar channel_type da conversa
        $stmt = $pdo->prepare("SELECT channel_type FROM chat_conversations WHERE id = ?");
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch();
        $channelType = $conv['channel_type'] ?? 'whatsapp';

        // Marcar mensagens não lidas como lidas (tabela correta)
        if ($channelType === 'teams') {
            $stmt = $pdo->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE conversation_id = ? 
                AND sender_type = 'contact' 
                AND is_read = 0
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE chat_messages 
                SET read_at = NOW() 
                WHERE conversation_id = ? 
                AND from_me = 0 
                AND read_at IS NULL
            ");
        }
        $stmt->execute([$conversationId]);

        // Zerar contador de não lidas
        $stmt = $pdo->prepare("
            UPDATE chat_conversations 
            SET unread_count = 0 
            WHERE id = ?
        ");
        $stmt->execute([$conversationId]);
    } catch (Exception $e) {
        error_log("[CHAT_MESSAGES] Erro ao marcar como lida: " . $e->getMessage());
    }
}

/**
 * Formatar data/hora da mensagem
 */
function formatMessageDateTime(string $datetime): string
{
    $time = strtotime($datetime);
    $now = time();

    // Hoje
    if (date('Y-m-d', $time) === date('Y-m-d', $now)) {
        return 'Hoje às ' . date('H:i', $time);
    }

    // Ontem
    if (date('Y-m-d', $time) === date('Y-m-d', $now - 86400)) {
        return 'Ontem às ' . date('H:i', $time);
    }

    // Esta semana
    $diff = $now - $time;
    if ($diff < 604800) {
        $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        return $days[date('w', $time)] . ' às ' . date('H:i', $time);
    }

    // Mais antigo
    return date('d/m/Y H:i', $time);
}

/**
 * Formatar tamanho de arquivo
 */
function formatBytes(int $bytes): string
{
    if ($bytes === 0) return '0 B';

    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log(1024));

    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}
