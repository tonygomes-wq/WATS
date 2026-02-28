<?php

namespace App\Services;

use Logger;

/**
 * Classe Base para Services
 * 
 * Fornece funcionalidades comuns:
 * - Logging
 * - Validação
 */
abstract class BaseService
{
    protected Logger $logger;
    
    public function __construct()
    {
        // Logger será injetado via container
        if (class_exists('Logger')) {
            $this->logger = new Logger();
        }
    }
    
    /**
     * Valida dados de entrada
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if ($rule === 'required' && empty($data[$field])) {
                $errors[] = "Campo $field é obrigatório";
            }
        }
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(', ', $errors));
        }
        
        return $data;
    }
    
    /**
     * Log de informação
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if (isset($this->logger)) {
            $this->logger->info($message, $context);
        }
    }
    
    /**
     * Log de erro
     */
    protected function logError(string $message, array $context = []): void
    {
        if (isset($this->logger)) {
            $this->logger->error($message, $context);
        }
    }
    
    /**
     * Log de warning
     */
    protected function logWarning(string $message, array $context = []): void
    {
        if (isset($this->logger)) {
            $this->logger->warning($message, $context);
        }
    }
}
