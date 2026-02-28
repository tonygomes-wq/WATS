<?php
/**
 * API DE CONVERSAS DO CHAT
 * Gerencia listagem e busca de conversas
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// ✅ FASE 2: Adicionar componentes de segurança
require_once '../includes/RateLimiter.php';
require_once '../includes/InputValidator.php';
require_once '../includes/Logger.php';

/**
 * Retorna o caminho local da foto de perfil se existir
 * Busca em múltiplas pastas para compatibilidade
 * @param string $phone Número do telefone
 * @return string|null Caminho local ou null
 */
function getLocalProfilePicture($phone) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    // Tentar diferentes formatos de nome de arquivo e pastas
    $locations = [
        ['dir' => 'uploads/profile_pictures/', 'file' => $cleanPhone . '.jpg'],
        ['dir' => 'uploads/profiles/', 'file' => 'profile_' . $cleanPhone . '.jpg'],
        ['dir' => 'uploads/profile_pictures/', 'file' => 'profile_' . $cleanPhone . '.jpg'],
    ];
    
    foreach ($locations as $loc) {
        $filepath = __DIR__ . '/../' . $loc['dir'] . $loc['file'];
        if (file_exists($filepath)) {
            return '/' . $loc['dir'] . $loc['file'] . '?v=' . filemtime($filepath);
        }
    }
    
    return null;
}

header('Content-Type: application/json');

