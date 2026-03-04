<?php
// Iniciar sessão
if (!isset($_SESSION)) {
    session_start();
}

$page_title = 'VoIP - Configurações';
require_once 'includes/header_spa.php';
require_once 'includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Buscar configurações do usuário
$stmt = $pdo->prepare("
    SELECT * FROM voip_user_settings 
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Se não existir, criar com valores padrão
if (!$settings) {
    $settings = [
        'single_call_mode' => 1,
        'ring_device' => 'default',
        'speaker_device' => 'default',
        'microphone_device' => 'default',
        'mic_adjustment' => 1,
        'enabled_codecs' => 'PCMU,PCMA',
        'video_enabled' => 0,
        'video_codec' => 'H264',
        'video_bitrate' => 256,
        'source_port' => 5060,
        'dns_srv' => 0,
        'stun_server' => 'stun:stun.l.google.com:19302',
        'dtmf_method' => 'rfc2833',
        'call_recording' => 0,
        'recording_format' => 'wav',
        'recording_path' => '',
        'deny_incoming' => 'disabled',
        'call_forwarding' => 'disabled',
        'forwarding_number' => '',
        'auto_answer' => 0,
        'auto_answer_delay' => 0,
        'check_updates' => 'weekly',
        'run_on_startup' => 0
    ];
}

// Codecs disponíveis
$availableCodecs = [
    'opus/48000' => 'Opus 48 kHz',
    'opus/24000' => 'Opus 24 kHz',
    'G722/16000' => 'G.722 16 kHz',
    'PCMU/8000' => 'G.711 μ-law 8 kHz',
    'PCMA/8000' => 'G.711 A-law 8 kHz',
    'GSM/8000' => 'GSM 8 kHz',
    'iLBC/8000' => 'iLBC 8 kHz',
    'speex/16000' => 'Speex 16 kHz',
    'speex/8000' => 'Speex 8 kHz'
];

$enabledCodecs = explode(',', $settings['enabled_codecs']);
?>

<link rel="stylesheet" href="/assets/css/voip-settings.css?v=<?php echo time(); ?>">

<div class="voip-settings-dialog">
    <!-- Header -->
    <div class="voip-dialog-header">
        <h3>Configurações VoIP</h3>
        <button class="voip-close-btn" onclick="closeDialog()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Tabs -->
    <div class="voip-settings-tabs">
        <button class="voip-tab-btn active" onclick="switchTab('general')">
            <i class="fas fa-cog"></i>
            Geral
        </button>
        <button class="voip-tab-btn" onclick="switchTab('audio')">
            <i class="fas fa-volume-up"></i>
            Áudio
        </button>
        <button class="voip-tab-btn" onclick="switchTab('video')">
            <i class="fas fa-video"></i>
            Vídeo
        </button>
        <button class="voip-tab-btn" onclick="switchTab('network')">
            <i class="fas fa-network-wired"></i>
            Rede
        </button>
        <button class="voip-tab-btn" onclick="switchTab('advanced')">
            <i class="fas fa-sliders-h"></i>
            Avançado
        </button>
    </div>

    <!-- Content -->
    <div class="voip-dialog-content">
        <form id="voip-settings-form" onsubmit="saveSettings(event)">
            <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

            <!-- Tab: General -->
            <div class="voip-tab-content active" id="tab-general">
                <h4>Configurações Gerais</h4>

                <!-- Single Call Mode -->
                <div class="voip-form-group voip-checkbox-group">
                    <label>
                        <input type="checkbox" 
                               name="single_call_mode" 
                               value="1"
                               <?php echo $settings['single_call_mode'] ? 'checked' : ''; ?>>
                        Single Call Mode
                        <a href="#" class="voip-help-link" title="Permitir apenas uma chamada por vez">?</a>
                    </label>
                </div>

                <!-- Ring Device -->
                <div class="voip-form-group">
                    <label for="ring_device">
                        Ring Device
                        <a href="#" class="voip-help-link" title="Dispositivo para tocar">?</a>
                    </label>
                    <select id="ring_device" name="ring_device">
                        <option value="default">Default</option>
                        <!-- Dispositivos serão carregados via JavaScript -->
                    </select>
                </div>

                <!-- Speaker -->
                <div class="voip-form-group voip-checkbox-group">
                    <label>
                        <input type="checkbox" 
                               name="speaker_enabled" 
                               value="1"
                               checked>
                        Speaker
                    </label>
                </div>

                <div class="voip-form-group">
                    <label for="speaker_device">Speaker Device</label>
                    <select id="speaker_device" name="speaker_device">
                        <option value="default">Default</option>
                    </select>
                </div>

                <!-- Microphone -->
                <div class="voip-form-group">
                    <label for="microphone_device">
                        Microphone
                        <a href="#" class="voip-help-link" title="Dispositivo de entrada de áudio">?</a>
                    </label>
                    <select id="microphone_device" name="microphone_device">
                        <option value="default">Default</option>
                    </select>
                </div>

                <!-- Microphone Adjustment -->
                <div class="voip-form-group voip-checkbox-group">
                    <label>
                        <input type="checkbox" 
                               name="mic_adjustment" 
                               value="1"
                               <?php echo $settings['mic_adjustment'] ? 'checked' : ''; ?>>
                        Microphone Adjustment
                        <a href="#" class="voip-help-link" title="Ajuste automático de ganho">?</a>
                    </label>
                </div>

                <!-- Auto Answer -->
                <div class="voip-form-group voip-checkbox-group">
                    <label>
                        <input type="checkbox" 
                               name="auto_answer" 
                               value="1"
                               <?php echo $settings['auto_answer'] ? 'checked' : ''; ?>>
                        Auto Answer
                        <a href="#" class="voip-help-link" title="Atender automaticamente">?</a>
                    </label>
                </div>

                <div class="voip-form-group" id="auto-answer-delay-group" style="display: <?php echo $settings['auto_answer'] ? 'block' : 'none'; ?>;">
                    <label for="auto_answer_delay">Delay (seconds)</label>
                    <input type="number" 
                           id="auto_answer_delay" 
                           name="auto_answer_delay" 
                           value="<?php echo $settings['auto_answer_delay']; ?>"
                           min="0"
                           max="30">
                </div>
            </div>

            <!-- Tab: Audio -->
            <div class="voip-tab-content" id="tab-audio">
                <h4>Codecs de Áudio</h4>

                <div class="voip-codec-selector">
                    <div class="voip-codec-column">
                        <label>Available Codecs</label>
                        <select id="available-codecs" multiple size="10">
                            <?php foreach ($availableCodecs as $codec => $name): ?>
                                <?php if (!in_array($codec, $enabledCodecs)): ?>
                                    <option value="<?php echo $codec; ?>"><?php echo $name; ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="voip-codec-buttons">
                        <button type="button" onclick="moveCodecUp()" title="Mover para cima">
                            <i class="fas fa-chevron-up"></i>
                        </button>
                        <button type="button" onclick="moveCodecDown()" title="Mover para baixo">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <button type="button" onclick="addCodec()" title="Adicionar">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button type="button" onclick="removeCodec()" title="Remover">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>

                    <div class="voip-codec-column">
                        <label>Enabled Codecs</label>
                        <select id="enabled-codecs" name="enabled_codecs[]" multiple size="10">
                            <?php foreach ($enabledCodecs as $codec): ?>
                                <?php if (isset($availableCodecs[$codec])): ?>
                                    <option value="<?php echo $codec; ?>"><?php echo $availableCodecs[$codec]; ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <p class="voip-help-text">
                    <i class="fas fa-info-circle"></i>
                    A ordem dos codecs determina a prioridade na negociação.
                </p>
            </div>

            <!-- Tab: Video -->
            <div class="voip-tab-content" id="tab-video">
                <h4>Configurações de Vídeo</h4>

                <!-- Video Enabled -->
                <div class="voip-form-group voip-checkbox-group">
                    <label>
                        <input type="checkbox" 
                               name="video_enabled" 
                               value="1"
                               <?php echo $settings['video_enabled'] ? 'checked' : ''; ?>>
                        Enable Video
                    </label>
                </div>

                <div id="video-settings" style="display: <?php echo $settings['video_enabled'] ? 'block' : 'none'; ?>;">
                    <!-- Camera -->
                    <div class="voip-form-group">
                        <label for="camera_device">Camera</label>
                        <select id="camera_device" name="camera_device">
                            <option value="default">Default</option>
                        </select>
                    </div>

                    <!-- Video Codec -->
                    <div class="voip-form-group">
                        <label for="video_codec">Video Codec</label>
                        <select id="video_codec" name="video_codec">
                            <option value="H264" <?php echo $settings['video_codec'] == 'H264' ? 'selected' : ''; ?>>H.264</option>
                            <option value="VP8" <?php echo $settings['video_codec'] == 'VP8' ? 'selected' : ''; ?>>VP8</option>
                            <option value="VP9" <?php echo $settings['video_codec'] == 'VP9' ? 'selected' : ''; ?>>VP9</option>
                        </select>
                    </div>

                    <!-- Video Bitrate -->
                    <div class="voip-form-group">
                        <label for="video_bitrate">Video Bitrate (kbps)</label>
                        <input type="number" 
                               id="video_bitrate" 
                               name="video_bitrate" 
                               value="<?php echo $settings['video_bitrate']; ?>"
                               min="64"
                               max="2048"
                               step="64">
                    </div>
                </div>
            </div>

            <!-- Tab: Network -->
            <div class="voip-tab-content" id="tab-network">
                <h4>Configurações de Rede</h4>

                <!-- Source Port -->
                <div class="voip-form-group">
                    <label for="source_port">
                        Source Port
                        <a href="#" class="voip-help-link" title="Porta local SIP">?</a>
                    </label>
                    <input type="number" 
                           id="source_port" 
                           name="source_port" 
                           value="<?php echo $settings['source_port']; ?>"
                           min="1024"
                           max="65535">
                </div>

                <!-- DNS SRV -->
                <div class="voip-form-group voip-checkbox-group">
                    <label>
                        <input type="checkbox" 
                               name="dns_srv" 
                               value="1"
                               <?php echo $settings['dns_srv'] ? 'checked' : ''; ?>>
                        DNS SRV
                        <a href="#" class="voip-help-link" title="Usar registros DNS SRV">?</a>
                    </label>
                </div>

                <!-- STUN Server -->
                <div class="voip-form-group">
                    <label for="stun_server">
                        STUN Server
                        <a href="#" class="voip-help-link" title="Servidor STUN para NAT traversal">?</a>
                    </label>
                    <input type="text" 
                           id="stun_server" 
                           name="stun_server" 
                           value="<?php echo htmlspecialchars($settings['stun_server']); ?>"
                           placeholder="stun:stun.l.google.com:19302">
                </div>

                <!-- DTMF Method -->
                <div class="voip-form-group">
                    <label for="dtmf_method">
                        DTMF Method
                        <a href="#" class="voip-help-link" title="Método de envio de DTMF">?</a>
                    </label>
                    <select id="dtmf_method" name="dtmf_method">
                        <option value="rfc2833" <?php echo $settings['dtmf_method'] == 'rfc2833' ? 'selected' : ''; ?>>RFC 2833</option>
                        <option value="inband" <?php echo $settings['dtmf_method'] == 'inband' ? 'selected' : ''; ?>>In-band</option>
                        <option value="info" <?php echo $settings['dtmf_method'] == 'info' ? 'selected' : ''; ?>>SIP INFO</option>
                    </select>
                </div>
            </div>

            <!-- Tab: Advanced -->
            <div class="voip-tab-content" id="tab-advanced">
                <h4>Configurações Avançadas</h4>

                <!-- Call Recording -->
                <div class="voip-form-group voip-checkbox-group">
                    <label>
                        <input type="checkbox" 
                               name="call_recording" 
                               value="1"
                               <?php echo $settings['call_recording'] ? 'checked' : ''; ?>>
                        Call Recording
                        <a href="#" class="voip-help-link" title="Gravar chamadas automaticamente">?</a>
                    </label>
                </div>

                <div id="recording-settings" style="display: <?php echo $settings['call_recording'] ? 'block' : 'none'; ?>;">
                    <div class="voip-form-group">
                        <label for="recording_format">Recording Format</label>
                        <select id="recording_format" name="recording_format">
                            <option value="wav" <?php echo $settings['recording_format'] == 'wav' ? 'selected' : ''; ?>>WAV</option>
                            <option value="mp3" <?php echo $settings['recording_format'] == 'mp3' ? 'selected' : ''; ?>>MP3</option>
                            <option value="ogg" <?php echo $settings['recording_format'] == 'ogg' ? 'selected' : ''; ?>>OGG</option>
                        </select>
                    </div>

                    <div class="voip-form-group">
                        <label for="recording_path">Recording Path</label>
                        <div class="voip-input-with-button">
                            <input type="text" 
                                   id="recording_path" 
                                   name="recording_path" 
                                   value="<?php echo htmlspecialchars($settings['recording_path']); ?>"
                                   placeholder="/var/recordings">
                            <button type="button" onclick="browseFolder()">Browse</button>
                        </div>
                    </div>
                </div>

                <!-- Deny Incoming -->
                <div class="voip-form-group">
                    <label for="deny_incoming">
                        Deny Incoming
                        <a href="#" class="voip-help-link" title="Bloquear chamadas recebidas">?</a>
                    </label>
                    <select id="deny_incoming" name="deny_incoming">
                        <option value="disabled" <?php echo $settings['deny_incoming'] == 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                        <option value="all" <?php echo $settings['deny_incoming'] == 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="anonymous" <?php echo $settings['deny_incoming'] == 'anonymous' ? 'selected' : ''; ?>>Anonymous</option>
                    </select>
                </div>

                <!-- Call Forwarding -->
                <div class="voip-form-group">
                    <label for="call_forwarding">
                        Call Forwarding
                        <a href="#" class="voip-help-link" title="Encaminhamento de chamadas">?</a>
                    </label>
                    <select id="call_forwarding" name="call_forwarding">
                        <option value="disabled" <?php echo $settings['call_forwarding'] == 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                        <option value="always" <?php echo $settings['call_forwarding'] == 'always' ? 'selected' : ''; ?>>Always</option>
                        <option value="busy" <?php echo $settings['call_forwarding'] == 'busy' ? 'selected' : ''; ?>>On Busy</option>
                        <option value="no_answer" <?php echo $settings['call_forwarding'] == 'no_answer' ? 'selected' : ''; ?>>On No Answer</option>
                    </select>
                </div>

                <div class="voip-form-group" id="forwarding-number-group" style="display: <?php echo $settings['call_forwarding'] != 'disabled' ? 'block' : 'none'; ?>;">
                    <label for="forwarding_number">Forwarding Number</label>
                    <input type="text" 
                           id="forwarding_number" 
                           name="forwarding_number" 
                           value="<?php echo htmlspecialchars($settings['forwarding_number']); ?>"
                           placeholder="+5511999999999">
                </div>

                <!-- Check for Updates -->
                <div class="voip-form-group">
                    <label for="check_updates">Check for Updates</label>
                    <select id="check_updates" name="check_updates">
                        <option value="weekly" <?php echo $settings['check_updates'] == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo $settings['check_updates'] == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="never" <?php echo $settings['check_updates'] == 'never' ? 'selected' : ''; ?>>Never</option>
                    </select>
                </div>

                <!-- Run on Startup -->
                <div class="voip-form-group voip-checkbox-group">
                    <label>
                        <input type="checkbox" 
                               name="run_on_startup" 
                               value="1"
                               <?php echo $settings['run_on_startup'] ? 'checked' : ''; ?>>
                        Run on System Startup
                    </label>
                </div>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <div class="voip-dialog-footer">
        <button type="button" class="voip-btn-secondary" onclick="closeDialog()">
            Cancel
        </button>
        <button type="submit" form="voip-settings-form" class="voip-btn-primary">
            Save
        </button>
    </div>
</div>

<script src="/assets/js/voip-settings.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer_spa.php'; ?>
