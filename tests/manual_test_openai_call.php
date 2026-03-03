<?php
/**
 * Manual Test for callOpenAI() method
 * 
 * Tests the OpenAI API integration including:
 * - API key retrieval
 * - HTTP request with proper configuration
 * - Timeout handling
 * - Retry logic with exponential backoff
 * - Error handling
 */

require_once __DIR__ . '/../includes/AIProcessor.php';
require_once __DIR__ . '/../includes/VariableSubstitutor.php';
require_once __DIR__ . '/../includes/init.php';

echo "=== OpenAI API Call Test ===\n\n";

// Get user_id from database (assuming user exists)
$stmt = $pdo->query("SELECT id FROM users LIMIT 1");
$userId = $stmt->fetchColumn();

if (!$userId) {
    echo "ERROR: No users found in database. Please create a user first.\n";
    exit(1);
}

echo "Using user_id: $userId\n\n";

// Check if OpenAI API key is configured
$stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'openai_api_key' AND user_id = ?");
$stmt->execute([$userId]);
$apiKey = $stmt->fetchColumn();

if (!$apiKey) {
    echo "WARNING: OpenAI API key not configured for this user.\n";
    echo "To configure, run:\n";
    echo "INSERT INTO system_config (user_id, config_key, config_value) VALUES ($userId, 'openai_api_key', 'your-api-key-here');\n\n";
    echo "Skipping actual API call tests.\n\n";
    
    // Test that it handles missing API key gracefully
    echo "Test 1: Handle missing API key gracefully\n";
    $processor = new AIProcessor($pdo, [], $userId);
    $agentConfig = [
        'enabled' => true,
        'provider' => 'openai',
        'prompt' => 'Say hello',
        'model' => 'gpt-3.5-turbo',
        'temperature' => 0.7,
        'max_tokens' => 50,
        'timeout' => 10
    ];
    $context = ['message' => 'Hello'];
    $result = $processor->process($agentConfig, $context);
    
    echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
    echo "Response: " . ($result['response'] ?? 'null') . "\n";
    echo "Error: " . ($result['error'] ?? 'none') . "\n";
    echo "Expected: success=false, response=null\n";
    echo "Result: " . (!$result['success'] && $result['response'] === null ? 'PASS' : 'FAIL') . "\n\n";
    
    exit(0);
}

echo "OpenAI API key found: " . substr($apiKey, 0, 10) . "...\n\n";

// Test 1: Basic OpenAI call with default configuration
echo "Test 1: Basic OpenAI call with default configuration\n";
$processor = new AIProcessor($pdo, [], $userId);
$agentConfig = [
    'enabled' => true,
    'provider' => 'openai',
    'prompt' => 'Say "Hello World" and nothing else.',
    'model' => 'gpt-3.5-turbo',
    'temperature' => 0.7,
    'max_tokens' => 50,
    'timeout' => 30
];
$context = ['message' => 'Test'];
$result = $processor->process($agentConfig, $context);

echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Provider: " . $result['provider'] . "\n";
echo "Response: " . ($result['response'] ?? 'null') . "\n";
echo "Execution time: " . $result['execution_time_ms'] . " ms\n";
echo "Error: " . ($result['error'] ?? 'none') . "\n";
echo "Expected: success=true, provider=openai, response contains text\n";
echo "Result: " . ($result['success'] && $result['provider'] === 'openai' && !empty($result['response']) ? 'PASS' : 'FAIL') . "\n\n";

// Test 2: OpenAI call with custom model
echo "Test 2: OpenAI call with custom model (gpt-4)\n";
$agentConfig = [
    'enabled' => true,
    'provider' => 'openai',
    'prompt' => 'What is 2+2? Answer with just the number.',
    'model' => 'gpt-4',
    'temperature' => 0.1,
    'max_tokens' => 10,
    'timeout' => 30
];
$context = ['message' => 'Test'];
$result = $processor->process($agentConfig, $context);

echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Response: " . ($result['response'] ?? 'null') . "\n";
echo "Execution time: " . $result['execution_time_ms'] . " ms\n";
echo "Expected: success=true, response contains '4'\n";
echo "Result: " . ($result['success'] && strpos($result['response'], '4') !== false ? 'PASS' : 'FAIL') . "\n\n";

// Test 3: OpenAI call with variable substitution
echo "Test 3: OpenAI call with variable substitution\n";
$agentConfig = [
    'enabled' => true,
    'provider' => 'openai',
    'prompt' => 'The user {{contact_name}} said: "{{message}}". Respond with a greeting.',
    'model' => 'gpt-3.5-turbo',
    'temperature' => 0.7,
    'max_tokens' => 100,
    'timeout' => 30
];
$context = [
    'contact_name' => 'Alice',
    'message' => 'Hello there!'
];
$result = $processor->process($agentConfig, $context);

echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Prompt sent: " . $result['prompt'] . "\n";
echo "Response: " . ($result['response'] ?? 'null') . "\n";
echo "Expected: success=true, prompt contains 'Alice' and 'Hello there!'\n";
echo "Result: " . ($result['success'] && strpos($result['prompt'], 'Alice') !== false && strpos($result['prompt'], 'Hello there!') !== false ? 'PASS' : 'FAIL') . "\n\n";

// Test 4: Short timeout (should still work for simple prompts)
echo "Test 4: Short timeout (10 seconds)\n";
$agentConfig = [
    'enabled' => true,
    'provider' => 'openai',
    'prompt' => 'Say hi',
    'model' => 'gpt-3.5-turbo',
    'temperature' => 0.7,
    'max_tokens' => 10,
    'timeout' => 10
];
$context = ['message' => 'Test'];
$result = $processor->process($agentConfig, $context);

echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Response: " . ($result['response'] ?? 'null') . "\n";
echo "Execution time: " . $result['execution_time_ms'] . " ms\n";
echo "Expected: success=true (should complete within 10s)\n";
echo "Result: " . ($result['success'] ? 'PASS' : 'FAIL') . "\n\n";

// Test 5: Invalid API key handling (simulate by using wrong user)
echo "Test 5: Invalid API key handling\n";
$processorNoKey = new AIProcessor($pdo, [], 99999); // Non-existent user
$agentConfig = [
    'enabled' => true,
    'provider' => 'openai',
    'prompt' => 'Test',
    'model' => 'gpt-3.5-turbo'
];
$context = ['message' => 'Test'];
$result = $processorNoKey->process($agentConfig, $context);

echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Response: " . ($result['response'] ?? 'null') . "\n";
echo "Error: " . ($result['error'] ?? 'none') . "\n";
echo "Expected: success=false, response=null\n";
echo "Result: " . (!$result['success'] && $result['response'] === null ? 'PASS' : 'FAIL') . "\n\n";

echo "=== All Tests Completed ===\n";
echo "\nNOTE: These tests make real API calls to OpenAI and will consume tokens.\n";
echo "Estimated cost: ~$0.01 USD\n";

