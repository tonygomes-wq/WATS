<?php
/**
 * API para Gerenciamento de 2FA de Atendentes pelo Supervisor
 * Permite ativar/desativar 2FA e gerar códigos de backup
 * MACIP Tecnologia LTDA
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/totp.php';

header('Content-Type: application/json');

// Verificar autenticação
requireLogin();

// Apenas supervisores e admins podem gerenciar 2FA de atendentes
$is_admin = isAdmin();
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;

if (!$is_admin && !$is_supervisor) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado. Apenas supervisores podem gerenciar 2FA de atendentes.'
    ]);
    exit;
}

// Permitir apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Fallback para POST normal
    $input = $_POST;
}

$action = $input['action'] ?? '';
$attendant_id = intval($input['attendant_id'] ?? 0);
$supervisor_id = $_SESSION['user_id'];

try {
    // Verificar se o atendente pertence a este supervisor
    $stmt = $pdo->prepare("SELECT * FROM supervisor_users WHERE id = ? AND supervisor_id = ?");
    $stmt->execute([$attendant_id, $supervisor_id]);
    $attendant = $stmt->fetch();
    
    if (!$attendant) {
        echo json_encode([
            'success' => false,
            'message' => 'Atendente não encontrado ou não pertence a você.'
        ]);
        exit;
    }
    
    switch ($action) {
        case 'enable_2fa':
            // ===== ATIVAR 2FA =====
            $force = isset($input['force']) && $input['force'] === true;
            
            // Gerar secret TOTP
            $secret = TOTP::generateSecret();
            
            // Gerar códigos de backup
            $backupCodes = TOTP::generateBackupCodes();
            
            // Atualizar no banco
            $stmt = $pdo->prepare("
                UPDATE supervisor_users 
                SET two_factor_enabled = 1,
                    two_factor_secret = ?,
                    backup_codes = ?,
                    two_factor_enabled_by_supervisor = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $secret,
                json_encode($backupCodes),
                $force ? 1 : 0,
                $attendant_id
            ]);
            
            // Gerar QR Code para o atendente
            $qrCodeUrl = TOTP::getQRCodeUrl(
                $attendant['email'],
                $secret,
                'WATS - ' . $attendant['name']
            );
            
            // Log da atividade
            $stmt = $pdo->prepare("
                INSERT INTO supervisor_activity_logs 
                (supervisor_user_id, action, description, metadata) 
                VALUES (?, 'enable_2fa', 'Supervisor ativou 2FA', ?)
            ");
            $stmt->execute([
                $attendant_id,
                json_encode([
                    'supervisor_id' => $supervisor_id,
                    'forced' => $force,
                    'timestamp' => date('Y-m-d H:i:s')
                ])
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => '2FA ativado com sucesso!',
                'data' => [
                    'secret' => $secret,
                    'qr_code_url' => $qrCodeUrl,
                    'backup_codes' => $backupCodes,
                    'forced' => $force
                ]
            ]);
            break;
            
        case 'disable_2fa':
            // ===== DESATIVAR 2FA =====
            $stmt = $pdo->prepare("
                UPDATE supervisor_users 
                SET two_factor_enabled = 0,
                    two_factor_secret = NULL,
                    backup_codes = NULL,
                    two_factor_enabled_by_supervisor = 0
                WHERE id = ?
            ");
            $stmt->execute([$attendant_id]);
            
            // Log da atividade
            $stmt = $pdo->prepare("
                INSERT INTO supervisor_activity_logs 
                (supervisor_user_id, action, description, metadata) 
                VALUES (?, 'disable_2fa', 'Supervisor desativou 2FA', ?)
            ");
            $stmt->execute([
                $attendant_id,
                json_encode([
                    'supervisor_id' => $supervisor_id,
                    'timestamp' => date('Y-m-d H:i:s')
                ])
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => '2FA desativado com sucesso!'
            ]);
            break;
            
        case 'regenerate_backup_codes':
            // ===== REGENERAR CÓDIGOS DE BACKUP =====
            if (!$attendant['two_factor_enabled']) {
                echo json_encode([
                    'success' => false,
                    'message' => '2FA não está ativado para este atendente.'
                ]);
                exit;
            }
            
            // Gerar novos códigos
            $backupCodes = TOTP::generateBackupCodes();
            
            // Atualizar no banco
            $stmt = $pdo->prepare("
                UPDATE supervisor_users 
                SET backup_codes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($backupCodes),
                $attendant_id
            ]);
            
            // Log da atividade
            $stmt = $pdo->prepare("
                INSERT INTO supervisor_activity_logs 
                (supervisor_user_id, action, description, metadata) 
                VALUES (?, 'regenerate_backup_codes', 'Supervisor regenerou códigos de backup', ?)
            ");
            $stmt->execute([
                $attendant_id,
                json_encode([
                    'supervisor_id' => $supervisor_id,
                    'timestamp' => date('Y-m-d H:i:s')
                ])
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Códigos de backup regenerados com sucesso!',
                'data' => [
                    'backup_codes' => $backupCodes
                ]
            ]);
            break;
            
        case 'get_2fa_status':
            // ===== OBTER STATUS DO 2FA =====
            echo json_encode([
                'success' => true,
                'data' => [
                    'enabled' => (bool)$attendant['two_factor_enabled'],
                    'forced_by_supervisor' => (bool)($attendant['two_factor_enabled_by_supervisor'] ?? 0),
                    'has_backup_codes' => !empty($attendant['backup_codes'])
                ]
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Ação inválida.'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro no gerenciamento de 2FA: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar solicitação: ' . $e->getMessage()
    ]);
}
?>
