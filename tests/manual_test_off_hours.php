<?php
/**
 * Manual Test for Off-Hours Trigger Evaluation
 * 
 * This script tests the evaluateOffHours() method of TriggerEvaluator
 * Run with: php tests/manual_test_off_hours.php
 */

// Carregar a classe TriggerEvaluator
require_once __DIR__ . '/../includes/TriggerEvaluator.php';

// Mock PDO (não é usado no evaluateOffHours)
$pdo = new PDO('sqlite::memory:');

// Criar instância do evaluator
$evaluator = new TriggerEvaluator($pdo);

// Função helper para testar
function testOffHours($evaluator, $testName, $config, $context, $expected) {
    $result = $evaluator->evaluate('off_hours', $config, $context);
    $status = ($result === $expected) ? '✓ PASS' : '✗ FAIL';
    $resultStr = $result ? 'true' : 'false';
    $expectedStr = $expected ? 'true' : 'false';
    echo "{$status} - {$testName}: got {$resultStr}, expected {$expectedStr}\n";
    return $result === $expected;
}

echo "=== Testing Off-Hours Trigger Evaluation ===\n\n";

$passed = 0;
$failed = 0;

// Test 1: Normal business hours (08:00 - 18:00)
echo "Test Group 1: Normal Business Hours (08:00 - 18:00)\n";
$config = [
    'start' => '08:00',
    'end' => '18:00',
    'timezone' => 'America/Sao_Paulo'
];

$tz = new DateTimeZone('America/Sao_Paulo');

