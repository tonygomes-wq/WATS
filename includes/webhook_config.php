<?php
/**
 * Funções para Configuração Automática de Webhook
 */

/**
 * Configurar webhook automaticamente para uma instância
 * 
 * @param string $instance Nome da instância
 * @param string $token Token da Evolution API
 * @param string $evolutionUrl URL da Evolution API
 * @return array Resultado da configuração
 */
function configureWebhookForInstance($instance, $token, $evolutionUrl) {
    try {
        // Configuração correta do webhook
        $webhookConfig = [
            'webhook' => [  // ← IMPORTANTE: Evolution API espera dentro de "webhook"
                'enabled' => true,
                'url' => 'https://wats.macip.com.br/api/webhook_simple.php',
                'webhookByEvents' => true,  // ← IMPORTANTE: eventos separados
                'webhookBase64' => false,
                'events' => [
                    'MESSAGES_UPSERT',      // Mensagens recebidas
                    'MESSAGES_UPDATE',      // Atualização de status
                    'SEND_MESSAGE',         // Mensagens enviadas
                    'CONNECTION_UPDATE',    // Status da conexão
                    'QRCODE_UPDATED'        // QR Code atualizado
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $evolutionUrl . '/webhook/set/' . $instance);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookConfig));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $token
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("WEBHOOK: Configurado com sucesso para instância $instance");
            return [
                'success' => true,
                'message' => 'Webhook configurado com sucesso',
                'http_code' => $httpCode,
                'response' => json_decode($response, true)
            ];
        } else {
            error_log("WEBHOOK: Erro ao configurar para instância $instance - HTTP $httpCode - $error");
            return [
                'success' => false,
                'message' => 'Erro ao configurar webhook',
                'http_code' => $httpCode,
                'error' => $error,
                'response' => $response
            ];
        }
        
    } catch (Exception $e) {
        error_log("WEBHOOK: Exceção ao configurar - " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Exceção: ' . $e->getMessage(),
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Verificar se webhook está configurado corretamente
 * 
 * @param string $instance Nome da instância
 * @param string $token Token da Evolution API
 * @param string $evolutionUrl URL da Evolution API
 * @return array Status da configuração
 */
function checkWebhookConfiguration($instance, $token, $evolutionUrl) {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $evolutionUrl . '/webhook/find/' . $instance);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $token
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $config = json_decode($response, true);
            
            // Verificar se está configurado corretamente
            $isCorrect = (
                isset($config['enabled']) && $config['enabled'] === true &&
                isset($config['webhookByEvents']) && $config['webhookByEvents'] === true &&
                isset($config['url']) && !empty($config['url'])
            );
            
            return [
                'success' => true,
                'configured' => $isCorrect,
                'config' => $config,
                'webhookByEvents' => $config['webhookByEvents'] ?? false
            ];
        } else {
            return [
                'success' => false,
                'configured' => false,
                'error' => 'Erro ao verificar webhook'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'configured' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Configurar webhook para todas as instâncias de um usuário
 * 
 * @param PDO $pdo Conexão com banco de dados
 * @param int $userId ID do usuário
 * @return array Resultado da configuração
 */
function configureWebhookForUser($pdo, $userId) {
    try {
        // Buscar dados do usuário
        $stmt = $pdo->prepare("SELECT evolution_instance, evolution_token FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || empty($user['evolution_instance'])) {
            return [
                'success' => false,
                'message' => 'Usuário sem instância configurada'
            ];
        }
        
        $instance = $user['evolution_instance'];
        $token = $user['evolution_token'] ?: EVOLUTION_API_KEY;
        $evolutionUrl = EVOLUTION_API_URL;
        
        // Configurar webhook
        return configureWebhookForInstance($instance, $token, $evolutionUrl);
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erro: ' . $e->getMessage()
        ];
    }
}

/**
 * Configurar webhook para todas as instâncias do sistema (admin)
 * 
 * @param PDO $pdo Conexão com banco de dados
 * @return array Resultado da configuração em massa
 */
function configureWebhookForAllInstances($pdo) {
    try {
        // Buscar todas as instâncias
        $stmt = $pdo->query("
            SELECT id, username, evolution_instance, evolution_token 
            FROM users 
            WHERE evolution_instance IS NOT NULL 
            AND evolution_instance != ''
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        $success = 0;
        $failed = 0;
        
        foreach ($users as $user) {
            $instance = $user['evolution_instance'];
            $token = $user['evolution_token'] ?: EVOLUTION_API_KEY;
            $evolutionUrl = EVOLUTION_API_URL;
            
            $result = configureWebhookForInstance($instance, $token, $evolutionUrl);
            
            $results[] = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'instance' => $instance,
                'result' => $result
            ];
            
            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        }
        
        return [
            'success' => true,
            'total' => count($users),
            'success_count' => $success,
            'failed_count' => $failed,
            'results' => $results
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erro: ' . $e->getMessage()
        ];
    }
}
?>
