<?php
/**
 * Logger - Sistema de Logs Estruturado
 * 
 * Registra eventos do sistema em formato JSON
 * Facilita debugging e auditoria
 * 
 * MACIP Tecnologia LTDA
 * @version 1.0.0
 */

class Logger {
    private static $logDir = null;
    
    /**
     * Inicializar diretório de logs
     */
    private static function init(): void {
        if (self::$logDir === null) {
            self::$logDir = __DIR__ . '/../logs/';
            
            // Criar diretório se não existir
            if (!is_dir(self::$logDir)) {
                mkdir(self::$logDir, 0755, true);
            }
        }
    }
    
    /**
     * Registrar log
     * 
     * @param string $level Nível do log (INFO, WARNING, ERROR, CRITICAL)
     * @param string $message Mensagem do log
     * @param array $context Contexto adicional (dados relevantes)
     * @return bool True se salvou com sucesso
     */
    public static function log(string $level, string $message, array $context = []): bool {
        self::init();
        
        // Preparar entrada do log
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null
        ];
        
        // Arquivo por dia (facilita rotação)
        $filename = self::$logDir . date('Y-m-d') . '.log';
        
        // Escrever log em formato JSON (uma linha por entrada)
        $result = file_put_contents(
            $filename,
            json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
        
        // Também logar no error_log do PHP para erros críticos
        if (in_array(strtoupper($level), ['ERROR', 'CRITICAL'])) {
            error_log("[{$level}] {$message} " . json_encode($context));
        }
        
        return $result !== false;
    }
    
    /**
     * Log de informação (eventos normais)
     * 
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return bool
     */
    public static function info(string $message, array $context = []): bool {
        return self::log('INFO', $message, $context);
    }
    
    /**
     * Log de aviso (situações anormais mas não críticas)
     * 
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return bool
     */
    public static function warning(string $message, array $context = []): bool {
        return self::log('WARNING', $message, $context);
    }
    
    /**
     * Log de erro (erros que precisam atenção)
     * 
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return bool
     */
    public static function error(string $message, array $context = []): bool {
        return self::log('ERROR', $message, $context);
    }
    
    /**
     * Log crítico (sistema em risco, requer ação imediata)
     * 
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return bool
     */
    public static function critical(string $message, array $context = []): bool {
        return self::log('CRITICAL', $message, $context);
    }
    
    /**
     * Limpar logs antigos (executar via cron)
     * 
     * @param int $daysToKeep Número de dias para manter logs
     * @return int Número de arquivos removidos
     */
    public static function cleanup(int $daysToKeep = 30): int {
        self::init();
        
        $files = glob(self::$logDir . '*.log');
        $now = time();
        $removed = 0;
        
        foreach ($files as $file) {
            $fileAge = $now - filemtime($file);
            $daysOld = $fileAge / 86400; // Converter para dias
            
            if ($daysOld > $daysToKeep) {
                if (unlink($file)) {
                    $removed++;
                }
            }
        }
        
        return $removed;
    }
    
    /**
     * Ler logs de um dia específico
     * 
     * @param string $date Data no formato Y-m-d
     * @param string|null $level Filtrar por nível (opcional)
     * @return array Array de entradas de log
     */
    public static function read(string $date, ?string $level = null): array {
        self::init();
        
        $filename = self::$logDir . $date . '.log';
        
        if (!file_exists($filename)) {
            return [];
        }
        
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            
            if ($entry === null) {
                continue; // Linha inválida
            }
            
            // Filtrar por nível se especificado
            if ($level !== null && strtoupper($entry['level']) !== strtoupper($level)) {
                continue;
            }
            
            $logs[] = $entry;
        }
        
        return $logs;
    }
}
