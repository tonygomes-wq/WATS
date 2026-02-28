<?php

namespace App\Helpers;

/**
 * Validador de Inputs
 * Cópia da classe includes/InputValidator.php para uso na nova arquitetura
 */
class InputValidator
{
    /**
     * Validar ID
     */
    public static function validateId($id): array
    {
        $errors = [];
        $id = filter_var($id, FILTER_VALIDATE_INT);
        
        if ($id === false || $id <= 0) {
            $errors[] = 'ID inválido: deve ser um número positivo';
        }
        
        return [
            'valid' => empty($errors),
            'sanitized' => $id ?: 0,
            'errors' => $errors
        ];
    }
    
    /**
     * Validar telefone
     */
    public static function validatePhone(string $phone): array
    {
        $errors = [];
        
        // Remover caracteres não numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Validar tamanho (aceita de 10 a 15 dígitos para flexibilidade internacional)
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            $errors[] = 'Telefone inválido: deve ter entre 10 e 15 dígitos';
        }
        
        // Adicionar código do Brasil se necessário (apenas para 10 e 11 dígitos)
        if (empty($errors) && strlen($phone) === 11 && substr($phone, 0, 2) !== '55') {
            $phone = '55' . $phone;
        } elseif (empty($errors) && strlen($phone) === 10 && substr($phone, 0, 2) !== '55') {
            $phone = '55' . $phone;
        }
        // Para 12 dígitos ou mais, mantém como está
        
        return [
            'valid' => empty($errors),
            'sanitized' => $phone,
            'errors' => $errors
        ];
    }
    
    /**
     * Validar nome
     */
    public static function validateName(string $name, int $minLength = 2, int $maxLength = 100): array
    {
        $errors = [];
        $name = trim($name);
        
        if (strlen($name) < $minLength) {
            $errors[] = "Nome muito curto: mínimo $minLength caracteres";
        }
        
        if (strlen($name) > $maxLength) {
            $errors[] = "Nome muito longo: máximo $maxLength caracteres";
        }
        
        return [
            'valid' => empty($errors),
            'sanitized' => $name,
            'errors' => $errors
        ];
    }
}
