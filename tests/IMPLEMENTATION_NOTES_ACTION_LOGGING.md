# Action Result Logging Implementation Notes

## Task 6.8: Add Action Result Logging

### Overview
Enhanced the `ActionExecutor::executeActions()` method to properly structure and log action results according to requirement 4.11.

### Changes Made

#### 1. Result Structure Enhancement
The `executeActions()` method now returns a **flattened structure** where action-specific data is merged directly into the result object, rather than being nested in a `data` field.

**Old Structure:**
```php
[
    'index' => 0,
    'type' => 'send_message',
    'status' => 'success',
    'timestamp' => 1234567890,
    'error' => null,
    'data' => [
        'action' => 'send_message',
        'status' => 'success',
        'message_id' => 'ABC123',
        'message_text' => 'Hello'
    ]
]
```

**New Structure:**
```php
[
    'type' => 'send_message',
    'status' => 'success',
    'timestamp' => 1234567890,
    'message_id' => 'ABC123',      // Flattened from data
    'message_text' => 'Hello',      // Flattened from data
    // 'error' field only present if status is 'failed'
]
```

#### 2. Benefits of New Structure

1. **Cleaner JSON for Database Storage**: The flattened structure matches the design document's expected `action_results` JSON format
2. **No Redundancy**: Removes duplicate 'action' and 'status' fields
3. **Easier to Query**: Action-specific fields are directly accessible
4. **Better Alignment with Requirements**: Matches requirement 4.11 specification

#### 3. Implementation Details

The `executeActions()` method now:
1. Creates a base result with `type`, `status`, and `timestamp`
2. Executes the action and gets execution data
3. Removes redundant fields (`action`, `status`) from execution data
4. Merges execution data directly into the result
5. Sets final status to 'success' or keeps 'failed' with error message

```php
// Remove redundant fields before merging
unset($executionData['action']); // Already have 'type'
unset($executionData['status']); // Will be set after merge

// Merge action-specific data directly
$actionResult = array_merge($actionResult, $executionData);
$actionResult['status'] = 'success';
```

#### 4. Error Handling

- Error field is only added when status is 'failed'
- Error isolation continues to work - one action failure doesn't stop others
- All errors are logged to error_log for debugging

### Example Results by Action Type

#### send_message
```php
[
    'type' => 'send_message',
    'status' => 'success',
    'timestamp' => 1234567890,
    'message_id' => 'ABC123',
    'message_text' => 'Hello World'
]
```

#### assign_attendant
```php
[
    'type' => 'assign_attendant',
    'status' => 'success',
    'timestamp' => 1234567890,
    'attendant_id' => 5,
    'attendant_name' => 'John Doe',
    'conversation_id' => 123,
    'bot_sessions_ended' => 1
]
```

#### add_tag
```php
[
    'type' => 'add_tag',
    'status' => 'success',
    'timestamp' => 1234567890,
    'tag' => 'automated',
    'tags' => ['automated', 'ai_processed']
]
```

#### create_task
```php
[
    'type' => 'create_task',
    'status' => 'success',
    'timestamp' => 1234567890,
    'card_id' => 456,
    'board_id' => 1,
    'column_id' => 2,
    'title' => 'Follow up with customer',
    'conversation_id' => 123
]
```

#### webhook
```php
[
    'type' => 'webhook',
    'status' => 'success',
    'timestamp' => 1234567890,
    'url' => 'https://example.com/webhook',
    'method' => 'POST',
    'http_code' => 200,
    'execution_time_ms' => 145,
    'response' => '{"success":true}'
]
```

#### update_field
```php
[
    'type' => 'update_field',
    'status' => 'success',
    'timestamp' => 1234567890,
    'contact_id' => 789,
    'field' => 'company',
    'value' => 'Acme Corp',
    'old_value' => null,
    'operation' => 'updated'
]
```

#### Failed Action Example
```php
[
    'type' => 'send_message',
    'status' => 'failed',
    'timestamp' => 1234567890,
    'error' => 'Phone number not available in context'
]
```

### Database Storage

When stored in the `automation_flow_logs.action_results` JSON column, the results array is stored as:

```json
{
  "actions": [
    {
      "type": "send_message",
      "status": "success",
      "timestamp": 1234567890,
      "message_id": "ABC123",
      "message_text": "Hello"
    },
    {
      "type": "add_tag",
      "status": "success",
      "timestamp": 1234567890,
      "tag": "automated",
      "tags": ["automated"]
    }
  ]
}
```

Note: The AutomationEngine wraps the results array in an "actions" key when saving to the database.

### Test Updates Required

Several unit tests need to be updated to work with the new flattened structure:

1. Tests that access `$result['data']` should access fields directly from `$result`
2. Tests that check for `$result['index']` should be removed (no longer needed)
3. Tests that check `$result['data']['action']` should check `$result['type']` instead
4. Tests that check `$result['data']['status']` should check `$result['status']` instead

**Example Test Update:**
```php
// Old
$data = $results[0]['data'];
$this->assertEquals('send_message', $data['action']);
$this->assertEquals('ABC123', $data['message_id']);

// New
$this->assertEquals('send_message', $results[0]['type']);
$this->assertEquals('ABC123', $results[0]['message_id']);
```

### Validation Against Requirements

✅ **Requirement 4.11**: "THE AutomationEngine SHALL registrar o resultado de cada ação em action_result no log"
- Each action result includes type, status, timestamp, execution data, and error (if failed)
- Results are returned in structured format ready for database storage
- Format matches design document specification

✅ **Requirement 4.10**: "IF uma ação falha, THEN THE AutomationEngine SHALL registrar o erro mas continuar executando as próximas ações"
- Error isolation is maintained
- Failed actions include error message
- Execution continues with remaining actions

✅ **Requirement 4.1**: "WHEN action_config contém ações configuradas, THE AutomationEngine SHALL executar todas as ações na ordem definida"
- Actions are executed in order
- Results maintain execution order
- All actions are processed

### Integration with AutomationEngine

The AutomationEngine will use these results as follows:

```php
// In AutomationEngine::executeFlowInternal()
$actionResults = $actionExecutor->executeActions($actions, $context);

// Store in database
$this->logExecution(
    $flowId,
    $conversationId,
    'success',
    [
        'trigger_payload' => [...],
        'agent_response' => $aiResponse,
        'action_results' => ['actions' => $actionResults], // Wrap in 'actions' key
        'execution_time_ms' => $executionTime
    ]
);
```

### Backward Compatibility

This change modifies the return structure of `executeActions()`. Any code that currently accesses `$result['data']` will need to be updated to access fields directly from `$result`.

The change is intentional to better align with the design specification and improve the overall data structure for logging and querying.

### Next Steps

1. ✅ Update ActionExecutor.php with flattened structure
2. ✅ Update key unit tests (ActionExecutorTest.php)
3. ✅ Update manual tests (manual_test_action_executor.php)
4. ⏳ Update remaining unit tests that reference 'data' field
5. ⏳ Integrate with AutomationEngine (Task 7.x)
6. ⏳ Test end-to-end flow with database logging

### Files Modified

- `includes/ActionExecutor.php` - Enhanced executeActions() method
- `tests/Unit/ActionExecutorTest.php` - Updated key tests and added documentation
- `tests/manual_test_action_executor.php` - Updated manual test assertions
- `tests/IMPLEMENTATION_NOTES_ACTION_LOGGING.md` - This documentation file

