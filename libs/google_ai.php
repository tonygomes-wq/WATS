<?php
/**
 * Helper para integração com o Google AI Studio (Gemini 2.5 Flash)
 */

class GoogleAI {
    private $apiKey;
    private $model;
    private $endpoint;
    
    public function __construct() {
        $this->apiKey = getenv('GOOGLE_AI_API_KEY') ?: ($_ENV['GOOGLE_AI_API_KEY'] ?? 'AIzaSyDNOcXvO-4E8vfmntfTEiPE7siWKUDUkKo');
        $this->model = getenv('GOOGLE_AI_MODEL') ?: ($_ENV['GOOGLE_AI_MODEL'] ?? 'gemini-2.0-flash-exp');
        $this->endpoint = getenv('GOOGLE_AI_ENDPOINT') ?: 'https://generativelanguage.googleapis.com/v1beta/models';
    }
    
    public function generateContent(string $prompt, array $options = []): string {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['maxOutputTokens'] ?? 2048,
            ]
        ];
        
        $url = rtrim($this->endpoint, '/') . '/' . rawurlencode($this->model) . ':generateContent?key=' . urlencode($this->apiKey);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('Erro ao comunicar com Google AI: ' . $curlError);
        }
        
        $data = json_decode($response, true);
        if ($statusCode >= 400) {
            $message = $data['error']['message'] ?? 'Erro desconhecido';
            throw new Exception('Google AI retornou erro (' . $statusCode . '): ' . $message);
        }
        
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
}

if (!function_exists('googleAiRequest')) {
    function googleAiRequest(array $params): array
    {
        $apiKey = getenv('GOOGLE_AI_API_KEY') ?: ($_ENV['GOOGLE_AI_API_KEY'] ?? null);
        $model = getenv('GOOGLE_AI_MODEL') ?: ($_ENV['GOOGLE_AI_MODEL'] ?? 'gemini-2.5-flash');
        $endpoint = getenv('GOOGLE_AI_ENDPOINT') ?: 'https://generativelanguage.googleapis.com/v1beta/models';

        if (!$apiKey) {
            throw new Exception('GOOGLE_AI_API_KEY não configurada');
        }

        $payload = [
            'contents' => $params['contents'] ?? [],
            'generationConfig' => [
                'temperature' => $params['temperature'] ?? 0.2,
                'maxOutputTokens' => $params['maxOutputTokens'] ?? 512,
            ],
        ];

        if (!empty($params['safetySettings'])) {
            $payload['safetySettings'] = $params['safetySettings'];
        }

        $url = rtrim($endpoint, '/') . '/' . rawurlencode($model) . ':generateContent?key=' . urlencode($apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Erro ao comunicar com Google AI: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($statusCode >= 400) {
            $message = $data['error']['message'] ?? 'Erro desconhecido';
            throw new Exception('Google AI retornou erro (' . $statusCode . '): ' . $message);
        }

        return $data;
    }
}

if (!function_exists('generateFlowResponse')) {
    function generateFlowResponse(array $flow, array $conversationContext, array $incomingMessage): array
    {
        $promptBase = $flow['agent_config']['prompt'] ?? 'Responda de forma cordial e objetiva.';
        $temperature = $flow['agent_config']['temperature'] ?? 0.2;
        $history = $conversationContext['history'] ?? [];

        $contents = [];
        $contents[] = [
            'role' => 'system',
            'parts' => [
                ['text' => $promptBase],
            ],
        ];

        foreach ($history as $message) {
            $contents[] = [
                'role' => $message['role'],
                'parts' => [
                    ['text' => $message['text']],
                ],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $incomingMessage['text'] ?? ''],
            ],
        ];

        $response = googleAiRequest([
            'contents' => $contents,
            'temperature' => $temperature,
            'maxOutputTokens' => $flow['agent_config']['max_tokens'] ?? 512,
        ]);

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'raw' => $response,
            'text' => trim($text),
        ];
    }
}
