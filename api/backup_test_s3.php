<?php
/**
 * API - Testar conexão Amazon S3
 * 
 * POST: Testa conexão com bucket S3
 * 
 * MACIP Tecnologia LTDA
 */

session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/cloud_providers/s3.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$accessKey = $input['access_key'] ?? '';
$secretKey = $input['secret_key'] ?? '';
$bucket = $input['bucket'] ?? '';
$region = $input['region'] ?? 'us-east-1';

if (empty($accessKey) || empty($secretKey) || empty($bucket)) {
    echo json_encode(['success' => false, 'error' => 'Preencha todos os campos obrigatórios']);
    exit;
}

$s3 = new S3Backup([
    'access_key' => $accessKey,
    'secret_key' => $secretKey,
    'bucket' => $bucket,
    'region' => $region
]);

$result = $s3->testConnection();

echo json_encode($result);
