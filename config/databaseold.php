<?php
/**
 * Configuração Segura do Banco de Dados
 * 
 * Usa variáveis de ambiente (.env) para credenciais
 * Mantém compatibilidade com sistema antigo
 * 
 * MACIP Tecnologia LTDA
 */

// Carregar variáveis de ambiente
require_once __DIR__ . '/env.php';

// Configurações do banco de dados
// Prioriza .env, mas mantém fallback para compatibilidade
if (!defined('DB_HOST')) {
    define('DB_HOST', env('DB_HOST', 'localhost'));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', env('DB_NAME', 'whatsapp_sender'));
}
if (!defined('DB_USER')) {
    define('DB_USER', env('DB_USER', 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', env('DB_PASS', ''));
}

// Configurações do site
if (!defined('SITE_NAME')) {
    define('SITE_NAME', env('APP_NAME', 'MAC-IP TECNOLOGIA'));
}
if (!defined('SITE_URL')) {
    define('SITE_URL', env('APP_URL', 'http://localhost'));
}

// Configurações da Evolution API
if (!defined('EVOLUTION_API_URL')) {
    define('EVOLUTION_API_URL', env('EVOLUTION_API_URL', 'https://evolution.macip.com.br'));
}
if (!defined('EVOLUTION_API_KEY')) {
    define('EVOLUTION_API_KEY', env('EVOLUTION_API_KEY', 'sua-chave-api'));
}
if (!defined('EVOLUTION_INSTANCE')) {
    define('EVOLUTION_INSTANCE', env('EVOLUTION_INSTANCE', ''));
}

// Configurações de segurança
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', env('APP_DEBUG', 'false') === 'true');
}
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', (int)env('SESSION_LIFETIME', 480));
}

// Conexão PDO com tratamento de erro melhorado
try {
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        DB_HOST,
        DB_NAME,
        env('DB_CHARSET', 'utf8mb4')
    );
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false, // Evitar problemas com conexões persistentes
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Log de conexão bem-sucedida (apenas em debug)
    if (APP_DEBUG) {
        error_log("Conexão com banco de dados estabelecida: " . DB_NAME);
    }
    
} catch (PDOException $e) {
    // Em produção, não expor detalhes do erro
    if (APP_DEBUG) {
        die("Erro de conexão com o banco de dados: " . $e->getMessage());
    } else {
        error_log("ERRO DB: " . $e->getMessage());
        die("Erro ao conectar com o banco de dados. Por favor, tente novamente mais tarde.");
    }
}

/**
 * Função helper para executar queries com log de erro
 */
function db_query($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("ERRO SQL: " . $e->getMessage() . " | Query: " . $query);
        throw $e;
    }
}

/**
 * Função helper para transações
 */
function db_transaction($callback) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("ERRO TRANSAÇÃO: " . $e->getMessage());
        throw $e;
    }
}
