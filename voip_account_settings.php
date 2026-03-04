<?php
// Iniciar sessão
if (!isset($_SESSION)) {
    session_start();
}

$page_title = 'VoIP - Configurações de Conta';
require_once 'includes/header_spa.php';
require_once 'includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Buscar configurações VoIP do usuário (se existir)
$voipUser = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM voip_users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $voipUser = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar conta VoIP: " . $e->getMessage());
}

// Se for edição, carregar dados
$accountId = $_GET['id'] ?? null;
$account = null;
if ($accountId && $voipUser && $voipUser['id'] == $accountId) {
    $account = $voipUser;
} elseif ($voipUser) {
    $account = $voipUser;
}
?>

<link rel="stylesheet" href="/assets/css/voip-account-settings.css?v=<?php echo time(); ?>">

<div class="voip-account-dialog">
    <!-- Header -->
    <div class="voip-dialog-header">
        <h3><?php echo $account ? 'Editar Conta VoIP' : 'Nova Conta VoIP'; ?></h3>
        <button class="voip-close-btn" onclick="closeDialog()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Content -->
    <div class="voip-dialog-content">
        <form id="voip-account-form" onsubmit="saveAccount(event)">
            <input type="hidden" name="account_id" value="<?php echo $account['id'] ?? ''; ?>">
            <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

            <!-- Account Name -->
            <div class="voip-form-group">
                <label for="account_name">
                    Account Name
                    <span class="voip-required">*</span>
                </label>
                <input type="text" 
                       id="account_name" 
                       name="account_name" 
                       value="<?php echo htmlspecialchars($account['account_name'] ?? 'Conta Principal'); ?>"
                       placeholder="Minha Conta VoIP"
                       required>
            </div>

            <!-- SIP Server -->
            <div class="voip-form-group">
                <label for="sip_server">
                    SIP Server
                    <span class="voip-required">*</span>
                    <a href="#" class="voip-help-link" title="Endereço do servidor SIP">?</a>
                </label>
                <input type="text" 
                       id="sip_server" 
                       name="sip_server" 
                       value="<?php echo htmlspecialchars($providerSettings['server_host'] ?? 'voip.macip.com.br'); ?>"
                       placeholder="voip.macip.com.br"
                       required>
            </div>

            <!-- SIP Proxy -->
            <div class="voip-form-group">
                <label for="sip_proxy">
                    SIP Proxy
                    <a href="#" class="voip-help-link" title="Servidor proxy SIP (opcional)">?</a>
                </label>
                <input type="text" 
                       id="sip_proxy" 
                       name="sip_proxy" 
                       value="<?php echo htmlspecialchars($account['sip_proxy'] ?? ''); ?>"
                       placeholder="proxy.voip.com.br">
            </div>

            <!-- Username -->
            <div class="voip-form-group">
                <label for="username">
                    Username
                    <span class="voip-required">*</span>
                    <a href="#" class="voip-help-link" title="Nome de usuário SIP">?</a>
                </label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       value="<?php echo htmlspecialchars($account['sip_username'] ?? ''); ?>"
                       placeholder="1001"
                       required>
            </div>

            <!-- Domain -->
            <div class="voip-form-group">
                <label for="domain">
                    Domain
                    <span class="voip-required">*</span>
                    <a href="#" class="voip-help-link" title="Domínio SIP">?</a>
                </label>
                <input type="text" 
                       id="domain" 
                       name="domain" 
                       value="<?php echo htmlspecialchars($account['sip_domain'] ?? $providerSettings['sip_domain'] ?? 'voip.macip.com.br'); ?>"
                       placeholder="voip.macip.com.br"
                       required>
            </div>

            <!-- Login (Auth ID) -->
            <div class="voip-form-group">
                <label for="login">
                    Login
                    <a href="#" class="voip-help-link" title="ID de autenticação (geralmente igual ao username)">?</a>
                </label>
                <input type="text" 
                       id="login" 
                       name="login" 
                       value="<?php echo htmlspecialchars($account['auth_id'] ?? ''); ?>"
                       placeholder="1001">
            </div>

            <!-- Password -->
            <div class="voip-form-group">
                <label for="password">
                    Password
                    <span class="voip-required">*</span>
                </label>
                <div class="voip-password-field">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           value="<?php echo $account ? '********' : ''; ?>"
                           placeholder="••••••••"
                           <?php echo $account ? '' : 'required'; ?>>
                    <button type="button" 
                            class="voip-toggle-password" 
                            onclick="togglePasswordVisibility()"
                            title="Mostrar/Ocultar senha">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <a href="#" class="voip-link-small" onclick="togglePasswordVisibility()">display password</a>
            </div>

            <!-- Display Name -->
            <div class="voip-form-group">
                <label for="display_name">
                    Display Name
                    <a href="#" class="voip-help-link" title="Nome exibido nas chamadas">?</a>
                </label>
                <input type="text" 
                       id="display_name" 
                       name="display_name" 
                       value="<?php echo htmlspecialchars($account['display_name'] ?? $_SESSION['user_name'] ?? ''); ?>"
                       placeholder="João Silva">
            </div>

            <!-- Voicemail Number -->
            <div class="voip-form-group">
                <label for="voicemail_number">
                    Voicemail Number
                    <a href="#" class="voip-help-link" title="Número para acessar correio de voz">?</a>
                </label>
                <input type="text" 
                       id="voicemail_number" 
                       name="voicemail_number" 
                       value="<?php echo htmlspecialchars($account['voicemail_number'] ?? '*97'); ?>"
                       placeholder="*97">
            </div>

            <!-- Dialing Profile -->
            <div class="voip-form-group">
                <label for="dialing_profile">
                    Dialing Profile
                    <a href="#" class="voip-help-link" title="Perfil de discagem">?</a>
                </label>
                <select id="dialing_profile" name="dialing_profile">
                    <option value="">Default</option>
                    <option value="brazil">Brasil (+55)</option>
                    <option value="usa">USA (+1)</option>
                    <option value="portugal">Portugal (+351)</option>
                </select>
            </div>

            <!-- Dial Plan -->
            <div class="voip-form-group">
                <label for="dial_plan">
                    Dial Plan
                    <a href="#" class="voip-help-link" title="Plano de discagem (regex)">?</a>
                </label>
                <input type="text" 
                       id="dial_plan" 
                       name="dial_plan" 
                       value="<?php echo htmlspecialchars($account['dial_plan'] ?? ''); ?>"
                       placeholder="^[0-9]{10,11}$">
            </div>

            <!-- Media Encryption (SRTP) -->
            <div class="voip-form-group">
                <label for="srtp">
                    Media Encryption
                    <a href="#" class="voip-help-link" title="Criptografia de mídia SRTP">?</a>
                </label>
                <select id="srtp" name="srtp">
                    <option value="0" <?php echo ($account['srtp'] ?? 0) == 0 ? 'selected' : ''; ?>>Disabled</option>
                    <option value="1" <?php echo ($account['srtp'] ?? 0) == 1 ? 'selected' : ''; ?>>Optional SRTP (RTP/AVP)</option>
                    <option value="2" <?php echo ($account['srtp'] ?? 0) == 2 ? 'selected' : ''; ?>>Mandatory SRTP (RTP/SAVP)</option>
                    <option value="3" <?php echo ($account['srtp'] ?? 0) == 3 ? 'selected' : ''; ?>>DTLS-SRTP/SRTP</option>
                    <option value="4" <?php echo ($account['srtp'] ?? 0) == 4 ? 'selected' : ''; ?>>DTLS-SRTP</option>
                </select>
            </div>

            <!-- Transport -->
            <div class="voip-form-group">
                <label for="transport">
                    Transport
                    <span class="voip-required">*</span>
                    <a href="#" class="voip-help-link" title="Protocolo de transporte">?</a>
                </label>
                <select id="transport" name="transport" required>
                    <option value="udp" <?php echo ($account['transport'] ?? 'udp') == 'udp' ? 'selected' : ''; ?>>UDP</option>
                    <option value="tcp" <?php echo ($account['transport'] ?? '') == 'tcp' ? 'selected' : ''; ?>>TCP</option>
                    <option value="udp+tcp" <?php echo ($account['transport'] ?? '') == 'udp+tcp' ? 'selected' : ''; ?>>UDP+TCP</option>
                    <option value="tls" <?php echo ($account['transport'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                </select>
            </div>

            <!-- Public Address -->
            <div class="voip-form-group">
                <label for="public_address">
                    Public Address
                    <a href="#" class="voip-help-link" title="Endereço público (NAT)">?</a>
                </label>
                <select id="public_address" name="public_address">
                    <option value="auto" <?php echo ($account['public_address'] ?? 'auto') == 'auto' ? 'selected' : ''; ?>>Auto</option>
                    <option value="manual" <?php echo ($account['public_address'] ?? '') == 'manual' ? 'selected' : ''; ?>>Manual</option>
                </select>
                <input type="text" 
                       id="public_address_manual" 
                       name="public_address_manual" 
                       value="<?php echo htmlspecialchars($account['public_address_manual'] ?? ''); ?>"
                       placeholder="203.0.113.1"
                       style="margin-top: 8px; display: none;">
            </div>

            <!-- Register Refresh -->
            <div class="voip-form-group">
                <label for="register_refresh">
                    Register Refresh (seconds)
                    <a href="#" class="voip-help-link" title="Intervalo de renovação de registro">?</a>
                </label>
                <input type="number" 
                       id="register_refresh" 
                       name="register_refresh" 
                       value="<?php echo $account['register_refresh'] ?? 360; ?>"
                       min="60"
                       max="3600"
                       step="60">
            </div>

            <!-- Checkboxes -->
            <div class="voip-form-group voip-checkbox-group">
                <label>
                    <input type="checkbox" 
                           name="hide_caller_id" 
                           value="1"
                           <?php echo ($account['hide_caller_id'] ?? 0) ? 'checked' : ''; ?>>
                    Hide Caller ID
                    <a href="#" class="voip-help-link" title="Ocultar identificador de chamadas">?</a>
                </label>
            </div>

            <div class="voip-form-group voip-checkbox-group">
                <label>
                    <input type="checkbox" 
                           name="publish_presence" 
                           value="1"
                           <?php echo ($account['publish_presence'] ?? 1) ? 'checked' : ''; ?>>
                    Publish Presence
                    <a href="#" class="voip-help-link" title="Publicar status de presença">?</a>
                </label>
            </div>

            <div class="voip-form-group voip-checkbox-group">
                <label>
                    <input type="checkbox" 
                           name="allow_ip_rewrite" 
                           value="1"
                           <?php echo ($account['allow_ip_rewrite'] ?? 0) ? 'checked' : ''; ?>>
                    Allow IP Rewrite
                    <a href="#" class="voip-help-link" title="Permitir reescrita de IP">?</a>
                </label>
            </div>

            <div class="voip-form-group voip-checkbox-group">
                <label>
                    <input type="checkbox" 
                           name="ice" 
                           value="1"
                           <?php echo ($account['ice'] ?? 1) ? 'checked' : ''; ?>>
                    ICE
                    <a href="#" class="voip-help-link" title="Interactive Connectivity Establishment">?</a>
                </label>
            </div>

            <div class="voip-form-group voip-checkbox-group">
                <label>
                    <input type="checkbox" 
                           name="disable_session_timers" 
                           value="1"
                           <?php echo ($account['disable_session_timers'] ?? 0) ? 'checked' : ''; ?>>
                    Disable Session Timers
                    <a href="#" class="voip-help-link" title="Desabilitar temporizadores de sessão">?</a>
                </label>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <div class="voip-dialog-footer">
        <?php if ($account): ?>
            <a href="#" class="voip-link-danger" onclick="deleteAccount(<?php echo $account['id']; ?>)">
                Delete Account
            </a>
        <?php endif; ?>
        <div class="voip-footer-buttons">
            <button type="button" class="voip-btn-secondary" onclick="closeDialog()">
                Cancel
            </button>
            <button type="submit" form="voip-account-form" class="voip-btn-primary">
                Save
            </button>
        </div>
    </div>
</div>

<script src="/assets/js/voip-account-settings.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer_spa.php'; ?>
