<?php

namespace App\Controllers;

/**
 * Classe Base para Controllers
 * 
 * Fornece métodos comuns para todos os controllers:
 * - Resposta JSON
 * - Renderização de views
 * - Redirecionamentos
 */
abstract class BaseController
{
    /**
     * Retorna resposta JSON
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_clean();
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Renderiza uma view
     */
    protected function render(string $view, array $data = []): void
    {
        extract($data);
        
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        
        if (!file_exists($viewPath)) {
            throw new \Exception("View não encontrada: $view");
        }
        
        require $viewPath;
    }
    
    /**
     * Redireciona para uma URL
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }
    
    /**
     * Valida se usuário está autenticado
     */
    protected function requireAuth(): int
    {
        if (!isset($_SESSION['user_id'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Não autorizado'
            ], 401);
        }
        
        return (int) $_SESSION['user_id'];
    }
    
    /**
     * Obtém ID do usuário autenticado
     */
    protected function getUserId(): int
    {
        return $this->requireAuth();
    }
    
    /**
     * Obtém dados JSON do input
     */
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return $data ?? [];
    }
    
    /**
     * Obtém parâmetros da requisição
     */
    protected function getRequestData(): array
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method === 'GET') {
            return $_GET;
        }
        
        if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            return $data ?? $_POST;
        }
        
        return [];
    }
}
