<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar se a tabela existe
$tableExists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'dispatch_time_analytics'");
    $tableExists = $check->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}

// Se tabela não existe, retornar dados vazios
if (!$tableExists) {
    echo json_encode([
        'success' => true,
        'message' => 'Tabelas não configuradas. Execute o SQL de migração.',
        'best_times' => [],
        'heatmap' => [],
        'suggestion' => null,
        'stats' => ['total_records' => 0, 'avg_score' => 0]
    ]);
    exit;
}

require_once '../includes/predictive_analytics.php';
$analytics = new PredictiveAnalytics($pdo, $userId);

try {
    switch ($action) {
        case 'best_times':
            $limit = (int)($_GET['limit'] ?? 5);
            $bestTimes = $analytics->getBestTimes($limit);
            
            echo json_encode([
                'success' => true,
                'best_times' => $bestTimes
            ]);
            break;
            
        case 'heatmap':
            $heatmap = $analytics->getEngagementHeatmap();
            
            echo json_encode([
                'success' => true,
                'heatmap' => $heatmap
            ]);
            break;
            
        case 'suggest_next':
            $suggestion = $analytics->suggestNextBestTime();
            
            echo json_encode([
                'success' => true,
                'suggestion' => $suggestion
            ]);
            break;
            
        case 'stats':
            $stats = $analytics->getTimeStats();
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        case 'recalculate':
            $analytics->calculateTimeAnalytics();
            
            echo json_encode([
                'success' => true,
                'message' => 'Analytics recalculadas com sucesso'
            ]);
            break;
            
        case 'full_report':
            $bestTimes = $analytics->getBestTimes(10);
            $heatmap = $analytics->getEngagementHeatmap();
            $suggestion = $analytics->suggestNextBestTime();
            $stats = $analytics->getTimeStats();
            
            echo json_encode([
                'success' => true,
                'best_times' => $bestTimes,
                'heatmap' => $heatmap,
                'suggestion' => $suggestion,
                'stats' => $stats
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