if (!isLoggedIn()) {
    Logger::warning('Tentativa de acesso não autorizado', [
        'endpoint' => 'chat_conversations',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// ✅ RATE LIMITING DESABILITADO TEMPORARIAMENTE
// MOTIVO: Erro 429 em massa impedindo funcionamento do sistema
// TODO: Reativar após implementar lazy loading e otimizar requisições
/*
$rateLimiter = new RateLimiter();
$userId = $_SESSION['user_id'];

// Limite: 300 requisições por minuto (listagem de conversas - polling frequente)
if (!$rateLimiter->allow($userId, 'list_conversations', 300, 60)) {
    Logger::warning('Rate limit excedido - listagem de conversas', [
        'user_id' => $userId,
        'endpoint' => 'chat_conversations'
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
$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$userType = $_SESSION['user_type'] ?? 'user';
$isAttendant = ($userType === 'attendant');

// Buscar a instância do usuário para compartilhar conversas entre atendentes
$userInstance = null;
$supervisorId = null;
$instanceOwnerId = null;
$attendantHasOwnInstance = false;
$attendantInstanceName = null;

if ($isAttendant) {
    // Atendente: buscar na tabela supervisor_users e verificar se tem instância própria
    // Primeiro verificar se a tabela attendant_instances existe
    $tableExists = false;
    $columnExists = false;
    
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'attendant_instances'");
        $tableExists = $checkTable->rowCount() > 0;
    } catch (Exception $e) {
        $tableExists = false;
    }
    
    // Verificar se coluna use_own_instance existe
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM supervisor_users LIKE 'use_own_instance'");
        $columnExists = $checkColumn->rowCount() > 0;
    } catch (Exception $e) {
        $columnExists = false;
    }
    
    if ($tableExists && $columnExists) {
        $stmt = $pdo->prepare("
            SELECT su.supervisor_id, su.use_own_instance, u.evolution_instance, u.id as owner_id,
                   ai.instance_name as attendant_instance_name, ai.status as attendant_instance_status
            FROM supervisor_users su 
            LEFT JOIN users u ON su.supervisor_id = u.id 
            LEFT JOIN attendant_instances ai ON su.id = ai.attendant_id
            WHERE su.id = ?
        ");
    } elseif ($columnExists) {
        // Tabela attendant_instances não existe, mas coluna use_own_instance existe
        $stmt = $pdo->prepare("
            SELECT su.supervisor_id, su.use_own_instance, u.evolution_instance, u.id as owner_id,
                   NULL as attendant_instance_name, NULL as attendant_instance_status
            FROM supervisor_users su 
            LEFT JOIN users u ON su.supervisor_id = u.id 
            WHERE su.id = ?
        ");
    } else {
        // Coluna use_own_instance não existe, usar query básica
        $stmt = $pdo->prepare("
            SELECT su.supervisor_id, 0 as use_own_instance, u.evolution_instance, u.id as owner_id,
                   NULL as attendant_instance_name, NULL as attendant_instance_status
            FROM supervisor_users su 
            LEFT JOIN users u ON su.supervisor_id = u.id 
            WHERE su.id = ?
        ");
    }
    
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $supervisorId = $userData['supervisor_id'] ?? null;
    $userInstance = $userData['evolution_instance'] ?? null;
    $instanceOwnerId = $userData['owner_id'] ?? null;
    
    // Verificar se atendente tem instância própria configurada
    // IMPORTANTE: Se use_own_instance=1, o atendente NÃO deve ver conversas do supervisor
    // mesmo que ainda não tenha criado/conectado sua instância
    $attendantHasOwnInstance = ($userData['use_own_instance'] ?? 0) == 1;
    $attendantInstanceName = $userData['attendant_instance_name'] ?? null;
    
    // Se tem instância própria configurada, usar ela como filtro
    if ($attendantHasOwnInstance) {
        if ($attendantInstanceName) {
            $userInstance = $attendantInstanceName;
        }
        $instanceOwnerId = $userId; // Atendente é dono da sua instância
    }
} else {
    // Usuário normal (admin/supervisor): buscar na tabela users
    $stmt = $pdo->prepare("SELECT id, evolution_instance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $userInstance = $userData['evolution_instance'] ?? null;
    $instanceOwnerId = $userId;
}

// Definir quais user_ids podem ver as conversas
// REGRA: Cada usuário vê apenas SUAS próprias conversas
// EXCEÇÃO: Atendentes SEM instância própria podem ver conversas do supervisor
// IMPORTANTE: Atendentes COM instância própria devem filtrar por instance_name
$sharedUserIds = [];
$filterByInstance = false;
$filterInstanceName = null;

// Debug log
error_log("CHAT_CONVERSATIONS: userId=$userId, isAttendant=" . ($isAttendant ? 'true' : 'false') . ", supervisorId=$supervisorId, attendantHasOwnInstance=" . ($attendantHasOwnInstance ? 'true' : 'false') . ", attendantInstanceName=$attendantInstanceName");

if ($isAttendant && $supervisorId) {
    if ($attendantHasOwnInstance) {
        // Atendente COM instância própria configurada: filtrar por instance_name
        // Isso evita o problema de user_id coincidir com o supervisor
        $filterByInstance = true;
        $filterInstanceName = $attendantInstanceName;
        // Fallback: se não tiver instance_name, não mostrar nada (lista vazia)
        $sharedUserIds = [-1]; // ID impossível para não retornar nada do supervisor
        error_log("CHAT_CONVERSATIONS: Atendente com instância própria - filtrando por instance_name=$attendantInstanceName");
    } else {
        // Atendente SEM instância própria: vê suas conversas E do supervisor
        $sharedUserIds = [$userId, $supervisorId];
        error_log("CHAT_CONVERSATIONS: Atendente sem instância própria - vê user_ids: " . implode(',', $sharedUserIds));
    }
} else {
    // Usuário normal/supervisor: vê apenas suas próprias conversas
    $sharedUserIds = [$userId];
    error_log("CHAT_CONVERSATIONS: Usuário normal - vê apenas user_id=$userId");
}

// IMPORTANTE: Para conversas do Teams, NUNCA compartilhar entre usuários
// Cada usuário do Teams deve ver APENAS suas próprias conversas
// Isso é crítico para privacidade e segurança
$forceIsolateTeams = true;

// Garantir que não há IDs duplicados e são inteiros
$sharedUserIds = array_unique(array_map('intval', $sharedUserIds));

try {
    switch ($method) {
        case 'GET':
            handleGetConversations($userId, $sharedUserIds, $filterByInstance, $filterInstanceName);
            break;
            
        case 'POST':
            handleCreateConversation($userId);
            break;
            
        case 'PUT':
            handleUpdateConversation($userId);
            break;
            
        case 'DELETE':
            handleDeleteConversation($userId);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    Logger::error('Exceção em chat_conversations', [
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
 * Listar conversas do usuário (e de outros usuários da mesma instância)
 * @param bool $filterByInstance Se true, filtra por instance_name em vez de user_id
 * @param string|null $filterInstanceName Nome da instância para filtrar
 */
function handleGetConversations(int $userId, array $sharedUserIds = [], bool $filterByInstance = false, ?string $filterInstanceName = null): void
{
    global $pdo, $forceIsolateTeams;
    
    // Garantir que $forceIsolateTeams tem valor padrão
    $forceIsolateTeams = $forceIsolateTeams ?? true;
    
    // Se conversation_id foi passado, buscar apenas essa conversa específica
    if (isset($_GET['conversation_id'])) {
        $conversationId = (int) $_GET['conversation_id'];
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    cc.*,
                    c.id as contact_id,
                    c.name as contact_db_name,
                    c.profile_picture_url,
                    COALESCE(cc.contact_name, c.name, cc.phone) as display_name,
                    u.name as owner_name,
                    COALESCE(cc.channel_type, 'whatsapp') as channel_type,
                    CASE 
                        WHEN cc.channel_type = 'teams' THEN 'Microsoft Teams'
                        WHEN cc.channel_type = 'email' THEN 'Email'
                        ELSE 'WhatsApp'
                    END as channel_name
                FROM chat_conversations cc
                LEFT JOIN contacts c ON cc.contact_id = c.id
                LEFT JOIN users u ON cc.user_id = u.id
                WHERE cc.id = ? AND cc.user_id = ?
            ");
            $stmt->execute([$conversationId, $userId]);
            $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($conversation) {
                // Formatar dados
                $conversation['last_message_time_formatted'] = formatMessageTime($conversation['last_message_time']);
                $conversation['unread_count'] = (int) ($conversation['unread_count'] ?? 0);
                $conversation['is_pinned'] = (bool) ($conversation['is_pinned'] ?? false);
                $conversation['is_archived'] = (bool) ($conversation['is_archived'] ?? false);
                $conversation['contact_number'] = $conversation['phone'];
                $conversation['status'] = $conversation['status'] ?? 'open';
                $conversation['owner_user_id'] = (int) $conversation['user_id'];
                
                echo json_encode([
                    'success' => true,
                    'conversation' => $conversation
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Conversa não encontrada'
                ]);
            }
            return;
        } catch (Exception $e) {
            error_log("[CHAT_CONVERSATIONS] Erro ao buscar conversa específica: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao buscar conversa'
            ]);
            return;
        }
    }
    
    $search = $_GET['search'] ?? '';
    $archived = isset($_GET['archived']) ? (int) $_GET['archived'] : 0;
    $limit = min((int) ($_GET['limit'] ?? 100), 200);
    $offset = (int) ($_GET['offset'] ?? 0);
    $filter = $_GET['filter'] ?? 'all'; // all, inbox, mine, history
    $userType = $_SESSION['user_type'] ?? 'user';
    
    // Se atendente com instância própria, filtrar por instance_name
    if ($filterByInstance && $filterInstanceName) {
        // Verificar se coluna instance_name existe na tabela chat_conversations
        $hasInstanceColumn = false;
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM chat_conversations LIKE 'instance_name'");
            $hasInstanceColumn = $checkCol->rowCount() > 0;
        } catch (Exception $e) {
            $hasInstanceColumn = false;
        }
        
        // Se não existe a coluna, criar ela
        if (!$hasInstanceColumn) {
            try {
                $pdo->exec("ALTER TABLE chat_conversations ADD COLUMN instance_name VARCHAR(100) DEFAULT NULL AFTER user_id");
                error_log("CHAT_CONVERSATIONS: Coluna instance_name criada na tabela chat_conversations");
            } catch (Exception $e) {
                error_log("CHAT_CONVERSATIONS: Erro ao criar coluna instance_name: " . $e->getMessage());
            }
        }
        
        // Retornar apenas conversas da instância do atendente (inicialmente vazio)
        // As conversas serão criadas quando mensagens chegarem na instância do atendente
        error_log("CHAT_CONVERSATIONS: Filtrando por instance_name=$filterInstanceName");
    }
    
    // Se não houver IDs compartilhados, usar apenas o ID do usuário
    if (empty($sharedUserIds)) {
        $sharedUserIds = [$userId];
    }
    
    // Criar placeholders para IN clause
    $placeholders = implode(',', array_fill(0, count($sharedUserIds), '?'));
    
    // Verificar se colunas de atendimento existem
    $hasStatusColumn = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM chat_conversations LIKE 'status'");
        $hasStatusColumn = $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        $hasStatusColumn = false;
    }
    
    // Verificar se colunas de atendimento existem
    $hasAttendedBy = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM chat_conversations LIKE 'attended_by'");
        $hasAttendedBy = $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        $hasAttendedBy = false;
    }
    
    // Verificar se colunas source/source_id existem em contacts
    $hasSourceColumns = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM contacts LIKE 'source'");
        $hasSourceColumns = $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        $hasSourceColumns = false;
    }
    
    // Verificar se tabela conversations existe (multi-canal)
    $hasConversationsTable = false;
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'conversations'");
        $hasConversationsTable = $checkTable->rowCount() > 0;
    } catch (Exception $e) {
        $hasConversationsTable = false;
    }
    
    // Definir campos de source baseado na existência das colunas
    $sourceFields = $hasSourceColumns ? "
                c.source,
                c.source_id," : "
                'whatsapp' as source,
                cc.phone as source_id,";
    
    if ($hasStatusColumn) {
        $attendedFields = $hasAttendedBy ? "
                cc.attended_by,
                cc.attended_by_name,
                cc.attended_at,
                cc.closed_by,
                cc.closed_by_name," : "
                NULL as attended_by,
                NULL as attended_by_name,
                NULL as attended_at,
                NULL as closed_by,
                NULL as closed_by_name,";
        
        // Query principal (WhatsApp) - SEMPRE funciona
        $sql = "
            SELECT 
                cc.id,
                cc.phone,
                cc.contact_name,
                cc.profile_pic_url,
                cc.last_message_text,
                cc.last_message_time,
                cc.unread_count,
                cc.is_pinned,
                cc.is_archived,
                cc.updated_at,
                cc.user_id as owner_user_id,
                cc.status,
                cc.assigned_to,
                cc.department_id,
                cc.priority,
                cc.started_at,
                cc.resolved_at,
                cc.closed_at,
                $attendedFields
                c.id as contact_id,
                c.name as contact_db_name,
                $sourceFields
                c.profile_picture_url,
                COALESCE(cc.contact_name, c.name, cc.phone) as display_name,
                u.name as owner_name,
                su.name as assigned_name,
                NULL as cached_profile_pic,
                NULL as photo_cache_status,
                COALESCE(cc.channel_type, 'whatsapp') as channel_type,
                CASE 
                    WHEN cc.channel_type = 'teams' THEN 'Microsoft Teams'
                    WHEN cc.channel_type = 'email' THEN 'Email'
                    ELSE 'WhatsApp'
                END as channel_name
            FROM chat_conversations cc
            LEFT JOIN contacts c ON cc.contact_id = c.id
            LEFT JOIN users u ON cc.user_id = u.id
            LEFT JOIN supervisor_users su ON cc.assigned_to = su.id
            WHERE cc.user_id IN ($placeholders) AND cc.is_archived = ?
        ";
        
        // CRÍTICO: Para conversas do Teams, SEMPRE filtrar apenas pelo usuário logado
        // Nunca compartilhar conversas do Teams entre usuários, mesmo que sejam da mesma instância
        // Isso garante privacidade e segurança das conversas do Microsoft Teams
        if ($forceIsolateTeams) {
            // Adicionar filtro adicional: se for Teams, DEVE ser do usuário logado
            $sql = str_replace(
                "WHERE cc.user_id IN ($placeholders) AND cc.is_archived = ?",
                "WHERE (
                    (cc.channel_type = 'teams' AND cc.user_id = ?) OR 
                    (cc.channel_type != 'teams' AND cc.user_id IN ($placeholders))
                ) AND cc.is_archived = ?",
                $sql
            );
        }
        
        // Se filtrar por instância, modificar a query
        if ($filterByInstance && $filterInstanceName) {
            $sql = str_replace(
                "WHERE cc.user_id IN ($placeholders) AND cc.is_archived = ?",
                "WHERE cc.instance_name = ? AND cc.is_archived = ?",
                $sql
            );
        }
    } else {
        // Versão sem colunas de atendimento
        $sql = "
            SELECT 
                cc.id,
                cc.phone,
                cc.contact_name,
                cc.profile_pic_url,
                cc.last_message_text,
                cc.last_message_time,
                cc.unread_count,
                cc.is_pinned,
                cc.is_archived,
                cc.updated_at,
                cc.user_id as owner_user_id,
                'open' as status,
                NULL as assigned_to,
                NULL as department_id,
                'normal' as priority,
                NULL as started_at,
                NULL as resolved_at,
                NULL as closed_at,
                c.id as contact_id,
                c.name as contact_db_name,
                $sourceFields
                c.profile_picture_url,
                COALESCE(cc.contact_name, c.name, cc.phone) as display_name,
                (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = cc.id) as total_messages,
                u.name as owner_name,
                NULL as assigned_name,
                COALESCE(cc.channel_type, 'whatsapp') as channel_type,
                CASE 
                    WHEN cc.channel_type = 'teams' THEN 'Microsoft Teams'
                    WHEN cc.channel_type = 'email' THEN 'Email'
                    ELSE 'WhatsApp'
                END as channel_name
            FROM chat_conversations cc
            LEFT JOIN contacts c ON cc.contact_id = c.id
            LEFT JOIN users u ON cc.user_id = u.id
            WHERE cc.user_id IN ($placeholders) AND cc.is_archived = ?
        ";
        
        // Se filtrar por instância, modificar a query
        if ($filterByInstance && $filterInstanceName) {
            $sql = str_replace(
                "WHERE cc.user_id IN ($placeholders) AND cc.is_archived = ?",
                "WHERE cc.instance_name = ? AND cc.is_archived = ?",
                $sql
            );
        }
    }
    
    // Definir parâmetros da query
    if ($filterByInstance && $filterInstanceName) {
        // Filtrar por instance_name
        $params = [$filterInstanceName, $archived];
    } else {
        // Filtrar por user_id
        // Se forceIsolateTeams está ativo, adicionar userId no início para o filtro de Teams
        if ($forceIsolateTeams) {
            $params = array_merge([$userId], $sharedUserIds, [$archived]);
        } else {
            $params = array_merge($sharedUserIds, [$archived]);
        }
    }
    
    // TEMPORARIAMENTE DESABILITADO: UNION com emails
    // TODO: Corrigir ORDER BY para funcionar com UNION
    // Por enquanto, apenas WhatsApp funciona
    /*
    if ($hasConversationsTable) {
        try {
            $checkEmailConvs = $pdo->prepare("SELECT COUNT(*) as total FROM conversations WHERE user_id IN ($placeholders) AND channel_type = 'email'");
            $checkEmailConvs->execute($sharedUserIds);
            $hasEmailConversations = $checkEmailConvs->fetch()['total'] > 0;
            
            if ($hasEmailConversations) {
                // UNION com emails aqui
            }
        } catch (Exception $e) {
            error_log("Erro ao verificar conversas de email: " . $e->getMessage());
        }
    }
    */
    
    // IMPORTANTE: Aplicar filtros ANTES do UNION (dentro de cada SELECT)
    // Não podemos usar alias 'cc' depois do UNION porque ele não existe na query de email
    
    // Construir filtros para WhatsApp
    // IMPORTANTE: Filtros de atendimento NÃO se aplicam ao Teams (Teams não usa attended_by)
    $whatsappFilters = "";
    $whatsappParams = [];
    
    if ($hasAttendedBy) {
        // Filtro global SEMPRE aplicado: não mostrar conversas que outro atendente pegou
        // EXCETO para Teams que não usa sistema de atendimento
        if ($filter !== 'history') {
            $whatsappFilters .= " AND (cc.channel_type = 'teams' OR cc.attended_by IS NULL OR cc.attended_by = ? OR cc.status = 'closed')";
            $whatsappParams[] = $userId;
        }
        
        // Filtro específico por tipo
        switch ($filter) {
            case 'inbox':
                // Teams sempre aparece no inbox (não usa attended_by)
                $whatsappFilters .= " AND (cc.channel_type = 'teams' OR (cc.attended_by IS NULL AND (cc.status IS NULL OR cc.status NOT IN ('closed', 'in_progress'))))";
                break;
                
            case 'mine':
            case 'my_chats':
                // Teams sempre aparece em "meus chats" (pertence ao usuário)
                $whatsappFilters .= " AND (cc.channel_type = 'teams' OR (cc.attended_by = ? AND (cc.status IS NULL OR cc.status != 'closed')))";
                $whatsappParams[] = $userId;
                break;
                
            case 'history':
                $whatsappFilters .= " AND cc.status = 'closed'";
                break;
                
            case 'all':
            default:
                $whatsappFilters .= " AND (cc.status IS NULL OR cc.status != 'closed')";
                break;
        }
    }
    
    // Busca por nome ou telefone (inclui Teams que não tem phone)
    if (!empty($search)) {
        $whatsappFilters .= " AND (
            cc.contact_name COLLATE utf8mb4_unicode_ci LIKE ? OR 
            c.name COLLATE utf8mb4_unicode_ci LIKE ? OR 
            cc.phone COLLATE utf8mb4_unicode_ci LIKE ? OR
            cc.teams_chat_id COLLATE utf8mb4_unicode_ci LIKE ?
        )";
        $searchParam = "%$search%";
        $whatsappParams[] = $searchParam;
        $whatsappParams[] = $searchParam;
        $whatsappParams[] = $searchParam;
        $whatsappParams[] = $searchParam;
    }
    
    // Adicionar filtros na query principal (ANTES do UNION)
    $sql .= $whatsappFilters;
    $params = array_merge($params, $whatsappParams);
    
    // Ordenar e limitar (DEPOIS do UNION, sem usar alias específico)
    // Usar last_message_time para ordenar por última mensagem recebida
    $sql .= " ORDER BY is_pinned DESC, last_message_time DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Log detalhado para debug
    error_log("[CHAT_CONVERSATIONS] Query SQL: " . $sql);
    error_log("[CHAT_CONVERSATIONS] Params: " . json_encode($params));
    error_log("[CHAT_CONVERSATIONS] Filter: $filter, User ID: $userId, Shared IDs: " . implode(',', $sharedUserIds));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conversations = $stmt->fetchAll();
    
    error_log("[CHAT_CONVERSATIONS] Total de conversas retornadas: " . count($conversations));
    if (count($conversations) > 0) {
        error_log("[CHAT_CONVERSATIONS] Primeira conversa ID: " . $conversations[0]['id'] . ", last_message_time: " . ($conversations[0]['last_message_time'] ?? 'NULL'));
        error_log("[CHAT_CONVERSATIONS] Última conversa ID: " . $conversations[count($conversations)-1]['id'] . ", last_message_time: " . ($conversations[count($conversations)-1]['last_message_time'] ?? 'NULL'));
    }
    
    // Formatar datas e campos
    foreach ($conversations as &$conv) {
        $conv['last_message_time_formatted'] = formatMessageTime($conv['last_message_time']);
        $conv['unread_count'] = (int) $conv['unread_count'];
        $conv['is_pinned'] = (bool) $conv['is_pinned'];
        $conv['is_archived'] = (bool) $conv['is_archived'];
        $conv['contact_number'] = $conv['phone']; // Adicionar para compatibilidade
        
        // Garantir que status tenha valor padrão
        $conv['status'] = $conv['status'] ?? 'open';
        $conv['assigned_to'] = $conv['assigned_to'] ? (int) $conv['assigned_to'] : null;
        $conv['department_id'] = $conv['department_id'] ? (int) $conv['department_id'] : null;
        $conv['owner_user_id'] = (int) $conv['owner_user_id'];
        
        // Informações do canal
        $conv['channel_type'] = $conv['channel_type'] ?? 'whatsapp';
        $conv['channel_name'] = $conv['channel_name'] ?? 'WhatsApp';
        
        // source deve ser igual ao channel_type para compatibilidade
        $conv['source'] = $conv['channel_type'];
        $conv['source_id'] = $conv['source_id'] ?? $conv['phone'];
        
        // Foto de perfil: priorizar foto local, depois do banco
        $localPhoto = getLocalProfilePicture($conv['phone']);
        $conv['profile_picture_url'] = $localPhoto ?? $conv['profile_picture_url'] ?? $conv['profile_pic_url'] ?? null;
    }
    
    // Contar total de conversas (de todos os usuários compartilhados)
    $countPlaceholders = implode(',', array_fill(0, count($sharedUserIds), '?'));
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM chat_conversations 
        WHERE user_id IN ($countPlaceholders) AND is_archived = ?
    ");
    $stmtCount->execute(array_merge($sharedUserIds, [$archived]));
    $total = $stmtCount->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations,
        'total' => (int) $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Criar nova conversa (iniciar chat com número)
 */
