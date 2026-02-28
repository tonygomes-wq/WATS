<?php
/**
 * Autoloader Manual - Substitui Composer
 * Carrega classes automaticamente do namespace App\
 * 
 * MACIP Tecnologia LTDA
 * Fase 3 - Refatoração Arquitetural
 */

spl_autoload_register(function ($class) {
    // Namespace base do projeto
    $prefix = 'App\\';
    
    // Diretório base onde estão as classes
    $base_dir = __DIR__ . '/../app/';
    
    // Verificar se a classe usa o namespace do projeto
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Não é uma classe do nosso namespace, deixar outro autoloader tentar
        return;
    }
    
    // Obter o nome relativo da classe (sem o namespace base)
    $relative_class = substr($class, $len);
    
    // Substituir namespace separators (\) por directory separators (/)
    // Adicionar .php no final
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // Se o arquivo existe, incluir
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Exemplos de uso:
 * 
 * new App\Controllers\ConversationController()
 * → Carrega: app/Controllers/ConversationController.php
 * 
 * new App\Services\MessageService()
 * → Carrega: app/Services/MessageService.php
 * 
 * new App\Repositories\ConversationRepository()
 * → Carrega: app/Repositories/ConversationRepository.php
 */
