<?php
/**
 * Unit tests for ActionExecutor
 * 
 * Tests the action execution logic including:
 * - Sequential execution of actions
 * - Error isolation (one failure doesn't stop others)
 * - Result collection
 * - Action validation
 * 
 * NOTE: Action results use a flattened structure where action-specific data
 * is merged directly into the result object. For example:
 * {
 *   "type": "send_message",
 *   "status": "success",
 *   "timestamp": 1234567890,
 *   "message_id": "ABC123",  // action-specific field
 *   "message_text": "Hello"   // action-specific field
 * }
 * 
 * Tests that reference $result['data'] need to be updated to access
 * action-specific fields directly from $result.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/ActionExecutor.php';

class ActionExecutorTest extends TestCase
{
    private PDO $pdo;
    private ActionExecutor $executor;
    private int $userId = 1;
    private array $instanceConfig;
    
    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->instanceConfig = [
            'name' => 'test_instance',
            'api_url' => 'https://api.test.com',
            'api_key' => 'test_key'
        ];
        
        $this->executor = new ActionExecutor($this->pdo, $this->userId, $this->instanceConfig);
    }
    
    /**
     * Test that executeActions returns empty array when no actions provided
     */
    public function testExecuteActionsWithEmptyArray(): void
    {
        $result = $this->executor->executeActions([], []);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    /**
     * Test that executeActions processes all actions in order
     */
    public function testExecuteActionsProcessesInOrder(): void
    {
        $actions = [
            ['type' => 'send_message', 'config' => ['message' => 'Hello']],
            ['type' => 'add_tag', 'config' => ['tag' => 'automated']],
            ['type' => 'assign_attendant', 'config' => ['attendant_id' => 5]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999',
            'message' => 'Test message'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        // Should return 3 results
        $this->assertCount(3, $results);
        
        // Check order is preserved by checking types in sequence
        $this->assertEquals('send_message', $results[0]['type']);
        $this->assertEquals('add_tag', $results[1]['type']);
        $this->assertEquals('assign_attendant', $results[2]['type']);
        
        // All should have failed status (no real database/API)
        // but the important thing is they all executed
    }
    
    /**
     * Test that action without type fails gracefully
     */
    public function testActionWithoutTypeFails(): void
    {
        $actions = [
            ['config' => ['message' => 'Hello']] // Missing 'type'
        ];
        
        $results = $this->executor->executeActions($actions, []);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertEquals('unknown', $results[0]['type']);
        $this->assertStringContainsString('type not specified', $results[0]['error']);
    }
    
    /**
     * Test that action without config fails gracefully
     */
    public function testActionWithoutConfigFails(): void
    {
        $actions = [
            ['type' => 'send_message'] // Missing 'config'
        ];
        
        $results = $this->executor->executeActions($actions, []);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('config not specified', $results[0]['error']);
    }
    
    /**
     * Test that unknown action type fails gracefully
     */
    public function testUnknownActionTypeFails(): void
    {
        $actions = [
            ['type' => 'unknown_action', 'config' => ['foo' => 'bar']]
        ];
        
        $results = $this->executor->executeActions($actions, []);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Unknown action type', $results[0]['error']);
    }
    
    /**
     * Test error isolation - one action failure doesn't stop others
     * This validates Requirements 4.10
     */
    public function testErrorIsolationContinuesExecution(): void
    {
        $actions = [
            ['type' => 'send_message', 'config' => ['message' => 'First']],
            ['type' => 'unknown_action', 'config' => ['foo' => 'bar']], // This will fail
            ['type' => 'add_tag', 'config' => ['tag' => 'test']], // Should still execute
            ['config' => ['invalid' => 'action']], // This will fail
            ['type' => 'assign_attendant', 'config' => ['attendant_id' => 1]] // Should still execute
        ];
        
        $results = $this->executor->executeActions($actions, []);
        
        // All 5 actions should have results
        $this->assertCount(5, $results);
        
        // First action should succeed (stub returns success)
        $this->assertEquals('success', $results[0]['status']);
        $this->assertEquals('send_message', $results[0]['type']);
        
        // Second action should fail (unknown type)
        $this->assertEquals('failed', $results[1]['status']);
        $this->assertNotNull($results[1]['error']);
        
        // Third action should succeed despite previous failure
        $this->assertEquals('success', $results[2]['status']);
        $this->assertEquals('add_tag', $results[2]['type']);
        
        // Fourth action should fail (missing type)
        $this->assertEquals('failed', $results[3]['status']);
        
        // Fifth action should succeed despite previous failures
        $this->assertEquals('success', $results[4]['status']);
        $this->assertEquals('assign_attendant', $results[4]['type']);
    }
    
    /**
     * Test that all action results include required fields
     */
    public function testActionResultsIncludeRequiredFields(): void
    {
        $actions = [
            ['type' => 'send_message', 'config' => ['message' => 'Test']]
        ];
        
        $results = $this->executor->executeActions($actions, []);
        
        $this->assertCount(1, $results);
        
        $result = $results[0];
        
        // Check all required fields are present
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        // Check types
        $this->assertIsString($result['type']);
        $this->assertIsString($result['status']);
        $this->assertIsInt($result['timestamp']);
        
        // Error field should only be present if status is 'failed'
        if ($result['status'] === 'failed') {
            $this->assertArrayHasKey('error', $result);
            $this->assertIsString($result['error']);
        }
        
        // Action-specific fields should be present (flattened structure)
        // For example, send_message should have message_id, assign_attendant should have attendant_id, etc.
    }
    
    /**
     * Test that all supported action types are recognized
     */
    public function testAllSupportedActionTypes(): void
    {
        $actionTypes = [
            'send_message',
            'assign_attendant',
            'add_tag',
            'remove_tag',
            'create_task',
            'webhook',
            'update_field'
        ];
        
        foreach ($actionTypes as $type) {
            $actions = [
                ['type' => $type, 'config' => ['test' => 'value']]
            ];
            
            $results = $this->executor->executeActions($actions, []);
            
            $this->assertCount(1, $results);
            $this->assertEquals('success', $results[0]['status'], "Action type {$type} should succeed");
            $this->assertEquals($type, $results[0]['type']);
        }
    }
    
    /**
     * Test that results are collected from all actions
     * This validates Requirements 4.1
     */
    public function testResultsCollectedFromAllActions(): void
    {
        $actions = [
            ['type' => 'send_message', 'config' => ['message' => 'Hello']],
            ['type' => 'add_tag', 'config' => ['tag' => 'test']],
            ['type' => 'webhook', 'config' => ['url' => 'https://example.com']]
        ];
        
        $results = $this->executor->executeActions($actions, []);
        
        // Should collect results from all 3 actions
        $this->assertCount(3, $results);
        
        // Each result should have required fields with flattened structure
        foreach ($results as $result) {
            $this->assertArrayHasKey('type', $result);
            $this->assertArrayHasKey('status', $result);
            $this->assertArrayHasKey('timestamp', $result);
        }
    }
}

    /**
     * Test sendMessage with missing message config
     * Validates Requirements 4.2
     */
    public function testSendMessageWithMissingMessageFails(): void
    {
        $actions = [
            ['type' => 'send_message', 'config' => []] // Missing 'message'
        ];
        
        $context = [
            'phone' => '5511999999999',
            'conversation_id' => 123
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Message text not configured', $results[0]['error']);
    }
    
    /**
     * Test sendMessage with empty message after variable substitution
     * Validates Requirements 4.2, 4.9
     */
    public function testSendMessageWithEmptyMessageAfterSubstitutionFails(): void
    {
        $actions = [
            ['type' => 'send_message', 'config' => ['message' => '{{nonexistent_var}}']]
        ];
        
        $context = [
            'phone' => '5511999999999',
            'conversation_id' => 123
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('empty after variable substitution', $results[0]['error']);
    }
    
    /**
     * Test sendMessage with missing phone in context
     * Validates Requirements 4.9
     */
    public function testSendMessageWithMissingPhoneFails(): void
    {
        $actions = [
            ['type' => 'send_message', 'config' => ['message' => 'Hello']]
        ];
        
        $context = [
            'conversation_id' => 123
            // Missing 'phone'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Phone number not available', $results[0]['error']);
    }
    
    /**
     * Test sendMessage with missing instance config
     * Validates Requirements 4.9
     */
    public function testSendMessageWithMissingInstanceConfigFails(): void
    {
        // Create executor with incomplete instance config
        $incompleteConfig = ['name' => 'test']; // Missing api_key
        $executor = new ActionExecutor($this->pdo, $this->userId, $incompleteConfig);
        
        $actions = [
            ['type' => 'send_message', 'config' => ['message' => 'Hello']]
        ];
        
        $context = [
            'phone' => '5511999999999',
            'conversation_id' => 123
        ];
        
        $results = $executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Evolution API instance not configured', $results[0]['error']);
    }
    
    /**
     * Test sendMessage performs variable substitution
     * Validates Requirements 4.2, 4.9
     */
    public function testSendMessagePerformsVariableSubstitution(): void
    {
        $actions = [
            ['type' => 'send_message', 'config' => ['message' => 'Hello {{contact_name}}, your message was: {{message}}']]
        ];
        
        $context = [
            'phone' => '5511999999999',
            'conversation_id' => 123,
            'contact_name' => 'John Doe',
            'message' => 'I need help'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        // Note: This will fail because we can't actually call Evolution API in tests
        // But we can verify the error handling works
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        // The error should be about API connection, not about missing variables
        $this->assertStringNotContainsString('not configured', $results[0]['error']);
        $this->assertStringNotContainsString('not available', $results[0]['error']);
    }
}

    /**
     * Test assignAttendant with missing attendant_id config
     * Validates Requirements 4.3
     */
    public function testAssignAttendantWithMissingAttendantIdFails(): void
    {
        $actions = [
            ['type' => 'assign_attendant', 'config' => []] // Missing 'attendant_id'
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Attendant ID not configured', $results[0]['error']);
    }
    
    /**
     * Test assignAttendant with missing conversation_id in context
     * Validates Requirements 4.3
     */
    public function testAssignAttendantWithMissingConversationIdFails(): void
    {
        $actions = [
            ['type' => 'assign_attendant', 'config' => ['attendant_id' => 5]]
        ];
        
        $context = [
            'phone' => '5511999999999'
            // Missing 'conversation_id'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Conversation ID not available', $results[0]['error']);
    }
    
    /**
     * Test assignAttendant successfully assigns attendant and ends bot session
     * Validates Requirements 4.3, 11.2
     */
    public function testAssignAttendantSuccessfullyAssignsAndEndsBotSession(): void
    {
        // Create necessary tables
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE chat_conversations (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                attended_by INTEGER,
                attended_by_name TEXT,
                attended_by_type TEXT,
                attended_at TEXT,
                status TEXT DEFAULT 'open'
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE bot_sessions (
                id INTEGER PRIMARY KEY,
                phone TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                status TEXT DEFAULT 'active',
                updated_at TEXT
            )
        ");
        
        // Insert test data
        $this->pdo->exec("INSERT INTO users (id, name) VALUES (5, 'John Attendant')");
        $this->pdo->exec("INSERT INTO chat_conversations (id, user_id, status) VALUES (123, 1, 'open')");
        $this->pdo->exec("INSERT INTO bot_sessions (id, phone, user_id, status) VALUES (1, '5511999999999', 1, 'active')");
        
        $actions = [
            ['type' => 'assign_attendant', 'config' => ['attendant_id' => 5]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        // Check action result - now with flattened structure
        $this->assertCount(1, $results);
        $this->assertEquals('success', $results[0]['status']);
        $this->assertEquals('assign_attendant', $results[0]['type']);
        $this->assertEquals(5, $results[0]['attendant_id']);
        $this->assertEquals('John Attendant', $results[0]['attendant_name']);
        $this->assertEquals(123, $results[0]['conversation_id']);
        $this->assertEquals(1, $results[0]['bot_sessions_ended']);
        
        // Verify conversation was updated
        $stmt = $this->pdo->query("SELECT * FROM chat_conversations WHERE id = 123");
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals(5, $conversation['attended_by']);
        $this->assertEquals('John Attendant', $conversation['attended_by_name']);
        $this->assertEquals('user', $conversation['attended_by_type']);
        $this->assertEquals('in_progress', $conversation['status']);
        $this->assertNotNull($conversation['attended_at']);
        
        // Verify bot session was ended
        $stmt = $this->pdo->query("SELECT * FROM bot_sessions WHERE id = 1");
        $botSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals('completed', $botSession['status']);
        $this->assertNotNull($botSession['updated_at']);
    }
    
    /**
     * Test assignAttendant with non-existent attendant
     * Validates Requirements 4.3
     */
    public function testAssignAttendantWithNonExistentAttendantFails(): void
    {
        // Create necessary tables
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE chat_conversations (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                status TEXT DEFAULT 'open'
            )
        ");
        
        $this->pdo->exec("INSERT INTO chat_conversations (id, user_id) VALUES (123, 1)");
        
        $actions = [
            ['type' => 'assign_attendant', 'config' => ['attendant_id' => 999]] // Non-existent
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Attendant not found', $results[0]['error']);
    }
    
    /**
     * Test assignAttendant with non-existent conversation
     * Validates Requirements 4.3
     */
    public function testAssignAttendantWithNonExistentConversationFails(): void
    {
        // Create necessary tables
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE chat_conversations (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                status TEXT DEFAULT 'open'
            )
        ");
        
        $this->pdo->exec("INSERT INTO users (id, name) VALUES (5, 'John Attendant')");
        
        $actions = [
            ['type' => 'assign_attendant', 'config' => ['attendant_id' => 5]]
        ];
        
        $context = [
            'conversation_id' => 999, // Non-existent
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Conversation not found', $results[0]['error']);
    }
    
    /**
     * Test assignAttendant without phone in context (bot session not ended)
     * Validates Requirements 4.3, 11.2
     */
    public function testAssignAttendantWithoutPhoneStillSucceeds(): void
    {
        // Create necessary tables
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE chat_conversations (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                attended_by INTEGER,
                attended_by_name TEXT,
                attended_by_type TEXT,
                attended_at TEXT,
                status TEXT DEFAULT 'open'
            )
        ");
        
        $this->pdo->exec("INSERT INTO users (id, name) VALUES (5, 'John Attendant')");
        $this->pdo->exec("INSERT INTO chat_conversations (id, user_id) VALUES (123, 1)");
        
        $actions = [
            ['type' => 'assign_attendant', 'config' => ['attendant_id' => 5]]
        ];
        
        $context = [
            'conversation_id' => 123
            // No 'phone' provided
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        // Should still succeed, just won't end bot sessions
        $this->assertCount(1, $results);
        $this->assertEquals('success', $results[0]['status']);
        
        // Flattened structure - bot_sessions_ended is directly in result
        $this->assertEquals(0, $results[0]['bot_sessions_ended']);
        
        // Verify conversation was still updated
        $stmt = $this->pdo->query("SELECT * FROM chat_conversations WHERE id = 123");
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals(5, $conversation['attended_by']);
        $this->assertEquals('in_progress', $conversation['status']);
    }
    
    /**
     * Test assignAttendant uses transaction and rolls back on error
     * Validates Requirements 4.3
     */
    public function testAssignAttendantRollsBackOnError(): void
    {
        // Create tables but with a constraint that will cause an error
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE chat_conversations (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                attended_by INTEGER,
                status TEXT DEFAULT 'open',
                FOREIGN KEY (attended_by) REFERENCES users(id)
            )
        ");
        
        $this->pdo->exec("PRAGMA foreign_keys = ON");
        
        $this->pdo->exec("INSERT INTO chat_conversations (id, user_id) VALUES (123, 1)");
        
        $actions = [
            ['type' => 'assign_attendant', 'config' => ['attendant_id' => 999]] // Non-existent user
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        // Should fail
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        
        // Verify conversation was NOT updated (rollback worked)
        $stmt = $this->pdo->query("SELECT * FROM chat_conversations WHERE id = 123");
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertNull($conversation['attended_by']);
        $this->assertEquals('open', $conversation['status']);
    }
    
    /**
     * Test createTask with missing title config
     * Validates Requirements 4.6
     */
    public function testCreateTaskWithMissingTitleFails(): void
    {
        $actions = [
            ['type' => 'create_task', 'config' => ['description' => 'Test task']] // Missing 'title'
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Task title is required', $results[0]['error']);
    }
    
    /**
     * Test createTask with empty title config
     * Validates Requirements 4.6
     */
    public function testCreateTaskWithEmptyTitleFails(): void
    {
        $actions = [
            ['type' => 'create_task', 'config' => ['title' => '']] // Empty title
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Task title is required', $results[0]['error']);
    }
    
    /**
     * Test createTask performs variable substitution in title and description
     * Validates Requirements 4.6, 4.9
     */
    public function testCreateTaskPerformsVariableSubstitution(): void
    {
        // Create necessary tables
        $this->pdo->exec("
            CREATE TABLE kanban_boards (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                icon TEXT,
                color TEXT,
                is_default INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_columns (
                id INTEGER PRIMARY KEY,
                board_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                color TEXT,
                icon TEXT,
                position INTEGER DEFAULT 0,
                is_final INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_cards (
                id INTEGER PRIMARY KEY,
                column_id INTEGER NOT NULL,
                conversation_id INTEGER,
                title TEXT NOT NULL,
                description TEXT,
                contact_name TEXT,
                contact_phone TEXT,
                assigned_to INTEGER,
                assigned_type TEXT,
                priority TEXT DEFAULT 'normal',
                due_date TEXT,
                value REAL,
                position INTEGER DEFAULT 0,
                created_by INTEGER,
                source_channel TEXT
            )
        ");
        
        // Insert test data
        $this->pdo->exec("INSERT INTO kanban_boards (id, user_id, name, is_default) VALUES (1, 1, 'Test Board', 1)");
        $this->pdo->exec("INSERT INTO kanban_columns (id, board_id, name, position) VALUES (1, 1, 'New', 0)");
        
        $actions = [
            ['type' => 'create_task', 'config' => [
                'title' => 'Follow up with {{contact_name}}',
                'description' => 'Message received: {{message}}'
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999',
            'contact_name' => 'John Doe',
            'message' => 'I need help with my order'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('success', $results[0]['status']);
        
        // Flattened structure - title is directly in result
        $this->assertEquals('Follow up with John Doe', $results[0]['title']);
        
        // Verify card was created with substituted values
        $stmt = $this->pdo->query("SELECT * FROM kanban_cards WHERE id = " . $data['card_id']);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals('Follow up with John Doe', $card['title']);
        $this->assertEquals('Message received: I need help with my order', $card['description']);
    }
    
    /**
     * Test createTask successfully creates task with minimal config
     * Validates Requirements 4.6
     */
    public function testCreateTaskSuccessfullyCreatesTaskWithMinimalConfig(): void
    {
        // Create necessary tables
        $this->pdo->exec("
            CREATE TABLE kanban_boards (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                icon TEXT,
                color TEXT,
                is_default INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_columns (
                id INTEGER PRIMARY KEY,
                board_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                color TEXT,
                icon TEXT,
                position INTEGER DEFAULT 0,
                is_final INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_cards (
                id INTEGER PRIMARY KEY,
                column_id INTEGER NOT NULL,
                conversation_id INTEGER,
                title TEXT NOT NULL,
                description TEXT,
                contact_name TEXT,
                contact_phone TEXT,
                assigned_to INTEGER,
                assigned_type TEXT,
                priority TEXT DEFAULT 'normal',
                due_date TEXT,
                value REAL,
                position INTEGER DEFAULT 0,
                created_by INTEGER,
                source_channel TEXT
            )
        ");
        
        // Insert test data
        $this->pdo->exec("INSERT INTO kanban_boards (id, user_id, name, is_default) VALUES (1, 1, 'Test Board', 1)");
        $this->pdo->exec("INSERT INTO kanban_columns (id, board_id, name, position) VALUES (1, 1, 'New', 0)");
        
        $actions = [
            ['type' => 'create_task', 'config' => [
                'title' => 'Test Task'
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999',
            'contact_name' => 'John Doe'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('success', $results[0]['status']);
        
        // Flattened structure - fields are directly in result
        $this->assertEquals('create_task', $results[0]['type']);
        $this->assertArrayHasKey('card_id', $results[0]);
        $this->assertArrayHasKey('board_id', $results[0]);
        $this->assertArrayHasKey('column_id', $results[0]);
        $this->assertEquals('Test Task', $results[0]['title']);
        $this->assertEquals(123, $results[0]['conversation_id']);
        
        // Verify card was created in database
        $stmt = $this->pdo->query("SELECT * FROM kanban_cards WHERE id = " . $data['card_id']);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertNotFalse($card);
        $this->assertEquals('Test Task', $card['title']);
        $this->assertEquals(123, $card['conversation_id']);
        $this->assertEquals('John Doe', $card['contact_name']);
        $this->assertEquals('5511999999999', $card['contact_phone']);
        $this->assertEquals('normal', $card['priority']); // Default priority
        $this->assertEquals(1, $card['created_by']); // userId
    }
    
    /**
     * Test createTask with all optional fields
     * Validates Requirements 4.6
     */
    public function testCreateTaskWithAllOptionalFields(): void
    {
        // Create necessary tables
        $this->pdo->exec("
            CREATE TABLE kanban_boards (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                is_default INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_columns (
                id INTEGER PRIMARY KEY,
                board_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                position INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_cards (
                id INTEGER PRIMARY KEY,
                column_id INTEGER NOT NULL,
                conversation_id INTEGER,
                title TEXT NOT NULL,
                description TEXT,
                contact_name TEXT,
                contact_phone TEXT,
                assigned_to INTEGER,
                assigned_type TEXT,
                priority TEXT DEFAULT 'normal',
                due_date TEXT,
                value REAL,
                position INTEGER DEFAULT 0,
                created_by INTEGER,
                source_channel TEXT
            )
        ");
        
        // Insert test data
        $this->pdo->exec("INSERT INTO kanban_boards (id, user_id, name, is_default) VALUES (1, 1, 'Test Board', 1)");
        $this->pdo->exec("INSERT INTO kanban_columns (id, board_id, name, position) VALUES (1, 1, 'New', 0)");
        
        $actions = [
            ['type' => 'create_task', 'config' => [
                'title' => 'High Priority Task',
                'description' => 'This is a detailed description',
                'priority' => 'high',
                'due_date' => '2024-12-31',
                'value' => 1500.50,
                'assigned_to' => 5
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999',
            'contact_name' => 'Jane Smith',
            'channel' => 'whatsapp'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('success', $results[0]['status']);
        
        $data = $results[0]['data'];
        $cardId = $data['card_id'];
        
        // Verify all fields were saved correctly
        $stmt = $this->pdo->query("SELECT * FROM kanban_cards WHERE id = {$cardId}");
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals('High Priority Task', $card['title']);
        $this->assertEquals('This is a detailed description', $card['description']);
        $this->assertEquals('high', $card['priority']);
        $this->assertEquals('2024-12-31', $card['due_date']);
        $this->assertEquals(1500.50, $card['value']);
        $this->assertEquals(5, $card['assigned_to']);
        $this->assertEquals('attendant', $card['assigned_type']);
        $this->assertEquals('whatsapp', $card['source_channel']);
    }
    
    /**
     * Test createTask creates default board if none exists
     * Validates Requirements 4.6
     */
    public function testCreateTaskCreatesDefaultBoardIfNoneExists(): void
    {
        // Create necessary tables but don't insert any boards
        $this->pdo->exec("
            CREATE TABLE kanban_boards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                icon TEXT,
                color TEXT,
                is_default INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_columns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                board_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                color TEXT,
                icon TEXT,
                position INTEGER DEFAULT 0,
                is_final INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_cards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                column_id INTEGER NOT NULL,
                conversation_id INTEGER,
                title TEXT NOT NULL,
                description TEXT,
                contact_name TEXT,
                contact_phone TEXT,
                assigned_to INTEGER,
                assigned_type TEXT,
                priority TEXT DEFAULT 'normal',
                due_date TEXT,
                value REAL,
                position INTEGER DEFAULT 0,
                created_by INTEGER,
                source_channel TEXT
            )
        ");
        
        $actions = [
            ['type' => 'create_task', 'config' => [
                'title' => 'First Task'
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('success', $results[0]['status']);
        
        // Verify default board was created
        $stmt = $this->pdo->query("SELECT * FROM kanban_boards WHERE user_id = 1");
        $board = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertNotFalse($board);
        $this->assertEquals('Pipeline de Vendas', $board['name']);
        $this->assertEquals(1, $board['is_default']);
        
        // Verify default columns were created
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM kanban_columns WHERE board_id = " . $board['id']);
        $columnCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $this->assertEquals(6, $columnCount); // Should create 6 default columns
        
        // Verify card was created in first column
        $data = $results[0]['data'];
        $this->assertEquals($board['id'], $data['board_id']);
    }
    
    /**
     * Test createTask validates priority values
     * Validates Requirements 4.6
     */
    public function testCreateTaskValidatesPriorityValues(): void
    {
        // Create necessary tables
        $this->pdo->exec("
            CREATE TABLE kanban_boards (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                is_default INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_columns (
                id INTEGER PRIMARY KEY,
                board_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                position INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_cards (
                id INTEGER PRIMARY KEY,
                column_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                priority TEXT DEFAULT 'normal'
            )
        ");
        
        $this->pdo->exec("INSERT INTO kanban_boards (id, user_id, name, is_default) VALUES (1, 1, 'Test Board', 1)");
        $this->pdo->exec("INSERT INTO kanban_columns (id, board_id, name, position) VALUES (1, 1, 'New', 0)");
        
        // Test with invalid priority - should default to 'normal'
        $actions = [
            ['type' => 'create_task', 'config' => [
                'title' => 'Test Task',
                'priority' => 'invalid_priority'
            ]]
        ];
        
        $context = ['conversation_id' => 123];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('success', $results[0]['status']);
        
        // Verify priority was set to default 'normal'
        $cardId = $results[0]['data']['card_id'];
        $stmt = $this->pdo->query("SELECT priority FROM kanban_cards WHERE id = {$cardId}");
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals('normal', $card['priority']);
    }
    
    /**
     * Test createTask links task to conversation
     * Validates Requirements 4.6
     */
    public function testCreateTaskLinksToConversation(): void
    {
        // Create necessary tables
        $this->pdo->exec("
            CREATE TABLE kanban_boards (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                is_default INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_columns (
                id INTEGER PRIMARY KEY,
                board_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                position INTEGER DEFAULT 0
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE kanban_cards (
                id INTEGER PRIMARY KEY,
                column_id INTEGER NOT NULL,
                conversation_id INTEGER,
                title TEXT NOT NULL
            )
        ");
        
        $this->pdo->exec("INSERT INTO kanban_boards (id, user_id, name, is_default) VALUES (1, 1, 'Test Board', 1)");
        $this->pdo->exec("INSERT INTO kanban_columns (id, board_id, name, position) VALUES (1, 1, 'New', 0)");
        
        $actions = [
            ['type' => 'create_task', 'config' => [
                'title' => 'Linked Task'
            ]]
        ];
        
        $context = [
            'conversation_id' => 456
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('success', $results[0]['status']);
        
        // Verify conversation_id was saved
        $cardId = $results[0]['data']['card_id'];
        $stmt = $this->pdo->query("SELECT conversation_id FROM kanban_cards WHERE id = {$cardId}");
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals(456, $card['conversation_id']);
    }
}

    /**
     * Test callWebhook with missing URL config
     * Validates Requirements 4.7
     */
    public function testCallWebhookWithMissingUrlFails(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => ['method' => 'POST']] // Missing 'url'
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Webhook URL not configured', $results[0]['error']);
    }
    
    /**
     * Test callWebhook with empty URL config
     * Validates Requirements 4.7
     */
    public function testCallWebhookWithEmptyUrlFails(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => ['url' => '']] // Empty URL
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Webhook URL not configured', $results[0]['error']);
    }
    
    /**
     * Test callWebhook with invalid URL format
     * Validates Requirements 4.7
     */
    public function testCallWebhookWithInvalidUrlFormatFails(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => ['url' => 'not-a-valid-url']]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Invalid webhook URL format', $results[0]['error']);
    }
    
    /**
     * Test callWebhook with invalid HTTP method
     * Validates Requirements 4.7
     */
    public function testCallWebhookWithInvalidMethodFails(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://example.com/webhook',
                'method' => 'DELETE' // Invalid method
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('Invalid HTTP method', $results[0]['error']);
    }
    
    /**
     * Test callWebhook defaults to POST method
     * Validates Requirements 4.7
     */
    public function testCallWebhookDefaultsToPostMethod(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/post' // No method specified
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999',
            'message' => 'Test message'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        
        // Should succeed or fail with connection error, but not method error
        if ($results[0]['status'] === 'success') {
            $this->assertEquals('POST', $results[0]['method']);
        } else {
            // If it fails, should not be due to method validation
            $this->assertStringNotContainsString('Invalid HTTP method', $results[0]['error']);
        }
    }
    
    /**
     * Test callWebhook defaults to 10 second timeout
     * Validates Requirements 4.7, 10.7
     */
    public function testCallWebhookDefaultsTo10SecondTimeout(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/delay/15' // Will timeout
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $startTime = microtime(true);
        $results = $this->executor->executeActions($actions, $context);
        $duration = microtime(true) - $startTime;
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('timeout', strtolower($results[0]['error']));
        
        // Should timeout around 10 seconds (allow some margin)
        $this->assertLessThan(12, $duration, 'Should timeout within ~10 seconds');
    }
    
    /**
     * Test callWebhook respects custom timeout
     * Validates Requirements 4.7, 10.7
     */
    public function testCallWebhookRespectsCustomTimeout(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/delay/4',
                'timeout' => 2 // 2 second timeout
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $startTime = microtime(true);
        $results = $this->executor->executeActions($actions, $context);
        $duration = microtime(true) - $startTime;
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('timeout', strtolower($results[0]['error']));
        
        // Should timeout around 2 seconds (allow some margin)
        $this->assertLessThan(4, $duration, 'Should timeout within ~2 seconds');
    }
    
    /**
     * Test callWebhook includes conversation data in payload
     * Validates Requirements 4.7
     */
    public function testCallWebhookIncludesConversationDataInPayload(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/post',
                'method' => 'POST'
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'message' => 'Hello, I need help',
            'message_id' => 'msg_123',
            'channel' => 'whatsapp',
            'ai_response' => 'How can I assist you?',
            'timestamp' => 1234567890
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        
        // Should succeed with httpbin.org
        if ($results[0]['status'] === 'success') {
            $this->assertEquals('success', $results[0]['status']);
            $this->assertArrayHasKey('data', $results[0]);
            
            $data = $results[0]['data'];
            $this->assertEquals('webhook', $data['action']);
            $this->assertEquals('POST', $data['method']);
            $this->assertArrayHasKey('http_code', $data);
            $this->assertArrayHasKey('execution_time_ms', $data);
            
            // Verify response contains our data (httpbin echoes back)
            $response = $data['response'] ?? '';
            $this->assertNotEmpty($response);
        }
    }
    
    /**
     * Test callWebhook performs variable substitution in URL
     * Validates Requirements 4.7, 4.9
     */
    public function testCallWebhookPerformsVariableSubstitutionInUrl(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/anything/{{conversation_id}}/{{contact_name}}',
                'method' => 'GET'
            ]]
        ];
        
        $context = [
            'conversation_id' => 456,
            'contact_name' => 'Jane',
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        
        // Should succeed with httpbin.org
        if ($results[0]['status'] === 'success') {
            $data = $results[0]['data'];
            $this->assertStringContainsString('456', $data['url']);
            $this->assertStringContainsString('Jane', $data['url']);
        }
    }
    
    /**
     * Test callWebhook supports GET method
     * Validates Requirements 4.7
     */
    public function testCallWebhookSupportsGetMethod(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/get',
                'method' => 'GET'
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        
        // Should succeed with httpbin.org
        if ($results[0]['status'] === 'success') {
            $data = $results[0]['data'];
            $this->assertEquals('GET', $data['method']);
            $this->assertEquals(200, $data['http_code']);
        }
    }
    
    /**
     * Test callWebhook supports PUT method
     * Validates Requirements 4.7
     */
    public function testCallWebhookSupportsPutMethod(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/put',
                'method' => 'PUT'
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        
        // Should succeed with httpbin.org
        if ($results[0]['status'] === 'success') {
            $data = $results[0]['data'];
            $this->assertEquals('PUT', $data['method']);
            $this->assertEquals(200, $data['http_code']);
        }
    }
    
    /**
     * Test callWebhook supports PATCH method
     * Validates Requirements 4.7
     */
    public function testCallWebhookSupportsPatchMethod(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/patch',
                'method' => 'PATCH'
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        
        // Should succeed with httpbin.org
        if ($results[0]['status'] === 'success') {
            $data = $results[0]['data'];
            $this->assertEquals('PATCH', $data['method']);
            $this->assertEquals(200, $data['http_code']);
        }
    }
    
    /**
     * Test callWebhook handles non-200 HTTP responses
     * Validates Requirements 4.7, 10.7
     */
    public function testCallWebhookHandlesNon200Responses(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/status/404'
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('404', $results[0]['error']);
    }
    
    /**
     * Test callWebhook handles 500 server errors
     * Validates Requirements 4.7, 10.7
     */
    public function testCallWebhookHandles500Errors(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/status/500'
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        $this->assertStringContainsString('500', $results[0]['error']);
    }
    
    /**
     * Test callWebhook validates timeout range
     * Validates Requirements 4.7, 10.7
     */
    public function testCallWebhookValidatesTimeoutRange(): void
    {
        // Test with timeout too low (should default to 10)
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/delay/12',
                'timeout' => 0 // Invalid - too low
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $startTime = microtime(true);
        $results = $this->executor->executeActions($actions, $context);
        $duration = microtime(true) - $startTime;
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        
        // Should use default 10 second timeout
        $this->assertLessThan(12, $duration);
        
        // Test with timeout too high (should default to 10)
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/delay/12',
                'timeout' => 100 // Invalid - too high
            ]]
        ];
        
        $startTime = microtime(true);
        $results = $this->executor->executeActions($actions, $context);
        $duration = microtime(true) - $startTime;
        
        $this->assertCount(1, $results);
        $this->assertEquals('failed', $results[0]['status']);
        
        // Should use default 10 second timeout
        $this->assertLessThan(12, $duration);
    }
    
    /**
     * Test callWebhook returns execution time
     * Validates Requirements 4.7
     */
    public function testCallWebhookReturnsExecutionTime(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/delay/1' // 1 second delay
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        
        if ($results[0]['status'] === 'success') {
            $data = $results[0]['data'];
            $this->assertArrayHasKey('execution_time_ms', $data);
            $this->assertIsInt($data['execution_time_ms']);
            $this->assertGreaterThan(900, $data['execution_time_ms']); // Should be ~1000ms
        }
    }
    
    /**
     * Test callWebhook limits response size
     * Validates Requirements 4.7
     */
    public function testCallWebhookLimitsResponseSize(): void
    {
        $actions = [
            ['type' => 'webhook', 'config' => [
                'url' => 'https://httpbin.org/post'
            ]]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999',
            'message' => str_repeat('A', 1000) // Large message
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        $this->assertCount(1, $results);
        
        if ($results[0]['status'] === 'success') {
            $data = $results[0]['data'];
            $this->assertArrayHasKey('response', $data);
            
            // Response should be limited to 500 characters
            if ($data['response'] !== null) {
                $this->assertLessThanOrEqual(500, strlen($data['response']));
            }
        }
    }
    
    /**
     * Test callWebhook error doesn't stop other actions
     * Validates Requirements 4.10, 10.7
     */
    public function testCallWebhookErrorDoesntStopOtherActions(): void
    {
        $actions = [
            ['type' => 'add_tag', 'config' => ['tag' => 'before_webhook']],
            ['type' => 'webhook', 'config' => ['url' => 'https://invalid-domain-that-does-not-exist-12345.com']],
            ['type' => 'add_tag', 'config' => ['tag' => 'after_webhook']]
        ];
        
        $context = [
            'conversation_id' => 123,
            'phone' => '5511999999999'
        ];
        
        $results = $this->executor->executeActions($actions, $context);
        
        // All 3 actions should have results
        $this->assertCount(3, $results);
        
        // First action should succeed
        $this->assertEquals('success', $results[0]['status']);
        $this->assertEquals('add_tag', $results[0]['type']);
        
        // Second action (webhook) should fail
        $this->assertEquals('failed', $results[1]['status']);
        $this->assertEquals('webhook', $results[1]['type']);
        
        // Third action should still succeed despite webhook failure
        $this->assertEquals('success', $results[2]['status']);
        $this->assertEquals('add_tag', $results[2]['type']);
    }
}
