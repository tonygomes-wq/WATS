<?php
/**
 * Carregador de Variáveis de Ambiente
 * 
 * Sistema simples para carregar variáveis do arquivo .env
 * Compatível com PHP 7.4+ sem dependências externas
 * 
 * MACIP Tecnologia LTDA
 */

class EnvLoader {
    private static $loaded = false;
    private static $vars = [];
    
    /**
     * Carrega o arquivo .env
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }
        
        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }
        
        if (!file_exists($path)) {
            // Se não existir .env, tentar carregar do .env.example como fallback
            $examplePath = dirname(__DIR__) . '/.env.example';
            if (file_exists($examplePath)) {
                error_log("AVISO: Arquivo .env não encontrado. Usando .env.example como referência.");
            }
            return;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorar comentários
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse da linha KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                
                $key = trim($key);
                $value = trim($value);
                
                // Remover aspas
                $value = trim($value, '"\'');
                
                // Armazenar
                self::$vars[$key] = $value;
                
                // Definir como constante se não existir
                if (!defined($key)) {
                    define($key, $value);
                }
                
                // Também definir em $_ENV e $_SERVER
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Obtém uma variável de ambiente
     */
    public static function get($key, $default = null) {
        if (isset(self::$vars[$key])) {
            return self::$vars[$key];
        }
        
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        return $default;
    }
    
    /**
     * Verifica se uma variável existe
     */
    public static function has($key) {
        return isset(self::$vars[$key]) || isset($_ENV[$key]) || isset($_SERVER[$key]);
    }
    
    /**
     * Obtém todas as variáveis carregadas
     */
    public static function all() {
        return self::$vars;
    }
}

/**
 * Função helper para obter variável de ambiente
 */
function env($key, $default = null) {
    return EnvLoader::get($key, $default);
}

// Carregar automaticamente ao incluir este arquivo
EnvLoader::load();
