<?php
/**
 * Manual Test: Tag Management Actions
 * 
 * Tests the addTag() and removeTag() actions in ActionExecutor
 * 
 * Usage: php tests/manual_test_tags.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ActionExecutor.php';

echo "=== Manual Test: Tag Management Actions ===\n\n";

// Setup
try {
    $pdo = getDBConnection();
    $userId = 1; // Test user ID
    $instanceConfig = [
        'name' => 'test_instance',
        'api_url' => 'https://api.test.com',
        'api_key' => 'test_key'
    ];
    
    $executor = new ActionExecutor($pdo, $userId, $instanceConfig);
    
    // First, we need to ensure the tags column exists
    echo "1. Checking if tags column exists...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM chat_conversations LIKE 'tags'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        echo "   ⚠ Tags column doesn't exist. Please run the migration:\n";
        echo "   migrations/add_tags_to_conversations.sql\n\n";
        echo "   Attempting to add column now...\n";
        
        try {
            $pdo->exec("ALTER TABLE chat_conversations ADD COLUMN tags JSON DEFAULT NULL COMMENT 'Array of tag IDs' AFTER status");
            echo "   ✓ Tags column added successfully\n\n";
        } catch (PDOException $e) {
            echo "   ✗ Failed to add tags column: " . $e->getMessage() . "\n";
            echo "   Please run the migration manually.\n";
            exit(1);
        }
    } else {
        echo "   ✓ Tags column exists\n\n";
    }
    
    // Create or get a test conversation
    echo "2. Setting up test conversation...\n";
    
    // Check if test conversation exists
    $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        // Create a test conversation
        $stmt = $pdo->prepare("
            INSERT INTO chat_conversations (user_id, contact_name, contact_number, channel_type, status, tags)
            VALUES (?, 'Test Contact', '5511999999999', 'whatsapp', 'active', NULL)
        ");
        $stmt->execute([$userId]);
        $conversationId = $pdo->lastInsertId();
        echo "   ✓ Created test conversation ID: $conversationId\n\n";
    } else {
        $conversationId = $conversation['id'];
        // Reset tags to null for clean test
        $stmt = $pdo->prepare("UPDATE chat_conversations SET tags = NULL WHERE id = ?");
        $stmt->execute([$conversationId]);
        echo "   ✓ Using existing conversation ID: $conversationId\n\n";
    }
    
    // Test context
    $context = [
        'conversation_id' => $conversationId,
        'phone' => '5511999999999',
        'message' => 'Test message'
    ];
    
    // Test 1: Add first tag
    echo "3. Test: Add first tag (tag: 'automated')\n";
    $actions = [
        ['type' => 'add_tag', 'config' => ['tag' => 'automated']]
    ];
    
    $results = $executor->executeActions($actions, $context);
    $result = $results[0];
    
    if ($result['status'] === 'success') {
        echo "   ✓ Tag added successfully\n";
        echo "   Tags: " . json_encode($result['tags']) . "\n\n";
    } else {
        echo "   ✗ Failed to add tag\n";
        echo "   Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
    }
    
    // Test 2: Add second tag
    echo "4. Test: Add second tag (tag: 'vip')\n";
    $actions = [
        ['type' => 'add_tag', 'config' => ['tag' => 'vip']]
    ];
    
    $results = $executor->executeActions($actions, $context);
    $result = $results[0];
    
    if ($result['status'] === 'success') {
        echo "   ✓ Tag added successfully\n";
        echo "   Tags: " . json_encode($result['tags']) . "\n\n";
    } else {
        echo "   ✗ Failed to add tag\n";
        echo "   Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
    }
    
    // Test 3: Add duplicate tag (should not duplicate)
    echo "5. Test: Add duplicate tag (tag: 'automated' again)\n";
    $actions = [
        ['type' => 'add_tag', 'config' => ['tag' => 'automated']]
    ];
    
    $results = $executor->executeActions($actions, $context);
    $result = $results[0];
    
    if ($result['status'] === 'success') {
        echo "   ✓ Tag operation completed\n";
        echo "   Tags: " . json_encode($result['tags']) . "\n";
        if (count($result['tags']) === 2) {
            echo "   ✓ Duplicate tag was not added (correct behavior)\n\n";
        } else {
            echo "   ✗ Unexpected tag count\n\n";
        }
    } else {
        echo "   ✗ Failed\n";
        echo "   Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
    }
    
    // Test 4: Remove a tag
    echo "6. Test: Remove tag (tag: 'automated')\n";
    $actions = [
        ['type' => 'remove_tag', 'config' => ['tag' => 'automated']]
    ];
    
    $results = $executor->executeActions($actions, $context);
    $result = $results[0];
    
    if ($result['status'] === 'success') {
        echo "   ✓ Tag removed successfully\n";
        echo "   Removed: " . ($result['removed'] ? 'yes' : 'no') . "\n";
        echo "   Tags: " . json_encode($result['tags']) . "\n\n";
    } else {
        echo "   ✗ Failed to remove tag\n";
        echo "   Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
    }
    
    // Test 5: Remove non-existent tag
    echo "7. Test: Remove non-existent tag (tag: 'nonexistent')\n";
    $actions = [
        ['type' => 'remove_tag', 'config' => ['tag' => 'nonexistent']]
    ];
    
    $results = $executor->executeActions($actions, $context);
    $result = $results[0];
    
    if ($result['status'] === 'success') {
        echo "   ✓ Operation completed\n";
        echo "   Removed: " . ($result['removed'] ? 'yes' : 'no') . "\n";
        echo "   Tags: " . json_encode($result['tags']) . "\n\n";
    } else {
        echo "   ✗ Failed\n";
        echo "   Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
    }
    
    // Test 6: Error handling - missing tag
    echo "8. Test: Error handling - missing tag\n";
    $actions = [
        ['type' => 'add_tag', 'config' => []]
    ];
    
    $results = $executor->executeActions($actions, $context);
    $result = $results[0];
    
    if ($result['status'] === 'failed') {
        echo "   ✓ Error handled correctly\n";
        echo "   Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
    } else {
        echo "   ✗ Should have failed but didn't\n\n";
    }
    
    // Test 7: Error handling - missing conversation_id
    echo "9. Test: Error handling - missing conversation_id\n";
    $actions = [
        ['type' => 'add_tag', 'config' => ['tag' => 'test']]
    ];
    
    $emptyContext = ['phone' => '5511999999999'];
    $results = $executor->executeActions($actions, $emptyContext);
    $result = $results[0];
    
    if ($result['status'] === 'failed') {
        echo "   ✓ Error handled correctly\n";
        echo "   Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
    } else {
        echo "   ✗ Should have failed but didn't\n\n";
    }
    
    // Verify database state
    echo "10. Verifying database state...\n";
    $stmt = $pdo->prepare("SELECT tags FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $dbTags = json_decode($row['tags'], true);
        echo "   Database tags: " . json_encode($dbTags) . "\n";
        
        if (is_array($dbTags) && count($dbTags) === 1 && $dbTags[0] === 'vip') {
            echo "   ✓ Database state is correct (only tag 'vip' remains)\n\n";
        } else {
            echo "   ⚠ Unexpected database state\n\n";
        }
    }
    
    echo "=== All Tests Completed ===\n";
    
} catch (Exception $e) {
    echo "✗ Test failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
