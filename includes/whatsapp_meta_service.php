<?php
/**
 * Funções utilitárias para integração com a WhatsApp Business Platform (Meta)
 */

/**
 * Normaliza número para o formato internacional aceito pela Meta (com DDI).
 */
function formatNumberForMeta($phone)
{
    $digits = preg_replace('/\D+/', '', $phone);

    if (empty($digits)) {
        return '';
    }

    // Se já possuir DDI (ex: 55) mantém, caso contrário prefixa 55 (Brasil)
    if (strpos($digits, '55') !== 0) {
        $digits = '55' . $digits;
    }

    return $digits;
}

/**
 * Valida se as configurações necessárias da Meta existem.
 */
function validateMetaConfig(array $userConfig)
{
    $required = ['meta_phone_number_id', 'meta_permanent_token', 'meta_api_version'];

    foreach ($required as $field) {
        if (empty($userConfig[$field])) {
            return "Configuração '{$field}' não encontrada. Configure em Minha Instância.";
        }
    }

    return null;
}

/**
 * Executa requisições na Graph API centralizando cabeçalhos e logs.
 */
function sendMetaGraphRequest(array $payload, array $userConfig, ?int $userId, string $context = 'text')
{
    $validationError = validateMetaConfig($userConfig);
    if ($validationError) {
        return [
            'success' => false,
            'error' => $validationError,
        ];
    }

    $endpoint = sprintf(
        'https://graph.facebook.com/%s/%s/messages',
        $userConfig['meta_api_version'] ?? 'v19.0',
        $userConfig['meta_phone_number_id']
    );

    $logPrefix = sprintf('[META_API][%s][user:%s]', strtoupper($context), $userId ?? 'n/a');
    error_log($logPrefix . ' Request: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . 'Bearer ' . $userConfig['meta_permanent_token'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log($logPrefix . ' Connection error: ' . $error);
        return [
            'success' => false,
            'error' => 'Erro de conexão com a API oficial: ' . $error,
            'http_code' => $httpCode,
            'response' => $response,
        ];
    }

    $decoded = json_decode($response, true);
    error_log($logPrefix . ' Response: ' . $response);

    $success = $httpCode >= 200 && $httpCode < 300;
    if (!$success) {
        $errorMessage = $decoded['error']['message'] ?? ('Erro HTTP: ' . $httpCode);
        return [
            'success' => false,
            'error' => $errorMessage,
            'response' => $decoded,
            'http_code' => $httpCode,
        ];
    }

    return [
        'success' => true,
        'response' => $decoded,
        'http_code' => $httpCode,
    ];
}

/**
 * Envia mensagem de texto simples.
 */
function sendMetaTextMessage($phone, $message, array $userConfig, ?int $userId = null)
{
    $metaPhone = formatNumberForMeta($phone);
    if (empty($metaPhone)) {
        return [
            'success' => false,
            'error' => 'Número inválido para envio via API oficial.',
        ];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $metaPhone,
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => $message,
        ],
    ];

    return sendMetaGraphRequest($payload, $userConfig, $userId, 'text');
}

/**
 * Envia mensagem utilizando template aprovado.
 */
function sendMetaTemplateMessage($phone, $templateName, $languageCode, array $components, array $userConfig, ?int $userId = null)
{
    $metaPhone = formatNumberForMeta($phone);
    if (empty($metaPhone)) {
        return [
            'success' => false,
            'error' => 'Número inválido para envio via API oficial.',
        ];
    }

    if (empty($templateName)) {
        return [
            'success' => false,
            'error' => 'Informe o nome do template aprovado pela Meta.',
        ];
    }

    if (empty($components)) {
        return [
            'success' => false,
            'error' => 'Componentes do template não informados.',
        ];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $metaPhone,
        'type' => 'template',
        'template' => [
            'name' => $templateName,
            'language' => [
                'code' => $languageCode ?: 'pt_BR',
            ],
            'components' => $components,
        ],
    ];

    return sendMetaGraphRequest($payload, $userConfig, $userId, 'template');
}
