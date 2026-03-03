# Implementation Notes: createTask Action

## Task 6.5 - Implement createTask() Action

**Status:** ✅ COMPLETED

**Date:** 2024

---

## Overview

The `createTask()` action has been successfully implemented in the `ActionExecutor` class. This action creates a new task (kanban card) in the system's kanban board and links it to the current conversation.

## Implementation Details

### Location
- **File:** `includes/ActionExecutor.php`
- **Method:** `private function createTask(array $config, array $context): array`
- **Lines:** 529-720

### Key Features

1. **Required Fields Validation**
   - Validates that `title` is provided and not empty
   - Throws exception if title is missing

2. **Variable Substitution**
   - Performs variable substitution on `title` and `description` fields
   - Uses `VariableSubstitutor::substitute()` to replace placeholders like `{{contact_name}}`, `{{message}}`, etc.

3. **Context Data Extraction**
   - Extracts `conversation_id`, `contact_name`, `contact_phone` from context
   - Extracts optional fields: `priority`, `due_date`, `value`, `assigned_to`

4. **Priority Validation**
   - Validates priority is one of: `low`, `normal`, `high`, `urgent`
   - Defaults to `normal` if invalid value provided

5. **Board Management**
   - Searches for existing default board for the user
   - Creates default board "Pipeline de Vendas" if none exists
   - Creates 6 default columns when creating new board:
     - Novos Leads
     - Em Contato
     - Proposta Enviada
     - Negociação
     - Fechado/Ganho
     - Perdido

6. **Column Selection**
   - Uses `column_id` from config if provided (with validation)
   - Otherwise uses first column of the board (by position)

7. **Card Creation**
   - Inserts card into `kanban_cards` table
   - Links to conversation via `conversation_id`
   - Sets contact information, priority, due date, value
   - Assigns to attendant if specified
   - Records source channel (defaults to 'automation')
   - Sets position at end of column

8. **Label Support**
   - Optionally adds labels to card if `labels` array provided in config
   - Gracefully handles invalid label IDs (logs error but continues)

9. **Error Handling**
   - Uses try-catch block to handle exceptions
   - Logs errors using `error_log()`
   - Re-throws exception to be caught by `executeActions()` wrapper

10. **Return Value**
    - Returns array with:
      - `action`: 'create_task'
      - `status`: 'success'
      - `card_id`: ID of created card
      - `board_id`: ID of board
      - `column_id`: ID of column
      - `title`: Final title (after variable substitution)
      - `conversation_id`: ID of conversation
      - `timestamp`: Unix timestamp

## Configuration Schema

```json
{
  "type": "create_task",
  "config": {
    "title": "string (required)",
    "description": "string (optional)",
    "priority": "low|normal|high|urgent (optional, default: normal)",
    "due_date": "YYYY-MM-DD (optional)",
    "value": "number (optional)",
    "assigned_to": "integer (optional, user_id)",
    "column_id": "integer (optional)",
    "labels": ["integer array (optional, label_ids)"]
  }
}
```

## Context Variables Used

- `conversation_id`: Links task to conversation
- `contact_name`: Sets contact name on card
- `phone` / `contact_phone`: Sets contact phone on card
- `channel`: Sets source channel (whatsapp, teams, etc.)
- Any variables used in title/description templates

## Database Tables Affected

1. **kanban_boards** (read/write)
   - Searches for existing board
   - Creates default board if needed

2. **kanban_columns** (read/write)
   - Searches for columns
   - Creates default columns if needed

3. **kanban_cards** (write)
   - Inserts new card with all fields

4. **kanban_card_labels** (write, optional)
   - Links labels to card if specified

## Requirements Validated

✅ **Requirement 4.6:** Create task in kanban system
- Task is inserted into `kanban_cards` table
- All required and optional fields are supported
- Variable substitution works correctly

✅ **Requirement 4.9:** Variable substitution in actions
- Title and description support variable placeholders
- Variables are replaced with context values

✅ **Requirement 4.10:** Error isolation
- Errors are caught and logged
- Exception is re-thrown for proper handling by wrapper
- Other actions continue even if this fails

✅ **Requirement 4.11:** Action result logging
- Returns detailed result array
- Includes all relevant IDs and data
- Status indicates success/failure

## Testing

### Unit Tests Added
Location: `tests/Unit/ActionExecutorTest.php`

1. **testCreateTaskWithMissingTitleFails**
   - Validates that missing title throws error

2. **testCreateTaskWithEmptyTitleFails**
   - Validates that empty title throws error

3. **testCreateTaskPerformsVariableSubstitution**
   - Validates variable substitution in title and description

4. **testCreateTaskSuccessfullyCreatesTaskWithMinimalConfig**
   - Validates basic task creation with only title

5. **testCreateTaskWithAllOptionalFields**
   - Validates all optional fields are saved correctly

6. **testCreateTaskCreatesDefaultBoardIfNoneExists**
   - Validates automatic board creation
   - Validates 6 default columns are created

7. **testCreateTaskValidatesPriorityValues**
   - Validates invalid priority defaults to 'normal'

8. **testCreateTaskLinksToConversation**
   - Validates conversation_id is saved correctly

### Manual Test Script
Location: `tests/manual_test_create_task.php`

Comprehensive manual test script with 6 test scenarios:
1. Minimal config (title only)
2. Variable substitution
3. All optional fields
4. Missing title (should fail)
5. Invalid priority (should default)
6. Conversation linking

Run with: `php tests/manual_test_create_task.php`

## Integration Points

### Called By
- `ActionExecutor::executeAction()` when action type is 'create_task'
- Invoked as part of automation flow action execution

### Dependencies
- `VariableSubstitutor::substitute()` for variable replacement
- PDO database connection
- `kanban_boards`, `kanban_columns`, `kanban_cards`, `kanban_card_labels` tables

### Error Propagation
- Exceptions are caught, logged, and re-thrown
- Wrapper in `executeActions()` catches and records in result array
- Other actions continue execution (error isolation)

## Example Usage

### Basic Task Creation
```php
$action = [
    'type' => 'create_task',
    'config' => [
        'title' => 'Follow up with customer'
    ]
];

$context = [
    'conversation_id' => 123,
    'phone' => '5511999999999',
    'contact_name' => 'John Doe'
];

$result = $executor->executeActions([$action], $context);
```

### Task with Variables
```php
$action = [
    'type' => 'create_task',
    'config' => [
        'title' => 'Follow up with {{contact_name}}',
        'description' => 'Message: {{message}}\nPhone: {{contact_phone}}',
        'priority' => 'high',
        'due_date' => '2024-12-31',
        'value' => 1500.00
    ]
];
```

## Known Limitations

1. **Board Creation**
   - Only creates default board if none exists
   - Cannot specify custom board name/columns in action config
   - Always uses first column if column_id not specified

2. **Label Validation**
   - Invalid label IDs are silently ignored
   - No validation that labels belong to the board

3. **Assigned User Validation**
   - No validation that assigned_to user exists
   - No validation that user has access to the board

## Future Enhancements

1. Support for custom board selection in config
2. Support for custom column selection by name
3. Validation of assigned_to user existence
4. Support for adding attachments to card
5. Support for adding comments to card
6. Support for setting custom fields

## Conclusion

The `createTask()` action is fully implemented and tested. It successfully:
- Creates tasks in the kanban system
- Links tasks to conversations
- Supports variable substitution
- Handles all optional fields
- Follows error handling patterns
- Integrates seamlessly with ActionExecutor

The implementation meets all requirements specified in Requirements 4.6 and Design document specifications.
