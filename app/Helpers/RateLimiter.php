<?php

namespace App\Helpers;

/**
 * Rate Limiter
 * Cópia da classe includes/RateLimiter.php para uso na nova arquitetura
 */
class RateLimiter
{
    private string $storageDir;
    
    public function __construct()
    {
        $this->storageDir = __DIR__ . '/../../storage/rate_limits/';
        
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
    
    /**
     * Verificar se ação é permitida
     */
    public function allow(int $userId, string $action, int $maxAttempts, int $decaySeconds): bool
    {
        $key = $this->getKey($userId, $action);
        $data = $this->getData($key);
        
        $now = time();
        
        // Limpar tentativas antigas
        $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($now, $decaySeconds) {
            return ($now - $timestamp) < $decaySeconds;
        });
        
        // Verificar limite
        if (count($data['attempts']) >= $maxAttempts) {
            return false;
        }
        
        // Adicionar nova tentativa
        $data['attempts'][] = $now;
        $this->saveData($key, $data);
        
        return true;
    }
    
    /**
     * Gerar chave única
     */
    private function getKey(int $userId, string $action): string
    {
        return md5("user_{$userId}_action_{$action}");
    }
    
    /**
     * Obter dados do arquivo
     */
    private function getData(string $key): array
    {
        $file = $this->storageDir . $key . '.json';
        
        if (!file_exists($file)) {
            return ['attempts' => []];
        }
        
        $content = file_get_contents($file);
        return json_decode($content, true) ?: ['attempts' => []];
    }
    
    /**
     * Salvar dados no arquivo
     */
    private function saveData(string $key, array $data): void
    {
        $file = $this->storageDir . $key . '.json';
        file_put_contents($file, json_encode($data));
    }
}
