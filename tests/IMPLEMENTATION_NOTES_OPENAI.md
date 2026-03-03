# Implementation Notes - callOpenAI() Method

## Task 5.2: Implement callOpenAI() method

### Implementation Summary

The `callOpenAI()` method has been successfully implemented in `includes/AIProcessor.php` with all required features:

#### Features Implemented

1. **OpenAI Chat Completions API Integration**
   - Uses the `/v1/chat/completions` endpoint
   - Sends messages in the correct format for chat models
   - Properly formats the Authorization header with Bearer token

2. **Configuration Support**
   - `model`: Defaults to 'gpt-4' if not specified
   - `temperature`: Defaults to 0.7 if not specified
   - `max_tokens`: Defaults to 500 if not specified
   - All configuration values are properly type-cast

3. **Timeout Handling**
   - Configurable timeout via `config['timeout']`
   - Defaults to 30 seconds if not specified
   - Uses cURL's `CURLOPT_TIMEOUT` option

4. **Retry Logic with Exponential Backoff**
   - Maximum 3 retry attempts
   - Base delay of 1 second
   - Exponential backoff: 1s, 2s, 4s
   - Only retries on 5xx server errors (retryable errors)
   - Does not retry on 4xx client errors (non-retryable)

5. **Error Handling**
   - Gracefully handles missing API key
   - Handles cURL errors
   - Handles HTTP error codes (4xx, 5xx)
   - Handles invalid response format
   - Logs all errors with descriptive messages
   - Returns `null` on any error (allows actions to continue)

6. **API Key Management**
   - Retrieves API key from `system_config` table
   - Requires `user_id` to be set in AIProcessor constructor
   - Validates API key exists before making request

### Code Structure

```php
private function callOpenAI(string $prompt, array $config): ?string
{
    // 1. Retrieve API key from database
    // 2. Extract configuration with defaults
    // 3. Prepare request payload
    // 4. Retry loop (3 attempts)
    //    a. Make cURL request
    //    b. Check for cURL errors
    //    c. Check HTTP status code
    //    d. Parse response
    //    e. Retry on 5xx errors with backoff
    //    f. Return null on 4xx errors
    // 5. Return response text or null
}
```

### Requirements Satisfied

- **Requirement 3.2**: OpenAI API integration with Chat Completions
- **Requirement 3.8**: Timeout handling with configurable timeout

### Testing

A comprehensive manual test file has been created: `tests/manual_test_openai_call.php`

The test file includes:
1. Missing API key handling
2. Basic OpenAI call with default configuration
3. Custom model configuration (gpt-4)
4. Variable substitution in prompts
5. Short timeout handling
6. Invalid API key handling

**Note**: The manual test makes real API calls to OpenAI and will consume tokens (estimated cost: ~$0.01 USD).

### Usage Example

```php
// Create AIProcessor with user_id
$processor = new AIProcessor($pdo, [], $userId);

// Configure agent
$agentConfig = [
    'enabled' => true,
    'provider' => 'openai',
    'prompt' => 'Hello {{contact_name}}, how can I help you with: {{message}}',
    'model' => 'gpt-4',
    'temperature' => 0.7,
    'max_tokens' => 500,
    'timeout' => 30
];

// Provide context
$context = [
    'contact_name' => 'John Doe',
    'message' => 'I need help with my order'
];

// Process with AI
$result = $processor->process($agentConfig, $context);

if ($result['success']) {
    echo "AI Response: " . $result['response'];
} else {
    echo "Error: " . $result['error'];
}
```

### Configuration in Database

To configure OpenAI API key for a user:

```sql
INSERT INTO system_config (user_id, config_key, config_value)
VALUES (1, 'openai_api_key', 'sk-...')
ON DUPLICATE KEY UPDATE config_value = 'sk-...';
```

### Error Handling Behavior

The method follows the requirement that AI failures should not stop action execution:

- Returns `null` on any error
- Logs detailed error messages for debugging
- The `process()` method continues with actions even if AI fails
- Error is recorded in the result array for logging

### Retry Logic Details

| Attempt | Delay Before | Total Time |
|---------|--------------|------------|
| 1       | 0s           | 0s         |
| 2       | 1s           | 1s         |
| 3       | 2s           | 3s         |

Only 5xx errors trigger retries. 4xx errors (bad request, auth failure, etc.) return immediately.

### Next Steps

- Task 5.3: Implement `callGemini()` method with similar structure
- Task 5.4: Ensure error handling is consistent across both providers
- Integration testing with AutomationEngine

### Changes Made

1. **Modified `AIProcessor` constructor**:
   - Added optional `$userId` parameter
   - Stored `userId` as private property

2. **Implemented `callOpenAI()` method**:
   - Complete implementation replacing the stub
   - ~140 lines of code with comprehensive error handling

3. **Created test file**:
   - `tests/manual_test_openai_call.php` for manual testing

4. **Created documentation**:
   - This file documenting the implementation

