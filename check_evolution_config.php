<?php
/**
 * Verificar configuração da Evolution API do usuário
 */

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    die('Não autenticado');
}

$userId = $_SESSION['user_id'];

// Buscar todas as colunas relacionadas à Evolution API
$stmt = $pdo->prepare("
    SELECT 
        id,
        whatsapp_provider,
        evolution_instance,
        evolution_token,
        evolution_api_url,
        evolution_go_instance,
        evolution_go_token
    FROM users 
    WHERE id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'user_config' => $user,
    'constants' => [
        'EVOLUTION_API_URL' => EVOLUTION_API_URL,
        'EVOLUTION_GO_API_URL' => EVOLUTION_GO_API_URL,
        'EVOLUTION_API_KEY' => substr(EVOLUTION_API_KEY, 0, 10) . '...',
        'EVOLUTION_GO_API_KEY' => substr(EVOLUTION_GO_API_KEY, 0, 10) . '...'
    ]
], JSON_PRETTY_PRINT);
?>
