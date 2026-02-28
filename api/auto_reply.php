<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar se a tabela existe
$tableExists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'user_settings'");
    $tableExists = $check->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}

// Se tabela não existe, retornar dados vazios
if (!$tableExists) {
    echo json_encode([
        'success' => true,
        'message' => 'Tabelas não configuradas. Execute o SQL de migração.',
        'settings' => [
            'enabled' => false,
            'config' => [
                'positive_template' => '',
                'negative_template' => '',
                'neutral_template' => ''
            ]
        ]
    ]);
    exit;
}

require_once '../includes/auto_reply_system.php';
$autoReply = new AutoReplySystem($pdo, $userId);

try {
    switch ($action) {
        case 'get_settings':
            $settings = $autoReply->getSettings();
            
            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
            break;
            
        case 'update_settings':
            $enabled = isset($_POST['enabled']) ? (bool)$_POST['enabled'] : false;
            $config = isset($_POST['config']) ? json_decode($_POST['config'], true) : [];
            
            $success = $autoReply->updateSettings($enabled, $config);
            
            echo json_encode([
                'success' => $success,
                'message' => 'Configurações atualizadas com sucesso'
            ]);
            break;
            
        case 'test_reply':
            $sentiment = $_POST['sentiment'] ?? 'positive';
            $phone = $_POST['phone'] ?? '';
            
            if (empty($phone)) {
                throw new Exception('Telefone é obrigatório para teste');
            }
            
            // Criar resposta de teste
            $stmt = $pdo->prepare("
                INSERT INTO dispatch_responses 
                (user_id, phone, message_text, sentiment, received_at)
                VALUES (?, ?, 'Teste de resposta automática', ?, NOW())
            ");
            
            $stmt->execute([$userId, $phone, $sentiment]);
            $responseId = $pdo->lastInsertId();
            
            $result = $autoReply->processAutoReply($responseId);
            
            echo json_encode([
                'success' => true,
                'result' => $result
            ]);
            break;
            
        case 'get_log':
            $limit = (int)($_GET['limit'] ?? 50);
            
            $stmt = $pdo->prepare("
                SELECT * FROM auto_reply_log
                WHERE user_id = ?
                ORDER BY sent_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$userId, $limit]);
            $log = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'log' => $log
            ]);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
