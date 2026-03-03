<?php

/**
 * Manual Test for VariableSubstitutor
 * 
 * Este script testa manualmente a funcionalidade do VariableSubstitutor
 * para verificar se a substituição de variáveis está funcionando corretamente.
 */

require_once __DIR__ . '/../includes/VariableSubstitutor.php';

echo "=== Manual Test: VariableSubstitutor ===\n\n";

// Test 1: Basic substitution
echo "Test 1: Basic substitution\n";
$context = [
    'contact_name' => 'João Silva',
    'contact_phone' => '5511999999999',
    'message' => 'Olá, preciso de ajuda'
];
$text = 'Olá {{contact_name}}, recebi sua mensagem: {{message}}';
$result = VariableSubstitutor::substitute($text, $context);
echo "Input: $text\n";
echo "Result: $result\n";
echo "Expected: Olá João Silva, recebi sua mensagem: Olá, preciso de ajuda\n";
echo "Status: " . ($result === 'Olá João Silva, recebi sua mensagem: Olá, preciso de ajuda' ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 2: Case-insensitive substitution
echo "Test 2: Case-insensitive substitution\n";
$context = [
    'contact_name' => 'Maria Santos'
];
$text = '{{CONTACT_NAME}} - {{Contact_Name}} - {{contact_name}}';
$result = VariableSubstitutor::substitute($text, $context);
echo "Input: $text\n";
echo "Result: $result\n";
echo "Expected: Maria Santos - Maria Santos - Maria Santos\n";
echo "Status: " . ($result === 'Maria Santos - Maria Santos - Maria Santos' ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 3: Missing variable (should replace with empty string)
echo "Test 3: Missing variable (empty string replacement)\n";
$context = [
    'contact_name' => 'João Silva'
];
$text = 'Olá {{contact_name}}, seu email é {{contact_email}}';
$result = VariableSubstitutor::substitute($text, $context);
echo "Input: $text\n";
echo "Result: $result\n";
echo "Expected: Olá João Silva, seu email é \n";
echo "Status: " . ($result === 'Olá João Silva, seu email é ' ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 4: All supported variables
echo "Test 4: All supported variables\n";
$context = [
    'contact_name' => 'João Silva',
    'contact_phone' => '5511999999999',
    'message' => 'Olá',
    'ai_response' => 'Oi! Como posso ajudar?',
    'timestamp' => '2024-01-15 10:30:00',
    'conversation_id' => '123',
    'history' => 'Histórico de mensagens',
    'channel' => 'whatsapp'
];
$text = 'Nome: {{contact_name}}, Telefone: {{contact_phone}}, Canal: {{channel}}';
$result = VariableSubstitutor::substitute($text, $context);
echo "Input: $text\n";
echo "Result: $result\n";
echo "Expected: Nome: João Silva, Telefone: 5511999999999, Canal: whatsapp\n";
echo "Status: " . ($result === 'Nome: João Silva, Telefone: 5511999999999, Canal: whatsapp' ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 5: History array formatting
echo "Test 5: History array formatting\n";
$context = [
    'history' => [
        ['role' => 'user', 'text' => 'Olá'],
        ['role' => 'assistant', 'text' => 'Oi! Como posso ajudar?'],
        ['role' => 'user', 'text' => 'Preciso de informações']
    ]
];
$text = 'Histórico:\n{{history}}';
$result = VariableSubstitutor::substitute($text, $context);
echo "Input: $text\n";
echo "Result:\n$result\n";
$expected = "Histórico:\nUsuário: Olá\nAssistente: Oi! Como posso ajudar?\nUsuário: Preciso de informações";
echo "Expected:\n$expected\n";
echo "Status: " . ($result === $expected ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 6: UTF-8 support
echo "Test 6: UTF-8 support\n";
$context = [
    'contact_name' => 'José Ação',
    'message' => 'Informação sobre situação'
];
$text = 'Olá {{contact_name}}, recebi: {{message}}';
$result = VariableSubstitutor::substitute($text, $context);
echo "Input: $text\n";
echo "Result: $result\n";
echo "Expected: Olá José Ação, recebi: Informação sobre situação\n";
echo "Status: " . ($result === 'Olá José Ação, recebi: Informação sobre situação' ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 7: Extract variables
echo "Test 7: Extract variables\n";
$text = 'Olá {{contact_name}}, sua mensagem {{message}} foi recebida. Canal: {{channel}}';
$variables = VariableSubstitutor::extractVariables($text);
echo "Input: $text\n";
echo "Result: " . implode(', ', $variables) . "\n";
echo "Expected: contact_name, message, channel\n";
$expected_vars = ['contact_name', 'message', 'channel'];
$match = count($variables) === 3 && 
         in_array('contact_name', $variables) && 
         in_array('message', $variables) && 
         in_array('channel', $variables);
echo "Status: " . ($match ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 8: Validate variables - all available
echo "Test 8: Validate variables - all available\n";
$text = 'Olá {{contact_name}}, mensagem: {{message}}';
$context = [
    'contact_name' => 'João',
    'message' => 'Teste'
];
$result = VariableSubstitutor::validateVariables($text, $context);
echo "Input: $text\n";
echo "Context: contact_name, message\n";
echo "Result: " . ($result ? 'true' : 'false') . "\n";
echo "Expected: true\n";
echo "Status: " . ($result === true ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 9: Validate variables - some missing
echo "Test 9: Validate variables - some missing\n";
$text = 'Olá {{contact_name}}, email: {{contact_email}}';
$context = [
    'contact_name' => 'João'
];
$result = VariableSubstitutor::validateVariables($text, $context);
echo "Input: $text\n";
echo "Context: contact_name (missing: contact_email)\n";
echo "Result: " . ($result ? 'true' : 'false') . "\n";
echo "Expected: false\n";
echo "Status: " . ($result === false ? '✓ PASS' : '✗ FAIL') . "\n\n";

// Test 10: Empty context
echo "Test 10: Empty context\n";
$context = [];
$text = 'Olá {{contact_name}}, mensagem: {{message}}';
$result = VariableSubstitutor::substitute($text, $context);
echo "Input: $text\n";
echo "Result: $result\n";
echo "Expected: Olá , mensagem: \n";
echo "Status: " . ($result === 'Olá , mensagem: ' ? '✓ PASS' : '✗ FAIL') . "\n\n";

echo "=== Test Summary ===\n";
echo "All manual tests completed. Review results above.\n";
