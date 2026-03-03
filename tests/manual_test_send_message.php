<?php
/**
 * Manual Test for ActionExecutor sendMessage() implementation
 * 
 * Tests the sendMessage action with variable substitution and error handling
 * Usage: php tests/manual_test_send_message.php
 */

require_once __DIR__ . '/../includes/ActionExecutor.php';
require_once __DIR__ . '/../includes/VariableSubstitutor.php';

echo "=== ActionExecutor sendMessage() Manual Test ===\n\n";

// Create in-memory SQLite database for testing
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Database connection created\n";
} catch (Exception $e) {
    echo "✗ Failed to create database: " . $e->getMessage() . "\n";
    exit(1);
}

$userId = 1;
$instanceConfig = [
    'name' => 'test_instance',
    'api_url' => 'https://api.test.com',
    'api_key' => 'test_key_123'
];

// Create ActionExecutor instance
try {
    $executor = new ActionExecutor($pdo, $userId, $instanceConfig);
    echo "✓ ActionExecutor instance created\n\n";
} catch (Exception $e) {
    echo "✗ Failed to create ActionExecutor: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 1: sendMessage with missing message config
echo "Test 1: sendMessage with missing message config (Requirements 4.2, 4.9)\n";
$actions = [
    ['type' => 'send_message', 'config' => []] // Missing 'message'
];

$context = [
    'phone' => '5511999999999',
    'conversation_id' => 123
];

$results = $executor->executeActions($actions, $context);

if (count($results) === 1 && $results[0]['status'] === 'failed' && 
    strpos($results[0]['error'], 'Message text not configured') !== false) {
    echo "✓ Fails gracefully with missing message config\n";
} else {
    echo "✗ Should fail with 'Message text not configured' error\n";
    echo "  Got: " . ($results[0]['error'] ?? 'no error') . "\n";
}
echo "\n";

// Test 2: sendMessage with empty message after variable substitution
echo "Test 2: sendMessage with empty message after variable substitution (Requirements 4.2, 4.9)\n";
$actions = [
    ['type' => 'send_message', 'config' => ['message' => '{{nonexistent_var}}']]
];

$context = [
    'phone' => '5511999999999',
    'conversation_id' => 123
];

$results = $executor->executeActions($actions, $context);

if (count($results) === 1 && $results[0]['status'] === 'failed' && 
    strpos($results[0]['error'], 'empty after variable substitution') !== false) {
    echo "✓ Fails gracefully when message is empty after substitution\n";
} else {
    echo "✗ Should fail with 'empty after variable substitution' error\n";
    echo "  Got: " . ($results[0]['error'] ?? 'no error') . "\n";
}
echo "\n";

// Test 3: sendMessage with missing phone in context
echo "Test 3: sendMessage with missing phone in context (Requirements 4.9)\n";
$actions = [
    ['type' => 'send_message', 'config' => ['message' => 'Hello']]
];

$context = [
    'conversation_id' => 123
    // Missing 'phone'
];

$results = $executor->executeActions($actions, $context);

if (count($results) === 1 && $results[0]['status'] === 'failed' && 
    strpos($results[0]['error'], 'Phone number not available') !== false) {
    echo "✓ Fails gracefully with missing phone number\n";
} else {
    echo "✗ Should fail with 'Phone number not available' error\n";
    echo "  Got: " . ($results[0]['error'] ?? 'no error') . "\n";
}
echo "\n";

// Test 4: sendMessage with missing instance config
echo "Test 4: sendMessage with missing instance config (Requirements 4.9)\n";
$incompleteConfig = ['name' => 'test']; // Missing api_key
$executorIncomplete = new ActionExecutor($pdo, $userId, $incompleteConfig);

$actions = [
    ['type' => 'send_message', 'config' => ['message' => 'Hello']]
];

$context = [
    'phone' => '5511999999999',
    'conversation_id' => 123
];

$results = $executorIncomplete->executeActions($actions, $context);

if (count($results) === 1 && $results[0]['status'] === 'failed' && 
    strpos($results[0]['error'], 'Evolution API instance not configured') !== false) {
    echo "✓ Fails gracefully with incomplete instance config\n";
} else {
    echo "✗ Should fail with 'Evolution API instance not configured' error\n";
    echo "  Got: " . ($results[0]['error'] ?? 'no error') . "\n";
}
echo "\n";

// Test 5: sendMessage performs variable substitution
echo "Test 5: sendMessage performs variable substitution (Requirements 4.2, 4.9)\n";
$actions = [
    ['type' => 'send_message', 'config' => ['message' => 'Hello {{contact_name}}, your message was: {{message}}']]
];

$context = [
    'phone' => '5511999999999',
    'conversation_id' => 123,
    'contact_name' => 'John Doe',
    'message' => 'I need help'
];

$results = $executor->executeActions($actions, $context);

// This will fail because we can't actually call Evolution API in tests
// But we can verify the error is about API connection, not about missing variables
if (count($results) === 1 && $results[0]['status'] === 'failed') {
    $error = $results[0]['error'];
    
    // Should NOT contain errors about missing config or variables
    if (strpos($error, 'not configured') === false && 
        strpos($error, 'not available') === false &&
        strpos($error, 'empty after variable substitution') === false) {
        echo "✓ Variable substitution works (error is about API connection, not variables)\n";
        echo "  Error: {$error}\n";
    } else {
        echo "✗ Should fail with API connection error, not variable error\n";
        echo "  Got: {$error}\n";
    }
} else {
    echo "✗ Expected failed status due to API connection\n";
}
echo "\n";

// Test 6: sendMessage with simple text (no variables)
echo "Test 6: sendMessage with simple text (Requirements 4.2)\n";
$actions = [
    ['type' => 'send_message', 'config' => ['message' => 'Hello, this is a test message']]
];

$context = [
    'phone' => '5511999999999',
    'conversation_id' => 123
];

$results = $executor->executeActions($actions, $context);

// This will fail because we can't actually call Evolution API in tests
if (count($results) === 1 && $results[0]['status'] === 'failed') {
    $error = $results[0]['error'];
    
    // Should be about API connection, not about config
    if (strpos($error, 'not configured') === false && 
        strpos($error, 'not available') === false) {
        echo "✓ Simple message passes validation (fails at API call)\n";
        echo "  Error: {$error}\n";
    } else {
        echo "✗ Should fail with API connection error, not config error\n";
        echo "  Got: {$error}\n";
    }
} else {
    echo "✗ Expected failed status due to API connection\n";
}
echo "\n";

// Test 7: sendMessage with AI response variable
echo "Test 7: sendMessage with AI response variable (Requirements 4.2, 4.9)\n";
$actions = [
    ['type' => 'send_message', 'config' => ['message' => '{{ai_response}}']]
];

$context = [
    'phone' => '5511999999999',
    'conversation_id' => 123,
    'ai_response' => 'This is an AI generated response to your question.'
];

$results = $executor->executeActions($actions, $context);

// This will fail because we can't actually call Evolution API in tests
if (count($results) === 1 && $results[0]['status'] === 'failed') {
    $error = $results[0]['error'];
    
    // Should NOT be about empty message or missing variables
    if (strpos($error, 'empty after variable substitution') === false) {
        echo "✓ AI response variable substitution works\n";
        echo "  Error: {$error}\n";
    } else {
        echo "✗ Should not fail with empty message error\n";
        echo "  Got: {$error}\n";
    }
} else {
    echo "✗ Expected failed status due to API connection\n";
}
echo "\n";

// Test 8: Error isolation - sendMessage failure doesn't stop other actions
echo "Test 8: Error isolation (Requirements 4.10)\n";
$actions = [
    ['type' => 'send_message', 'config' => ['message' => 'First message']],
    ['type' => 'send_message', 'config' => []], // This will fail (no message)
    ['type' => 'add_tag', 'config' => ['tag' => 'test']], // Should still execute
];

$context = [
    'phone' => '5511999999999',
    'conversation_id' => 123
];

$results = $executor->executeActions($actions, $context);

if (count($results) === 3) {
    echo "✓ All 3 actions processed\n";
    
    // Second action should fail
    if ($results[1]['status'] === 'failed') {
        echo "✓ Second action failed as expected\n";
    } else {
        echo "✗ Second action should fail\n";
    }
    
    // Third action should succeed despite previous failure
    if ($results[2]['status'] === 'success') {
        echo "✓ Third action succeeded despite previous failure\n";
    } else {
        echo "✗ Third action should succeed\n";
    }
} else {
    echo "✗ Expected 3 results, got: " . count($results) . "\n";
}
echo "\n";

echo "=== All sendMessage Tests Completed ===\n";
echo "\nNote: Tests that reach the Evolution API call will fail with connection errors\n";
echo "since we're not actually connecting to a real API. This is expected behavior.\n";
echo "The important validation is that the code handles errors gracefully and\n";
echo "performs variable substitution correctly before attempting the API call.\n";
