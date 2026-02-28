<?php
/**
 * API para buscar e salvar fotos de perfil dos contatos
 * Pode ser chamada manualmente ou via cron
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT evolution_instance, evolution_token, evolution_api_url FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || empty($user['evolution_instance'])) {
    echo json_encode(['success' => false, 'error' => 'Instância não configurada']);
    exit;
}

$instance = $user['evolution_instance'];
$token = $user['evolution_token'] ?: EVOLUTION_API_KEY;
$apiUrl = $user['evolution_api_url'] ?: EVOLUTION_API_URL;

// Buscar conversas que precisam de foto
$stmt = $pdo->prepare("
    SELECT DISTINCT phone 
    FROM chat_conversations 
    WHERE user_id = ? 
    AND phone IS NOT NULL 
    AND phone != ''
    ORDER BY last_message_time DESC
    LIMIT 50
");
$stmt->execute([$userId]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = [
    'total' => count($conversations),
    'updated' => 0,
    'failed' => 0,
    'skipped' => 0,
    'details' => []
];

// Criar diretório se não existir
$uploadDir = __DIR__ . '/../uploads/profile_pictures/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

foreach ($conversations as $conv) {
    $phone = preg_replace('/[^0-9]/', '', $conv['phone']);
    
    if (empty($phone)) {
        $results['skipped']++;
        continue;
    }
    
    // Verificar se já tem foto local recente (menos de 24h)
    $localFile = $uploadDir . $phone . '.jpg';
    if (file_exists($localFile) && (time() - filemtime($localFile)) < 86400) {
        $results['skipped']++;
        $results['details'][] = ['phone' => $phone, 'status' => 'skipped', 'reason' => 'Foto recente existe'];
        continue;
    }
    
    // Buscar foto da Evolution API
    try {
        $url = rtrim($apiUrl, '/') . "/chat/fetchProfilePictureUrl/{$instance}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['number' => $phone]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $results['failed']++;
            $results['details'][] = ['phone' => $phone, 'status' => 'failed', 'reason' => "HTTP $httpCode"];
            continue;
        }
        
        $data = json_decode($response, true);
        $picUrl = $data['profilePictureUrl'] ?? null;
        
        if (empty($picUrl)) {
            $results['skipped']++;
            $results['details'][] = ['phone' => $phone, 'status' => 'skipped', 'reason' => 'Sem foto de perfil'];
            continue;
        }
        
        // Baixar a imagem
        $imageContent = @file_get_contents($picUrl);
        
        if ($imageContent && strlen($imageContent) > 100) {
            file_put_contents($localFile, $imageContent);
            $results['updated']++;
            $results['details'][] = ['phone' => $phone, 'status' => 'updated', 'file' => $phone . '.jpg'];
        } else {
            $results['failed']++;
            $results['details'][] = ['phone' => $phone, 'status' => 'failed', 'reason' => 'Falha ao baixar imagem'];
        }
        
    } catch (Exception $e) {
        $results['failed']++;
        $results['details'][] = ['phone' => $phone, 'status' => 'failed', 'reason' => $e->getMessage()];
    }
    
    // Pequeno delay para não sobrecarregar a API
    usleep(200000); // 200ms
}

echo json_encode([
    'success' => true,
    'results' => $results
]);
