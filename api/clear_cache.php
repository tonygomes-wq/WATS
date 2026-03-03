<?php
require_once '../includes/auth.php';
require_once '../libs/RedisCache.php';

header('Content-Type: application/json');

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$cache = new RedisCache();
$cleared = $cache->flush('*');

echo json_encode([
    'success' => true,
    'message' => "Cache limpo com sucesso! {$cleared} chaves removidas."
]);
