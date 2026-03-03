# Implementation Notes: assignAttendant Action

## Task 6.3: Implement assignAttendant() action

### Requirements
- **4.3**: Assign attendant action
- **11.2**: End bot sessions when attendant assigned

### Implementation Details

#### Database Schema
The implementation uses the following database columns in `chat_conversations`:
- `attended_by` (INT): ID of the attendant
- `attended_by_name` (VARCHAR): Name of the attendant
- `attended_by_type` (VARCHAR): Type of attendant (e.g., 'user')
- `attended_at` (DATETIME): Timestamp when assigned
- `status` (ENUM): Updated to 'in_progress' when assigned

**Note**: The spec mentions `attendant_id` but the actual database uses `attended_by`. This implementation follows the actual database schema.

#### Method Signature
```php
private function assignAttendant(array $config, array $context): array
```

#### Configuration Parameters
- `attendant_id` (required): ID of the attendant to assign

#### Context Parameters
- `conversation_id` (required): ID of the conversation to assign
- `phone` (optional): Phone number to end bot sessions for

#### Implementation Steps

1. **Validation**
   - Validates that `attendant_id` is provided in config
   - Validates that `conversation_id` is available in context
   - Throws exception if validation fails

2. **Transaction Management**
   - Uses database transaction to ensure atomicity
   - Rolls back on any error to maintain consistency

3. **Attendant Lookup**
   - Queries `users` table to get attendant information
   - Throws exception if attendant not found

4. **Conversation Update**
   - Updates `chat_conversations` with attendant information:
     - Sets `attended_by` to attendant ID
     - Sets `attended_by_name` to attendant name
     - Sets `attended_by_type` to 'user'
     - Sets `attended_at` to current timestamp
     - Updates `status` to 'in_progress'
   - Verifies conversation exists and belongs to the user
   - Throws exception if conversation not found

5. **Bot Session Termination**
   - If `phone` is available in context:
     - Updates all active `bot_sessions` for that phone
     - Sets status to 'completed'
     - Updates timestamp
   - Counts number of bot sessions ended
   - If phone not available, skips this step (returns 0 sessions ended)

6. **Transaction Commit**
   - Commits transaction if all steps succeed
   - Returns success result with details

7. **Error Handling**
   - Catches any exceptions
   - Rolls back transaction if in progress
   - Re-throws exception to be handled by executeActions()

#### Return Value
On success, returns:
```php
[
    'action' => 'assign_attendant',
    'status' => 'success',
    'attendant_id' => <int>,
    'attendant_name' => <string>,
    'conversation_id' => <int>,
    'bot_sessions_ended' => <int>,
    'timestamp' => <int>
]
```

On failure, throws exception with descriptive message.

#### Error Cases Handled

1. **Missing attendant_id**: "Attendant ID not configured"
2. **Missing conversation_id**: "Conversation ID not available in context"
3. **Attendant not found**: "Attendant not found: {id}"
4. **Conversation not found**: "Conversation not found or already assigned"
5. **Database errors**: Rolled back via transaction

#### Integration with Requirements

**Requirement 4.3: Assign attendant action**
- ✓ Updates conversation attendant_id (attended_by)
- ✓ Validates configuration
- ✓ Handles errors gracefully
- ✓ Returns success/failure status with details

**Requirement 11.2: End bot sessions when attendant assigned**
- ✓ Queries active bot sessions by phone
- ✓ Updates status to 'completed'
- ✓ Reports number of sessions ended
- ✓ Gracefully handles missing phone (doesn't fail)

#### Testing

**Unit Tests** (tests/Unit/ActionExecutorTest.php):
- testAssignAttendantWithMissingAttendantIdFails
- testAssignAttendantWithMissingConversationIdFails
- testAssignAttendantSuccessfullyAssignsAndEndsBotSession
- testAssignAttendantWithNonExistentAttendantFails
- testAssignAttendantWithNonExistentConversationFails
- testAssignAttendantWithoutPhoneStillSucceeds
- testAssignAttendantRollsBackOnError

**Manual Test** (tests/manual_test_assign_attendant.php):
- Test 1: Successful assignment with bot session termination
- Test 2: Missing attendant_id validation
- Test 3: Missing conversation_id validation
- Test 4: Non-existent attendant handling

#### Usage Example

```php
$actions = [
    [
        'type' => 'assign_attendant',
        'config' => [
            'attendant_id' => 5
        ]
    ]
];

$context = [
    'conversation_id' => 123,
    'phone' => '5511999999999',
    'message' => 'Customer needs help'
];

$executor = new ActionExecutor($pdo, $userId, $instanceConfig);
$results = $executor->executeActions($actions, $context);
```

#### Notes

1. **Transaction Safety**: The implementation uses transactions to ensure that either both the conversation update and bot session termination succeed, or neither does.

2. **Phone Optional**: The phone number is optional in the context. If not provided, the conversation is still assigned but bot sessions are not terminated. This allows flexibility for different use cases.

3. **User Verification**: The conversation update includes a WHERE clause checking `user_id` to ensure users can only assign attendants to their own conversations.

4. **Status Update**: The conversation status is automatically updated to 'in_progress' when an attendant is assigned, following the pattern in `api/chat_attend.php`.

5. **Compatibility**: The implementation follows the same pattern as the existing `api/chat_attend.php` endpoint for consistency.

#### Future Enhancements

1. Could add support for attendant types other than 'user' (e.g., 'supervisor', 'admin')
2. Could add notification to the assigned attendant
3. Could add validation to check if attendant is available/online
4. Could add support for reassignment (currently allows overwriting)
5. Could add audit logging for attendant assignments

