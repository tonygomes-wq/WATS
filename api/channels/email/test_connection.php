<?php
/**
 * API para testar conexão e buscar emails (DEBUG)
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Buscar canal ativo
    $stmt = $pdo->prepare("
        SELECT c.*, ce.*
        FROM channels c
        INNER JOIN channel_email ce ON c.id = ce.channel_id
        WHERE c.channel_type = 'email' 
        AND c.status = 'active'
        AND c.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$channel) {
        echo json_encode([
            'success' => false, 
            'error' => 'Nenhum canal de email ativo encontrado',
            'debug' => 'Verifique se o canal está configurado e ativo'
        ]);
        exit;
    }
    
    // Verificar método de autenticação
    $authMethod = $channel['auth_method'] ?? 'password';
    
    $debug = [
        'channel_id' => $channel['id'],
        'email' => $channel['email'],
        'auth_method' => $authMethod,
        'status' => $channel['status']
    ];
    
    // Testar conexão IMAP
    if ($authMethod === 'password') {
        if (!function_exists('imap_open')) {
            echo json_encode([
                'success' => false,
                'error' => 'Extensão IMAP não está instalada no PHP',
                'debug' => $debug
            ]);
            exit;
        }
        
        $mailbox = sprintf(
            '{%s:%d/imap/%s}INBOX',
            $channel['imap_host'],
            $channel['imap_port'],
            strtolower($channel['imap_encryption'])
        );
        
        $debug['mailbox'] = $mailbox;
        
        // Tentar conectar
        $imap = @imap_open(
            $mailbox,
            $channel['email'],
            $channel['password']
        );
        
        if (!$imap) {
            $error = imap_last_error();
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao conectar ao servidor IMAP: ' . $error,
                'debug' => $debug
            ]);
            exit;
        }
        
        // Buscar emails
        $status = imap_status($imap, $mailbox, SA_ALL);
        $totalEmails = $status->messages ?? 0;
        $unreadEmails = imap_search($imap, 'UNSEEN');
        $unreadCount = $unreadEmails ? count($unreadEmails) : 0;
        
        imap_close($imap);
        
        echo json_encode([
            'success' => true,
            'message' => 'Conexão IMAP bem-sucedida',
            'total_emails' => $totalEmails,
            'unread_emails' => $unreadCount,
            'debug' => $debug
        ]);
        
    } else {
        // OAuth (Microsoft)
        echo json_encode([
            'success' => false,
            'error' => 'OAuth não implementado neste teste',
            'debug' => $debug
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
