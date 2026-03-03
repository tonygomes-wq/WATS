<?php
/**
 * API: Testar Conexão VoIP
 * Testa a conexão com o servidor FreeSWITCH
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/voip/FreeSwitchAPI.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$userId = $_SESSION['user_id'];
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Apenas admin pode testar conexão
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Acesso negado. Apenas administradores podem testar a conexão.'
    ]);
    exit;
}

try {
    // Buscar configurações do provedor
    $stmt = $pdo->prepare("SELECT * FROM voip_provider_settings WHERE id = 1");
    $stmt->execute();
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$provider || empty($provider['server_host'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Provedor VoIP não configurado',
            'configured' => false
        ]);
        exit;
    }
    
    $tests = [];
    
    // Teste 1: Ping no servidor
    $tests['ping'] = [
        'name' => 'Ping no Servidor',
        'status' => 'testing'
    ];
    
    $pingResult = @fsockopen($provider['server_host'], $provider['wss_port'], $errno, $errstr, 5);
    
    if ($pingResult) {
        fclose($pingResult);
        $tests['ping']['status'] = 'success';
        $tests['ping']['message'] = 'Servidor acessível';
    } else {
        $tests['ping']['status'] = 'failed';
        $tests['ping']['message'] = "Não foi possível conectar: {$errstr} ({$errno})";
    }
    
    // Teste 2: Conexão ESL (Event Socket Layer)
    $tests['esl'] = [
        'name' => 'Conexão ESL',
        'status' => 'testing'
    ];
    
    try {
        $freeswitchAPI = new FreeSwitchAPI();
        
        // Tentar executar comando simples
        $eslSocket = @fsockopen(
            $provider['server_host'],
            $provider['esl_port'] ?? 8021,
            $errno,
            $errstr,
            5
        );
        
        if ($eslSocket) {
            // Ler banner
            $banner = fgets($eslSocket);
            
            // Tentar autenticar
            fwrite($eslSocket, "auth {$provider['esl_password']}\n\n");
            $authResponse = fgets($eslSocket);
            
            fclose($eslSocket);
            
            if (strpos($authResponse, '+OK') !== false) {
                $tests['esl']['status'] = 'success';
                $tests['esl']['message'] = 'Autenticação ESL bem-sucedida';
            } else {
                $tests['esl']['status'] = 'failed';
                $tests['esl']['message'] = 'Falha na autenticação ESL (senha incorreta?)';
            }
        } else {
            $tests['esl']['status'] = 'failed';
            $tests['esl']['message'] = "Não foi possível conectar na porta ESL: {$errstr}";
        }
        
    } catch (Exception $e) {
        $tests['esl']['status'] = 'failed';
        $tests['esl']['message'] = 'Erro: ' . $e->getMessage();
    }
    
    // Teste 3: WebSocket (WSS)
    $tests['websocket'] = [
        'name' => 'WebSocket Secure (WSS)',
        'status' => 'testing'
    ];
    
    $wssSocket = @fsockopen(
        'ssl://' . $provider['server_host'],
        $provider['wss_port'],
        $errno,
        $errstr,
        5
    );
    
    if ($wssSocket) {
        fclose($wssSocket);
        $tests['websocket']['status'] = 'success';
        $tests['websocket']['message'] = 'Porta WSS acessível';
    } else {
        $tests['websocket']['status'] = 'warning';
        $tests['websocket']['message'] = 'Porta WSS não acessível (pode ser normal se SSL não estiver configurado)';
    }
    
    // Teste 4: Domínio SIP
    $tests['sip_domain'] = [
        'name' => 'Domínio SIP',
        'status' => 'info'
    ];
    
    if (!empty($provider['sip_domain'])) {
        $tests['sip_domain']['status'] = 'success';
        $tests['sip_domain']['message'] = "Domínio configurado: {$provider['sip_domain']}";
    } else {
        $tests['sip_domain']['status'] = 'warning';
        $tests['sip_domain']['message'] = 'Domínio SIP não configurado';
    }
    
    // Determinar status geral
    $allSuccess = true;
    $hasFailure = false;
    
    foreach ($tests as $test) {
        if ($test['status'] === 'failed') {
            $hasFailure = true;
            $allSuccess = false;
        } elseif ($test['status'] !== 'success') {
            $allSuccess = false;
        }
    }
    
    $overallStatus = $hasFailure ? 'failed' : ($allSuccess ? 'success' : 'partial');
    
    echo json_encode([
        'success' => true,
        'overall_status' => $overallStatus,
        'message' => $overallStatus === 'success' 
            ? 'Todos os testes passaram com sucesso!' 
            : ($overallStatus === 'failed' 
                ? 'Alguns testes falharam. Verifique a configuração.' 
                : 'Conexão parcial. Alguns recursos podem não funcionar.'),
        'tests' => $tests,
        'provider' => [
            'type' => $provider['provider_type'],
            'host' => $provider['server_host'],
            'wss_port' => $provider['wss_port'],
            'esl_port' => $provider['esl_port'],
            'sip_domain' => $provider['sip_domain']
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("VoIP Test Connection Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao testar conexão: ' . $e->getMessage()
    ]);
}
