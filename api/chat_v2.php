<?php
/**
 * Entry Point da Nova Arquitetura (Fase 3)
 * API para Chat usando padrão MVC
 * 
 * MACIP Tecnologia LTDA
 * 
 * IMPORTANTE: Este é um endpoint PARALELO ao sistema atual
 * O sistema antigo (chat_conversations.php, chat_messages.php) continua funcionando
 * Use este endpoint para testar a nova arquitetura sem afetar produção
 * 
 * SUPORTA:
 * - Query strings: ?action=conversations
 * - REST URLs: /conversations (futuro)
 */

// Headers de segurança
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Iniciar sessão
session_start();

// Carregar autoloader manual (substitui Composer)
require_once __DIR__ . '/../includes/autoloader.php';

// Carregar configurações
require_once __DIR__ . '/../config/database.php';

// Verificar se conexão com banco foi estabelecida
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao conectar com banco de dados'
    ]);
    exit;
}

try {
    // Verificar se está usando query string (?action=)
    if (isset($_GET['action'])) {
        // Modo query string (compatibilidade com sistema atual)
        $controller = new \App\Controllers\ApiController();
        $controller->dispatch();
    } else {
        // Modo REST (futuro - quando migrar frontend)
        // Carregar container de dependências
        require_once __DIR__ . '/../config/container.php';
        
        // Carregar definição de rotas
        require_once __DIR__ . '/../config/routes.php';
        
        // Carregar Router
        require_once __DIR__ . '/../includes/Router.php';
        
        // Inicializar Router
        $router = new Router();
        
        // Registrar todas as rotas
        registerRoutes($router);
        
        // Obter método e URI da requisição
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remover o caminho base (chat_v2.php)
        $uri = str_replace('/api/chat_v2.php', '', $uri);
        
        // Se URI vazia, usar raiz
        if (empty($uri) || $uri === '/') {
            $uri = '/';
        }
        
        // Processar requisição
        $router->dispatch($method, $uri);
    }
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro no chat_v2.php: " . $e->getMessage());
    
    // Resposta de erro
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição',
        'error' => $e->getMessage()
    ]);
}
