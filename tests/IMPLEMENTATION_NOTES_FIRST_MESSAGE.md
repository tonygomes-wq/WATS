# Implementation Notes: evaluateFirstMessage()

## Task 2.3 - Implement evaluateFirstMessage() for first message detection

### Overview
This implementation adds the `evaluateFirstMessage()` method to the `TriggerEvaluator` class, which determines if a received message is the first message from a contact in a conversation.

### Requirements Addressed
- **Requirement 2.3**: WHEN trigger_type is 'first_message', THE AutomationEngine SHALL verify if it's the first message from the contact

### Implementation Details

#### Method Signature
```php
private function evaluateFirstMessage(array $config, array $context): bool
```

#### Parameters
- `$config`: Configuration array that may contain:
  - `window_seconds` (optional): Time window in seconds to check for first message
- `$context`: Context array that must contain either:
  - `conversation_id`: ID of the conversation
  - `phone`: Phone number of the contact

#### Logic Flow

1. **Context Validation**
   - Checks if either `conversation_id` or `phone` is provided
   - Returns `false` if neither is available
   - Logs error for debugging

2. **Query Construction**
   - Builds SQL query to count messages from the contact
   - Uses `conversation_id` directly if available
   - Joins with `chat_conversations` table if using `phone`
   - Filters only messages with `sender_type = 'contact'` (excludes system/user messages)

3. **Time Window Support**
   - If `window_seconds` is configured, adds time constraint
   - Uses `DATE_SUB(NOW(), INTERVAL :window_seconds SECOND)` to check within window
   - Allows checking "first message in last X seconds" instead of "first message ever"

4. **Result Evaluation**
   - Counts messages matching the criteria
   - Returns `true` if count equals 1 (current message is the first)
   - Returns `false` if count is 0 or > 1

5. **Error Handling**
   - Catches `PDOException` and logs error
   - Returns `false` on database errors to prevent automation trigger

#### SQL Query Examples

**With conversation_id:**
```sql
SELECT COUNT(*) as message_count 
FROM chat_messages cm
WHERE cm.conversation_id = :conversation_id
AND cm.sender_type = 'contact'
```

**With phone:**
```sql
SELECT COUNT(*) as message_count 
FROM chat_messages cm
INNER JOIN chat_conversations cc ON cm.conversation_id = cc.id
WHERE cc.contact_number = :phone
AND cm.sender_type = 'contact'
```

**With window_seconds:**
```sql
SELECT COUNT(*) as message_count 
FROM chat_messages cm
WHERE cm.conversation_id = :conversation_id
AND cm.sender_type = 'contact'
AND cm.created_at >= DATE_SUB(NOW(), INTERVAL :window_seconds SECOND)
```

### Key Design Decisions

1. **Message Count = 1 Logic**
   - Assumes the current message has already been inserted into the database
   - Count of 1 means this is the first message
   - Count of 0 would indicate the message hasn't been saved yet (edge case)

2. **Sender Type Filter**
   - Only counts messages from `sender_type = 'contact'`
   - Excludes system messages and user responses
   - Ensures we're checking for first message FROM the contact, not TO the contact

3. **Flexible Context**
   - Supports both `conversation_id` and `phone` for flexibility
   - Prioritizes `conversation_id` when both are available (more efficient)
   - Allows webhook to pass either identifier based on what's available

4. **Optional Time Window**
   - `window_seconds` is optional for flexibility
   - Without it: checks if first message ever
   - With it: checks if first message in recent time window
   - Useful for "welcome back" scenarios vs "first contact" scenarios

### Test Coverage

The implementation includes comprehensive unit tests:

1. ✅ First message with conversation_id (count = 1)
2. ✅ Not first message (count > 1)
3. ✅ First message with phone instead of conversation_id
4. ✅ First message with window_seconds configuration
5. ✅ Missing context (no conversation_id or phone)
6. ✅ Database error handling

### Usage Example

```php
// Example 1: Check if first message ever
$config = [];
$context = ['conversation_id' => 123];
$isFirst = $evaluator->evaluate('first_message', $config, $context);

// Example 2: Check if first message in last 10 minutes
$config = ['window_seconds' => 600];
$context = ['conversation_id' => 123];
$isFirst = $evaluator->evaluate('first_message', $config, $context);

// Example 3: Using phone number
$config = [];
$context = ['phone' => '5511999999999'];
$isFirst = $evaluator->evaluate('first_message', $config, $context);
```

### Integration with AutomationEngine

The AutomationEngine will call this method when evaluating automation flows with `trigger_type = 'first_message'`. The typical flow:

1. Webhook receives message
2. Message is saved to `chat_messages` table
3. AutomationEngine checks active flows
4. For flows with `first_message` trigger, calls `TriggerEvaluator::evaluate()`
5. If returns `true`, automation flow is executed

### Performance Considerations

- Query uses indexed columns (`conversation_id`, `contact_number`, `created_at`)
- COUNT(*) is efficient for this use case
- No need for full table scan due to WHERE clause on indexed columns
- Time window query uses DATE_SUB which can leverage index on `created_at`

### Future Enhancements

Potential improvements for future iterations:

1. Cache first message status to avoid repeated queries
2. Support for "first message per channel" (if contact uses multiple channels)
3. Support for "first message per time period" (daily, weekly, etc.)
4. Configurable sender_type filter (e.g., include/exclude system messages)

### Related Files

- `includes/TriggerEvaluator.php` - Main implementation
- `tests/Unit/TriggerEvaluatorTest.php` - Unit tests
- `tests/manual_test_first_message.php` - Manual test script
- `.kiro/specs/automation-flows-completion/requirements.md` - Requirements
- `.kiro/specs/automation-flows-completion/design.md` - Design document
