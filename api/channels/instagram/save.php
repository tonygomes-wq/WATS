<?php
/**
 * API para salvar configuração do canal Instagram
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/channels/InstagramChannel.php';

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Verificar permissões (apenas admin/supervisor)
$userType = $_SESSION['user_type'] ?? 'user';
if (!in_array($userType, ['admin', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$instagramAccountId = $data['instagram_account_id'] ?? '';
$accessToken = $data['access_token'] ?? '';
$pageId = $data['page_id'] ?? '';
$name = $data['name'] ?? 'Instagram';

// Validar campos obrigatórios
if (empty($instagramAccountId) || empty($accessToken)) {
    echo json_encode([
        'success' => false,
        'error' => 'Instagram Account ID e Access Token são obrigatórios'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Verificar se já existe um canal Instagram
    $stmt = $pdo->prepare("SELECT id FROM channels WHERE channel_type = 'instagram' LIMIT 1");
    $stmt->execute();
    $existingChannel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingChannel) {
        // Atualizar canal existente
        $channelId = $existingChannel['id'];
        
        $stmt = $pdo->prepare("
            UPDATE channels 
            SET name = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $channelId]);
        
        // Atualizar configurações do Instagram
        $stmt = $pdo->prepare("
            UPDATE channel_instagram 
            SET instagram_account_id = ?, access_token = ?, page_id = ?, updated_at = NOW()
            WHERE channel_id = ?
        ");
        $stmt->execute([$instagramAccountId, $accessToken, $pageId, $channelId]);
        
    } else {
        // Criar novo canal
        $stmt = $pdo->prepare("
            INSERT INTO channels (channel_type, name, is_active, created_at, updated_at)
            VALUES ('instagram', ?, 1, NOW(), NOW())
        ");
        $stmt->execute([$name]);
        $channelId = $pdo->lastInsertId();
        
        // Inserir configurações do Instagram
        $stmt = $pdo->prepare("
            INSERT INTO channel_instagram (channel_id, instagram_account_id, access_token, page_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$channelId, $instagramAccountId, $accessToken, $pageId]);
    }
    
    // Inicializar canal para validar credenciais
    $instagram = new InstagramChannel($pdo, $channelId);
    
    try {
        $validation = $instagram->validateCredentials();
        
        // Atualizar nome com username do Instagram se disponível
        if (isset($validation['username'])) {
            $stmt = $pdo->prepare("UPDATE channels SET name = ? WHERE id = ?");
            $stmt->execute(['Instagram - ' . $validation['username'], $channelId]);
        }
        
        // Configurar webhook
        $webhookUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
                    . '://' . $_SERVER['HTTP_HOST'] 
                    . '/api/webhooks/instagram.php';
        
        try {
            $instagram->setupWebhook($webhookUrl);
        } catch (Exception $e) {
            // Webhook pode falhar mas não impede o salvamento
            error_log('Aviso: Falha ao configurar webhook do Instagram: ' . $e->getMessage());
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Canal Instagram configurado com sucesso',
            'channel_id' => $channelId,
            'username' => $validation['username'] ?? null,
            'webhook_url' => $webhookUrl
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao validar credenciais: ' . $e->getMessage()
        ]);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao salvar configuração: ' . $e->getMessage()
    ]);
}
