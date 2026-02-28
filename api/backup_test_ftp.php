<?php
/**
 * API - Testar Conexão FTP/SFTP
 * 
 * Testa se as credenciais FTP fornecidas são válidas
 * 
 * MACIP Tecnologia LTDA
 */

session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$host = $input['host'] ?? '';
$port = (int)($input['port'] ?? 21);
$user = $input['user'] ?? '';
$pass = $input['pass'] ?? '';
$useSsl = (bool)($input['ssl'] ?? false);

if (empty($host) || empty($user) || empty($pass)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Preencha todos os campos obrigatórios']);
    exit;
}

try {
    if ($useSsl && $port == 22) {
        // Conexão SFTP via SSH2
        if (!function_exists('ssh2_connect')) {
            echo json_encode(['success' => false, 'error' => 'Extensão SSH2 não disponível no servidor. Use FTP normal ou contate o suporte.']);
            exit;
        }
        
        $connection = @ssh2_connect($host, $port);
        if (!$connection) {
            echo json_encode(['success' => false, 'error' => 'Não foi possível conectar ao servidor SFTP']);
            exit;
        }
        
        if (!@ssh2_auth_password($connection, $user, $pass)) {
            echo json_encode(['success' => false, 'error' => 'Autenticação SFTP falhou. Verifique usuário e senha.']);
            exit;
        }
        
        $sftp = @ssh2_sftp($connection);
        if (!$sftp) {
            echo json_encode(['success' => false, 'error' => 'Não foi possível inicializar subsistema SFTP']);
            exit;
        }
        
        echo json_encode(['success' => true, 'message' => 'Conexão SFTP estabelecida com sucesso!']);
        
    } else {
        // Conexão FTP normal ou FTPS
        if ($useSsl) {
            $conn = @ftp_ssl_connect($host, $port, 10);
        } else {
            $conn = @ftp_connect($host, $port, 10);
        }
        
        if (!$conn) {
            echo json_encode(['success' => false, 'error' => 'Não foi possível conectar ao servidor FTP. Verifique o endereço e porta.']);
            exit;
        }
        
        $login = @ftp_login($conn, $user, $pass);
        if (!$login) {
            @ftp_close($conn);
            echo json_encode(['success' => false, 'error' => 'Autenticação FTP falhou. Verifique usuário e senha.']);
            exit;
        }
        
        // Tentar modo passivo (mais comum em firewalls)
        @ftp_pasv($conn, true);
        
        // Testar listagem do diretório
        $list = @ftp_nlist($conn, '.');
        
        @ftp_close($conn);
        
        $protocol = $useSsl ? 'FTPS' : 'FTP';
        echo json_encode(['success' => true, 'message' => "Conexão {$protocol} estabelecida com sucesso!"]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro: ' . $e->getMessage()]);
}
