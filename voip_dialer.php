<?php
// Iniciar sessão
if (!isset($_SESSION)) {
    session_start();
}

$page_title = 'VoIP - Discador';
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
?>

<link rel="stylesheet" href="/assets/css/voip-dialer.css?v=<?php echo time(); ?>">

<div class="voip-dialer-container">
    <!-- Header -->
    <div class="voip-dialer-header">
        <div class="voip-status-indicator">
            <i class="fas fa-circle" id="voip-status-dot"></i>
            <span id="voip-status-text">Desconectado</span>
        </div>
        <div class="voip-account-selector">
            <select id="voip-account-select" class="voip-select">
                <?php if ($voipUser): ?>
                    <option value="<?php echo $voipUser['id']; ?>" selected>
                        <?php echo htmlspecialchars($voipUser['extension']); ?> - 
                        <?php echo htmlspecialchars($voipUser['sip_domain']); ?>
                    </option>
                <?php else: ?>
                    <option value="">Nenhuma conta configurada</option>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <!-- Display do Número -->
    <div class="voip-number-display">
        <input type="text" 
               id="voip-number-input" 
               class="voip-number-input" 
               placeholder="Digite o número..."
               autocomplete="off">
        <button class="voip-clear-btn" onclick="clearNumber()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Nome do Contato (se encontrado) -->
    <div class="voip-contact-name" id="voip-contact-name" style="display: none;">
        <i class="fas fa-user"></i>
        <span id="voip-contact-name-text"></span>
    </div>

    <!-- Teclado Numérico -->
    <div class="voip-keypad">
        <div class="voip-keypad-row">
            <button class="voip-key" onclick="dialKey('1')">
                <span class="voip-key-number">1</span>
                <span class="voip-key-letters"></span>
            </button>
            <button class="voip-key" onclick="dialKey('2')">
                <span class="voip-key-number">2</span>
                <span class="voip-key-letters">ABC</span>
            </button>
            <button class="voip-key" onclick="dialKey('3')">
                <span class="voip-key-number">3</span>
                <span class="voip-key-letters">DEF</span>
            </button>
        </div>

        <div class="voip-keypad-row">
            <button class="voip-key" onclick="dialKey('4')">
                <span class="voip-key-number">4</span>
                <span class="voip-key-letters">GHI</span>
            </button>
            <button class="voip-key" onclick="dialKey('5')">
                <span class="voip-key-number">5</span>
                <span class="voip-key-letters">JKL</span>
            </button>
            <button class="voip-key" onclick="dialKey('6')">
                <span class="voip-key-number">6</span>
                <span class="voip-key-letters">MNO</span>
            </button>
        </div>

        <div class="voip-keypad-row">
            <button class="voip-key" onclick="dialKey('7')">
                <span class="voip-key-number">7</span>
                <span class="voip-key-letters">PQRS</span>
            </button>
            <button class="voip-key" onclick="dialKey('8')">
                <span class="voip-key-number">8</span>
                <span class="voip-key-letters">TUV</span>
            </button>
            <button class="voip-key" onclick="dialKey('9')">
                <span class="voip-key-number">9</span>
                <span class="voip-key-letters">WXYZ</span>
            </button>
        </div>

        <div class="voip-keypad-row">
            <button class="voip-key" onclick="dialKey('*')">
                <span class="voip-key-number">*</span>
                <span class="voip-key-letters"></span>
            </button>
            <button class="voip-key" onclick="dialKey('0')">
                <span class="voip-key-number">0</span>
                <span class="voip-key-letters">+</span>
            </button>
            <button class="voip-key" onclick="dialKey('#')">
                <span class="voip-key-number">#</span>
                <span class="voip-key-letters"></span>
            </button>
        </div>
    </div>

    <!-- Botões de Ação -->
    <div class="voip-action-buttons">
        <button class="voip-action-btn voip-btn-call" onclick="makeCall()" id="voip-btn-call">
            <i class="fas fa-phone"></i>
            <span>Ligar</span>
        </button>

        <button class="voip-action-btn voip-btn-video" onclick="makeVideoCall()" id="voip-btn-video" style="display: none;">
            <i class="fas fa-video"></i>
            <span>Vídeo</span>
        </button>

        <button class="voip-action-btn voip-btn-message" onclick="sendMessage()" id="voip-btn-message">
            <i class="fas fa-comment"></i>
            <span>Mensagem</span>
        </button>
    </div>

    <!-- Controles de Chamada (Ocultos inicialmente) -->
    <div class="voip-call-controls" id="voip-call-controls" style="display: none;">
        <div class="voip-call-info">
            <div class="voip-call-status">Em chamada</div>
            <div class="voip-call-timer" id="voip-call-timer">00:00</div>
        </div>

        <div class="voip-call-buttons">
            <button class="voip-control-btn" onclick="toggleMute()" id="voip-btn-mute" title="Mute">
                <i class="fas fa-microphone"></i>
            </button>

            <button class="voip-control-btn" onclick="toggleHold()" id="voip-btn-hold" title="Hold">
                <i class="fas fa-pause"></i>
            </button>

            <button class="voip-control-btn voip-btn-hangup" onclick="hangupCall()" title="Desligar">
                <i class="fas fa-phone-slash"></i>
            </button>

            <button class="voip-control-btn" onclick="openTransfer()" title="Transferir">
                <i class="fas fa-exchange-alt"></i>
            </button>
        </div>
    </div>

    <!-- Controles de Volume -->
    <div class="voip-volume-controls">
        <div class="voip-volume-group">
            <label>
                <i class="fas fa-microphone"></i>
                Microfone
            </label>
            <div class="voip-volume-slider">
                <button onclick="adjustVolume('input', -10)"><i class="fas fa-minus"></i></button>
                <input type="range" id="voip-input-volume" min="0" max="100" value="80" 
                       oninput="setVolume('input', this.value)">
                <button onclick="adjustVolume('input', 10)"><i class="fas fa-plus"></i></button>
            </div>
            <button class="voip-mute-btn" onclick="toggleMuteInput()" id="voip-mute-input">
                <i class="fas fa-microphone"></i>
            </button>
        </div>

        <div class="voip-volume-group">
            <label>
                <i class="fas fa-volume-up"></i>
                Alto-falante
            </label>
            <div class="voip-volume-slider">
                <button onclick="adjustVolume('output', -10)"><i class="fas fa-minus"></i></button>
                <input type="range" id="voip-output-volume" min="0" max="100" value="80" 
                       oninput="setVolume('output', this.value)">
                <button onclick="adjustVolume('output', 10)"><i class="fas fa-plus"></i></button>
            </div>
            <button class="voip-mute-btn" onclick="toggleMuteOutput()" id="voip-mute-output">
                <i class="fas fa-volume-up"></i>
            </button>
        </div>
    </div>

    <!-- Botões Inferiores (DND, FWD, AA, etc) -->
    <div class="voip-bottom-buttons">
        <button class="voip-bottom-btn" onclick="toggleDND()" id="voip-btn-dnd" title="Não Perturbe">
            <i class="fas fa-moon"></i>
            <span>DND</span>
        </button>

        <button class="voip-bottom-btn" onclick="toggleForwarding()" id="voip-btn-fwd" title="Encaminhar">
            <i class="fas fa-share"></i>
            <span>FWD</span>
        </button>

        <button class="voip-bottom-btn" onclick="toggleAutoAnswer()" id="voip-btn-aa" title="Atendimento Automático">
            <i class="fas fa-robot"></i>
            <span>AA</span>
        </button>

        <button class="voip-bottom-btn" onclick="toggleRecording()" id="voip-btn-rec" title="Gravar">
            <i class="fas fa-record-vinyl"></i>
            <span>REC</span>
        </button>

        <button class="voip-bottom-btn" onclick="openConference()" id="voip-btn-conf" title="Conferência">
            <i class="fas fa-users"></i>
            <span>CONF</span>
        </button>
    </div>

    <!-- Menu Inferior -->
    <div class="voip-menu-bar">
        <button class="voip-menu-btn active" onclick="openDialer()">
            <i class="fas fa-phone"></i>
            <span>Phone</span>
        </button>

        <button class="voip-menu-btn" onclick="openCalls()">
            <i class="fas fa-history"></i>
            <span>Calls</span>
        </button>

        <button class="voip-menu-btn" onclick="openContacts()">
            <i class="fas fa-address-book"></i>
            <span>Contacts</span>
        </button>

        <button class="voip-menu-btn" onclick="toggleSettingsMenu(event)">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </button>
    </div>

    <!-- Menu Dropdown de Configurações -->
    <div class="voip-settings-dropdown" id="voip-settings-dropdown" style="display: none;">
        <div class="voip-settings-menu">
            <button class="voip-settings-menu-item" onclick="openAccountSettings()">
                <i class="fas fa-user-cog"></i>
                <span>Configurar Conta SIP</span>
            </button>
            <button class="voip-settings-menu-item" onclick="openGeneralSettings()">
                <i class="fas fa-sliders-h"></i>
                <span>Configurações Gerais</span>
            </button>
            <button class="voip-settings-menu-item" onclick="openAudioSettings()">
                <i class="fas fa-volume-up"></i>
                <span>Áudio e Codecs</span>
            </button>
            <button class="voip-settings-menu-item" onclick="openNetworkSettings()">
                <i class="fas fa-network-wired"></i>
                <span>Rede e Firewall</span>
            </button>
        </div>
    </div>
</div>

<script src="/assets/js/voip-dialer.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer_spa.php'; ?>