function handleCreateConversation(int $userId): void
{
    global $pdo;
    
    // ✅ RATE LIMITING - Prevenir criação em massa
    $rateLimiter = new RateLimiter();
    if (!$rateLimiter->allow($userId, 'create_conversation', 10, 60)) {
        Logger::warning('Rate limit excedido - criar conversa', [
            'user_id' => $userId
        ]);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Muitas conversas criadas. Aguarde 1 minuto.',
            'retry_after' => 60
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // ✅ VALIDAÇÃO DE INPUT
    $phoneValidation = InputValidator::validatePhone($input['phone'] ?? '');
    if (!$phoneValidation['valid']) {
        Logger::warning('Validação falhou - criar conversa', [
            'user_id' => $userId,
            'errors' => $phoneValidation['errors']
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $phoneValidation['errors'])
        ]);
        return;
    }
    
    $phone = $phoneValidation['sanitized'];
    
    // Validar nome do contato (opcional)
    $contactName = '';
    if (!empty($input['contact_name'])) {
        $nameValidation = InputValidator::validateName($input['contact_name'], 2, 100);
        if ($nameValidation['valid']) {
            $contactName = $nameValidation['sanitized'];
        }
    }
    
    Logger::info('Tentativa de criar conversa', [
        'user_id' => $userId,
        'phone' => $phone
    ]);
    
    // Adicionar código do Brasil se necessário (já feito pelo InputValidator)
    // Removido código duplicado
    
    // Verificar se já existe (buscar por telefone exato ou variações)
    $stmt = $pdo->prepare("
        SELECT id FROM chat_conversations 
        WHERE user_id = ? AND (
            phone = ? OR 
            phone = ? OR 
            phone = ?
        )
        LIMIT 1
    ");
    // Tentar com e sem código do país
    $phoneWithout55 = strlen($phone) > 11 && substr($phone, 0, 2) === '55' ? substr($phone, 2) : $phone;
    $phoneWith55 = substr($phone, 0, 2) !== '55' ? '55' . $phone : $phone;
    
    $stmt->execute([$userId, $phone, $phoneWithout55, $phoneWith55]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        Logger::info('Conversa já existe', [
            'user_id' => $userId,
            'conversation_id' => $existing['id'],
            'phone' => $phone
        ]);
        
        echo json_encode([
            'success' => true,
            'conversation_id' => (int) $existing['id'],
            'message' => 'Conversa já existe'
        ]);
        return;
    }
    
    // Buscar contato no banco
    $stmt = $pdo->prepare("
        SELECT id, name FROM contacts 
        WHERE user_id = ? AND phone = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $phone]);
    $contact = $stmt->fetch();
    
    $contactId = $contact['id'] ?? null;
    $finalName = $contactName ?: ($contact['name'] ?? null);
    
    // Criar conversa com timestamp atual para aparecer no topo
    // Verificar quais colunas existem
    $columns = ['user_id', 'contact_id', 'phone', 'contact_name'];
    $values = [$userId, $contactId, $phone, $finalName];
    $placeholders = ['?', '?', '?', '?'];
    
    // Tentar adicionar colunas de timestamp e status se existirem
    try {
        $checkCols = $pdo->query("SHOW COLUMNS FROM chat_conversations");
        $existingCols = $checkCols->fetchAll(PDO::FETCH_COLUMN);
        
        // Adicionar status = 'open' para conversas novas aparecerem no inbox
        if (in_array('status', $existingCols)) {
            $columns[] = 'status';
            $values[] = 'open';
            $placeholders[] = '?';
        }
        
        if (in_array('last_message_time', $existingCols)) {
            $columns[] = 'last_message_time';
            $placeholders[] = 'NOW()';
        }
        
        if (in_array('created_at', $existingCols)) {
            $columns[] = 'created_at';
            $placeholders[] = 'NOW()';
        }
        
        if (in_array('updated_at', $existingCols)) {
            $columns[] = 'updated_at';
            $placeholders[] = 'NOW()';
        }
    } catch (Exception $e) {
        // Ignorar erro de verificação
    }
    
    $sql = "INSERT INTO chat_conversations (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute($values);
        $conversationId = $pdo->lastInsertId();
        
        if (!$conversationId) {
            throw new Exception('Falha ao obter ID da conversa criada');
        }
        
        Logger::info('Conversa inserida no banco', [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'phone' => $phone,
            'columns' => $columns
        ]);
    } catch (PDOException $e) {
        Logger::error('Erro ao criar conversa no banco', [
            'error' => $e->getMessage(),
            'user_id' => $userId,
            'phone' => $phone,
            'sql' => $sql
        ]);
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao criar conversa: ' . $e->getMessage()
        ]);
        return;
    }
    
    // Criar mensagem inicial para garantir que a conversa apareça na listagem
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (conversation_id, sender_type, message_text, created_at)
            VALUES (?, 'system', 'Conversa iniciada', NOW())
        ");
        $stmt->execute([$conversationId]);
        
        // Atualizar last_message_time da conversa para garantir que apareça no topo
        $stmt = $pdo->prepare("
            UPDATE chat_conversations 
            SET last_message_time = NOW(), 
                last_message_text = 'Conversa iniciada',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$conversationId]);
        
        Logger::info('Mensagem inicial criada', [
            'conversation_id' => $conversationId
        ]);
    } catch (Exception $e) {
        // Se falhar, não é crítico, apenas log
        Logger::warning('Erro ao criar mensagem inicial', [
            'conversation_id' => $conversationId,
            'error' => $e->getMessage()
        ]);
    }
    
    // Buscar a conversa criada para retornar dados completos
    try {
        $stmt = $pdo->prepare("
            SELECT 
                cc.*,
                c.name as contact_db_name,
                c.profile_picture_url
            FROM chat_conversations cc
            LEFT JOIN contacts c ON cc.contact_id = c.id
            WHERE cc.id = ?
        ");
        $stmt->execute([$conversationId]);
        $conversationData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        Logger::info('Conversa criada com sucesso', [
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'phone' => $phone,
            'contact_name' => $finalName,
            'last_message_time' => $conversationData['last_message_time'] ?? null
        ]);
        
        echo json_encode([
            'success' => true,
            'conversation_id' => (int) $conversationId,
            'conversation' => $conversationData,
            'message' => 'Conversa criada com sucesso'
        ]);
    } catch (Exception $e) {
        Logger::warning('Erro ao buscar conversa criada', [
            'conversation_id' => $conversationId,
            'error' => $e->getMessage()
        ]);
        
        // Retornar apenas o ID se falhar ao buscar dados completos
        echo json_encode([
            'success' => true,
            'conversation_id' => (int) $conversationId,
            'message' => 'Conversa criada com sucesso'
        ]);
    }
}