// 07:00 - Before hours (should be off-hours = true)
$dt = new DateTime('2024-01-15 07:00:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '07:00 - Before hours', $config, $context, true) ? $passed++ : $failed++;

// 08:00 - Start of hours (should be within hours = false)
$dt = new DateTime('2024-01-15 08:00:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '08:00 - Start of hours', $config, $context, false) ? $passed++ : $failed++;

// 12:00 - Middle of hours (should be within hours = false)
$dt = new DateTime('2024-01-15 12:00:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '12:00 - Middle of hours', $config, $context, false) ? $passed++ : $failed++;

// 17:59 - Almost end of hours (should be within hours = false)
$dt = new DateTime('2024-01-15 17:59:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '17:59 - Almost end of hours', $config, $context, false) ? $passed++ : $failed++;

// 18:00 - End of hours (should be off-hours = true)
$dt = new DateTime('2024-01-15 18:00:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '18:00 - End of hours', $config, $context, true) ? $passed++ : $failed++;

// 19:00 - After hours (should be off-hours = true)
$dt = new DateTime('2024-01-15 19:00:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '19:00 - After hours', $config, $context, true) ? $passed++ : $failed++;

echo "\n";

// Test 2: Overnight hours (18:00 - 08:00)
echo "Test Group 2: Overnight Hours (18:00 - 08:00)\n";
$config = [
    'start' => '18:00',
    'end' => '08:00',
    'timezone' => 'America/Sao_Paulo'
];

// 07:00 - Before end (should be within overnight hours = false)
$dt = new DateTime('2024-01-15 07:00:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '07:00 - Before end (overnight)', $config, $context, false) ? $passed++ : $failed++;

// 08:00 - End of overnight (should be off-hours = true)
$dt = new DateTime('2024-01-15 08:00:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '08:00 - End of overnight', $config, $context, true) ? $passed++ : $failed++;

// 12:00 - Midday (should be off-hours = true)
$dt = new DateTime('2024-01-15 12:00:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '12:00 - Midday', $config, $context, true) ? $passed++ : $failed++;

// 17:59 - Almost start (should be off-hours = true)
$dt = new DateTime('2024-01-15 17:59:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '17:59 - Almost start', $config, $context, true) ? $passed++ : $failed++;

// 18:00 - Start of overnight (should be within hours = false)
$dt = new DateTime('2024-01-15 18:00:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '18:00 - Start of overnight', $config, $context, false) ? $passed++ : $failed++;

// 23:00 - Night (should be within overnight hours = false)
$dt = new DateTime('2024-01-15 23:00:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '23:00 - Night', $config, $context, false) ? $passed++ : $failed++;

// 00:00 - Midnight (should be within overnight hours = false)
$dt = new DateTime('2024-01-15 00:00:00', $tz);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '00:00 - Midnight', $config, $context, false) ? $passed++ : $failed++;

echo "\n";

// Test 3: Different timezone
echo "Test Group 3: Different Timezone (America/New_York)\n";
$config = [
    'start' => '09:00',
    'end' => '17:00',
    'timezone' => 'America/New_York'
];

$tzNY = new DateTimeZone('America/New_York');

// 08:00 NY - Off hours
$dt = new DateTime('2024-01-15 08:00:00', $tzNY);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '08:00 NY - Off hours', $config, $context, true) ? $passed++ : $failed++;

// 10:00 NY - Within hours
$dt = new DateTime('2024-01-15 10:00:00', $tzNY);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '10:00 NY - Within hours', $config, $context, false) ? $passed++ : $failed++;

echo "\n";

// Test 4: Default timezone (UTC)
echo "Test Group 4: Default Timezone (UTC)\n";
$config = [
    'start' => '09:00',
    'end' => '17:00'
    // No timezone specified, should use UTC
];

$tzUTC = new DateTimeZone('UTC');

// 08:00 UTC - Off hours
$dt = new DateTime('2024-01-15 08:00:00', $tzUTC);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '08:00 UTC - Off hours', $config, $context, true) ? $passed++ : $failed++;

// 10:00 UTC - Within hours
$dt = new DateTime('2024-01-15 10:00:00', $tzUTC);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '10:00 UTC - Within hours', $config, $context, false) ? $passed++ : $failed++;

echo "\n";

// Test 5: Edge cases with minutes
echo "Test Group 5: Edge Cases with Minutes (08:30 - 17:45)\n";
$config = [
    'start' => '08:30',
    'end' => '17:45',
    'timezone' => 'UTC'
];

// 08:29 - Off hours
$dt = new DateTime('2024-01-15 08:29:00', $tzUTC);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '08:29 - Off hours', $config, $context, true) ? $passed++ : $failed++;

// 08:30 - Within hours
$dt = new DateTime('2024-01-15 08:30:00', $tzUTC);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '08:30 - Within hours', $config, $context, false) ? $passed++ : $failed++;

// 17:44 - Within hours
$dt = new DateTime('2024-01-15 17:44:00', $tzUTC);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '17:44 - Within hours', $config, $context, false) ? $passed++ : $failed++;

// 17:45 - Off hours
$dt = new DateTime('2024-01-15 17:45:00', $tzUTC);
$context = ['timestamp' => $dt->getTimestamp()];
testOffHours($evaluator, '17:45 - Off hours', $config, $context, true) ? $passed++ : $failed++;

echo "\n";

// Test 6: Invalid configurations
echo "Test Group 6: Invalid Configurations\n";

// Missing start
$config = ['end' => '18:00', 'timezone' => 'UTC'];
$context = ['timestamp' => time()];
testOffHours($evaluator, 'Missing start', $config, $context, false) ? $passed++ : $failed++;

// Missing end
$config = ['start' => '08:00', 'timezone' => 'UTC'];
$context = ['timestamp' => time()];
testOffHours($evaluator, 'Missing end', $config, $context, false) ? $passed++ : $failed++;

// Invalid time format
$config = ['start' => '8:00', 'end' => '18:00', 'timezone' => 'UTC'];
$context = ['timestamp' => time()];
testOffHours($evaluator, 'Invalid time format (8:00)', $config, $context, false) ? $passed++ : $failed++;

// Invalid timezone
$config = ['start' => '08:00', 'end' => '18:00', 'timezone' => 'Invalid/Timezone'];
$context = ['timestamp' => time()];
testOffHours($evaluator, 'Invalid timezone', $config, $context, false) ? $passed++ : $failed++;

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo "Total: " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n✓ All tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed!\n";
    exit(1);
}
