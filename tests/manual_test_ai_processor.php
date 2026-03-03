<?php
/**
 * Manual Test for AIProcessor
 * 
 * Tests the AIProcessor functionality including:
 * - Provider selection
 * - Prompt preparation with variable substitution
 * - Context preparation with conversation history
 */

require_once __DIR__ . '/../includes/AIProcessor.php';
require_once __DIR__ . '/../includes/VariableSubstitutor.php';
require_once __DIR__ . '/../includes/init.php';

echo "=== AIProcessor Manual Test ===\n\n";

// Test 1: Process with AI disabled
echo "Test 1: Process with AI disabled\n";
$processor = new AIProcessor($pdo);
$agentConfig = ['enabled' => false];
$context = ['message' => 'Hello'];
$result = $processor->process($agentConfig, $context);
echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Expected: false\n";
echo "Result: " . ($result['success'] === false ? 'PASS' : 'FAIL') . "\n\n";

// Test 2: Provider selection - default to OpenAI
echo "Test 2: Provider selection - default to OpenAI\n";
$agentConfig = [
    'enabled' => true,
    'prompt' => 'Test prompt'
];
$context = ['message' => 'Hello'];
$result = $processor->process($agentConfig, $context);
echo "Provider: " . $result['provider'] . "\n";
echo "Expected: openai\n";
echo "Result: " . ($result['provider'] === 'openai' ? 'PASS' : 'FAIL') . "\n\n";

// Test 3: Provider selection - Gemini
echo "Test 3: Provider selection - Gemini\n";
$agentConfig = [
    'enabled' => true,
    'provider' => 'gemini',
    'prompt' => 'Test prompt'
];
$context = ['message' => 'Hello'];
$result = $processor->process($agentConfig, $context);
echo "Provider: " . $result['provider'] . "\n";
echo "Expected: gemini\n";
echo "Result: " . ($result['provider'] === 'gemini' ? 'PASS' : 'FAIL') . "\n\n";

// Test 4: Invalid provider defaults to OpenAI
echo "Test 4: Invalid provider defaults to OpenAI\n";
$agentConfig = [
    'enabled' => true,
    'provider' => 'invalid_provider',
    'prompt' => 'Test prompt'
];
$context = ['message' => 'Hello'];
$result = $processor->process($agentConfig, $context);
echo "Provider: " . $result['provider'] . "\n";
echo "Expected: openai\n";
echo "Result: " . ($result['provider'] === 'openai' ? 'PASS' : 'FAIL') . "\n\n";

// Test 5: Variable substitution in prompt
echo "Test 5: Variable substitution in prompt\n";
$agentConfig = [
    'enabled' => true,
    'prompt' => 'Hello {{contact_name}}, your message: {{message}}'
];
$context = [
    'contact_name' => 'John Doe',
    'message' => 'I need help'
];
$result = $processor->process($agentConfig, $context);
echo "Prompt: " . $result['prompt'] . "\n";
echo "Expected: Hello John Doe, your message: I need help\n";
echo "Result: " . ($result['prompt'] === 'Hello John Doe, your message: I need help' ? 'PASS' : 'FAIL') . "\n\n";

// Test 6: Missing prompt error
echo "Test 6: Missing prompt error\n";
$agentConfig = [
    'enabled' => true
    // No prompt
];
$context = ['message' => 'Hello'];
$result = $processor->process($agentConfig, $context);
echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Error: " . $result['error'] . "\n";
echo "Expected: false with error message\n";
echo "Result: " . (!$result['success'] && strpos($result['error'], 'Prompt is required') !== false ? 'PASS' : 'FAIL') . "\n\n";

// Test 7: Timestamp formatting
echo "Test 7: Timestamp formatting\n";
$agentConfig = [
    'enabled' => true,
    'prompt' => 'Time: {{timestamp}}'
];
$context = [
    'message' => 'Hello',
    'timestamp' => 1704110400 // 2024-01-01 12:00:00
];
$result = $processor->process($agentConfig, $context);
echo "Prompt: " . $result['prompt'] . "\n";
echo "Expected: Contains '2024-01-01'\n";
echo "Result: " . (strpos($result['prompt'], '2024-01-01') !== false ? 'PASS' : 'FAIL') . "\n\n";

// Test 8: Execution time is recorded
echo "Test 8: Execution time is recorded\n";
$agentConfig = [
    'enabled' => true,
    'prompt' => 'Test'
];
$context = ['message' => 'Hello'];
$result = $processor->process($agentConfig, $context);
echo "Execution time: " . $result['execution_time_ms'] . " ms\n";
echo "Expected: >= 0\n";
echo "Result: " . ($result['execution_time_ms'] >= 0 ? 'PASS' : 'FAIL') . "\n\n";

// Test 9: Case-insensitive provider handling
echo "Test 9: Case-insensitive provider handling\n";
$agentConfig = [
    'enabled' => true,
    'provider' => 'OpenAI',
    'prompt' => 'Test'
];
$context = ['message' => 'Hello'];
$result = $processor->process($agentConfig, $context);
echo "Provider: " . $result['provider'] . "\n";
echo "Expected: openai\n";
echo "Result: " . ($result['provider'] === 'openai' ? 'PASS' : 'FAIL') . "\n\n";

// Test 10: Empty history when no conversation_id
echo "Test 10: Empty history when no conversation_id\n";
$agentConfig = [
    'enabled' => true,
    'prompt' => 'History: {{history}}'
];
$context = ['message' => 'Hello'];
$result = $processor->process($agentConfig, $context);
echo "Prompt: " . $result['prompt'] . "\n";
echo "Expected: 'History: '\n";
echo "Result: " . ($result['prompt'] === 'History: ' ? 'PASS' : 'FAIL') . "\n\n";

echo "=== All Tests Completed ===\n";
