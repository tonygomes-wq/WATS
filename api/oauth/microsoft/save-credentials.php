<?php
/**
 * Salvar credenciais OAuth na sessão PHP
 * Usado antes de abrir popup de autenticação
 */

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$email = $data['email'] ?? '';
$clientId = $data['client_id'] ?? '';
$clientSecret = $data['client_secret'] ?? '';
$tenantId = $data['tenant_id'] ?? '';

if (empty($email) || empty($clientId) || empty($clientSecret) || empty($tenantId)) {
    echo json_encode([
        'success' => false,
        'error' => 'Todos os campos são obrigatórios'
    ]);
    exit;
}

// Salvar na sessão PHP
$_SESSION['oauth_email'] = $email;
$_SESSION['oauth_client_id'] = $clientId;
$_SESSION['oauth_client_secret'] = $clientSecret;
$_SESSION['oauth_tenant_id'] = $tenantId;

echo json_encode([
    'success' => true,
    'message' => 'Credenciais salvas na sessão'
]);
