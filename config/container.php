<?php

/**
 * Dependency Injection Container
 * 
 * Gerencia criação e injeção de dependências
 */
class Container
{
    private array $services = [];
    private array $instances = [];
    
    /**
     * Registra um serviço
     */
    public function set(string $name, callable $factory): void
    {
        $this->services[$name] = $factory;
    }
    
    /**
     * Obtém um serviço (lazy loading + singleton)
     */
    public function get(string $name): mixed
    {
        // Se já foi instanciado, retornar instância
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }
        
        // Se não está registrado, erro
        if (!isset($this->services[$name])) {
            throw new Exception("Serviço não encontrado: $name");
        }
        
        // Criar instância (lazy loading)
        $factory = $this->services[$name];
        $instance = $factory($this);
        
        // Salvar instância (singleton)
        $this->instances[$name] = $instance;
        
        return $instance;
    }
    
    /**
     * Verifica se serviço está registrado
     */
    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }
}

// ============================================
// CONFIGURAR SERVIÇOS
// ============================================

$container = new Container();

// PDO (Banco de Dados)
$container->set('pdo', function() {
    require_once __DIR__ . '/../config/database.php';
    return $pdo; // $pdo é criado em database.php
});

// Logger
$container->set('logger', function() {
    require_once __DIR__ . '/../includes/Logger.php';
    return new Logger();
});

// Rate Limiter
$container->set('rateLimiter', function() {
    require_once __DIR__ . '/../includes/RateLimiter.php';
    return new RateLimiter();
});

// Input Validator
$container->set('inputValidator', function() {
    require_once __DIR__ . '/../includes/InputValidator.php';
    return new InputValidator();
});

// ============================================
// REPOSITORIES
// ============================================

$container->set('conversationRepository', function($c) {
    return new \App\Repositories\ConversationRepository($c->get('pdo'));
});

$container->set('messageRepository', function($c) {
    return new \App\Repositories\MessageRepository($c->get('pdo'));
});

$container->set('contactRepository', function($c) {
    return new \App\Repositories\ContactRepository($c->get('pdo'));
});

// ============================================
// SERVICES
// ============================================

$container->set('conversationService', function($c) {
    return new \App\Services\ConversationService(
        $c->get('conversationRepository'),
        $c->get('contactService'),
        $c->get('logger')
    );
});

$container->set('messageService', function($c) {
    return new \App\Services\MessageService(
        $c->get('messageRepository'),
        $c->get('logger')
    );
});

$container->set('contactService', function($c) {
    return new \App\Services\ContactService(
        $c->get('contactRepository'),
        $c->get('logger')
    );
});

$container->set('notificationService', function($c) {
    return new \App\Services\NotificationService(
        $c->get('logger')
    );
});

return $container;
