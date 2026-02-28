<?php
/**
 * Debug - Mostrar logs do Teams Sync
 * Acesse: https://wats.macip.com.br/api/teams_debug_logs.php
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/channels/TeamsGraphAPI.php';

header('Content-Type: text/html; charset=utf-8');

if (!isLoggedIn()) {
    die('Não autorizado');
}

$userId = $_SESSION['user_id'];
$teamsAPI = new TeamsGraphAPI($pdo, $userId);

if (!$teamsAPI->isAuthenticated()) {
    die('Teams não autenticado');
}

echo "<h1>Debug - Teams Sync</h1>";
echo "<pre style='background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; overflow-x: auto;'>";

try {
    echo "=== BUSCANDO CHATS DO TEAMS ===\n\n";
    
    $chatsResult = $teamsAPI->listChats();
    
    if (!$chatsResult['success']) {
        echo "❌ ERRO: " . ($chatsResult['error'] ?? 'Erro desconhecido') . "\n";
        exit;
    }
    
    $chats = $chatsResult['data']['value'] ?? [];
    echo "✅ Total de chats encontrados: " . count($chats) . "\n\n";
    
    if (count($chats) === 0) {
        echo "⚠️ Nenhum chat encontrado!\n";
        exit;
    }
    
    echo "=== ESTRUTURA DO PRIMEIRO CHAT ===\n\n";
    echo json_encode($chats[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "=== ANÁLISE DE TODOS OS CHATS ===\n\n";
    
    foreach ($chats as $index => $chat) {
        $chatId = $chat['id'] ?? 'sem-id';
        $chatType = $chat['chatType'] ?? 'unknown';
        $chatTopic = $chat['topic'] ?? 'sem-título';
        $memberCount = isset($chat['members']) ? count($chat['members']) : 0;
        
        echo "Chat #" . ($index + 1) . ":\n";
        echo "  ID: {$chatId}\n";
        echo "  Tipo: {$chatType}\n";
        echo "  Título: {$chatTopic}\n";
        echo "  Membros: {$memberCount}\n";
        
        // Verificar filtros
        $passouFiltro1 = ($chatType === 'oneOnOne') ? '✅' : '❌';
        $passouFiltro2 = ($memberCount === 2) ? '✅' : '❌';
        
        echo "  Filtro 1 (oneOnOne): {$passouFiltro1}\n";
        echo "  Filtro 2 (2 membros): {$passouFiltro2}\n";
        
        if ($chatType === 'oneOnOne' && $memberCount === 2) {
            echo "  ✅ SERIA ACEITO\n";
        } else {
            echo "  ❌ SERIA REJEITADO\n";
        }
        
        echo "\n";
    }
    
    echo "=== RESUMO ===\n\n";
    
    $aceitos = 0;
    $rejeitados = 0;
    
    foreach ($chats as $chat) {
        $chatType = $chat['chatType'] ?? 'unknown';
        $memberCount = isset($chat['members']) ? count($chat['members']) : 0;
        
        if ($chatType === 'oneOnOne' && $memberCount === 2) {
            $aceitos++;
        } else {
            $rejeitados++;
        }
    }
    
    echo "Total: " . count($chats) . " chats\n";
    echo "Aceitos: {$aceitos} chats\n";
    echo "Rejeitados: {$rejeitados} chats\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString();
}

echo "</pre>";
?>
