<?php
/**
 * API PARA MESCLAR CONVERSAS DUPLICADAS
 * Identifica e mescla conversas do mesmo número de telefone
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

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';

// Se for atendente, pegar o supervisor
if ($userType === 'attendant') {
    $stmt = $pdo->prepare("SELECT supervisor_id FROM supervisor_users WHERE id = ?");
    $stmt->execute([$userId]);
    $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($attendant && $attendant['supervisor_id']) {
        $userId = $attendant['supervisor_id'];
    }
}

try {
    // Buscar TODAS as conversas do usuário
    $stmt = $pdo->prepare("
        SELECT id, phone, contact_name, created_at
        FROM chat_conversations
        WHERE user_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$userId]);
    $allConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por número normalizado (apenas dígitos, últimos 11 caracteres)
    $grouped = [];
    foreach ($allConversations as $conv) {
        // Normalizar: remover tudo exceto dígitos
        $normalized = preg_replace('/[^0-9]/', '', $conv['phone'] ?? '');
        
        // Pegar últimos 11 dígitos (DDD + número) para comparação
        if (strlen($normalized) > 11) {
            $key = substr($normalized, -11);
        } else {
            $key = $normalized;
        }
        
        if (!isset($grouped[$key])) {
            $grouped[$key] = [];
        }
        $grouped[$key][] = $conv;
    }
    
    // Filtrar apenas grupos com mais de 1 conversa (duplicadas)
    $duplicates = [];
    foreach ($grouped as $phone => $convs) {
        if (count($convs) > 1) {
            $duplicates[] = [
                'normalized_phone' => $phone,
                'conversations' => $convs
            ];
        }
    }
    
    error_log("[MERGE] Encontradas " . count($duplicates) . " grupos de duplicadas");
    
    $mergedCount = 0;
    $details = [];
    
    foreach ($duplicates as $dup) {
        $convs = $dup['conversations'];
        $keepId = (int) $convs[0]['id']; // Manter a primeira (mais antiga)
        
        $mergeInfo = [
            'phone' => $dup['normalized_phone'],
            'kept_id' => $keepId,
            'merged_ids' => []
        ];
        
        error_log("[MERGE] Mesclando " . count($convs) . " conversas para telefone " . $dup['normalized_phone']);
        
        // Mesclar as outras na primeira
        for ($i = 1; $i < count($convs); $i++) {
            $mergeId = (int) $convs[$i]['id'];
            
            error_log("[MERGE] Movendo mensagens de $mergeId para $keepId");
            
            // Mover mensagens
            $stmt = $pdo->prepare("
                UPDATE chat_messages 
                SET conversation_id = ? 
                WHERE conversation_id = ?
            ");
            $stmt->execute([$keepId, $mergeId]);
            $movedMessages = $stmt->rowCount();
            
            // Deletar conversa duplicada
            $stmt = $pdo->prepare("DELETE FROM chat_conversations WHERE id = ?");
            $stmt->execute([$mergeId]);
            
            $mergeInfo['merged_ids'][] = [
                'id' => $mergeId,
                'messages_moved' => $movedMessages
            ];
            
            $mergedCount++;
        }
        
        // Atualizar contadores da conversa mantida
        $stmt = $pdo->prepare("
            UPDATE chat_conversations 
            SET 
                last_message_text = (
                    SELECT message_text FROM chat_messages 
                    WHERE conversation_id = ? 
                    ORDER BY created_at DESC LIMIT 1
                ),
                last_message_time = (
                    SELECT MAX(created_at) FROM chat_messages WHERE conversation_id = ?
                ),
                unread_count = (
                    SELECT COUNT(*) FROM chat_messages 
                    WHERE conversation_id = ? AND from_me = 0 AND read_at IS NULL
                ),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$keepId, $keepId, $keepId, $keepId]);
        
        $details[] = $mergeInfo;
    }
    
    echo json_encode([
        'success' => true,
        'duplicates_found' => count($duplicates),
        'conversations_merged' => $mergedCount,
        'details' => $details
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
