<?php
/**
 * Manual test for assignAttendant action
 * 
 * This script tests the assignAttendant() implementation in ActionExecutor
 * 
 * Requirements tested:
 * - 4.3: Assign attendant action
 * - 11.2: End bot sessions when attendant assigned
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ActionExecutor.php';
require_once __DIR__ . '/../includes/VariableSubstitutor.php';

echo "<h1>Manual Test: assignAttendant Action</h1>\n";
echo "<pre>\n";

// Test configuration
$userId = 1; // Adjust to a valid user ID in your database
$attendantId = 1; // Adjust to a valid attendant ID in your database
$testPhone = '5511999999999';

try {
    // ========================================
    // Setup: Create test data
    // ========================================
    echo "=== SETUP ===\n";
    
    // Create a test conversation
    $stmt = $pdo->prepare("
        INSERT INTO chat_conversations 
        (user_id, contact_number, contact_name, channel_type, status, last_message_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $testPhone, 'Test Contact', 'whatsapp', 'open']);
    $conversationId = $pdo->lastInsertId();
    echo "✓ Created test conversation ID: {$conversationId}\n";
    
    // Create an active bot session
    $stmt = $pdo->prepare("
        INSERT INTO bot_sessions 
        (flow_id, version, user_id, phone, status, last_step_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([1, 1, $userId, $testPhone, 'active']);
    $botSessionId = $pdo->lastInsertId();
    echo "✓ Created active bot session ID: {$botSessionId}\n";
    
    // Verify attendant exists
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $stmt->execute([$attendantId]);
    $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attendant) {
        throw new Exception("Attendant ID {$attendantId} not found. Please adjust the test configuration.");
    }
    echo "✓ Found attendant: {$attendant['name']} (ID: {$attendantId})\n\n";
    
    // ========================================
    // Test 1: Successful assignment
    // ========================================
    echo "=== TEST 1: Successful Assignment ===\n";
    
    $instanceConfig = [
        'name' => 'test_instance',
        'api_key' => 'test_key'
    ];
    
    $executor = new ActionExecutor($pdo, $userId, $instanceConfig);
    
    $actions = [
        [
            'type' => 'assign_attendant',
            'config' => [
                'attendant_id' => $attendantId
            ]
        ]
    ];
    
    $context = [
        'conversation_id' => $conversationId,
        'phone' => $testPhone,
        'message' => 'Test message'
    ];
    
    $results = $executor->executeActions($actions, $context);
    
    echo "Action result:\n";
    print_r($results);
    
    // Verify conversation was updated
    $stmt = $pdo->prepare("
        SELECT attended_by, attended_by_name, attended_by_type, status, attended_at
        FROM chat_conversations 
        WHERE id = ?
    ");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\nConversation after assignment:\n";
    print_r($conversation);
    
    // Verify bot session was ended
    $stmt = $pdo->prepare("SELECT status FROM bot_sessions WHERE id = ?");
    $stmt->execute([$botSessionId]);
    $botSession = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\nBot session after assignment:\n";
    print_r($botSession);
    
    // Assertions
    $success = true;
    
    if ($results[0]['status'] !== 'success') {
        echo "\n✗ FAIL: Action status should be 'success', got: {$results[0]['status']}\n";
        $success = false;
    } else {
        echo "\n✓ PASS: Action status is 'success'\n";
    }
    
    if ($conversation['attended_by'] != $attendantId) {
        echo "✗ FAIL: Conversation attended_by should be {$attendantId}, got: {$conversation['attended_by']}\n";
        $success = false;
    } else {
        echo "✓ PASS: Conversation attended_by is correct\n";
    }
    
    if ($conversation['status'] !== 'in_progress') {
        echo "✗ FAIL: Conversation status should be 'in_progress', got: {$conversation['status']}\n";
        $success = false;
    } else {
        echo "✓ PASS: Conversation status is 'in_progress'\n";
    }
    
    if ($botSession['status'] !== 'completed') {
        echo "✗ FAIL: Bot session status should be 'completed', got: {$botSession['status']}\n";
        $success = false;
    } else {
        echo "✓ PASS: Bot session was ended\n";
    }
    
    // ========================================
    // Test 2: Missing attendant_id
    // ========================================
    echo "\n=== TEST 2: Missing attendant_id ===\n";
    
    $actions = [
        [
            'type' => 'assign_attendant',
            'config' => [] // Missing attendant_id
        ]
    ];
    
    $results = $executor->executeActions($actions, $context);
    
    echo "Action result:\n";
    print_r($results);
    
    if ($results[0]['status'] !== 'failed') {
        echo "\n✗ FAIL: Action should fail with missing attendant_id\n";
        $success = false;
    } else {
        echo "\n✓ PASS: Action failed as expected\n";
    }
    
    if (!str_contains($results[0]['error'], 'Attendant ID not configured')) {
        echo "✗ FAIL: Error message should mention 'Attendant ID not configured'\n";
        $success = false;
    } else {
        echo "✓ PASS: Error message is correct\n";
    }
    
    // ========================================
    // Test 3: Missing conversation_id
    // ========================================
    echo "\n=== TEST 3: Missing conversation_id ===\n";
    
    $actions = [
        [
            'type' => 'assign_attendant',
            'config' => ['attendant_id' => $attendantId]
        ]
    ];
    
    $contextNoConv = [
        'phone' => $testPhone
        // Missing conversation_id
    ];
    
    $results = $executor->executeActions($actions, $contextNoConv);
    
    echo "Action result:\n";
    print_r($results);
    
    if ($results[0]['status'] !== 'failed') {
        echo "\n✗ FAIL: Action should fail with missing conversation_id\n";
        $success = false;
    } else {
        echo "\n✓ PASS: Action failed as expected\n";
    }
    
    if (!str_contains($results[0]['error'], 'Conversation ID not available')) {
        echo "✗ FAIL: Error message should mention 'Conversation ID not available'\n";
        $success = false;
    } else {
        echo "✓ PASS: Error message is correct\n";
    }
    
    // ========================================
    // Test 4: Non-existent attendant
    // ========================================
    echo "\n=== TEST 4: Non-existent attendant ===\n";
    
    $actions = [
        [
            'type' => 'assign_attendant',
            'config' => ['attendant_id' => 99999] // Non-existent
        ]
    ];
    
    $results = $executor->executeActions($actions, $context);
    
    echo "Action result:\n";
    print_r($results);
    
    if ($results[0]['status'] !== 'failed') {
        echo "\n✗ FAIL: Action should fail with non-existent attendant\n";
        $success = false;
    } else {
        echo "\n✓ PASS: Action failed as expected\n";
    }
    
    if (!str_contains($results[0]['error'], 'Attendant not found')) {
        echo "✗ FAIL: Error message should mention 'Attendant not found'\n";
        $success = false;
    } else {
        echo "✓ PASS: Error message is correct\n";
    }
    
    // ========================================
    // Cleanup
    // ========================================
    echo "\n=== CLEANUP ===\n";
    
    $stmt = $pdo->prepare("DELETE FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    echo "✓ Deleted test conversation\n";
    
    $stmt = $pdo->prepare("DELETE FROM bot_sessions WHERE id = ?");
    $stmt->execute([$botSessionId]);
    echo "✓ Deleted test bot session\n";
    
    // ========================================
    // Summary
    // ========================================
    echo "\n=== SUMMARY ===\n";
    
    if ($success) {
        echo "✓ ALL TESTS PASSED\n";
    } else {
        echo "✗ SOME TESTS FAILED\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>\n";
?>
