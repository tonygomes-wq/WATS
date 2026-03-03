# Implementation Notes - callGemini() Method

## Task 5.3: Implement callGemini() method

### Implementation Summary

The `callGemini()` method has been successfully implemented in `includes/AIProcessor.php` with all required features:

#### Features Implemented

1. **Google Gemini API Integration**
   - Uses the `/v1/models/{model}:generateContent` endpoint
   - Sends content in the correct format for Gemini models
   - API key passed as URL parameter (Gemini's authentication method)

2. **Configuration Support**
   - `model`: Defaults to 'gemini-pro' if not specified
   - `temperature`: Defaults to 0.7 if not specified
   - `max_tokens`: Defaults to 500 if not specified (mapped to `maxOutputTokens`)
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
private function callGemini(string $prompt, array $config): ?string
{
    // 1. Retrieve API key from database
    // 2. Extract configuration with defaults
    // 3. Prepare request payload (Gemini format)
    // 4. Retry loop (3 attempts)
    //    a. Make cURL request
    //    b. Check for cURL errors
    //    c. Check HTTP status code
    //    d. Parse response (Gemini format)
    //    e. Retry on 5xx errors with backoff
    //    f. Return null on 4xx errors
    // 5. Return response text or null
}
```

### Gemini API Differences from OpenAI

1. **Authentication**: API key in URL parameter instead of Authorization header
2. **Request Format**: Uses `contents` array with `parts` instead of `messages`
3. **Response Format**: Response in `candidates[0].content.parts[0].text` instead of `choices[0].message.content`
4. **Generation Config**: Uses `generationConfig` object with `maxOutputTokens` instead of `max_tokens`

### Requirements Satisfied

- **Requirement 3.3**: Gemini API integration with generation config
- **Requirement 3.8**: Timeout handling with configurable timeout

### Request/Response Format

**Request Payload:**
```json
{
  "contents": [
    {
      "parts": [
        {"text": "Your prompt here"}
      ]
    }
  ],
  "generationConfig": {
    "temperature": 0.7,
    "maxOutputTokens": 500
  }
}
```

**Response Format:**
```json
{
  "candidates": [
    {
      "content": {
        "parts": [
          {"text": "AI generated response"}
        ]
      }
    }
  ]
}
```

### Usage Example

```php
// Create AIProcessor with user_id
$processor = new AIProcessor($pdo, [], $userId);

// Configure agent with Gemini
$agentConfig = [
    'enabled' => true,
    'provider' => 'gemini',
    'prompt' => 'Hello {{contact_name}}, how can I help you with: {{message}}',
    'model' => 'gemini-pro',
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

To configure Gemini API key for a user:

```sql
INSERT INTO system_config (user_id, config_key, config_value)
VALUES (1, 'gemini_api_key', 'AIza...')
ON DUPLICATE KEY UPDATE config_value = 'AIza...';
```

### Error Handling Behavior

The method follows the same pattern as `callOpenAI()`:

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

### API Endpoint

```
https://generativelanguage.googleapis.com/v1/models/{model}:generateContent?key={apiKey}
```

Where:
- `{model}` is the model name (e.g., 'gemini-pro')
- `{apiKey}` is the API key from system_config

### Supported Models

Common Gemini models:
- `gemini-pro`: Text generation
- `gemini-pro-vision`: Multimodal (text + images)
- `gemini-1.5-pro`: Latest version with extended context

### Changes Made

1. **Implemented `callGemini()` method**:
   - Complete implementation replacing the stub
   - ~140 lines of code with comprehensive error handling
   - Follows same pattern as `callOpenAI()` for consistency

2. **Created documentation**:
   - This file documenting the implementation

### Next Steps

- Task 5.4: Ensure error handling is consistent across both providers
- Create manual test file for Gemini (similar to OpenAI test)
- Integration testing with AutomationEngine

### Testing Notes

To test the Gemini implementation:
1. Obtain a Gemini API key from Google AI Studio
2. Configure the key in system_config table
3. Create a manual test file similar to `manual_test_openai_call.php`
4. Test with various configurations and error scenarios

**Note**: Gemini API has a free tier with generous limits, making it suitable for testing.
