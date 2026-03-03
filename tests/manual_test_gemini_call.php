<?php
/**
 * Manual Test for callGemini() Method
 * 
 * This script tests the Gemini API integration in AIProcessor.
 * 
 * IMPORTANT: This test makes REAL API calls to Google Gemini.
 * You need to configure a Gemini API key in the database before running.
 * 
 * Setup:
 * 1. Get a Gemini API key from https://makersuite.google.com/app/apikey
 * 2. Insert into database:
 *    INSERT INTO system_config (user_id, config_key, config_value)
 *    VALUES (1, 'gemini_api_key', 'YOUR_API_KEY_HERE');
 * 3. Run this script: php tests/manual_test_gemini_call.php
 */

require_once __DIR__ . '/../includes/AIProcessor.php';
require_once __DIR__ . '/../includes/VariableSubstitutor.php';
require_once __DIR__ . '/../includes/init.php';

echo "=== Gemini API Integration Test ===\n\n";

// Get user_id from command line or use default
$userId = isset($argv[1]) ? (int)$argv[1] : 1;
echo "Using user_id: $userId\n\n";

// Test 1: Missing API key
echo "Test 1: Missing API key handling\n";
echo "-----------------------------------\n";
$processor = new AIProcessor($pdo, [], 999); // Non-existent user
$agentConfig = [
    'enabled' => true,
    'provider' => 'gemini',
    'prompt' => 'Say hello',
    'model' => 'gemini-pro'
];
$context = ['message' => 'Test'];
$result = $processor->process($agentConfig, $context);
echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Error: " . ($result['error'] ?? 'none') . "\n";
echo "Expected: false (API key not configured)\n";
echo "Result: " . (!$result['success'] ? 'PASS' : 'FAIL') . "\n\n";

// Test 2: Basic Gemini call with default configuration
echo "Test 2: Basic Gemini call (default config)\n";
echo "-------------------------------------------\n";
$processor = new AIProcessor($pdo, [], $userId);
$agentConfig = [
    'enabled' => true,
    'provider' => 'gemini',
    'prompt' => 'Say "Hello from Gemini!" and nothing else.',
    'model' => 'gemini-pro'
];
$context = ['message' => 'Test'];
$result = $processor->process($agentConfig, $context);
echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Response: " . ($result['response'] ?? 'null') . "\n";
echo "Error: " . ($result['error'] ?? 'none') . "\n";
echo "Execution time: " . $result['execution_time_ms'] . " ms\n";
echo "Expected: Success with greeting response\n";
echo "Result: " . ($result['success'] ? 'PASS' : 'FAIL') . "\n\n";

// Test 3: Custom model configuration
echo "Test 3: Custom model (gemini-pro)\n";
echo "-----------------------------------\n";
$processor = new AIProcessor($pdo, [], $userId);
$agentConfig = [
    'enabled' => true,
    'provider' => 'gemini',
    'prompt' => 'What is 2+2? Answer with just the number.',
    'model' => 'gemini-pro',
    'temperature' => 0.1,
    'max_tokens' => 10
];
$context = ['message' => 'Test'];
$result = $processor->process($agentConfig, $context);
echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Response: " . ($result['response'] ?? 'null') . "\n";
echo "Error: " . ($result['error'] ?? 'none') . "\n";
echo "Expected: Success with '4' or similar\n";
echo "Result: " . ($result['success'] ? 'PASS' : 'FAIL') . "\n\n";

// Test 4: Variable substitution in prompt
echo "Test 4: Variable substitution\n";
echo "------------------------------\n";
$processor = new AIProcessor($pdo, [], $userId);
$agentConfig = [
    'enabled' => true,
    'provider' => 'gemini',
    'prompt' => 'The user {{contact_name}} said: "{{message}}". Respond with a greeting that includes their name.',
    'model' => 'gemini-pro'
];
$context = [
    'contact_name' => 'Alice',
    'message' => 'Hello there!'
];
$result = $processor->process($agentConfig, $context);
echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Prompt sent: " . $result['prompt'] . "\n";
echo "Response: " . ($result['response'] ?? 'null') . "\n";
echo "Error: " . ($result['error'] ?? 'none') . "\n";
echo "Expected: Success with personalized greeting\n";
echo "Result: " . ($result['success'] && strpos($result['response'], 'Alice') !== false ? 'PASS' : 'FAIL') . "\n\n";

