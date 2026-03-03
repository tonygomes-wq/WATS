# Implementation Notes: callWebhook() Action

## Task 6.6 - Implement callWebhook() Action

**Status:** ✅ COMPLETE

**Requirements:** 4.7, 10.7

## Implementation Summary

The `callWebhook()` action has been fully implemented in `includes/ActionExecutor.php` (lines 718-862). The implementation meets all requirements specified in the task.

## Features Implemented

### 1. HTTP Request to External Webhook ✅
- Supports multiple HTTP methods: GET, POST, PUT, PATCH
- Default method: POST
- Validates HTTP method (rejects unsupported methods like DELETE)
- Uses cURL for reliable HTTP requests

### 2. Conversation Data in Payload ✅
The webhook payload includes comprehensive conversation data:

```json
{
  "conversation_id": 123,
  "contact": {
    "name": "João Silva",
    "phone": "5511999999999",
    "email": "joao@example.com"
  },
  "message": {
    "text": "Olá, preciso de ajuda",
    "timestamp": 1234567890,
    "message_id": "MSG123"
  },
  "channel": "whatsapp",
  "ai_response": "Resposta da IA",
  "metadata": {
    "user_id": 1,
    "triggered_at": "2024-01-15T10:30:00+00:00"
  }
}
```

### 3. Timeout Handling ✅
- Default timeout: 10 seconds
- Configurable timeout: 1-60 seconds
- Invalid timeouts are forced to default (10s)
- Connection timeout: min(timeout, 5) seconds
- Proper timeout error detection and reporting

### 4. Error Handling ✅
- URL validation (format check)
- HTTP method validation
- Connection error handling
- Timeout error handling
- HTTP error status handling (non-2xx responses)
- Error messages extracted from response body when available
- All errors throw exceptions that are caught by executeActions()

### 5. Error Isolation Pattern ✅
- Errors in webhook calls don't stop execution of other actions
- `executeActions()` catches exceptions and continues with next action
- Each action result includes status (success/failed) and error message
- Failed webhooks are logged but don't break the automation flow

## Additional Features

### Variable Substitution
- Webhook URLs support variable substitution
- Example: `https://api.example.com/webhook?contact={{contact_name}}`
- Variables are replaced before making the request

### Security Features
- SSL certificate verification enabled
- Host verification enabled
- Follows redirects (max 3)
- User-Agent header: "WATS-AutomationEngine/1.0"

### Response Handling
- Captures HTTP status code
- Records execution time in milliseconds
- Stores response body (limited to 500 characters)
- Extracts error messages from JSON responses

### Method-Specific Behavior
- **GET**: Payload sent as query string parameters
- **POST/PUT/PATCH**: Payload sent as JSON in request body with Content-Type header

## Configuration Example

```json
{
  "type": "webhook",
  "config": {
    "url": "https://example.com/webhook",
    "method": "POST",
    "timeout": 10
  }
}
```

## Testing

A comprehensive manual test suite has been created in `tests/manual_test_webhook.php` that covers:

1. ✅ Valid webhook with POST method
2. ✅ Webhook with variable substitution
3. ✅ Webhook with GET method
4. ✅ Webhook with custom timeout
5. ✅ Invalid webhook URL (error handling)
6. ✅ Missing webhook URL (error handling)
7. ✅ Error isolation (webhook fails but execution continues)
8. ✅ Payload structure verification
9. ✅ Default timeout (10 seconds)
10. ✅ Invalid HTTP method

### Running the Tests

```bash
php tests/manual_test_webhook.php
```

**Note:** To test with real webhooks:
1. Create a webhook receiver at https://webhook.site
2. Replace 'https://webhook.site/unique-id' in the test with your unique URL
3. Run the test and check the webhook receiver to verify payload structure

## Code Quality

### Validation
- ✅ URL format validation
- ✅ HTTP method validation
- ✅ Timeout range validation (1-60 seconds)
- ✅ Configuration structure validation

### Error Messages
- Clear, descriptive error messages
- Includes HTTP status codes
- Extracts error details from response body
- Logs errors for debugging

### Performance
- Tracks execution time in milliseconds
- Configurable timeouts prevent hanging
- Connection timeout separate from request timeout

## Integration with ActionExecutor

The webhook action is fully integrated into the ActionExecutor:

```php
case 'webhook':
    return $this->callWebhook($config, $context);
```

Error isolation is handled by `executeActions()`:

```php
try {
    $result = $this->executeAction($action, $context);
    $actionResult['status'] = 'success';
    $actionResult['data'] = $result;
} catch (Exception $e) {
    // Registra erro mas continua com próximas ações
    $actionResult['error'] = $e->getMessage();
    error_log("ActionExecutor: Error executing action {$action['type']}: " . $e->getMessage());
}
```

## Requirements Validation

### Requirement 4.7 ✅
> WHEN action_type é 'webhook', THE AutomationEngine SHALL chamar o webhook externo configurado com os dados da conversa

**Validated:** The implementation calls external webhooks with comprehensive conversation data including contact info, message details, channel, and AI response.

### Requirement 10.7 ✅
> WHEN webhook externo não responde dentro do timeout, THE AutomationEngine SHALL registrar timeout e continuar

**Validated:** The implementation detects timeout errors (CURLE_OPERATION_TIMEDOUT), throws an exception with a clear message, and the error isolation pattern ensures execution continues with the next action.

## Conclusion

Task 6.6 is **COMPLETE**. The `callWebhook()` action is fully implemented with:
- ✅ HTTP requests to external webhooks
- ✅ Comprehensive conversation data in payload
- ✅ Configurable timeout handling (default 10 seconds)
- ✅ Robust error handling
- ✅ Error isolation pattern
- ✅ Variable substitution support
- ✅ Security features (SSL verification)
- ✅ Comprehensive test suite

The implementation follows the same pattern as other actions (sendMessage, assignAttendant, addTag, removeTag, createTask) and integrates seamlessly with the ActionExecutor component.
