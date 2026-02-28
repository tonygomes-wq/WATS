<?php
/**
 * Verificação de Autenticação para APIs
 * WATS - Sistema de Automação WhatsApp
 */

// Iniciar sessão se não estiver iniciada
if (!isset($_SESSION)) {
    session_start();
}

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Não autorizado',
        'message' => 'Você precisa estar logado para acessar esta API'
    ]);
    exit;
}

// Verificar se sessão expirou (1 hora de inatividade)
$sessionTimeout = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Sessão expirada',
        'message' => 'Sua sessão expirou. Faça login novamente.'
    ]);
    exit;
}

// Atualizar timestamp da última atividade
$_SESSION['last_activity'] = time();

// Definir variáveis globais para facilitar uso nas APIs
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
