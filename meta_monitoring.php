<?php
/**
 * Página de Monitoramento Meta API
 * Acesso direto, sem passar pelo dashboard.php
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();
requirePasswordChange();

// Apenas Admin pode acessar
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Monitoramento Meta API';
require_once 'includes/header_spa.php';
require_once 'includes/Meta24HourWindow.php';
require_once 'includes/MetaRateLimiter.php';

$userId = $_SESSION['user_id'];

// Período de análise (padrão: 7 dias)
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;

// Buscar configuração Meta do usuário
$stmt = $pdo->prepare("
    SELECT 
        whatsapp_provider,
        meta_phone_number_id,
        meta_business_account_id,
        meta_api_version
    FROM users 
    WHERE id = ?
");
$stmt->execute([$userId]);
$userConfig = $stmt->fetch(PDO::FETCH_ASSOC);

$isMetaConfigured = ($userConfig['whatsapp_provider'] === 'meta' && !empty($userConfig['meta_phone_number_id']));

// Métricas gerais
$metrics = [
    'messages_sent' => 0,
    'messages_received' => 0,
    'messages_failed' => 0,
    'active_conversations' => 0,
    'success_rate' => 0
];

if ($isMetaConfigured) {
    // Mensagens enviadas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM chat_messages
        WHERE user_id = ?
        AND provider = 'meta'
        AND from_me = 1
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$userId, $days]);
    $metrics['messages_sent'] = $stmt->fetchColumn();
    
    // Mensagens recebidas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM chat_messages
        WHERE user_id = ?
        AND provider = 'meta'
        AND from_me = 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$userId, $days]);
    $metrics['messages_received'] = $stmt->fetchColumn();
    
    // Mensagens com falha
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM dispatch_history
        WHERE user_id = ?
        AND status = 'failed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$userId, $days]);
    $metrics['messages_failed'] = $stmt->fetchColumn();
    
    // Conversas ativas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM chat_conversations
        WHERE user_id = ?
        AND provider = 'meta'
        AND last_message_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$userId, $days]);
    $metrics['active_conversations'] = $stmt->fetchColumn();
    
    // Taxa de sucesso
    $total = $metrics['messages_sent'] + $metrics['messages_failed'];
    if ($total > 0) {
        $metrics['success_rate'] = round(($metrics['messages_sent'] / $total) * 100, 2);
    }
}

// Criar instância do windowManager para uso no unified
$windowManager = new Meta24HourWindow($pdo);

// Incluir o dashboard unificado com abas
include 'api/meta_monitoring_unified.php';

require_once 'includes/footer.php';
?>
