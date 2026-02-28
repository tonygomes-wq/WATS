<?php
/**
 * API DE CONVERSAS DE EMAIL
 * Lista apenas conversas de email (separado do WhatsApp)
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Apenas Admin pode acessar
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

$userId = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';
$folder = $_GET['folder'] ?? 'inbox';
$limit = min((int) ($_GET['limit'] ?? 100), 200);
$offset = (int) ($_GET['offset'] ?? 0);

try {
    // Verificar quais colunas existem
    $hasChannelType = false;
    $hasContactId = false;
    $hasUserId = false;
    
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'channel_type'");
        $hasChannelType = $checkCol->rowCount() > 0;
        
        $checkCol = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'contact_id'");
        $hasContactId = $checkCol->rowCount() > 0;
        
        $checkCol = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'user_id'");
        $hasUserId = $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        error_log('[Email Conversations] Erro ao verificar colunas: ' . $e->getMessage());
    }
    
    // Construir query baseado nas colunas disponíveis
    $contactJoin = $hasContactId ? "LEFT JOIN contacts c ON conv.contact_id = c.id" : "";
    $userJoin = $hasUserId ? "LEFT JOIN users u ON conv.user_id = u.id" : "";
    
    $sql = "
        SELECT 
            conv.id,
            conv.contact_number as phone,
            conv.contact_name,
            NULL as profile_pic_url,
            (SELECT message_text FROM messages WHERE conversation_id = conv.id ORDER BY created_at DESC LIMIT 1) as last_message_text,
            conv.last_message_at as last_message_time,
            (SELECT COUNT(*) FROM messages WHERE conversation_id = conv.id AND is_read = 0 AND sender_type = 'contact') as unread_count,
            0 as is_pinned,
            0 as is_archived,
            conv.updated_at,
            " . ($hasUserId ? "conv.user_id as owner_user_id," : "0 as owner_user_id,") . "
            conv.status,
            conv.created_at,
            " . ($hasContactId ? "c.id as contact_id, c.name as contact_db_name, c.source, c.source_id," : "NULL as contact_id, NULL as contact_db_name, NULL as source, NULL as source_id,") . "
            COALESCE(conv.contact_name, " . ($hasContactId ? "c.name," : "") . " conv.contact_number) as display_name,
            " . ($hasUserId ? "u.name as owner_name," : "NULL as owner_name,") . "
            'email' as channel_type,
            'Email' as channel_name,
            conv.additional_attributes
        FROM conversations conv
        $contactJoin
        $userJoin
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filtrar por user_id se coluna existe
    if ($hasUserId) {
        $sql .= " AND conv.user_id = ?";
        $params[] = $userId;
    }
    
    // Filtrar por channel_type se coluna existe
    if ($hasChannelType) {
        $sql .= " AND conv.channel_type = 'email'";
    } else {
        // Se não tem channel_type, filtrar por channel_id de email
        $sql .= " AND conv.channel_id IN (
            SELECT id FROM channels WHERE channel_type = 'email'
        )";
    }
    
    $baseParamCount = count($params);
    
    // Filtrar por pasta
    if ($folder === 'starred') {
        $sql .= " AND (
            JSON_EXTRACT(conv.additional_attributes, '$.starred') = true
        )";
    } elseif ($folder === 'sent') {
        // Emails enviados (onde última mensagem é do usuário)
        $sql .= " AND (
            SELECT sender_type FROM messages 
            WHERE conversation_id = conv.id 
            ORDER BY created_at DESC LIMIT 1
        ) = 'user'";
    } elseif ($folder === 'drafts') {
        // Rascunhos (implementar futuramente)
        $sql .= " AND conv.status = 'draft'";
    } elseif ($folder === 'trash') {
        // Lixeira (implementar futuramente)
        $sql .= " AND conv.status = 'deleted'";
    }
    // inbox = todos os emails ativos (padrão)
    
    // Busca por nome ou email
    if (!empty($search)) {
        $searchConditions = ["conv.contact_name LIKE ?", "conv.contact_number LIKE ?"];
        if ($hasContactId) {
            $searchConditions[] = "c.name LIKE ?";
        }
        
        $sql .= " AND (" . implode(" OR ", $searchConditions) . ")";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        if ($hasContactId) {
            $params[] = $searchParam;
        }
    }
    
    // Ordenar por última mensagem
    $sql .= " ORDER BY conv.updated_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar datas e adicionar assunto do email
    foreach ($conversations as &$conv) {
        $conv['last_message_time_formatted'] = formatMessageTime($conv['last_message_time']);
        $conv['unread_count'] = (int) $conv['unread_count'];
        $conv['is_pinned'] = false;
        $conv['is_archived'] = false;
        $conv['contact_number'] = $conv['phone'];
        
        // Extrair assunto e preview do additional_attributes
        if (!empty($conv['additional_attributes'])) {
            $attrs = json_decode($conv['additional_attributes'], true);
            $conv['subject'] = $attrs['subject'] ?? 'Sem assunto';
            $conv['starred'] = $attrs['starred'] ?? false;
        } else {
            $conv['subject'] = 'Sem assunto';
            $conv['starred'] = false;
        }
        
        // Preview da última mensagem
        $conv['preview'] = $conv['last_message_text'] ? 
            mb_substr(strip_tags($conv['last_message_text']), 0, 100) : '';
        
        // Verificar se não lida
        $conv['unread'] = $conv['unread_count'] > 0;
    }
    
    // Contar total
    $countSql = "SELECT COUNT(*) as total FROM conversations conv WHERE 1=1";
    $countParams = [];
    
    if ($hasUserId) {
        $countSql .= " AND conv.user_id = ?";
        $countParams[] = $userId;
    }
    
    if ($hasChannelType) {
        $countSql .= " AND conv.channel_type = 'email'";
    } else {
        $countSql .= " AND conv.channel_id IN (SELECT id FROM channels WHERE channel_type = 'email')";
    }
    
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($countParams);
    $total = $stmtCount->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations,
        'total' => (int) $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function formatMessageTime(?string $datetime): string
{
    if (!$datetime) return '';
    
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if (date('Y-m-d', $time) === date('Y-m-d', $now)) {
        return date('H:i', $time);
    }
    
    if (date('Y-m-d', $time) === date('Y-m-d', $now - 86400)) {
        return 'Ontem';
    }
    
    if ($diff < 604800) {
        $days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        return $days[date('w', $time)];
    }
    
    return date('d/m/Y', $time);
}
