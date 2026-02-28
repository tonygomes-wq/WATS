<?php
/**
 * Rate Limiter - Controle de Taxa de Requisições
 * 
 * Previne abuso de APIs e spam através de limitação de requisições
 * Usa sistema de arquivos (migrar para Redis futuramente)
 * 
 * MACIP Tecnologia LTDA
 * @version 1.0.0
 */

class RateLimiter {
    private $storageDir;
    
    /**
     * Construtor
     */
    public function __construct() {
        $this->storageDir = __DIR__ . '/../storage/rate_limits/';
        
        // Criar diretório se não existir
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
    
    /**
     * Verificar se ação é permitida
     * 
     * @param string $key Identificador único (user_id, ip, etc)
     * @param string $action Nome da ação (send_message, login, etc)
     * @param int $maxAttempts Máximo de tentativas permitidas
     * @param int $windowSeconds Janela de tempo em segundos
     * @return bool True se permitido, False se excedeu limite
     */
    public function allow(string $key, string $action, int $maxAttempts, int $windowSeconds): bool {
        $filename = $this->getFilename($key, $action);
        
        // Ler tentativas anteriores
        $attempts = $this->getAttempts($filename);
        
        // Remover tentativas antigas (fora da janela de tempo)
        $now = time();
        $attempts = array_filter($attempts, function($timestamp) use ($now, $windowSeconds) {
            return ($now - $timestamp) < $windowSeconds;
        });
        
        // Verificar se excedeu limite
        if (count($attempts) >= $maxAttempts) {
            // Salvar tentativas filtradas
            $this->saveAttempts($filename, $attempts);
            return false;
        }
        
        // Registrar nova tentativa
        $attempts[] = $now;
        $this->saveAttempts($filename, $attempts);
        
        return true;
    }
    
    /**
     * Obter número de tentativas restantes
     * 
     * @param string $key Identificador único
     * @param string $action Nome da ação
     * @param int $maxAttempts Máximo de tentativas
     * @param int $windowSeconds Janela de tempo
     * @return int Número de tentativas restantes
     */
    public function remaining(string $key, string $action, int $maxAttempts, int $windowSeconds): int {
        $filename = $this->getFilename($key, $action);
        $attempts = $this->getAttempts($filename);
        
        // Filtrar tentativas dentro da janela
        $now = time();
        $attempts = array_filter($attempts, function($timestamp) use ($now, $windowSeconds) {
            return ($now - $timestamp) < $windowSeconds;
        });
        
        return max(0, $maxAttempts - count($attempts));
    }
    
    /**
     * Resetar contador para uma chave/ação
     * 
     * @param string $key Identificador único
     * @param string $action Nome da ação
     * @return bool True se resetado com sucesso
     */
    public function reset(string $key, string $action): bool {
        $filename = $this->getFilename($key, $action);
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return true;
    }
    
    /**
     * Limpar rate limits antigos (executar via cron)
     * 
     * @param int $olderThanSeconds Remover arquivos mais antigos que X segundos
     * @return int Número de arquivos removidos
     */
    public function cleanup(int $olderThanSeconds = 3600): int {
        $files = glob($this->storageDir . '*.json');
        $now = time();
        $removed = 0;
        
        foreach ($files as $file) {
            if (($now - filemtime($file)) > $olderThanSeconds) {
                if (unlink($file)) {
                    $removed++;
                }
            }
        }
        
        return $removed;
    }
    
    /**
     * Obter nome do arquivo para chave/ação
     * 
     * @param string $key Identificador
     * @param string $action Ação
     * @return string Caminho completo do arquivo
     */
    private function getFilename(string $key, string $action): string {
        return $this->storageDir . md5($key . '_' . $action) . '.json';
    }
    
    /**
     * Ler tentativas do arquivo
     * 
     * @param string $filename Caminho do arquivo
     * @return array Array de timestamps
     */
    private function getAttempts(string $filename): array {
        if (!file_exists($filename)) {
            return [];
        }
        
        $data = @file_get_contents($filename);
        if ($data === false) {
            return [];
        }
        
        $decoded = json_decode($data, true);
        return $decoded['attempts'] ?? [];
    }
    
    /**
     * Salvar tentativas no arquivo
     * 
     * @param string $filename Caminho do arquivo
     * @param array $attempts Array de timestamps
     * @return bool True se salvou com sucesso
     */
    private function saveAttempts(string $filename, array $attempts): bool {
        $data = json_encode([
            'attempts' => array_values($attempts), // Reindexar array
            'updated_at' => time()
        ]);
        
        return file_put_contents($filename, $data) !== false;
    }
}
