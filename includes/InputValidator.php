<?php
/**
 * Input Validator - Validação Centralizada de Inputs
 * 
 * Valida e sanitiza todos os inputs do sistema
 * Previne SQL Injection, XSS e outros ataques
 * 
 * MACIP Tecnologia LTDA
 * @version 1.0.0
 */

class InputValidator {
    
    /**
     * Validar mensagem de texto
     * 
     * @param string $message Mensagem a validar
     * @return array ['valid' => bool, 'errors' => array, 'sanitized' => string]
     */
    public static function validateMessage(string $message): array {
        $errors = [];
        
        // Remover espaços em branco no início e fim
        $message = trim($message);
        
        // Verificar se está vazio
        if (empty($message)) {
            $errors[] = 'Mensagem não pode ser vazia';
        }
        
        // Verificar tamanho máximo (WhatsApp limita em 4096 caracteres)
        if (strlen($message) > 4096) {
            $errors[] = 'Mensagem muito longa (máximo 4096 caracteres)';
        }
        
        // Verificar caracteres suspeitos (SQL injection básico)
        if (preg_match('/(\bSELECT\b|\bUNION\b|\bDROP\b|\bINSERT\b|\bDELETE\b|\bUPDATE\b)/i', $message)) {
            $errors[] = 'Mensagem contém conteúdo suspeito';
        }
        
        // Sanitizar HTML (prevenir XSS)
        $sanitized = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized' => $sanitized
        ];
    }
    
    /**
     * Validar número de telefone
     * 
     * @param string $phone Telefone a validar
     * @return array ['valid' => bool, 'errors' => array, 'sanitized' => string]
     */
    public static function validatePhone(string $phone): array {
        $errors = [];
        
        // Remover caracteres não numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Verificar se está vazio
        if (empty($phone)) {
            $errors[] = 'Telefone não pode ser vazio';
        }
        
        // Verificar tamanho (Brasil: 10-13 dígitos)
        // 10 dígitos: DDD + 8 dígitos (fixo)
        // 11 dígitos: DDD + 9 dígitos (celular)
        // 12 dígitos: 55 + DDD + 8 dígitos OU números especiais
        // 13 dígitos: 55 + DDD + 9 dígitos
        if (strlen($phone) < 10 || strlen($phone) > 13) {
            $errors[] = 'Telefone inválido (deve ter 10-13 dígitos)';
        }
        
        // Adicionar código do país se necessário (apenas para 10 e 11 dígitos)
        if (strlen($phone) === 11 && substr($phone, 0, 2) !== '55') {
            $phone = '55' . $phone;
        } elseif (strlen($phone) === 10 && substr($phone, 0, 2) !== '55') {
            $phone = '55' . $phone;
        }
        // Para 12 dígitos, mantém como está (pode ser com ou sem código 55)
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized' => $phone
        ];
    }
    
    /**
     * Validar ID numérico
     * 
     * @param mixed $id ID a validar
     * @param string $fieldName Nome do campo (para mensagem de erro)
     * @return array ['valid' => bool, 'errors' => array, 'sanitized' => int]
     */
    public static function validateId($id, string $fieldName = 'ID'): array {
        $errors = [];
        
        // Verificar se é numérico
        if (!is_numeric($id)) {
            $errors[] = "$fieldName deve ser numérico";
        }
        
        // Converter para inteiro
        $id = (int) $id;
        
        // Verificar se é positivo
        if ($id <= 0) {
            $errors[] = "$fieldName deve ser maior que zero";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized' => $id
        ];
    }
    
    /**
     * Validar email
     * 
     * @param string $email Email a validar
     * @return array ['valid' => bool, 'errors' => array, 'sanitized' => string]
     */
    public static function validateEmail(string $email): array {
        $errors = [];
        $email = trim($email);
        
        if (empty($email)) {
            $errors[] = 'Email não pode ser vazio';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        } elseif (strlen($email) > 255) {
            $errors[] = 'Email muito longo (máximo 255 caracteres)';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized' => strtolower($email)
        ];
    }
    
    /**
     * Validar nome (usuário, contato, etc)
     * 
     * @param string $name Nome a validar
     * @param int $minLength Tamanho mínimo
     * @param int $maxLength Tamanho máximo
     * @return array ['valid' => bool, 'errors' => array, 'sanitized' => string]
     */
    public static function validateName(string $name, int $minLength = 2, int $maxLength = 100): array {
        $errors = [];
        $name = trim($name);
        
        if (empty($name)) {
            $errors[] = 'Nome não pode ser vazio';
        } elseif (strlen($name) < $minLength) {
            $errors[] = "Nome deve ter no mínimo {$minLength} caracteres";
        } elseif (strlen($name) > $maxLength) {
            $errors[] = "Nome deve ter no máximo {$maxLength} caracteres";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        ];
    }
    
    /**
     * Validar string genérica
     * 
     * @param string $value Valor a validar
     * @param int $maxLength Tamanho máximo
     * @param bool $allowEmpty Permitir vazio
     * @return array ['valid' => bool, 'errors' => array, 'sanitized' => string]
     */
    public static function validateString(string $value, int $maxLength = 255, bool $allowEmpty = false): array {
        $errors = [];
        $value = trim($value);
        
        if (empty($value) && !$allowEmpty) {
            $errors[] = 'Campo não pode ser vazio';
        } elseif (strlen($value) > $maxLength) {
            $errors[] = "Campo muito longo (máximo {$maxLength} caracteres)";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
        ];
    }
}
