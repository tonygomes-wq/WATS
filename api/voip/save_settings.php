<?php
/**
 * API: Salvar Configurações VoIP
 * Salva configurações gerais do sistema VoIP
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
    // Coletar dados do formulário
    $singleCallMode = isset($_POST['single_call_mode']) ? 1 : 0;
    $ringDevice = $_POST['ring_device'] ?? 'default';
    $speakerDevice = $_POST['speaker_device'] ?? 'default';
    $microphoneDevice = $_POST['microphone_device'] ?? 'default';
    $micAdjustment = isset($_POST['mic_adjustment']) ? 1 : 0;
    $enabledCodecs = $_POST['enabled_codecs'] ?? 'PCMU,PCMA';
    $videoEnabled = isset($_POST['video_enabled']) ? 1 : 0;
    $cameraDevice = $_POST['camera_device'] ?? 'default';
    $videoCodec = $_POST['video_codec'] ?? 'H264';
    $videoBitrate = intval($_POST['video_bitrate'] ?? 256);
    $sourcePort = intval($_POST['source_port'] ?? 5060);
    $dnsSrv = isset($_POST['dns_srv']) ? 1 : 0;
    $stunServer = trim($_POST['stun_server'] ?? 'stun:stun.l.google.com:19302');
    $dtmfMethod = $_POST['dtmf_method'] ?? 'rfc2833';
    $callRecording = isset($_POST['call_recording']) ? 1 : 0;
    $recordingFormat = $_POST['recording_format'] ?? 'wav';
    $recordingPath = trim($_POST['recording_path'] ?? '');
    $denyIncoming = $_POST['deny_incoming'] ?? 'disabled';
    $callForwarding = $_POST['call_forwarding'] ?? 'disabled';
    $forwardingNumber = trim($_POST['forwarding_number'] ?? '');
    $autoAnswer = isset($_POST['auto_answer']) ? 1 : 0;
    $autoAnswerDelay = intval($_POST['auto_answer_delay'] ?? 0);
    $checkUpdates = $_POST['check_updates'] ?? 'weekly';
    $runOnStartup = isset($_POST['run_on_startup']) ? 1 : 0;
    
    // Validações
    if ($sourcePort < 1024 || $sourcePort > 65535) {
        throw new Exception('Porta inválida (deve estar entre 1024 e 65535)');
    }
    
    if ($videoBitrate < 64 || $videoBitrate > 2048) {
        throw new Exception('Bitrate de vídeo inválido (deve estar entre 64 e 2048)');
    }
    
    if ($autoAnswerDelay < 0 || $autoAnswerDelay > 30) {
        throw new Exception('Delay de auto-resposta inválido (deve estar entre 0 e 30)');
    }
    
    // Verificar se já existe configuração
    $stmt = $pdo->prepare("SELECT id FROM voip_user_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Atualizar
        $stmt = $pdo->prepare("
            UPDATE voip_user_settings SET
                single_call_mode = ?,
                ring_device = ?,
                speaker_device = ?,
                microphone_device = ?,
                mic_adjustment = ?,
                enabled_codecs = ?,
                video_enabled = ?,
                camera_device = ?,
                video_codec = ?,
                video_bitrate = ?,
                source_port = ?,
                dns_srv = ?,
                stun_server = ?,
                dtmf_method = ?,
                call_recording = ?,
                recording_format = ?,
                recording_path = ?,
                deny_incoming = ?,
                call_forwarding = ?,
                forwarding_number = ?,
                auto_answer = ?,
                auto_answer_delay = ?,
                check_updates = ?,
                run_on_startup = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([
            $singleCallMode, $ringDevice, $speakerDevice, $microphoneDevice,
            $micAdjustment, $enabledCodecs, $videoEnabled, $cameraDevice,
            $videoCodec, $videoBitrate, $sourcePort, $dnsSrv, $stunServer,
            $dtmfMethod, $callRecording, $recordingFormat, $recordingPath,
            $denyIncoming, $callForwarding, $forwardingNumber, $autoAnswer,
            $autoAnswerDelay, $checkUpdates, $runOnStartup, $userId
        ]);
        
        $message = 'Configurações atualizadas com sucesso';
        
    } else {
        // Inserir
        $stmt = $pdo->prepare("
            INSERT INTO voip_user_settings (
                user_id, single_call_mode, ring_device, speaker_device,
                microphone_device, mic_adjustment, enabled_codecs, video_enabled,
                camera_device, video_codec, video_bitrate, source_port, dns_srv,
                stun_server, dtmf_method, call_recording, recording_format,
                recording_path, deny_incoming, call_forwarding, forwarding_number,
                auto_answer, auto_answer_delay, check_updates, run_on_startup,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )
        ");
        $stmt->execute([
            $userId, $singleCallMode, $ringDevice, $speakerDevice,
            $microphoneDevice, $micAdjustment, $enabledCodecs, $videoEnabled,
            $cameraDevice, $videoCodec, $videoBitrate, $sourcePort, $dnsSrv,
            $stunServer, $dtmfMethod, $callRecording, $recordingFormat,
            $recordingPath, $denyIncoming, $callForwarding, $forwardingNumber,
            $autoAnswer, $autoAnswerDelay, $checkUpdates, $runOnStartup
        ]);
        
        $message = 'Configurações salvas com sucesso';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
