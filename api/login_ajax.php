<?php
/**
 * API de Login via AJAX
 * Processa autenticação sem recarregar a página
 * MACIP Tecnologia LTDA
 * 
 * CORRIGIDO: Compatível com RateLimiter da Fase 1
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/totp.php';
require_once '../includes/AuthService.php';
require_once '../includes/RateLimiter.php';
require_once '../includes/SecurityHelpers.php';

// Adicionar security headers
SecurityHelpers::setSecurityHeaders();

header('Content-Type: application/json');

// Permitir apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Rate limiting - CORRIGIDO: Sem parâmetros
$rateLimiter = new RateLimiter();

// Obter IP do cliente - CORRIGIDO: Direto do $_SERVER
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Verificar rate limit por IP (5 tentativas a cada 15 minutos = 900 segundos)
// CORRIGIDO: Usar método allow() ao invés de check()
if (!$rateLimiter->allow($clientIP, 'login_attempt', 5, 900)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Muitas tentativas de login. Aguarde 15 minutos.'
    ]);
    exit;
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Fallback para POST normal
    $input = $_POST;
}

$action = $input['action'] ?? 'login';
$authService = new AuthService($pdo);

try {
    if ($action === 'login') {
        $email = sanitize($input['email'] ?? '');
        $password = $input['password'] ?? '';

        // Verificar rate limit por email também
        // CORRIGIDO: Usar método allow()
        if (!empty($email)) {
            if (!$rateLimiter->allow($email, 'login_attempt', 5, 900)) {
                http_response_code(429);
                echo json_encode([
                    'success' => false,
                    'message' => 'Muitas tentativas de login. Aguarde 15 minutos.'
                ]);
                exit;
            }
        }

        $result = $authService->authenticate($email, $password);
        
        // Se login bem-sucedido, regenerar session ID
        // CORRIGIDO: Removido reset() e record() que não existem
        if ($result['success']) {
            session_regenerate_id(true);
        }
        
        echo json_encode($result);
        exit;
        
    } elseif ($action === 'verify_2fa') {
        $code = trim($input['code'] ?? '');
        $backupCode = trim($input['backup_code'] ?? '');

        $result = $authService->verifyTwoFactor($code, $backupCode);
        echo json_encode($result);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Erro no login AJAX: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar login. Tente novamente.'
    ]);
    exit;
}

// Ação inválida
echo json_encode([
    'success' => false,
    'message' => 'Ação inválida.'
]);