/**
 * Atualizar conversa (marcar como lida, arquivar, fixar)
 */
function handleUpdateConversation(int $userId): void
{
    global $pdo;
    
    // ✅ RATE LIMITING - Prevenir abuso de atualizações
    $rateLimiter = new RateLimiter();
    if (!$rateLimiter->allow($userId, 'update_conversation', 30, 60)) {
        Logger::warning('Rate limit excedido - atualizar conversa', [
            'user_id' => $userId
        ]);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Muitas atualizações. Aguarde 1 minuto.',
            'retry_after' => 60
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // ✅ VALIDAÇÃO DE INPUT
    $idValidation = InputValidator::validateId($input['conversation_id'] ?? 0);
    if (!$idValidation['valid']) {
        Logger::warning('Validação falhou - atualizar conversa', [
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
    $action = trim($input['action'] ?? '');
    
    // Validar ação
    $validActions = ['mark_read', 'archive', 'unarchive', 'pin', 'unpin'];
    if (!in_array($action, $validActions)) {
        Logger::warning('Ação inválida - atualizar conversa', [
            'user_id' => $userId,
            'action' => $action
        ]);
        
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        return;
    }
    
    // Verificar propriedade
    $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $userId]);
    if (!$stmt->fetch()) {
        Logger::warning('Tentativa de atualizar conversa não autorizada', [
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'action' => $action
        ]);
        
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
        return;
    }
    
    switch ($action) {
        case 'mark_read':
            // Marcar todas mensagens como lidas
            $stmt = $pdo->prepare("
                UPDATE chat_messages 
                SET read_at = NOW() 
                WHERE conversation_id = ? AND from_me = 0 AND read_at IS NULL
            ");
            $stmt->execute([$conversationId]);
            $messagesUpdated = $stmt->rowCount();
            
            // Zerar contador
            $stmt = $pdo->prepare("
                UPDATE chat_conversations 
                SET unread_count = 0 
                WHERE id = ?
            ");
            $stmt->execute([$conversationId]);
            
            Logger::info('Conversa marcada como lida', [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'messages_marked' => $messagesUpdated
            ]);
            break;
            
        case 'archive':
            $stmt = $pdo->prepare("
                UPDATE chat_conversations 
                SET is_archived = 1 
                WHERE id = ?
            ");
            $stmt->execute([$conversationId]);
            
            Logger::info('Conversa arquivada', [
                'user_id' => $userId,
                'conversation_id' => $conversationId
            ]);
            break;
            
        case 'unarchive':
            $stmt = $pdo->prepare("
                UPDATE chat_conversations 
                SET is_archived = 0 
                WHERE id = ?
            ");
            $stmt->execute([$conversationId]);
            
            Logger::info('Conversa desarquivada', [
                'user_id' => $userId,
                'conversation_id' => $conversationId
            ]);
            break;
            
        case 'pin':
            $stmt = $pdo->prepare("
                UPDATE chat_conversations 
                SET is_pinned = 1 
                WHERE id = ?
            ");
            $stmt->execute([$conversationId]);
            
            Logger::info('Conversa fixada', [
                'user_id' => $userId,
                'conversation_id' => $conversationId
            ]);
            break;
            
        case 'unpin':
            $stmt = $pdo->prepare("
                UPDATE chat_conversations 
                SET is_pinned = 0 
                WHERE id = ?
            ");
            $stmt->execute([$conversationId]);
            
            Logger::info('Conversa desfixada', [
                'user_id' => $userId,
                'conversation_id' => $conversationId
            ]);
            break;
    }
    
    echo json_encode(['success' => true, 'message' => 'Conversa atualizada']);
}

/**
 * Deletar conversa
 */
function handleDeleteConversation(int $userId): void
{
    global $pdo;
    
    // ✅ RATE LIMITING - Prevenir deleções em massa
    $rateLimiter = new RateLimiter();
    if (!$rateLimiter->allow($userId, 'delete_conversation', 10, 60)) {
        Logger::warning('Rate limit excedido - deletar conversa', [
            'user_id' => $userId
        ]);
        
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Muitas deleções. Aguarde 1 minuto.',
            'retry_after' => 60
        ]);
        return;
    }
    
    // ✅ VALIDAÇÃO DE INPUT
    $idValidation = InputValidator::validateId($_GET['id'] ?? 0);
    if (!$idValidation['valid']) {
        Logger::warning('Validação falhou - deletar conversa', [
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
    
    // Buscar informações da conversa antes de deletar (para auditoria)
    $stmt = $pdo->prepare("
        SELECT phone, contact_name, 
               (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = ?) as message_count
        FROM chat_conversations 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$conversationId, $conversationId, $userId]);
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        Logger::warning('Tentativa de deletar conversa não encontrada', [
            'user_id' => $userId,
            'conversation_id' => $conversationId
        ]);
        
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
        return;
    }
    
    // Deletar conversa (mensagens serão deletadas por CASCADE se configurado)
    $stmt = $pdo->prepare("
        DELETE FROM chat_conversations 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$conversationId, $userId]);
    
    // ✅ LOG DE AUDITORIA - Importante para rastreabilidade
    Logger::info('Conversa deletada', [
        'user_id' => $userId,
        'conversation_id' => $conversationId,
        'phone' => $conversation['phone'],
        'contact_name' => $conversation['contact_name'],
        'messages_deleted' => $conversation['message_count']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Conversa deletada']);
}

/**
 * Formatar tempo da mensagem
 */
function formatMessageTime(?string $datetime): string
{
    if (!$datetime) return '';
    
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    // Hoje
    if (date('Y-m-d', $time) === date('Y-m-d', $now)) {
        return date('H:i', $time);
    }
    
    // Ontem
    if (date('Y-m-d', $time) === date('Y-m-d', $now - 86400)) {
        return 'Ontem';
    }
    
    // Esta semana
    if ($diff < 604800) {
        $days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        return $days[date('w', $time)];
    }
    
    // Mais antigo
    return date('d/m/Y', $time);
}
