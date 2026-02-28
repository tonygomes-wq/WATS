<?php
/**
 * Verificar estrutura do banco de dados para emails
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

try {
    $tables = [];
    
    // Verificar tabela conversations
    $result = $pdo->query("SHOW COLUMNS FROM conversations");
    $conversationColumns = $result->fetchAll(PDO::FETCH_COLUMN);
    $tables['conversations'] = $conversationColumns;
    
    // Verificar tabela messages
    $result = $pdo->query("SHOW COLUMNS FROM messages");
    $messageColumns = $result->fetchAll(PDO::FETCH_COLUMN);
    $tables['messages'] = $messageColumns;
    
    // Verificar tabela channels
    $result = $pdo->query("SHOW COLUMNS FROM channels");
    $channelColumns = $result->fetchAll(PDO::FETCH_COLUMN);
    $tables['channels'] = $channelColumns;
    
    // Verificar tabela channel_email
    $result = $pdo->query("SHOW COLUMNS FROM channel_email");
    $emailColumns = $result->fetchAll(PDO::FETCH_COLUMN);
    $tables['channel_email'] = $emailColumns;
    
    // Verificar se há conversas de email
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM conversations");
    $totalConversations = $stmt->fetch()['total'];
    
    // Verificar se há canais de email
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM channels WHERE channel_type = 'email'");
    $totalEmailChannels = $stmt->fetch()['total'];
    
    // Verificar se há configurações de email
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM channel_email");
    $totalEmailConfigs = $stmt->fetch()['total'];
    
    // Colunas recomendadas
    $recommended = [
        'conversations' => ['id', 'channel_id', 'channel_type', 'contact_id', 'user_id', 'contact_name', 'contact_number', 'status', 'additional_attributes', 'last_message_at', 'created_at', 'updated_at'],
        'messages' => ['id', 'conversation_id', 'channel_id', 'channel_type', 'sender_type', 'message_text', 'external_id', 'additional_data', 'is_read', 'created_at'],
        'channels' => ['id', 'user_id', 'channel_type', 'name', 'status', 'created_at', 'updated_at'],
        'channel_email' => ['id', 'channel_id', 'email', 'auth_method', 'imap_host', 'imap_port', 'imap_encryption', 'smtp_host', 'smtp_port', 'smtp_encryption', 'password']
    ];
    
    // Verificar colunas faltantes
    $missing = [];
    foreach ($recommended as $table => $cols) {
        $missing[$table] = array_diff($cols, $tables[$table]);
    }
    
    echo json_encode([
        'success' => true,
        'tables' => $tables,
        'recommended' => $recommended,
        'missing' => $missing,
        'stats' => [
            'total_conversations' => $totalConversations,
            'total_email_channels' => $totalEmailChannels,
            'total_email_configs' => $totalEmailConfigs
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
