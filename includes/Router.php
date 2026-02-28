<?php

/**
 * Sistema de Rotas Simples
 * 
 * Mapeia URLs para Controllers e Actions
 */
class Router
{
    private array $routes = [];
    private array $params = [];
    
    /**
     * Adiciona uma rota
     */
    public function add(string $method, string $path, string $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->compilePath($path),
            'original_path' => $path,
            'handler' => $handler
        ];
    }
    
    /**
     * Adiciona rota GET
     */
    public function get(string $path, string $handler): void
    {
        $this->add('GET', $path, $handler);
    }
    
    /**
     * Adiciona rota POST
     */
    public function post(string $path, string $handler): void
    {
        $this->add('POST', $path, $handler);
    }
    
    /**
     * Adiciona rota PUT
     */
    public function put(string $path, string $handler): void
    {
        $this->add('PUT', $path, $handler);
    }
    
    /**
     * Adiciona rota DELETE
     */
    public function delete(string $path, string $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }
    
    /**
     * Despacha a requisição para o handler correto
     */
    public function dispatch(string $method, string $uri): void
    {
        // Remover query string
        $uri = strtok($uri, '?');
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['path'], $uri, $matches)) {
                // Extrair parâmetros da URL
                array_shift($matches); // Remove o match completo
                $this->params = $matches;
                
                $this->callHandler($route['handler']);
                return;
            }
        }
        
        // Rota não encontrada
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Rota não encontrada',
            'method' => $method,
            'uri' => $uri
        ]);
    }
    
    /**
     * Compila o path para regex
     * Converte /api/conversations/:id para /api/conversations/(\d+)
     */
    private function compilePath(string $path): string
    {
        // Escapar barras
        $pattern = str_replace('/', '\/', $path);
        
        // Converter :id, :name, etc para (\d+) ou (\w+)
        $pattern = preg_replace('/:(\w+)/', '(\d+)', $pattern);
        
        return '/^' . $pattern . '$/';
    }
    
    /**
     * Chama o handler (Controller@method)
     */
    private function callHandler(string $handler): void
    {
        [$controller, $method] = explode('@', $handler);
        
        // Namespace completo do controller
        $controllerClass = "App\\Controllers\\$controller";
        
        if (!class_exists($controllerClass)) {
            throw new Exception("Controller não encontrado: $controllerClass");
        }
        
        $controllerInstance = new $controllerClass();
        
        if (!method_exists($controllerInstance, $method)) {
            throw new Exception("Método não encontrado: $method em $controllerClass");
        }
        
        // Chamar método com parâmetros da URL
        call_user_func_array([$controllerInstance, $method], $this->params);
    }
    
    /**
     * Obtém parâmetros extraídos da URL
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
