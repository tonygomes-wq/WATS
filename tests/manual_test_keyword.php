<?php
/**
 * Manual Test for TriggerEvaluator::evaluateKeyword()
 * 
 * This script manually tests the keyword evaluation functionality
 * Run with: php tests/manual_test_keyword.php
 */

require_once __DIR__ . '/../includes/TriggerEvaluator.php';

// Mock PDO
$pdo = new class extends PDO {
    public function __construct() {}
};

$evaluator = new TriggerEvaluator($pdo);

// Test cases
$tests = [
    // Test 1: Case-insensitive matching
    [
        'name' => 'Case-insensitive matching - lowercase keyword, uppercase message',
        'config' => ['keywords' => ['olá']],
        'context' => ['message' => 'OLÁ, tudo bem?'],
        'expected' => true
    ],
    [
        'name' => 'Case-insensitive matching - uppercase keyword, lowercase message',
        'config' => ['keywords' => ['OLÁ']],
        'context' => ['message' => 'olá, tudo bem?'],
        'expected' => true
    ],
    
    // Test 2: Multiple keywords
    [
        'name' => 'Multiple keywords - first keyword matches',
        'config' => ['keywords' => ['ajuda', 'suporte', 'problema']],
        'context' => ['message' => 'Preciso de ajuda'],
        'expected' => true
    ],
    [
        'name' => 'Multiple keywords - second keyword matches',
        'config' => ['keywords' => ['ajuda', 'suporte', 'problema']],
        'context' => ['message' => 'Suporte técnico'],
        'expected' => true
    ],
    [
        'name' => 'Multiple keywords - no match',
        'config' => ['keywords' => ['ajuda', 'suporte', 'problema']],
        'context' => ['message' => 'Olá, tudo bem?'],
        'expected' => false
    ],
    
    // Test 3: Comma-separated string
    [
        'name' => 'Comma-separated string',
        'config' => ['keywords' => 'olá, oi, bom dia'],
        'context' => ['message' => 'Bom dia!'],
        'expected' => true
    ],
    
    // Test 4: Empty keywords
    [
        'name' => 'Empty keywords array',
        'config' => ['keywords' => []],
        'context' => ['message' => 'Olá'],
        'expected' => false
    ],
    [
        'name' => 'No keywords config',
        'config' => [],
        'context' => ['message' => 'Olá'],
        'expected' => false
    ],
    
    // Test 5: Empty message
    [
        'name' => 'Empty message',
        'config' => ['keywords' => ['olá']],
        'context' => ['message' => ''],
        'expected' => false
    ],
    [
        'name' => 'No message in context',
        'config' => ['keywords' => ['olá']],
        'context' => [],
        'expected' => false
    ],
    
    // Test 6: UTF-8 support
    [
        'name' => 'UTF-8 characters',
        'config' => ['keywords' => ['ação', 'informação']],
        'context' => ['message' => 'Preciso de uma ação'],
        'expected' => true
    ],
    
    // Test 7: Partial matching
    [
        'name' => 'Partial matching - keyword in middle',
        'config' => ['keywords' => ['ajuda']],
        'context' => ['message' => 'preciso de ajuda urgente'],
        'expected' => true
    ],
];

// Run tests
$passed = 0;
$failed = 0;

echo "Running TriggerEvaluator::evaluateKeyword() tests...\n";
echo str_repeat("=", 70) . "\n\n";

foreach ($tests as $i => $test) {
    $result = $evaluator->evaluate('keyword', $test['config'], $test['context']);
    $success = $result === $test['expected'];
    
    if ($success) {
        $passed++;
        echo "✓ Test " . ($i + 1) . ": {$test['name']}\n";
    } else {
        $failed++;
        echo "✗ Test " . ($i + 1) . ": {$test['name']}\n";
        echo "  Expected: " . ($test['expected'] ? 'true' : 'false') . "\n";
        echo "  Got: " . ($result ? 'true' : 'false') . "\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";

if ($failed === 0) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed!\n";
    exit(1);
}
