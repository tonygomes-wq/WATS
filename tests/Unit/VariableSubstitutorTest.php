<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// Carregar a classe VariableSubstitutor
require_once __DIR__ . '/../../includes/VariableSubstitutor.php';

/**
 * Testes para VariableSubstitutor
 * 
 * Valida a substituição de variáveis de contexto em textos
 */
class VariableSubstitutorTest extends TestCase
{
    /**
     * Testa substituição básica de variáveis
     * Validates: Requirements 9.1, 9.2, 9.3
     */
    public function testBasicVariableSubstitution(): void
    {
        $context = [
            'contact_name' => 'João Silva',
            'contact_phone' => '5511999999999',
            'message' => 'Olá, preciso de ajuda'
        ];
        
        $text = 'Olá {{contact_name}}, recebi sua mensagem: {{message}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Olá João Silva, recebi sua mensagem: Olá, preciso de ajuda', $result);
    }
    
    /**
     * Testa substituição case-insensitive
     * Validates: Requirements 9.10
     */
    public function testCaseInsensitiveSubstitution(): void
    {
        $context = [
            'contact_name' => 'Maria Santos',
            'message' => 'Teste'
        ];
        
        // Variáveis com diferentes cases
        $text = '{{CONTACT_NAME}} - {{Contact_Name}} - {{contact_name}} - {{MESSAGE}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Maria Santos - Maria Santos - Maria Santos - Teste', $result);
    }
    
    /**
     * Testa substituição de variável não encontrada (deve retornar string vazia)
     * Validates: Requirements 9.9
     */
    public function testMissingVariableReplacedWithEmpty(): void
    {
        $context = [
            'contact_name' => 'João Silva'
        ];
        
        $text = 'Olá {{contact_name}}, seu email é {{contact_email}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Olá João Silva, seu email é ', $result);
    }
    
    /**
     * Testa substituição de todas as variáveis de contexto suportadas
     * Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8
     */
    public function testAllSupportedVariables(): void
    {
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
        
        $text = 'Nome: {{contact_name}}, Telefone: {{contact_phone}}, Mensagem: {{message}}, ' .
                'Resposta IA: {{ai_response}}, Timestamp: {{timestamp}}, Conversa: {{conversation_id}}, ' .
                'Histórico: {{history}}, Canal: {{channel}}';
        
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $expected = 'Nome: João Silva, Telefone: 5511999999999, Mensagem: Olá, ' .
                    'Resposta IA: Oi! Como posso ajudar?, Timestamp: 2024-01-15 10:30:00, Conversa: 123, ' .
                    'Histórico: Histórico de mensagens, Canal: whatsapp';
        
        $this->assertEquals($expected, $result);
    }
    
    /**
     * Testa substituição com histórico formatado como array
     * Validates: Requirements 9.7
     */
    public function testHistoryArrayFormatting(): void
    {
        $context = [
            'history' => [
                ['role' => 'user', 'text' => 'Olá'],
                ['role' => 'assistant', 'text' => 'Oi! Como posso ajudar?'],
                ['role' => 'user', 'text' => 'Preciso de informações']
            ]
        ];
        
        $text = 'Histórico:\n{{history}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $expected = "Histórico:\nUsuário: Olá\nAssistente: Oi! Como posso ajudar?\nUsuário: Preciso de informações";
        
        $this->assertEquals($expected, $result);
    }
    
    /**
     * Testa substituição com histórico usando campo 'message' ao invés de 'text'
     * Validates: Requirements 9.7
     */
    public function testHistoryArrayWithMessageField(): void
    {
        $context = [
            'history' => [
                ['role' => 'user', 'message' => 'Olá'],
                ['role' => 'assistant', 'message' => 'Oi!']
            ]
        ];
        
        $text = '{{history}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $expected = "Usuário: Olá\nAssistente: Oi!";
        
        $this->assertEquals($expected, $result);
    }
    
    /**
     * Testa substituição com array não-histórico (deve retornar JSON)
     */
    public function testNonHistoryArrayAsJson(): void
    {
        $context = [
            'tags' => ['vip', 'urgente', 'suporte']
        ];
        
        $text = 'Tags: {{tags}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Tags: ["vip","urgente","suporte"]', $result);
    }
    
    /**
     * Testa substituição com valores numéricos
     */
    public function testNumericValues(): void
    {
        $context = [
            'conversation_id' => 123,
            'message_count' => 5,
            'timestamp' => 1705320600
        ];
        
        $text = 'Conversa #{{conversation_id}} tem {{message_count}} mensagens ({{timestamp}})';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Conversa #123 tem 5 mensagens (1705320600)', $result);
    }
    
    /**
     * Testa substituição com valores booleanos
     */
    public function testBooleanValues(): void
    {
        $context = [
            'is_active' => true,
            'is_blocked' => false
        ];
        
        $text = 'Ativo: {{is_active}}, Bloqueado: {{is_blocked}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Ativo: 1, Bloqueado: ', $result);
    }
    
    /**
     * Testa substituição com caracteres UTF-8
     */
    public function testUTF8Support(): void
    {
        $context = [
            'contact_name' => 'José Ação',
            'message' => 'Informação sobre situação'
        ];
        
        $text = 'Olá {{contact_name}}, recebi: {{message}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Olá José Ação, recebi: Informação sobre situação', $result);
    }
    
    /**
     * Testa substituição com múltiplas ocorrências da mesma variável
     */
    public function testMultipleOccurrencesOfSameVariable(): void
    {
        $context = [
            'contact_name' => 'João'
        ];
        
        $text = 'Olá {{contact_name}}! Como vai, {{contact_name}}? Até logo, {{contact_name}}.';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Olá João! Como vai, João? Até logo, João.', $result);
    }
    
    /**
     * Testa substituição com texto sem variáveis
     */
    public function testTextWithoutVariables(): void
    {
        $context = [
            'contact_name' => 'João'
        ];
        
        $text = 'Este texto não tem variáveis';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Este texto não tem variáveis', $result);
    }
    
    /**
     * Testa substituição com texto vazio
     */
    public function testEmptyText(): void
    {
        $context = [
            'contact_name' => 'João'
        ];
        
        $text = '';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('', $result);
    }
    
    /**
     * Testa substituição com contexto vazio
     */
    public function testEmptyContext(): void
    {
        $context = [];
        
        $text = 'Olá {{contact_name}}, mensagem: {{message}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Olá , mensagem: ', $result);
    }
    
    /**
     * Testa substituição com variáveis adjacentes
     */
    public function testAdjacentVariables(): void
    {
        $context = [
            'first_name' => 'João',
            'last_name' => 'Silva'
        ];
        
        $text = '{{first_name}}{{last_name}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('JoãoSilva', $result);
    }
    
    /**
     * Testa substituição com variáveis contendo underscores
     */
    public function testVariablesWithUnderscores(): void
    {
        $context = [
            'contact_name' => 'João',
            'contact_phone' => '5511999999999',
            'ai_response' => 'Resposta'
        ];
        
        $text = '{{contact_name}} - {{contact_phone}} - {{ai_response}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('João - 5511999999999 - Resposta', $result);
    }
    
    /**
     * Testa que variáveis inválidas não são substituídas
     */
    public function testInvalidVariableSyntax(): void
    {
        $context = [
            'name' => 'João'
        ];
        
        // Variáveis com sintaxe inválida não devem ser substituídas
        $text = '{name} {{name} {{{name}}} {{ name }} {{123name}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        // Apenas variáveis válidas são substituídas
        $this->assertEquals('{name} {{name} {{{name}}} {{ name }} {{123name}}', $result);
    }
    
    /**
     * Testa extração de variáveis de um texto
     */
    public function testExtractVariables(): void
    {
        $text = 'Olá {{contact_name}}, sua mensagem {{message}} foi recebida. Canal: {{channel}}';
        $variables = \VariableSubstitutor::extractVariables($text);
        
        $this->assertCount(3, $variables);
        $this->assertContains('contact_name', $variables);
        $this->assertContains('message', $variables);
        $this->assertContains('channel', $variables);
    }
    
    /**
     * Testa extração de variáveis duplicadas (deve retornar lista única)
     */
    public function testExtractVariablesUnique(): void
    {
        $text = '{{name}} {{name}} {{name}}';
        $variables = \VariableSubstitutor::extractVariables($text);
        
        $this->assertCount(1, $variables);
        $this->assertContains('name', $variables);
    }
    
    /**
     * Testa extração de variáveis de texto sem variáveis
     */
    public function testExtractVariablesFromTextWithoutVariables(): void
    {
        $text = 'Este texto não tem variáveis';
        $variables = \VariableSubstitutor::extractVariables($text);
        
        $this->assertCount(0, $variables);
    }
    
    /**
     * Testa validação de variáveis - todas disponíveis
     */
    public function testValidateVariablesAllAvailable(): void
    {
        $text = 'Olá {{contact_name}}, mensagem: {{message}}';
        $context = [
            'contact_name' => 'João',
            'message' => 'Teste'
        ];
        
        $this->assertTrue(\VariableSubstitutor::validateVariables($text, $context));
    }
    
    /**
     * Testa validação de variáveis - algumas faltando
     */
    public function testValidateVariablesSomeMissing(): void
    {
        $text = 'Olá {{contact_name}}, email: {{contact_email}}';
        $context = [
            'contact_name' => 'João'
        ];
        
        $this->assertFalse(\VariableSubstitutor::validateVariables($text, $context));
    }
    
    /**
     * Testa validação de variáveis - texto sem variáveis
     */
    public function testValidateVariablesNoVariables(): void
    {
        $text = 'Texto sem variáveis';
        $context = [];
        
        $this->assertTrue(\VariableSubstitutor::validateVariables($text, $context));
    }
    
    /**
     * Testa validação case-insensitive
     */
    public function testValidateVariablesCaseInsensitive(): void
    {
        $text = 'Olá {{CONTACT_NAME}}';
        $context = [
            'contact_name' => 'João'
        ];
        
        $this->assertTrue(\VariableSubstitutor::validateVariables($text, $context));
    }
    
    /**
     * Testa substituição com histórico vazio
     */
    public function testEmptyHistoryArray(): void
    {
        $context = [
            'history' => []
        ];
        
        $text = 'Histórico: {{history}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Histórico: []', $result);
    }
    
    /**
     * Testa substituição com valor null
     */
    public function testNullValue(): void
    {
        $context = [
            'contact_email' => null
        ];
        
        $text = 'Email: {{contact_email}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Email: ', $result);
    }
    
    /**
     * Testa substituição com caracteres especiais no valor
     */
    public function testSpecialCharactersInValue(): void
    {
        $context = [
            'message' => 'Teste com $pecial ch@rs & símbolos!'
        ];
        
        $text = 'Mensagem: {{message}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals('Mensagem: Teste com $pecial ch@rs & símbolos!', $result);
    }
    
    /**
     * Testa substituição com quebras de linha no valor
     */
    public function testNewlinesInValue(): void
    {
        $context = [
            'message' => "Linha 1\nLinha 2\nLinha 3"
        ];
        
        $text = 'Mensagem:\n{{message}}';
        $result = \VariableSubstitutor::substitute($text, $context);
        
        $this->assertEquals("Mensagem:\nLinha 1\nLinha 2\nLinha 3", $result);
    }
}
