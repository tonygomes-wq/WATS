# Implementation Notes - AI Error Handling

## Task 5.4: Implement error handling for AI failures

### Implementation Summary

Task 5.4 has been **successfully completed**. The error handling for AI failures was already comprehensively implemented in previous tasks (5.2 and 5.3), and this task verified that all requirements are met.

### Requirements Validated

**Requirements: 3.7, 10.6**

#### Requirement 3.7
> IF a chamada à IA falha, THEN THE AutomationEngine SHALL registrar o erro e continuar com as ações configuradas

#### Requirement 10.6
> WHEN OpenAI_API ou Gemini_API retorna erro, THE AutomationEngine SHALL registrar o erro e continuar com as ações

### Verification Checklist

All 5 verification points from the task context have been validated:

#### ✅ 1. Both callOpenAI() and callGemini() return null on errors

**Implementation:**
- Both methods have comprehensive error handling with try-catch blocks
- All error paths return `null` instead of throwing exceptions
- Errors include: missing API key, cURL errors, HTTP errors, invalid response format

**Code locations:**
- `callOpenAI()`: Lines 175-280 in AIProcessor.php
- `callGemini()`: Lines 295-400 in AIProcessor.php

**Error scenarios handled:**
- Missing user_id
- Missing API key in database
- Database query errors
- cURL connection errors
- HTTP 4xx errors (client errors)
- HTTP 5xx errors (server errors)
- Invalid JSON response format
- Timeout errors

#### ✅ 2. All errors are logged appropriately

**Implementation:**
- Every error path includes `error_log()` calls
- Error messages include context (attempt number, HTTP code, error details)
- Logs are descriptive for debugging

**Examples:**
```php
error_log("AIProcessor: user_id not set, cannot call OpenAI");
error_log("AIProcessor: OpenAI API key not configured for user {$this->userId}");
error_log("AIProcessor: Error fetching OpenAI API key: " . $e->getMessage());
error_log("AIProcessor: OpenAI returned {$httpCode}, retrying in {$delay}s (attempt " . ($attempt + 1) . "/{$maxRetries})");
error_log("AIProcessor: OpenAI API error: {$errorMsg}");
error_log("AIProcessor: Error calling OpenAI (attempt " . ($attempt + 1) . "/{$maxRetries}): " . $e->getMessage());
```

#### ✅ 3. The process() method handles null responses gracefully

**Implementation:**
- Lines 67-73 in AIProcessor.php
- Checks if response is null after calling AI provider
- Sets appropriate error message when null
- Never throws exceptions to calling code
- Always returns structured result array

**Code:**
```php
if ($response !== null) {
    $result['success'] = true;
    $result['response'] = $response;
} else {
    $result['error'] = "AI provider returned null response";
}
```

#### ✅ 4. Actions can continue even if AI fails

**Implementation:**
- The `process()` method catches all exceptions and returns gracefully
- Never throws exceptions to calling code
- Returns structured result with error information
- Calling code (AutomationEngine) can check `success` flag and continue with actions

**Code:**
```php
try {
    // ... AI processing ...
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
    error_log("AIProcessor: Error processing AI: " . $e->getMessage());
}

$result['execution_time_ms'] = round((microtime(true) - $startTime) * 1000);

return $result; // Always returns, never throws
```

#### ✅ 5. Error messages are descriptive for debugging

**Implementation:**
- All error messages include context
- HTTP codes are included in error messages
- Attempt numbers are included in retry messages
- Provider name is included in error messages
- Original exception messages are preserved

**Examples:**
- "AIProcessor: user_id not set, cannot call OpenAI"
- "AIProcessor: OpenAI API key not configured for user 123"
- "AIProcessor: OpenAI returned 503, retrying in 2s (attempt 2/3)"
- "AIProcessor: OpenAI API error: Rate limit exceeded"
- "AI provider returned null response"

### Result Structure

The `process()` method always returns a consistent structure:

```php
[
    'success' => bool,           // true if AI call succeeded
    'response' => ?string,       // AI response or null
    'prompt' => ?string,         // Prepared prompt or null
    'provider' => ?string,       // 'openai' or 'gemini' or null
    'error' => ?string,          // Error message or null
    'execution_time_ms' => int   // Execution time in milliseconds
]
```

### Error Handling Flow

```
process() called
    ↓
Check if AI enabled
    ↓ (if disabled)
    Return result with success=false
    ↓ (if enabled)
Select provider (default: openai)
    ↓
Prepare context and prompt
    ↓
Call AI provider (callOpenAI or callGemini)
    ↓
    ├─ Success → Return response text
    ├─ Error → Log error, return null
    └─ Retry (3 attempts with backoff)
    ↓
Check response
    ├─ Not null → Set success=true, response=text
    └─ Null → Set error="AI provider returned null response"
    ↓
Catch any exceptions
    └─ Set error=exception message, log error
    ↓
Calculate execution time
    ↓
Return result (never throws)
```

### Retry Logic

Both `callOpenAI()` and `callGemini()` implement retry logic:

- **Maximum retries:** 3 attempts
- **Backoff strategy:** Exponential (1s, 2s, 4s)
- **Retryable errors:** HTTP 5xx (server errors)
- **Non-retryable errors:** HTTP 4xx (client errors)
- **Total max time:** ~7 seconds (3 attempts + 3s backoff)

### Testing

A comprehensive test file has been created: `tests/manual_test_ai_error_handling.php`

The test validates all 5 requirements:

1. **Test 1-2:** Missing API key for both providers → Returns null
2. **Test 3:** Process continues without exceptions
3. **Test 4:** Error messages are descriptive
4. **Test 5:** Invalid provider defaults gracefully
5. **Test 6:** Missing prompt returns error gracefully
6. **Test 7:** Result structure is always consistent
7. **Test 8:** Execution time is always recorded

### Integration with AutomationEngine

The error handling ensures that AutomationEngine can safely use AIProcessor:

```php
// In AutomationEngine
$aiProcessor = new AIProcessor($this->pdo, [], $this->userId);
$result = $aiProcessor->process($agentConfig, $context);

// AI failure doesn't stop execution
if ($result['success']) {
    $context['ai_response'] = $result['response'];
    // Log successful AI response
} else {
    // Log AI failure but continue with actions
    error_log("AI processing failed: " . $result['error']);
    $context['ai_response'] = ''; // Empty response
}

// Continue executing actions regardless of AI success/failure
$actionExecutor->executeActions($actions, $context);
```

### Conclusion

Task 5.4 is **complete**. All error handling requirements are satisfied:

- ✅ Both AI providers return null on errors
- ✅ All errors are logged with descriptive messages
- ✅ The process() method handles null responses gracefully
- ✅ Actions can continue even if AI fails (no exceptions thrown)
- ✅ Error messages include context for debugging

The implementation follows best practices:
- Consistent error handling across both providers
- Graceful degradation (system continues without AI)
- Comprehensive logging for debugging
- Retry logic for transient failures
- Clear separation of retryable vs non-retryable errors

### Next Steps

- Task 5.5: Write property test for AI error handling (optional)
- Task 5.6: Write unit tests for AI timeout handling (optional)
- Continue to Task 6: Implement ActionExecutor component
