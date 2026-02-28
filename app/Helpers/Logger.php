<?php

namespace App\Helpers;

/**
 * Logger
 * Cópia da classe includes/Logger.php para uso na nova arquitetura
 */
class Logger
{
    private static string $logDir = '';
    
    /**
     * Log de informação
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log de warning
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log de erro
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Escrever log
     */
    private static function log(string $level, string $message, array $context): void
    {
        if (empty(self::$logDir)) {
            self::$logDir = __DIR__ . '/../../logs/';
            
            if (!is_dir(self::$logDir)) {
                mkdir(self::$logDir, 0755, true);
            }
        }
        
        $date = date('Y-m-d');
        $time = date('Y-m-d H:i:s');
        $file = self::$logDir . "app_{$date}.log";
        
        $logEntry = "[$time] $level: $message\n";
        
        if (!empty($context)) {
            $logEntry .= json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        
        $logEntry .= "\n";
        
        file_put_contents($file, $logEntry, FILE_APPEND);
    }
}
