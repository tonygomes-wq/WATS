<?php
/**
 * Script para Medir Performance do Sistema
 * 
 * Executar ANTES e DEPOIS de cada melhoria para comparar resultados
 * 
 * Uso: php scripts/measure_performance.php
 */

require_once __DIR__ . '/../config/database.php';

echo "===========================================\n";
echo "  MEDIÇÃO DE PERFORMANCE - SISTEMA CHAT\n";
echo "===========================================\n\n";

$metrics = [];
$metrics['timestamp'] = date('Y-m-d H:i:s');

// 1. Tempo de carregamento de conversas
echo "1. Testando query de conversas...\n";
$start = microtime(true);
$stmt = $pdo->query("SELECT COUNT(*) as total FROM chat_conversations");
$metrics['total_conversations'] = $stmt->fetchColumn();
$metrics['conversations_query_time'] = microtime(true) - $start;
echo "   ✓ Total de conversas: {$metrics['total_conversations']}\n";
echo "   ✓ Tempo: " . round($metrics['conversations_query_time'] * 1000, 2) . "ms\n\n";

// 2. Tempo de carregamento de mensagens
echo "2. Testando query de mensagens...\n";
$start = microtime(true);
$stmt = $pdo->query("SELECT COUNT(*) as total FROM chat_messages");
$metrics['total_messages'] = $stmt->fetchColumn();
$metrics['messages_query_time'] = microtime(true) - $start;
echo "   ✓ Total de mensagens: {$metrics['total_messages']}\n";
echo "   ✓ Tempo: " . round($metrics['messages_query_time'] * 1000, 2) . "ms\n\n";

// 3. Verificar índices existentes
echo "3. Verificando índices...\n";
$stmt = $pdo->query("SHOW INDEX FROM chat_conversations");
$metrics['conversations_indexes'] = $stmt->rowCount();
echo "   ✓ Índices em chat_conversations: {$metrics['conversations_indexes']}\n";

$stmt = $pdo->query("SHOW INDEX FROM chat_messages");
$metrics['messages_indexes'] = $stmt->rowCount();
echo "   ✓ Índices em chat_messages: {$metrics['messages_indexes']}\n\n";

// 4. Tamanho das tabelas
echo "4. Verificando tamanho das tabelas...\n";
$stmt = $pdo->query("
    SELECT 
        table_name,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
    FROM information_schema.TABLES
    WHERE table_schema = DATABASE()
    AND table_name IN ('chat_conversations', 'chat_messages', 'messages', 'contacts')
    ORDER BY size_mb DESC
");
$metrics['table_sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($metrics['table_sizes'] as $table) {
    echo "   ✓ {$table['table_name']}: {$table['size_mb']} MB\n";
}
echo "\n";

// 5. Testar query complexa (listagem de conversas)
echo "5. Testando query complexa (listagem de conversas)...\n";
$start = microtime(true);
$stmt = $pdo->query("
    SELECT 
        cc.id,
        cc.phone,
        cc.contact_name,
        cc.last_message_text,
        cc.last_message_time,
        cc.unread_count
    FROM chat_conversations cc
    WHERE cc.user_id = 1 AND cc.is_archived = 0
    ORDER BY cc.last_message_time DESC
    LIMIT 50
");
$results = $stmt->fetchAll();
$metrics['complex_query_time'] = microtime(true) - $start;
$metrics['complex_query_results'] = count($results);
echo "   ✓ Resultados: {$metrics['complex_query_results']}\n";
echo "   ✓ Tempo: " . round($metrics['complex_query_time'] * 1000, 2) . "ms\n\n";

// 6. Testar query de mensagens
echo "6. Testando query de mensagens (50 últimas)...\n";
$start = microtime(true);
$stmt = $pdo->query("
    SELECT 
        m.id,
        m.message_text,
        m.from_me,
        m.timestamp
    FROM chat_messages m
    WHERE m.conversation_id = 1
    ORDER BY m.timestamp DESC
    LIMIT 50
");
$results = $stmt->fetchAll();
$metrics['messages_query_50_time'] = microtime(true) - $start;
$metrics['messages_query_50_results'] = count($results);
echo "   ✓ Resultados: {$metrics['messages_query_50_results']}\n";
echo "   ✓ Tempo: " . round($metrics['messages_query_50_time'] * 1000, 2) . "ms\n\n";

// Salvar métricas em arquivo JSON
$filename = __DIR__ . '/../metrics_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($filename, json_encode($metrics, JSON_PRETTY_PRINT));

echo "===========================================\n";
echo "  RESUMO\n";
echo "===========================================\n";
echo "Conversas: {$metrics['total_conversations']} (" . round($metrics['conversations_query_time'] * 1000, 2) . "ms)\n";
echo "Mensagens: {$metrics['total_messages']} (" . round($metrics['messages_query_time'] * 1000, 2) . "ms)\n";
echo "Query complexa: " . round($metrics['complex_query_time'] * 1000, 2) . "ms\n";
echo "Query 50 mensagens: " . round($metrics['messages_query_50_time'] * 1000, 2) . "ms\n";
echo "\n✅ Métricas salvas em: $filename\n\n";

// Análise e recomendações
echo "===========================================\n";
echo "  ANÁLISE\n";
echo "===========================================\n";

if ($metrics['complex_query_time'] > 0.5) {
    echo "⚠️  Query de conversas está LENTA (>500ms)\n";
    echo "    Recomendação: Criar índices\n\n";
} else {
    echo "✅ Query de conversas está RÁPIDA (<500ms)\n\n";
}

if ($metrics['messages_query_50_time'] > 0.2) {
    echo "⚠️  Query de mensagens está LENTA (>200ms)\n";
    echo "    Recomendação: Criar índices\n\n";
} else {
    echo "✅ Query de mensagens está RÁPIDA (<200ms)\n\n";
}

if ($metrics['conversations_indexes'] < 3) {
    echo "⚠️  Poucos índices em chat_conversations ({$metrics['conversations_indexes']})\n";
    echo "    Recomendação: Executar migration de índices\n\n";
} else {
    echo "✅ Índices adequados em chat_conversations\n\n";
}

if ($metrics['messages_indexes'] < 3) {
    echo "⚠️  Poucos índices em chat_messages ({$metrics['messages_indexes']})\n";
    echo "    Recomendação: Executar migration de índices\n\n";
} else {
    echo "✅ Índices adequados em chat_messages\n\n";
}

echo "===========================================\n";
