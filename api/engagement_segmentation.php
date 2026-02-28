<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar se a tabela existe
$tableExists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'contact_engagement_scores'");
    $tableExists = $check->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}

// Se tabela não existe, retornar dados vazios
if (!$tableExists) {
    echo json_encode([
        'success' => true,
        'message' => 'Tabelas não configuradas. Execute o SQL de migração.',
        'summary' => ['high' => 0, 'medium' => 0, 'low' => 0, 'inactive' => 0],
        'contacts' => [],
        'top_engaged' => [],
        'need_attention' => []
    ]);
    exit;
}

require_once '../includes/engagement_segmentation.php';
$segmentation = new EngagementSegmentation($pdo, $userId);

try {
    switch ($action) {
        case 'summary':
            $summary = $segmentation->getSegmentationSummary();
            
            echo json_encode([
                'success' => true,
                'summary' => $summary
            ]);
            break;
            
        case 'by_level':
            $level = $_GET['level'] ?? 'high';
            $limit = (int)($_GET['limit'] ?? 50);
            
            if (!in_array($level, ['high', 'medium', 'low', 'inactive'])) {
                throw new Exception('Nível inválido');
            }
            
            $contacts = $segmentation->getContactsByLevel($level, $limit);
            
            echo json_encode([
                'success' => true,
                'level' => $level,
                'contacts' => $contacts
            ]);
            break;
            
        case 'top_engaged':
            $limit = (int)($_GET['limit'] ?? 10);
            $contacts = $segmentation->getTopEngaged($limit);
            
            echo json_encode([
                'success' => true,
                'contacts' => $contacts
            ]);
            break;
            
        case 'need_attention':
            $limit = (int)($_GET['limit'] ?? 20);
            $contacts = $segmentation->getNeedAttention($limit);
            
            echo json_encode([
                'success' => true,
                'contacts' => $contacts
            ]);
            break;
            
        case 'calculate_contact':
            $contactId = (int)($_POST['contact_id'] ?? 0);
            
            if (!$contactId) {
                throw new Exception('ID do contato é obrigatório');
            }
            
            $result = $segmentation->calculateContactScore($contactId);
            
            echo json_encode([
                'success' => true,
                'result' => $result
            ]);
            break;
            
        case 'recalculate_all':
            $processed = $segmentation->calculateAllScores();
            
            echo json_encode([
                'success' => true,
                'processed' => $processed,
                'message' => "{$processed} contatos processados"
            ]);
            break;
            
        case 'full_report':
            $summary = $segmentation->getSegmentationSummary();
            $topEngaged = $segmentation->getTopEngaged(10);
            $needAttention = $segmentation->getNeedAttention(10);
            
            echo json_encode([
                'success' => true,
                'summary' => $summary,
                'top_engaged' => $topEngaged,
                'need_attention' => $needAttention
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
