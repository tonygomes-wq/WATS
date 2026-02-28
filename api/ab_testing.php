<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar se a tabela existe
$tableExists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'ab_tests'");
    $tableExists = $check->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}

// Se tabela não existe, retornar dados vazios
if (!$tableExists) {
    echo json_encode([
        'success' => true,
        'message' => 'Tabelas não configuradas. Execute o SQL de migração.',
        'tests' => [],
        'test' => null,
        'results' => null
    ]);
    exit;
}

require_once '../includes/ab_testing.php';
$abTesting = new ABTesting($pdo, $userId);

try {
    switch ($action) {
        case 'create':
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'variant_a_message' => $_POST['variant_a_message'] ?? '',
                'variant_b_message' => $_POST['variant_b_message'] ?? ''
            ];
            
            if (empty($data['name']) || empty($data['variant_a_message']) || empty($data['variant_b_message'])) {
                throw new Exception('Nome e mensagens das variantes são obrigatórios');
            }
            
            $testId = $abTesting->createTest($data);
            
            echo json_encode([
                'success' => true,
                'test_id' => $testId,
                'message' => 'Teste A/B criado com sucesso'
            ]);
            break;
            
        case 'start':
            $testId = (int)($_POST['test_id'] ?? 0);
            
            if (!$testId) {
                throw new Exception('ID do teste é obrigatório');
            }
            
            $success = $abTesting->startTest($testId);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Teste iniciado' : 'Não foi possível iniciar o teste'
            ]);
            break;
            
        case 'list':
            $status = $_GET['status'] ?? null;
            $limit = (int)($_GET['limit'] ?? 20);
            
            $tests = $abTesting->listTests($status, $limit);
            
            echo json_encode([
                'success' => true,
                'tests' => $tests
            ]);
            break;
            
        case 'get':
            $testId = (int)($_GET['test_id'] ?? 0);
            
            if (!$testId) {
                throw new Exception('ID do teste é obrigatório');
            }
            
            $test = $abTesting->getTest($testId);
            
            echo json_encode([
                'success' => true,
                'test' => $test
            ]);
            break;
            
        case 'results':
            $testId = (int)($_GET['test_id'] ?? 0);
            
            if (!$testId) {
                throw new Exception('ID do teste é obrigatório');
            }
            
            $results = $abTesting->getTestResults($testId);
            
            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
            break;
            
        case 'complete':
            $testId = (int)($_POST['test_id'] ?? 0);
            
            if (!$testId) {
                throw new Exception('ID do teste é obrigatório');
            }
            
            $results = $abTesting->completeTest($testId);
            
            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
            break;
            
        case 'cancel':
            $testId = (int)($_POST['test_id'] ?? 0);
            
            if (!$testId) {
                throw new Exception('ID do teste é obrigatório');
            }
            
            $success = $abTesting->cancelTest($testId);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Teste cancelado' : 'Não foi possível cancelar o teste'
            ]);
            break;
            
        case 'get_variant':
            $testId = (int)($_GET['test_id'] ?? 0);
            $contactId = (int)($_GET['contact_id'] ?? 0);
            
            if (!$testId || !$contactId) {
                throw new Exception('IDs do teste e contato são obrigatórios');
            }
            
            $variant = $abTesting->getVariantForContact($testId, $contactId);
            $message = $abTesting->getVariantMessage($testId, $variant);
            
            echo json_encode([
                'success' => true,
                'variant' => $variant,
                'message' => $message
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
