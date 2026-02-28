<?php
/**
 * Proxy para Imagens do Microsoft Teams
 * 
 * As URLs de imagens do Teams requerem autenticação via token.
 * Este proxy busca a imagem usando o token do usuário e retorna para o navegador.
 * 
 * @version 1.0
 * @since 2026-01-29
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/channels/TeamsGraphAPI.php';

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];

// Obter URL da imagem
$imageUrl = $_GET['url'] ?? '';

if (empty($imageUrl)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'URL não fornecida']);
    exit;
}

// ✅ CACHE: Verificar se já temos a imagem em cache
$cacheKey = 'teams_img_' . md5($imageUrl . $userId);
$cacheDir = __DIR__ . '/../storage/cache/teams_images/';
$cacheFile = $cacheDir . $cacheKey;

// Criar diretório de cache se não existir
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Se existe em cache e não expirou (1 hora), retornar do cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
    $cachedData = file_get_contents($cacheFile);
    $cachedMeta = file_get_contents($cacheFile . '.meta');
    
    if ($cachedData && $cachedMeta) {
        $meta = json_decode($cachedMeta, true);
        header('Content-Type: ' . ($meta['content_type'] ?? 'image/jpeg'));
        header('Cache-Control: public, max-age=3600');
        header('X-Cache: HIT');
        echo $cachedData;
        exit;
    }
}

// Validar URL (deve ser do domínio do Microsoft Graph)
if (strpos($imageUrl, 'graph.microsoft.com') === false && 
    strpos($imageUrl, 'sharepoint.com') === false &&
    strpos($imageUrl, 'onedrive.com') === false) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'URL inválida']);
    exit;
}

try {
    // Criar instância da API Teams
    $teamsAPI = new TeamsGraphAPI($pdo, $userId);
    
    if (!$teamsAPI->isAuthenticated()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Teams não autenticado']);
        exit;
    }
    
    // Buscar token de acesso
    $stmt = $pdo->prepare("
        SELECT access_token 
        FROM teams_auth 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $auth = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$auth || empty($auth['access_token'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Token não encontrado']);
        exit;
    }
    
    $accessToken = $auth['access_token'];
    
    // Fazer requisição para buscar a imagem
    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: image/*'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Verificar se houve erro
    if ($httpCode !== 200) {
        error_log("[Teams Image Proxy] Erro ao buscar imagem: HTTP {$httpCode}");
        error_log("[Teams Image Proxy] URL: {$imageUrl}");
        error_log("[Teams Image Proxy] Error: {$error}");
        
        // ✅ Se for erro 429 (rate limit), retornar imagem placeholder
        if ($httpCode === 429) {
            error_log("[Teams Image Proxy] Rate limit atingido - retornando placeholder");
            
            // Criar imagem placeholder simples
            $placeholder = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mN8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');
            
            header('Content-Type: image/png');
            header('Cache-Control: no-cache');
            header('X-Rate-Limited: true');
            echo $placeholder;
            exit;
        }
        
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Erro ao buscar imagem',
            'http_code' => $httpCode,
            'details' => $error
        ]);
        exit;
    }
    
    // ✅ SALVAR NO CACHE
    file_put_contents($cacheFile, $imageData);
    file_put_contents($cacheFile . '.meta', json_encode([
        'content_type' => $contentType,
        'cached_at' => time()
    ]));
    
    // Retornar imagem
    header('Content-Type: ' . ($contentType ?: 'image/jpeg'));
    header('Cache-Control: public, max-age=3600'); // Cache por 1 hora
    header('Content-Length: ' . strlen($imageData));
    header('X-Cache: MISS');
    
    echo $imageData;
    
} catch (Exception $e) {
    error_log("[Teams Image Proxy] Exceção: " . $e->getMessage());
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erro interno',
        'message' => $e->getMessage()
    ]);
}
