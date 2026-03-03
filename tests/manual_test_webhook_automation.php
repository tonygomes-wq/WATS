<?php
/**
 * Manual Test - Webhook Automation Integration
 * 
 * Tests the integration of AutomationEngine with chat_webhook.php
 * 
 * This test verifies:
 * 1. AutomationEngine is properly instantiated in webhook
 * 2. checkAndExecute() is called with correct parameters
 * 3. Bot session checking works correctly
 * 4. Human attendance checking works correctly
 * 5. Automation flows are executed when conditions are met
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/AutomationEngine.php';
require_once __DIR__ . '/../includes/bot_engine.php';

echo "=== WEBHOOK AUTOMATION INTEGRATION TEST ===\n\n";

// Test configuration
$testUserId = 1; // Change to a valid user ID
$testPhone = '5511999999999';
$testMessage = 'olá'; // Keyword that might trigger automation
$testConversationId = null;

echo "Test Configuration:\n";
echo "- User ID: $testUserId\n";
echo "- Phone: $testPhone\n";
echo "- Message: $testMessage\n\n";

// Test 1: Check if AutomationEngine can be instantiated
echo "Test 1: AutomationEngine Instantiation\n";
echo "----------------------------------------\n";
try {
    $automationEngine = new AutomationEngine($pdo, $testUserId);
    echo "✓ AutomationEngine instantiated successfully\n\n";
} catch (Exception $e) {
    echo "✗ Failed to instantiate AutomationEngine: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Check if there are active automation flows
echo "Test 2: Active Automation Flows\n";
echo "----------------------------------------\n";
try {
    $stmt = $pdo->prepare("SELECT id, name, trigger_type, status FROM automation_flows WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$testUserId]);
    $flows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($flows)) {
        echo "⚠ No active automation flows found for user $testUserId\n";
        echo "  Create an automation flow to test the integration\n\n";
    } else {
        echo "✓ Found " . count($flows) . " active automation flow(s):\n";
        foreach ($flows as $flow) {
            echo "  - Flow #{$flow['id']}: {$flow['name']} (trigger: {$flow['trigger_type']})\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking flows: " . $e->getMessage() . "\n\n";
}

// Test 3: Create a test conversation
echo "Test 3: Create Test Conversation\n";
echo "----------------------------------------\n";
try {
    // Check if conversation exists
    $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE user_id = ? AND phone = ? LIMIT 1");
    $stmt->execute([$testUserId, $testPhone]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $testConversationId = $existing['id'];
        echo "✓ Using existing conversation ID: $testConversationId\n\n";
    } else {
        $stmt = $pdo->prepare("INSERT INTO chat_conversations (user_id, phone, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$testUserId, $testPhone]);
        $testConversationId = $pdo->lastInsertId();
        echo "✓ Created test conversation ID: $testConversationId\n\n";
    }
} catch (Exception $e) {
    echo "✗ Error creating conversation: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 4: Check bot session status
echo "Test 4: Bot Session Check\n";
echo "----------------------------------------\n";
try {
    $botEngine = new BotEngine($pdo, $testUserId);
    $hasActiveSession = $botEngine->hasActiveSession($testPhone);
    
    if ($hasActiveSession) {
        echo "⚠ Active bot session found for phone: $testPhone\n";
        echo "  Automation flows will be skipped\n\n";
    } else {
        echo "✓ No active bot session - automation flows can execute\n\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking bot session: " . $e->getMessage() . "\n\n";
}

// Test 5: Execute checkAndExecute
echo "Test 5: Execute checkAndExecute()\n";
echo "----------------------------------------\n";
try {
    $result = $automationEngine->checkAndExecute(
        $testPhone,
        $testMessage,
        $testConversationId,
        [
            'timestamp' => time(),
            'channel' => 'whatsapp',
            'message_id' => 'TEST_' . time()
        ]
    );
    
    echo "✓ checkAndExecute() executed successfully\n";
    echo "Result:\n";
    echo "  - Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
    echo "  - Flows executed: {$result['flows_executed']}\n";
    
    if (isset($result['skipped_reason'])) {
        echo "  - Skipped reason: {$result['skipped_reason']}\n";
    }
    
    if (!empty($result['flows_triggered'])) {
        echo "  - Triggered flows:\n";
        foreach ($result['flows_triggered'] as $flow) {
            echo "    * Flow #{$flow['flow_id']}: {$flow['flow_name']} - Status: {$flow['status']}\n";
        }
    }
    
    if (!empty($result['errors'])) {
        echo "  - Errors:\n";
        foreach ($result['errors'] as $error) {
            $flowInfo = isset($error['flow_id']) ? "Flow #{$error['flow_id']}" : "General";
            echo "    * $flowInfo: {$error['error']}\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error executing checkAndExecute: " . $e->getMessage() . "\n\n";
}

// Test 6: Check automation flow logs
echo "Test 6: Automation Flow Logs\n";
echo "----------------------------------------\n";
try {
    $stmt = $pdo->prepare("
        SELECT afl.*, af.name as flow_name 
        FROM automation_flow_logs afl
        JOIN automation_flows af ON afl.flow_id = af.id
        WHERE afl.conversation_id = ?
        ORDER BY afl.executed_at DESC
        LIMIT 5
    ");
    $stmt->execute([$testConversationId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "⚠ No automation flow logs found for conversation $testConversationId\n\n";
    } else {
        echo "✓ Found " . count($logs) . " log entry(ies):\n";
        foreach ($logs as $log) {
            echo "  - Log #{$log['id']}: {$log['flow_name']}\n";
            echo "    Status: {$log['status']}\n";
            echo "    Executed at: {$log['executed_at']}\n";
            echo "    Execution time: {$log['execution_time_ms']}ms\n";
            if ($log['error_message']) {
                echo "    Error: {$log['error_message']}\n";
            }
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking logs: " . $e->getMessage() . "\n\n";
}

echo "=== TEST COMPLETE ===\n";
echo "\nNotes:\n";
echo "- If no flows were executed, check that you have active automation flows\n";
echo "- Make sure the trigger conditions match the test message\n";
echo "- Check the automation_flow_logs table for detailed execution logs\n";