// Test 5: Short timeout (should fail or succeed quickly)
echo "Test 5: Short timeout (2 seconds)\n";
echo "----------------------------------\n";
$processor = new AIProcessor($pdo, [], $userId);
$agentConfig = [
    'enabled' => true,
    'provider' => 'gemini',
    'prompt' => 'Write a very short poem.',
    'model' => 'gemini-pro',
    'timeout' => 2
];
$context = ['message' => 'Test'];
$result = $processor->process($agentConfig, $context);
echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Response: " . ($result['response'] ?? 'null') . "\n";
echo "Error: " . ($result['error'] ?? 'none') . "\n";
echo "Execution time: " . $result['execution_time_ms'] . " ms\n";
echo "Expected: Success or timeout error\n";
echo "Result: " . ($result['execution_time_ms'] <= 3000 ? 'PASS' : 'FAIL') . "\n\n";

// Test 6: Invalid API key (if you want to test error handling)
echo "Test 6: Invalid API key\n";
echo "------------------------\n";
echo "Skipping (would require temporarily changing API key)\n";
echo "To test manually: Update system_config with invalid key and run again\n\n";

// Test 7: Temperature and max_tokens configuration
echo "Test 7: Temperature and max_tokens\n";
echo "-----------------------------------\n";
$processor = new AIProcessor($pdo, [], $userId);
$agentConfig = [
    'enabled' => true,
    'provider' => 'gemini',
    'prompt' => 'List 3 colors.',
    'model' => 'gemini-pro',
    'temperature' => 0.9,
    'max_tokens' => 50
];
$context = ['message' => 'Test'];
$result = $processor->process($agentConfig, $context);
echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Response: " . ($result['response'] ?? 'null') . "\n";
echo "Response length: " . strlen($result['response'] ?? '') . " chars\n";
echo "Error: " . ($result['error'] ?? 'none') . "\n";
echo "Expected: Success with short response\n";
echo "Result: " . ($result['success'] ? 'PASS' : 'FAIL') . "\n\n";

// Test 8: Comparison with OpenAI (same prompt)
echo "Test 8: Comparison with OpenAI\n";
echo "-------------------------------\n";
$prompt = 'What is the capital of France? Answer with just the city name.';

// Gemini
$processorGemini = new AIProcessor($pdo, [], $userId);
$agentConfigGemini = [
    'enabled' => true,
    'provider' => 'gemini',
    'prompt' => $prompt,
    'model' => 'gemini-pro'
];
$resultGemini = $processorGemini->process($agentConfigGemini, ['message' => 'Test']);

// OpenAI
$processorOpenAI = new AIProcessor($pdo, [], $userId);
$agentConfigOpenAI = [
    'enabled' => true,
    'provider' => 'openai',
    'prompt' => $prompt,
    'model' => 'gpt-4'
];
$resultOpenAI = $processorOpenAI->process($agentConfigOpenAI, ['message' => 'Test']);

echo "Gemini:\n";
echo "  Success: " . ($resultGemini['success'] ? 'true' : 'false') . "\n";
echo "  Response: " . ($resultGemini['response'] ?? 'null') . "\n";
echo "  Time: " . $resultGemini['execution_time_ms'] . " ms\n\n";

echo "OpenAI:\n";
echo "  Success: " . ($resultOpenAI['success'] ? 'true' : 'false') . "\n";
echo "  Response: " . ($resultOpenAI['response'] ?? 'null') . "\n";
echo "  Time: " . $resultOpenAI['execution_time_ms'] . " ms\n\n";

echo "Expected: Both should return 'Paris'\n";
$geminiHasParis = stripos($resultGemini['response'] ?? '', 'Paris') !== false;
$openaiHasParis = stripos($resultOpenAI['response'] ?? '', 'Paris') !== false;
echo "Result: " . ($geminiHasParis && $openaiHasParis ? 'PASS' : 'PARTIAL') . "\n\n";

echo "=== All Tests Completed ===\n";
echo "\nNOTE: This test makes real API calls to Gemini.\n";
echo "Gemini has a generous free tier, but be aware of usage limits.\n";
echo "Check your usage at: https://makersuite.google.com/app/apikey\n";
