<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/AIProcessor.php';
require_once __DIR__ . '/../../includes/VariableSubstitutor.php';

/**
 * Unit tests for AIProcessor
 * 
 * Tests the AI processing functionality including:
 * - Provider selection
 * - Prompt preparation with variable substitution
 * - Context preparation with conversation history
 */
class AIProcessorTest extends TestCase
{
    private PDO $pdo;
    private AIProcessor $processor;
    
    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create necessary tables
        $this->createTables();
        
        // Create processor instance
        $this->processor = new AIProcessor($this->pdo);
    }
    
    private function createTables(): void
    {
        // Create chat_messages table
        $this->pdo->exec("
            CREATE TABLE chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                direction TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    /**
     * Test that process() returns expected structure when AI is disabled
     */
    public function testProcessReturnsEmptyWhenDisabled(): void
    {
        $agentConfig = ['enabled' => false];
        $context = ['message' => 'Hello'];
        
        $result = $this->processor->process($agentConfig, $context);
        
        $this->assertFalse($result['success']);
        $this->assertNull($result['response']);
        $this->assertNull($result['prompt']);
    }
    
    /**
     * Test that process() selects OpenAI as default provider
     */
    public function testProcessSelectsOpenAIAsDefault(): void
    {
        $agentConfig = [
            'enabled' => true,
            'prompt' => 'Test prompt'
        ];
        $context = ['message' => 'Hello'];
        
        $result = $this->processor->process($agentConfig, $context);
        
        $this->assertEquals('openai', $result['provider']);
    }
    
    /**
     * Test that process() selects specified provider
     */
    public function testProcessSelectsSpecifiedProvider(): void
    {
        $agentConfig = [
            'enabled' => true,
            'provider' => 'gemini',
            'prompt' => 'Test prompt'
        ];
        $context = ['message' => 'Hello'];
        
        $result = $this->processor->process($agentConfig, $context);
        
        $this->assertEquals('gemini', $result['provider']);
    }
    
    /**
     * Test that process() defaults to OpenAI for invalid provider
     */
    public function testProcessDefaultsToOpenAIForInvalidProvider(): void
    {
        $agentConfig = [
            'enabled' => true,
            'provider' => 'invalid_provider',
            'prompt' => 'Test prompt'
        ];
        $context = ['message' => 'Hello'];
        
        $result = $this->processor->process($agentConfig, $context);
        
        $this->assertEquals('openai', $result['provider']);
    }
    
    /**
     * Test that process() substitutes variables in prompt
     */
    public function testProcessSubstitutesVariablesInPrompt(): void
    {
        $agentConfig = [
            'enabled' => true,
            'prompt' => 'Hello {{contact_name}}, your message: {{message}}'
        ];
        $context = [
            'contact_name' => 'John',
            'message' => 'Help me'
        ];
        
        $result = $this->processor->process($agentConfig, $context);
        
        $this->assertEquals('Hello John, your message: Help me', $result['prompt']);
    }
    
    /**
     * Test that process() includes conversation history in context
     */
    public function testProcessIncludesConversationHistory(): void
    {
        // Insert test messages
        $conversationId = 1;
        $this->pdo->exec("
            INSERT INTO chat_messages (conversation_id, message, direction, created_at)
            VALUES 
                ($conversationId, 'Hello', 'incoming', '2024-01-01 10:00:00'),
                ($conversationId, 'Hi there!', 'outgoing', '2024-01-01 10:00:01'),
                ($conversationId, 'How are you?', 'incoming', '2024-01-01 10:00:02')
        ");
        
        $agentConfig = [
            'enabled' => true,
            'prompt' => 'History: {{history}}'
        ];
        $context = [
            'conversation_id' => $conversationId,
            'message' => 'New message'
        ];
        
        $result = $this->processor->process($agentConfig, $context);
        
        // Verify history is included in prompt
        $this->assertStringContainsString('Usuário: Hello', $result['prompt']);
        $this->assertStringContainsString('Assistente: Hi there!', $result['prompt']);
        $this->assertStringContainsString('Usuário: How are you?', $result['prompt']);
    }
    
    /**
     * Test that process() handles missing prompt gracefully
     */
    public function testProcessHandlesMissingPrompt(): void
    {
        $agentConfig = [
            'enabled' => true
            // No prompt specified
        ];
        $context = ['message' => 'Hello'];
        
        $result = $this->processor->process($agentConfig, $context);
        
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Prompt is required', $result['error']);
    }
    
    /**
     * Test that process() adds formatted timestamp to context
     */
    public function testProcessAddsFormattedTimestamp(): void
    {
        $agentConfig = [
            'enabled' => true,
            'prompt' => 'Time: {{timestamp}}'
        ];
        $context = [
            'message' => 'Hello',
            'timestamp' => 1704110400 // 2024-01-01 12:00:00
        ];
        
        $result = $this->processor->process($agentConfig, $context);
        
        // Verify timestamp is formatted
        $this->assertStringContainsString('2024-01-01', $result['prompt']);
    }
    
    /**
     * Test that process() handles empty conversation history
     */
    public function testProcessHandlesEmptyHistory(): void
    {
        $agentConfig = [
            'enabled' => true,
            'prompt' => 'History: {{history}}'
        ];
        $context = [
            'conversation_id' => 999, // Non-existent conversation
            'message' => 'Hello'
        ];
        
        $result = $this->processor->process($agentConfig, $context);
        
        // Should not fail, just have empty history
        $this->assertNotNull($result['prompt']);
        $this->assertEquals('History: ', $result['prompt']);
    }
    
    /**
     * Test that process() returns execution time
     */
    public function testProcessReturnsExecutionTime(): void
    {
        $agentConfig = [
            'enabled' => true,
            'prompt' => 'Test'
        ];
        $context = ['message' => 'Hello'];
        
        $result = $this->processor->process($agentConfig, $context);
        
        $this->assertArrayHasKey('execution_time_ms', $result);
        $this->assertIsNumeric($result['execution_time_ms']);
        $this->assertGreaterThanOrEqual(0, $result['execution_time_ms']);
    }
    
    /**
     * Test that process() handles provider case-insensitively
     */
    public function testProcessHandlesProviderCaseInsensitively(): void
    {
        $agentConfig = [
            'enabled' => true,
            'provider' => 'OpenAI',
            'prompt' => 'Test'
        ];
        $context = ['message' => 'Hello'];
        
        $result = $this->processor->process($agentConfig, $context);
        
        $this->assertEquals('openai', $result['provider']);
    }
    
    /**
     * Test that process() limits history to 10 messages
     */
    public function testProcessLimitsHistoryTo10Messages(): void
    {
        $conversationId = 1;
        
        // Insert 15 messages
        for ($i = 1; $i <= 15; $i++) {
            $this->pdo->exec("
                INSERT INTO chat_messages (conversation_id, message, direction, created_at)
                VALUES ($conversationId, 'Message $i', 'incoming', '2024-01-01 10:00:$i')
            ");
        }
        
        $agentConfig = [
            'enabled' => true,
            'prompt' => '{{history}}'
        ];
        $context = [
            'conversation_id' => $conversationId,
            'message' => 'New message'
        ];
        
        $result = $this->processor->process($agentConfig, $context);
        
        // Count number of "Usuário:" occurrences in history
        $count = substr_count($result['prompt'], 'Usuário:');
        $this->assertEquals(10, $count, 'History should be limited to 10 messages');
        
        // Verify it includes the most recent messages (6-15)
        $this->assertStringContainsString('Message 15', $result['prompt']);
        $this->assertStringNotContainsString('Message 1', $result['prompt']);
    }
}
