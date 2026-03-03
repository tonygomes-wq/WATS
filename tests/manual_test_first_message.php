<?php
/**
 * Manual test for evaluateFirstMessage() implementation
 * 
 * This script tests the first_message trigger evaluation logic
 * without requiring a full PHPUnit setup.
 */

require_once __DIR__ . '/../includes/TriggerEvaluator.php';

echo "=== Manual Test: evaluateFirstMessage() ===\n\n";

// Test 1: Mock PDO for first message scenario
echo "Test 1: First message (count = 1)\n";
try {
    $pdo = new class extends PDO {
        public function __construct() {}
        public function prepare($sql) {
            return new class {
                public function execute($params) { return true; }
                public function fetch($mode) { return ['message_count' => 1]; }
            };
        }
    };
    
    $evaluator = new TriggerEvaluator($pdo);
    $result = $evaluator->evaluate('first_message', [], ['conversation_id' => 123]);
    echo "Result: " . ($result ? "PASS ✓" : "FAIL ✗") . "\n";
    echo "Expected: true, Got: " . ($result ? "true" : "false") . "\n\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
}

// Test 2: Not first message scenario
echo "Test 2: Not first message (count = 5)\n";
try {
    $pdo = new class extends PDO {
        public function __construct() {}
        public function prepare($sql) {
            return new class {
                public function execute($params) { return true; }
                public function fetch($mode) { return ['message_count' => 5]; }
            };
        }
    };
    
    $evaluator = new TriggerEvaluator($pdo);
    $result = $evaluator->evaluate('first_message', [], ['conversation_id' => 123]);
    echo "Result: " . (!$result ? "PASS ✓" : "FAIL ✗") . "\n";
    echo "Expected: false, Got: " . ($result ? "true" : "false") . "\n\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
}

// Test 3: Missing context (no conversation_id or phone)
echo "Test 3: Missing context (should return false)\n";
try {
    $pdo = new class extends PDO {
        public function __construct() {}
        public function prepare($sql) {
            return new class {
                public function execute($params) { return true; }
                public function fetch($mode) { return ['message_count' => 1]; }
            };
        }
    };
    
    $evaluator = new TriggerEvaluator($pdo);
    $result = $evaluator->evaluate('first_message', [], ['message' => 'Hello']);
    echo "Result: " . (!$result ? "PASS ✓" : "FAIL ✗") . "\n";
    echo "Expected: false, Got: " . ($result ? "true" : "false") . "\n\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
}

// Test 4: With window_seconds configuration
echo "Test 4: First message with window_seconds\n";
try {
    $pdo = new class extends PDO {
        public function __construct() {}
        public function prepare($sql) {
            // Verify SQL contains window_seconds logic
            $hasWindow = strpos($sql, 'DATE_SUB') !== false;
            return new class($hasWindow) {
                private $hasWindow;
                public function __construct($hasWindow) { $this->hasWindow = $hasWindow; }
                public function execute($params) { 
                    // Verify window_seconds parameter is passed
                    if ($this->hasWindow && !isset($params[':window_seconds'])) {
                        throw new Exception("window_seconds parameter not found");
                    }
                    return true; 
                }
                public function fetch($mode) { return ['message_count' => 1]; }
            };
        }
    };
    
    $evaluator = new TriggerEvaluator($pdo);
    $result = $evaluator->evaluate('first_message', ['window_seconds' => 600], ['conversation_id' => 123]);
    echo "Result: " . ($result ? "PASS ✓" : "FAIL ✗") . "\n";
    echo "Expected: true, Got: " . ($result ? "true" : "false") . "\n\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
}

// Test 5: Using phone instead of conversation_id
echo "Test 5: First message with phone\n";
try {
    $pdo = new class extends PDO {
        public function __construct() {}
        public function prepare($sql) {
            // Verify SQL uses phone join
            $hasPhoneJoin = strpos($sql, 'contact_number') !== false;
            return new class($hasPhoneJoin) {
                private $hasPhoneJoin;
                public function __construct($hasPhoneJoin) { $this->hasPhoneJoin = $hasPhoneJoin; }
                public function execute($params) { 
                    if ($this->hasPhoneJoin && !isset($params[':phone'])) {
                        throw new Exception("phone parameter not found");
                    }
                    return true; 
                }
                public function fetch($mode) { return ['message_count' => 1]; }
            };
        }
    };
    
    $evaluator = new TriggerEvaluator($pdo);
    $result = $evaluator->evaluate('first_message', [], ['phone' => '5511999999999']);
    echo "Result: " . ($result ? "PASS ✓" : "FAIL ✗") . "\n";
    echo "Expected: true, Got: " . ($result ? "true" : "false") . "\n\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
}

echo "=== All manual tests completed ===\n";
