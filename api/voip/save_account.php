<?php
/**
 * API: Salvar Conta VoIP
 * Salva ou atualiza configurações de conta SIP
 */

header('Content-Type: application/json');

// Iniciar sessão
if (!isset($_SESSION)) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];

try {
    // Validar dados recebidos
    $accountId = $_POST['account_id'] ?? null;
    $accountName = trim($_POST['account_name'] ?? '');
    $sipServer = trim($_POST['sip_server'] ?? '');
    $sipProxy = trim($_POST['sip_proxy'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');
    $voicemailNumber = trim($_POST['voicemail_number'] ?? '');
    $dialingProfile = $_POST['dialing_profile'] ?? '';
    $dialPlan = trim($_POST['dial_plan'] ?? '');
    $srtp = intval($_POST['srtp'] ?? 0);
    $transport = $_POST['transport'] ?? 'udp';
    $publicAddress = $_POST['public_address'] ?? 'auto';
    $publicAddressManual = trim($_POST['public_address_manual'] ?? '');
    $registerRefresh = intval($_POST['register_refresh'] ?? 360);
    $hideCallerId = isset($_POST['hide_caller_id']) ? 1 : 0;
    $publishPresence = isset($_POST['publish_presence']) ? 1 : 0;
    $allowIpRewrite = isset($_POST['allow_ip_rewrite']) ? 1 : 0;
    $ice = isset($_POST['ice']) ? 1 : 0;
    $disableSessionTimers = isset($_POST['disable_session_timers']) ? 1 : 0;
    
    // Validações
    if (empty($accountName)) {
        throw new Exception('Nome da conta é obrigatório');
    }
    
    if (empty($sipServer)) {
        throw new Exception('Servidor SIP é obrigatório');
    }
    
    if (empty($username)) {
        throw new Exception('Username é obrigatório');
    }
    
    if (empty($domain)) {
        throw new Exception('Domínio é obrigatório');
    }
    
    if (empty($transport)) {
        throw new Exception('Transporte é obrigatório');
    }
    
    // Se for nova conta, senha é obrigatória
    if (!$accountId && empty($password)) {
        throw new Exception('Senha é obrigatória para nova conta');
    }
    
    // Validar formato de domínio
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?(\.[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?)*$/', $domain)) {
        throw new Exception('Formato de domínio inválido');
    }
    
    // Se login não fornecido, usar username
    if (empty($login)) {
        $login = $username;
    }
    
    // Gerar extension se não existir
    $extension = $username;
    
    // Hash da senha se fornecida
    $passwordHash = null;
    if (!empty($password) && $password !== '********') {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    }
    
    $pdo->beginTransaction();
    
    if ($accountId) {
        // Atualizar conta existente
        
        // Verificar se pertence ao usuário
        $stmt = $pdo->prepare("SELECT id FROM voip_users WHERE id = ? AND user_id = ?");
        $stmt->execute([$accountId, $userId]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Conta não encontrada ou sem permissão');
        }
        
        // Atualizar
        if ($passwordHash) {
            $stmt = $pdo->prepare("
                UPDATE voip_users SET
                    account_name = ?,
                    extension = ?,
                    sip_username = ?,
                    sip_password = ?,
                    sip_domain = ?,
                    sip_server = ?,
                    sip_proxy = ?,
                    auth_id = ?,
                    display_name = ?,
                    voicemail_number = ?,
                    dialing_profile = ?,
                    dial_plan = ?,
                    srtp = ?,
                    transport = ?,
                    public_address = ?,
                    public_address_manual = ?,
                    register_refresh = ?,
                    hide_caller_id = ?,
                    publish_presence = ?,
                    allow_ip_rewrite = ?,
                    ice = ?,
                    disable_session_timers = ?,
                    updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $accountName, $extension, $username, $passwordHash, $domain,
                $sipServer, $sipProxy, $login, $displayName, $voicemailNumber,
                $dialingProfile, $dialPlan, $srtp, $transport, $publicAddress,
                $publicAddressManual, $registerRefresh, $hideCallerId, $publishPresence,
                $allowIpRewrite, $ice, $disableSessionTimers,
                $accountId, $userId
            ]);
        } else {
            // Atualizar sem mudar senha
            $stmt = $pdo->prepare("
                UPDATE voip_users SET
                    account_name = ?,
                    extension = ?,
                    sip_username = ?,
                    sip_domain = ?,
                    sip_server = ?,
                    sip_proxy = ?,
                    auth_id = ?,
                    display_name = ?,
                    voicemail_number = ?,
                    dialing_profile = ?,
                    dial_plan = ?,
                    srtp = ?,
                    transport = ?,
                    public_address = ?,
                    public_address_manual = ?,
                    register_refresh = ?,
                    hide_caller_id = ?,
                    publish_presence = ?,
                    allow_ip_rewrite = ?,
                    ice = ?,
                    disable_session_timers = ?,
                    updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $accountName, $extension, $username, $domain,
                $sipServer, $sipProxy, $login, $displayName, $voicemailNumber,
                $dialingProfile, $dialPlan, $srtp, $transport, $publicAddress,
                $publicAddressManual, $registerRefresh, $hideCallerId, $publishPresence,
                $allowIpRewrite, $ice, $disableSessionTimers,
                $accountId, $userId
            ]);
        }
        
        $message = 'Conta atualizada com sucesso';
        
    } else {
        // Criar nova conta
        
        // Verificar se já existe conta para este usuário
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM voip_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            throw new Exception('Usuário já possui uma conta VoIP. Edite a conta existente.');
        }
        
        // Inserir
        $stmt = $pdo->prepare("
            INSERT INTO voip_users (
                user_id, account_name, extension, sip_username, sip_password,
                sip_domain, sip_server, sip_proxy, auth_id, display_name,
                voicemail_number, dialing_profile, dial_plan, srtp, transport,
                public_address, public_address_manual, register_refresh,
                hide_caller_id, publish_presence, allow_ip_rewrite, ice,
                disable_session_timers, status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW()
            )
        ");
        $stmt->execute([
            $userId, $accountName, $extension, $username, $passwordHash,
            $domain, $sipServer, $sipProxy, $login, $displayName,
            $voicemailNumber, $dialingProfile, $dialPlan, $srtp, $transport,
            $publicAddress, $publicAddressManual, $registerRefresh,
            $hideCallerId, $publishPresence, $allowIpRewrite, $ice,
            $disableSessionTimers
        ]);
        
        $accountId = $pdo->lastInsertId();
        $message = 'Conta criada com sucesso';
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'account_id' => $accountId
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
