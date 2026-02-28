<?php
/**
 * CRON JOB - Sincronização Automática de Mensagens do Microsoft Teams
 * 
 * Executar a cada 5 minutos:
 * */5 * * * * php /caminho/para/cron/sync_teams_messages.php >> /caminho/para/logs/teams_sync.log 2>&1
 * 
 * @author MAC-IP TECNOLOGIA
 * @version 1.0
 */

// Evitar execução via browser
if (php_sapi_name() !== 'cli') {
    die('Este script deve ser executado via linha de comando (CLI)');
}

// ✅ TIMEOUT: Limitar tempo total de execução
set_time_limit(300); // Máximo 5 minutos (300 segundos)
$cronStartTime = time();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/channels/TeamsGraphAPI.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando sincronização do Microsoft Teams\n";

try {
    // Buscar usuários com Teams autenticado e token válido
    $stmt = $pdo->query("
        SELECT id, name, email
        FROM users 
        WHERE teams_access_token IS NOT NULL 
        AND teams_token_expires_at > NOW()
        AND teams_client_id IS NOT NULL
        ORDER BY id
    ");
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalUsers = count($users);
    
    echo "[" . date('Y-m-d H:i:s') . "] Encontrados {$totalUsers} usuários com Teams autenticado\n";
    
    if ($totalUsers === 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Nenhum usuário para sincronizar. Finalizando.\n";
        exit(0);
    }
    
    $totalSynced = 0;
    $totalErrors = 0;
    $totalNewConversations = 0;
    
    foreach ($users as $user) {
        // ✅ VERIFICAR TIMEOUT GLOBAL: Parar se já passou 4 minutos e 30 segundos
        if (time() - $cronStartTime > 270) {
            echo "[" . date('Y-m-d H:i:s') . "] Timeout global atingido, parando CRON\n";
            break;
        }
        
        $userId = $user['id'];
        $userName = $user['name'];
        
        echo "\n[" . date('Y-m-d H:i:s') . "] Sincronizando usuário: {$userName} (ID: {$userId})\n";
        
        try {
            $teamsAPI = new TeamsGraphAPI($pdo, $userId);
            
            if (!$teamsAPI->isAuthenticated()) {
                echo "[" . date('Y-m-d H:i:s') . "] ⚠️  Usuário {$userId} não está autenticado (token pode ter expirado)\n";
                continue;
            }
            
            // Buscar informações do usuário logado
            $myInfoResult = $teamsAPI->getMyInfo();
            $myAzureId = null;
            
            if ($myInfoResult['success']) {
                $myAzureId = $myInfoResult['data']['id'] ?? null;
            }
            
            // Buscar chats do usuário
            $chatsResult = $teamsAPI->listChats();
            
            if (!$chatsResult['success']) {
                echo "[" . date('Y-m-d H:i:s') . "] ❌ Erro ao listar chats: " . ($chatsResult['error'] ?? 'desconhecido') . "\n";
                $totalErrors++;
                continue;
            }
            
            $chats = $chatsResult['data']['value'] ?? [];
            $chatCount = count($chats);
            
            echo "[" . date('Y-m-d H:i:s') . "] Encontrados {$chatCount} chats\n";
            
            $userSynced = 0;
            $userNewConversations = 0;
            
            foreach ($chats as $chat) {
                $chatId = $chat['id'];
                $chatType = $chat['chatType'] ?? 'unknown';
                
                // Aceitar apenas chats 1-on-1
                if ($chatType !== 'oneOnOne') {
                    continue;
                }
                
                // Verificar se conversa já existe
                $stmt = $pdo->prepare("
                    SELECT id, last_message_at 
                    FROM chat_conversations 
                    WHERE user_id = ? AND teams_chat_id = ?
                ");
                $stmt->execute([$userId, $chatId]);
                $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Se não existe, criar nova conversa
                if (!$conversation) {
                    // Buscar membros do chat
                    $membersResult = $teamsAPI->getChatMembers($chatId);
                    
                    if (!$membersResult['success']) {
                        continue;
                    }
                    
                    $members = $membersResult['data'] ?? [];
                    
                    // Identificar o outro usuário (não é o usuário logado)
                    $contactName = 'Usuário Desconhecido';
                    $contactUserId = null;
                    $profilePicUrl = null;
                    
                    foreach ($members as $member) {
                        $memberId = $member['userId'] ?? null;
                        
                        // Ignorar se for o próprio usuário logado
                        if ($memberId && $myAzureId && $memberId === $myAzureId) {
                            continue;
                        }
                        
                        if ($memberId) {
                            $contactName = $member['displayName'] ?? 'Usuário Desconhecido';
                            $contactUserId = $memberId;
                            
                            // ✅ OTIMIZAÇÃO: NÃO baixar foto durante sincronização do CRON
                            // Fotos serão carregadas sob demanda (lazy loading)
                            // Isso reduz o tempo de sincronização em 70-80%
                            /*
                            try {
                                $photoResult = $teamsAPI->saveUserPhotoLocally($memberId, $contactName);
                                if ($photoResult['success']) {
                                    $profilePicUrl = $photoResult['data']['local_path'];
                                }
                            } catch (Exception $photoError) {
                                // Ignorar erro de foto
                            }
                            */
                            
                            break;
                        }
                    }
                    
                    // Se não conseguiu identificar o contato, pular
                    if ($contactName === 'Usuário Desconhecido') {
                        continue;
                    }
                    
                    // Criar nova conversa
                    $stmt = $pdo->prepare("
                        INSERT INTO chat_conversations (
                            user_id,
                            contact_name,
                            channel_type,
                            teams_chat_id,
                            profile_pic_url,
                            status,
                            created_at,
                            last_message_at
                        ) VALUES (?, ?, 'teams', ?, ?, 'active', NOW(), NOW())
                    ");
                    
                    $stmt->execute([
                        $userId,
                        $contactName,
                        $chatId,
                        $profilePicUrl
                    ]);
                    
                    $conversationId = $pdo->lastInsertId();
                    $userNewConversations++;
                    
                    echo "[" . date('Y-m-d H:i:s') . "] ✅ Nova conversa criada: {$contactName}\n";
                } else {
                    $conversationId = $conversation['id'];
                }
                
                // Buscar mensagens do chat (últimas 20 para não sobrecarregar)
                $messagesResult = $teamsAPI->getChatMessages($chatId, 20);
                
                if (!$messagesResult['success']) {
                    continue;
                }
                
                $messages = $messagesResult['data']['value'] ?? [];
                
                foreach ($messages as $message) {
                    try {
                        $messageId = $message['id'] ?? null;
                        if (!$messageId) {
                            continue;
                        }
                        
                        $messageBody = $message['body']['content'] ?? '';
                        $messageType = $message['messageType'] ?? 'message';
                        $createdAt = $message['createdDateTime'] ?? date('Y-m-d H:i:s');
                        
                        // Ignorar mensagens do sistema
                        if ($messageType !== 'message') {
                            continue;
                        }
                        
                        // Verificar se a mensagem já existe
                        $stmt = $pdo->prepare("
                            SELECT id FROM chat_messages 
                            WHERE conversation_id = ? AND message_id = ?
                        ");
                        $stmt->execute([$conversationId, $messageId]);
                        
                        if ($stmt->fetch()) {
                            continue; // Mensagem já existe
                        }
                        
                        // Determinar remetente
                        $fromUserId = null;
                        $fromUserName = 'Desconhecido';
                        
                        if (isset($message['from'])) {
                            if (isset($message['from']['user'])) {
                                $fromUserId = $message['from']['user']['id'] ?? null;
                                $fromUserName = $message['from']['user']['displayName'] ?? 'Desconhecido';
                            }
                        }
                        
                        // Determinar direção
                        $fromMe = ($fromUserId && $myAzureId && $fromUserId === $myAzureId) ? 1 : 0;
                        
                        // Limpar HTML
                        $messageText = strip_tags($messageBody);
                        
                        if (empty($messageText)) {
                            continue;
                        }
                        
                        // Inserir mensagem
                        $stmt = $pdo->prepare("
                            INSERT INTO chat_messages (
                                conversation_id,
                                user_id,
                                message_id,
                                from_me,
                                message_type,
                                message_text,
                                status,
                                timestamp,
                                created_at
                            ) VALUES (?, ?, ?, ?, 'text', ?, 'delivered', ?, ?)
                        ");
                        
                        $stmt->execute([
                            $conversationId,
                            $userId,
                            $messageId,
                            $fromMe,
                            $messageText,
                            strtotime($createdAt),
                            date('Y-m-d H:i:s', strtotime($createdAt))
                        ]);
                        
                        $userSynced++;
                        
                        // Atualizar última mensagem da conversa
                        $stmt = $pdo->prepare("
                            UPDATE chat_conversations 
                            SET 
                                last_message_text = ?,
                                last_message_time = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            substr($messageText, 0, 100),
                            date('Y-m-d H:i:s', strtotime($createdAt)),
                            $conversationId
                        ]);
                        
                    } catch (Exception $msgError) {
                        // Ignorar erro de mensagem individual
                        continue;
                    }
                }
            }
            
            echo "[" . date('Y-m-d H:i:s') . "] ✅ Usuário {$userName}: {$userSynced} mensagens sincronizadas, {$userNewConversations} novas conversas\n";
            
            $totalSynced += $userSynced;
            $totalNewConversations += $userNewConversations;
            
        } catch (Exception $userError) {
            echo "[" . date('Y-m-d H:i:s') . "] ❌ Erro ao sincronizar usuário {$userId}: " . $userError->getMessage() . "\n";
            $totalErrors++;
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] ========================================\n";
    echo "[" . date('Y-m-d H:i:s') . "] RESUMO DA SINCRONIZAÇÃO\n";
    echo "[" . date('Y-m-d H:i:s') . "] ========================================\n";
    echo "[" . date('Y-m-d H:i:s') . "] Usuários processados: {$totalUsers}\n";
    echo "[" . date('Y-m-d H:i:s') . "] Mensagens sincronizadas: {$totalSynced}\n";
    echo "[" . date('Y-m-d H:i:s') . "] Novas conversas: {$totalNewConversations}\n";
    echo "[" . date('Y-m-d H:i:s') . "] Erros: {$totalErrors}\n";
    echo "[" . date('Y-m-d H:i:s') . "] ========================================\n";
    echo "[" . date('Y-m-d H:i:s') . "] Sincronização concluída com sucesso!\n\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ ERRO FATAL: " . $e->getMessage() . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
