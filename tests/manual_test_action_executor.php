<?php
/**
 * Manual Test for ActionExecutor
 * 
 * Run this script to manually test the ActionExecutor implementation
 * Usage: php tests/manual_test_action_executor.php
 */

require_once __DIR__ . '/../includes/ActionExecutor.php';

echo "=== ActionExecutor Manual Test ===\n\n";

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
    'api_key' => 'test_key'
];

// Create ActionExecutor instance
try {
    $executor = new ActionExecutor($pdo, $userId, $instanceConfig);
    echo "✓ ActionExecutor instance created\n\n";
} catch (Exception $e) {
    echo "✗ Failed to create ActionExecutor: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 1: Empty actions array
echo "Test 1: Empty actions array\n";
$result = $executor->executeActions([], []);
if (empty($result)) {
    echo "✓ Returns empty array for empty input\n\n";
} else {
    echo "✗ Expected empty array, got: " . print_r($result, true) . "\n\n";
}

// Test 2: Execute actions in order
echo "Test 2: Execute actions in order\n";
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

$results = $executor->executeActions($actions, $context);

if (count($results) === 3) {
    echo "✓ Executed 3 actions\n";
    
    if ($results[0]['type'] === 'send_message') {
        echo "✓ First action is send_message\n";
    } else {
        echo "✗ First action incorrect\n";
    }
    
    if ($results[1]['type'] === 'add_tag') {
        echo "✓ Second action is add_tag\n";
    } else {
        echo "✗ Second action incorrect\n";
    }
    
    if ($results[2]['type'] === 'assign_attendant') {
        echo "✓ Third action is assign_attendant\n";
    } else {
        echo "✗ Third action incorrect\n";
    }
} else {
    echo "✗ Expected 3 results, got: " . count($results) . "\n";
}
echo "\n";

// Test 3: Error isolation - one failure doesn't stop others
echo "Test 3: Error isolation (Requirements 4.10)\n";
$actions = [
    ['type' => 'send_message', 'config' => ['message' => 'First']],
    ['type' => 'unknown_action', 'config' => ['foo' => 'bar']], // This will fail
    ['type' => 'add_tag', 'config' => ['tag' => 'test']], // Should still execute
    ['config' => ['invalid' => 'action']], // This will fail (no type)
    ['type' => 'assign_attendant', 'config' => ['attendant_id' => 1]] // Should still execute
];

$results = $executor->executeActions($actions, []);

if (count($results) === 5) {
    echo "✓ All 5 actions processed\n";
    
    // Check first action succeeded
    if ($results[0]['status'] === 'success') {
        echo "✓ First action succeeded\n";
    } else {
        echo "✗ First action should succeed\n";
    }
    
    // Check second action failed
    if ($results[1]['status'] === 'failed' && !empty($results[1]['error'])) {
        echo "✓ Second action failed as expected (unknown type)\n";
    } else {
        echo "✗ Second action should fail\n";
    }
    
    // Check third action succeeded despite previous failure
    if ($results[2]['status'] === 'success') {
        echo "✓ Third action succeeded despite previous failure\n";
    } else {
        echo "✗ Third action should succeed\n";
    }
    
    // Check fourth action failed
    if ($results[3]['status'] === 'failed') {
        echo "✓ Fourth action failed as expected (missing type)\n";
    } else {
        echo "✗ Fourth action should fail\n";
    }
    
    // Check fifth action succeeded despite previous failures
    if ($results[4]['status'] === 'success') {
        echo "✓ Fifth action succeeded despite previous failures\n";
    } else {
        echo "✗ Fifth action should succeed\n";
    }
} else {
    echo "✗ Expected 5 results, got: " . count($results) . "\n";
}
echo "\n";

// Test 4: All supported action types
echo "Test 4: All supported action types\n";
$actionTypes = [
    'send_message',
    'assign_attendant',
    'add_tag',
    'remove_tag',
    'create_task',
    'webhook',
    'update_field'
];

$allSupported = true;
foreach ($actionTypes as $type) {
    $actions = [
        ['type' => $type, 'config' => ['test' => 'value']]
    ];
    
    $results = $executor->executeActions($actions, []);
    
    if (count($results) === 1 && $results[0]['status'] === 'success' && $results[0]['type'] === $type) {
        echo "✓ Action type '{$type}' is supported\n";
    } else {
        echo "✗ Action type '{$type}' failed\n";
        $allSupported = false;
    }
}

if ($allSupported) {
    echo "✓ All action types are supported\n";
}
echo "\n";

// Test 5: Result structure validation
echo "Test 5: Result structure validation\n";
$actions = [
    ['type' => 'send_message', 'config' => ['message' => 'Test']]
];

$results = $executor->executeActions($actions, []);
$result = $results[0];

$requiredFields = ['type', 'status', 'timestamp'];
$allFieldsPresent = true;

foreach ($requiredFields as $field) {
    if (!array_key_exists($field, $result)) {
        echo "✗ Missing required field: {$field}\n";
        $allFieldsPresent = false;
    }
}

if ($allFieldsPresent) {
    echo "✓ All required fields present in result\n";
    
    // Check field types
    if (is_string($result['type']) && 
        is_string($result['status']) && is_int($result['timestamp'])) {
        echo "✓ All field types are correct\n";
    } else {
        echo "✗ Some field types are incorrect\n";
    }
    
    // Check that error field exists only if status is failed
    if ($result['status'] === 'failed' && !isset($result['error'])) {
        echo "✗ Error field missing for failed action\n";
    } elseif ($result['status'] === 'success' && isset($result['error'])) {
        echo "✗ Error field should not be present for successful action\n";
    } else {
        echo "✓ Error field handling is correct\n";
    }
}
echo "\n";

// Test 6: Action without type
echo "Test 6: Action without type\n";
$actions = [
    ['config' => ['message' => 'Hello']] // Missing 'type'
];

$results = $executor->executeActions($actions, []);

if (count($results) === 1 && $results[0]['status'] === 'failed' && 
    strpos($results[0]['error'], 'type not specified') !== false) {
    echo "✓ Action without type fails gracefully\n";
} else {
    echo "✗ Action without type should fail with appropriate error\n";
}
echo "\n";

// Test 7: Action without config
echo "Test 7: Action without config\n";
$actions = [
    ['type' => 'send_message'] // Missing 'config'
];

$results = $executor->executeActions($actions, []);

if (count($results) === 1 && $results[0]['status'] === 'failed' && 
    strpos($results[0]['error'], 'config not specified') !== false) {
    echo "✓ Action without config fails gracefully\n";
} else {
    echo "✗ Action without config should fail with appropriate error\n";
}
echo "\n";

echo "=== All Tests Completed ===\n";
