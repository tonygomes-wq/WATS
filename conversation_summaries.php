<?php
/**
 * API de Resumos de Conversas
 * Endpoints para geração e gerenciamento de resumos
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/ConversationSummaryService.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar permissão (apenas Admin e Supervisor)
if (!isAdmin() && !isSupervisor()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas supervisores e administradores.']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$input = [];

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $input = json_decode($rawInput, true);
    }
    if (!$input) {
        $input = $_POST;
    }
}

$action = $method === 'GET' ? ($_GET['action'] ?? 'list') : ($input['action'] ?? '');

// Inicializar serviço
$service = new ConversationSummaryService($pdo, $userId, $userId);

function jsonResponse($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    // Verificar se tabelas existem
    $stmt = $pdo->query("SHOW TABLES LIKE 'conversation_summaries'");
    <div class="refined-container">$stmt->rowCount() === 0) {
        throw new Exception('Sistema não configurado. Execute a migration SQL primeiro: migrations/conversation_summaries.sql');
    }
    
    switch ($action) {
        case 'list':
            // Listar resumos existentes
            $filters = [
                'attendant_id' => $_GET['attendant_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'status' => $_GET['status'] ?? null,
                'sentiment' => $_GET['sentiment'] ?? null
            ];
            
            $summaries = $service->listSummaries(array_filter($filters));
            jsonResponse(['success' => true, 'summaries' => $summaries]);
            break;
            
        case 'list_conversations':
            // Listar conversas disponíveis para resumo
            $filters = [
                'attendant_id' => $_GET['attendant_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'status' => $_GET['status'] ?? null,
                'has_summary' => isset($_GET['has_summary']) ? (bool)$_GET['has_summary'] : null
            ];
            
            $conversations = $service->listAvailableConversations(array_filter($filters));
            jsonResponse(['success' => true, 'conversations' => $conversations]);
            break;
            
        case 'get':
            // Obter resumo específico
            $summaryId = intval($_GET['id'] ?? 0);
            if ($summaryId <= 0) {
                throw new Exception('ID inválido');
            }
            
            $summary = $service->getSummary($summaryId);
            if (!$summary) {
                throw new Exception('Resumo não encontrado');
            }
            
            jsonResponse(['success' => true, 'summary' => $summary]);
            break;
            
        case 'generate':
            // Gerar resumo de uma conversa
            $conversationId = intval($input['conversation_id'] ?? 0);
            if ($conversationId <= 0) {
                throw new Exception('ID da conversa inválido');
            }
            
            $result = $service->generateSummary($conversationId);
            jsonResponse($result);
            break;
            
        case 'generate_batch':
            // Gerar resumos em lote
            $conversationIds = $input['conversation_ids'] ?? [];
            
            if (!is_array($conversationIds) || empty($conversationIds)) {
                throw new Exception('Lista de conversas inválida');
            }
            
            // Limitar a 50 conversas por vez
            if (count($conversationIds) > 50) {
                throw new Exception('Máximo de 50 conversas por lote');
            }
            
            $result = $service->generateBatchSummaries($conversationIds);
            jsonResponse($result);
            break;
            
        case 'delete':
            // Deletar resumo
            $summaryId = intval($input['id'] ?? $_GET['id'] ?? 0);
            if ($summaryId <= 0) {
                throw new Exception('ID inválido');
            }
            
            $success = $service->deleteSummary($summaryId);
            jsonResponse([
                'success' => $success,
                'message' => $success ? 'Resumo deletado' : 'Erro ao deletar'
            ]);
            break;
            
        case 'download':
            // Download em PDF
            $summaryId = intval($_GET['id'] ?? 0);
            if ($summaryId <= 0) {
                throw new Exception('ID inválido');
            }
            
            $summary = $service->getSummary($summaryId);
            if (!$summary) {
                throw new Exception('Resumo não encontrado');
            }
            
            // Gerar PDF
            require_once '../includes/pdf_generator.php';
            $pdfPath = generateSummaryPDF($summary);
            
            if ($pdfPath && file_exists($pdfPath)) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="resumo_' . $summaryId . '.pdf"');
                readfile($pdfPath);
                exit;
            } else {
                throw new Exception('Erro ao gerar PDF');
            }
            break;
            
        case 'stats':
            // Estatísticas gerais
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_summaries,
                    AVG(processing_time_ms) as avg_processing_time,
                    SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as positive_count,
                    SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral_count,
                    SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as negative_count,
                    SUM(CASE WHEN sentiment = 'mixed' THEN 1 ELSE 0 END) as mixed_count
                FROM conversation_summaries
                WHERE user_id = ? 
                AND generated_at BETWEEN ? AND ?
                AND status = 'completed'
            ");
            $stmt->execute([$userId, $dateFrom, $dateTo]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Top atendentes
            $stmt = $pdo->prepare("
                SELECT u.name, COUNT(*) as summary_count
                FROM conversation_summaries cs
                JOIN users u ON cs.attendant_id = u.id
                WHERE cs.user_id = ? 
                AND cs.generated_at BETWEEN ? AND ?
                AND cs.status = 'completed'
                GROUP BY cs.attendant_id
                ORDER BY summary_count DESC
                LIMIT 5
            ");
            $stmt->execute([$userId, $dateFrom, $dateTo]);
            $topAttendants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Palavras-chave mais frequentes
            $stmt = $pdo->prepare("
                SELECT keywords
                FROM conversation_summaries
                WHERE user_id = ? 
                AND generated_at BETWEEN ? AND ?
                AND status = 'completed'
                AND keywords IS NOT NULL
            ");
            $stmt->execute([$userId, $dateFrom, $dateTo]);
            $allKeywords = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $keywords = json_decode($row['keywords'], true);
                if ($keywords) {
                    $allKeywords = array_merge($allKeywords, $keywords);
                }
            }
            $keywordCounts = array_count_values($allKeywords);
            arsort($keywordCounts);
            $topKeywords = array_slice($keywordCounts, 0, 10, true);
            
            jsonResponse([
                'success' => true,
                'stats' => $stats,
                'top_attendants' => $topAttendants,
                'top_keywords' => $topKeywords
            ]);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    error_log("[CONVERSATION_SUMMARIES_API] Erro: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
