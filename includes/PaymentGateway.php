<?php
/**
 * Interface base para gateways de pagamento
 * Implementa padrão Strategy para múltiplos gateways
 * 
 * MACIP Tecnologia LTDA
 */

interface PaymentGatewayInterface {
    public function createSubscription($user, $plan, $paymentMethod);
    public function cancelSubscription($subscriptionId);
    public function createPayment($user, $amount, $description, $paymentMethod);
    public function getPaymentStatus($paymentId);
    public function processWebhook($payload);
}

/**
 * Factory para criar instâncias de gateways
 */
class PaymentGatewayFactory {
    public static function create($gateway) {
        switch ($gateway) {
            case 'mercadopago':
                return new MercadoPagoGateway();
            case 'stripe':
                return new StripeGateway();
            case 'pagseguro':
                return new PagSeguroGateway();
            default:
                throw new Exception("Gateway não suportado: $gateway");
        }
    }
    
    public static function getActiveGateways($pdo) {
        $stmt = $pdo->query("
            SELECT gateway, is_active, public_key, secret_key 
            FROM payment_settings 
            WHERE is_active = 1
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Classe base abstrata para gateways
 */
abstract class BasePaymentGateway implements PaymentGatewayInterface {
    protected $pdo;
    protected $publicKey;
    protected $secretKey;
    protected $webhookSecret;
    protected $settings;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->loadSettings();
    }
    
    protected function loadSettings() {
        $stmt = $this->pdo->prepare("
            SELECT public_key, secret_key, webhook_secret, settings 
            FROM payment_settings 
            WHERE gateway = ? AND is_active = 1
        ");
        $stmt->execute([$this->getGatewayName()]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            $this->publicKey = $config['public_key'];
            $this->secretKey = $config['secret_key'];
            $this->webhookSecret = $config['webhook_secret'];
            $this->settings = json_decode($config['settings'], true);
        }
    }
    
    abstract protected function getGatewayName();
    
    protected function logPayment($userId, $planId, $amount, $status, $paymentMethod, $gatewayPaymentId = null, $gatewayResponse = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO payments (user_id, plan_id, amount, status, payment_method, gateway, gateway_payment_id, gateway_response)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $planId,
            $amount,
            $status,
            $paymentMethod,
            $this->getGatewayName(),
            $gatewayPaymentId,
            json_encode($gatewayResponse)
        ]);
        return $this->pdo->lastInsertId();
    }
    
    protected function updatePaymentStatus($paymentId, $status, $gatewayResponse = null) {
        $stmt = $this->pdo->prepare("
            UPDATE payments 
            SET status = ?, gateway_response = ?, paid_at = IF(? = 'paid', NOW(), paid_at), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $status,
            json_encode($gatewayResponse),
            $status,
            $paymentId
        ]);
    }
    
    protected function logWebhook($eventType, $eventId, $payload) {
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_webhooks (gateway, event_type, event_id, payload)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->getGatewayName(),
            $eventType,
            $eventId,
            json_encode($payload)
        ]);
        return $this->pdo->lastInsertId();
    }
    
    protected function markWebhookProcessed($webhookId, $error = null) {
        $stmt = $this->pdo->prepare("
            UPDATE payment_webhooks 
            SET processed = 1, processed_at = NOW(), error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$error, $webhookId]);
    }
}

/**
 * Implementação Mercado Pago
 */
class MercadoPagoGateway extends BasePaymentGateway {
    protected function getGatewayName() {
        return 'mercadopago';
    }
    
    public function createSubscription($user, $plan, $paymentMethod) {
        // Implementação de assinatura recorrente do Mercado Pago
        $url = 'https://api.mercadopago.com/preapproval';
        
        $data = [
            'reason' => $plan['name'] . ' - ' . $plan['description'],
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => (float)$plan['price'],
                'currency_id' => 'BRL'
            ],
            'back_url' => env('APP_URL') . '/subscription/success',
            'payer_email' => $user['email'],
            'external_reference' => 'user_' . $user['id'] . '_plan_' . $plan['id']
        ];
        
        $response = $this->makeRequest($url, 'POST', $data);
        
        if (isset($response['id'])) {
            // Criar registro de assinatura
            $stmt = $this->pdo->prepare("
                INSERT INTO subscriptions (user_id, plan_id, status, payment_method, gateway, gateway_subscription_id, current_period_start, current_period_end)
                VALUES (?, ?, 'pending', ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH))
            ");
            $stmt->execute([
                $user['id'],
                $plan['id'],
                $paymentMethod,
                $this->getGatewayName(),
                $response['id']
            ]);
            
            return [
                'success' => true,
                'subscription_id' => $this->pdo->lastInsertId(),
                'init_point' => $response['init_point'],
                'gateway_subscription_id' => $response['id']
            ];
        }
        
        return ['success' => false, 'error' => $response['message'] ?? 'Erro ao criar assinatura'];
    }
    
    public function cancelSubscription($subscriptionId) {
        $stmt = $this->pdo->prepare("SELECT gateway_subscription_id FROM subscriptions WHERE id = ?");
        $stmt->execute([$subscriptionId]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$subscription) {
            return ['success' => false, 'error' => 'Assinatura não encontrada'];
        }
        
        $url = 'https://api.mercadopago.com/preapproval/' . $subscription['gateway_subscription_id'];
        $response = $this->makeRequest($url, 'PUT', ['status' => 'cancelled']);
        
        if (isset($response['status']) && $response['status'] === 'cancelled') {
            $stmt = $this->pdo->prepare("
                UPDATE subscriptions 
                SET status = 'canceled', canceled_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subscriptionId]);
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Erro ao cancelar assinatura'];
    }
    
    public function createPayment($user, $amount, $description, $paymentMethod) {
        $url = 'https://api.mercadopago.com/checkout/preferences';
        
        $data = [
            'items' => [[
                'title' => $description,
                'quantity' => 1,
                'unit_price' => (float)$amount,
                'currency_id' => 'BRL'
            ]],
            'payer' => [
                'email' => $user['email'],
                'name' => $user['name']
            ],
            'back_urls' => [
                'success' => env('APP_URL') . '/payment/success',
                'failure' => env('APP_URL') . '/payment/failure',
                'pending' => env('APP_URL') . '/payment/pending'
            ],
            'auto_return' => 'approved',
            'external_reference' => 'user_' . $user['id'] . '_' . time(),
            'notification_url' => env('APP_URL') . '/webhooks/mercadopago'
        ];
        
        if ($paymentMethod === 'boleto') {
            $data['payment_methods'] = ['excluded_payment_types' => [['id' => 'credit_card']]];
        } elseif ($paymentMethod === 'pix') {
            $data['payment_methods'] = ['excluded_payment_types' => [['id' => 'credit_card'], ['id' => 'ticket']]];
        }
        
        $response = $this->makeRequest($url, 'POST', $data);
        
        if (isset($response['id'])) {
            return [
                'success' => true,
                'payment_id' => $response['id'],
                'init_point' => $response['init_point'],
                'sandbox_init_point' => $response['sandbox_init_point']
            ];
        }
        
        return ['success' => false, 'error' => $response['message'] ?? 'Erro ao criar pagamento'];
    }
    
    public function getPaymentStatus($paymentId) {
        $url = 'https://api.mercadopago.com/v1/payments/' . $paymentId;
        $response = $this->makeRequest($url, 'GET');
        
        if (isset($response['status'])) {
            $statusMap = [
                'approved' => 'paid',
                'pending' => 'pending',
                'in_process' => 'processing',
                'rejected' => 'failed',
                'cancelled' => 'canceled',
                'refunded' => 'refunded'
            ];
            
            return [
                'success' => true,
                'status' => $statusMap[$response['status']] ?? 'pending',
                'gateway_response' => $response
            ];
        }
        
        return ['success' => false, 'error' => 'Erro ao consultar status'];
    }
    
    public function processWebhook($payload) {
        $webhookId = $this->logWebhook($payload['type'] ?? 'unknown', $payload['id'] ?? null, $payload);
        
        try {
            if ($payload['type'] === 'payment') {
                $paymentInfo = $this->getPaymentStatus($payload['data']['id']);
                
                if ($paymentInfo['success']) {
                    // Atualizar pagamento no banco
                    $stmt = $this->pdo->prepare("
                        SELECT id FROM payments 
                        WHERE gateway = 'mercadopago' AND gateway_payment_id = ?
                    ");
                    $stmt->execute([$payload['data']['id']]);
                    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($payment) {
                        $this->updatePaymentStatus($payment['id'], $paymentInfo['status'], $paymentInfo['gateway_response']);
                        
                        // Se pagamento aprovado, ativar assinatura
                        if ($paymentInfo['status'] === 'paid') {
                            $this->activateSubscriptionFromPayment($payment['id']);
                        }
                    }
                }
            }
            
            $this->markWebhookProcessed($webhookId);
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->markWebhookProcessed($webhookId, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function makeRequest($url, $method, $data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json'
        ]);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    private function activateSubscriptionFromPayment($paymentId) {
        $stmt = $this->pdo->prepare("
            UPDATE subscriptions s
            JOIN payments p ON p.user_id = s.user_id
            SET s.status = 'active', s.current_period_start = CURDATE(), s.current_period_end = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            WHERE p.id = ? AND s.status = 'pending'
        ");
        $stmt->execute([$paymentId]);
    }
}

/**
 * Implementação Stripe (estrutura base)
 */
class StripeGateway extends BasePaymentGateway {
    protected function getGatewayName() {
        return 'stripe';
    }
    
    public function createSubscription($user, $plan, $paymentMethod) {
        // TODO: Implementar integração com Stripe
        return ['success' => false, 'error' => 'Stripe não implementado ainda'];
    }
    
    public function cancelSubscription($subscriptionId) {
        return ['success' => false, 'error' => 'Stripe não implementado ainda'];
    }
    
    public function createPayment($user, $amount, $description, $paymentMethod) {
        return ['success' => false, 'error' => 'Stripe não implementado ainda'];
    }
    
    public function getPaymentStatus($paymentId) {
        return ['success' => false, 'error' => 'Stripe não implementado ainda'];
    }
    
    public function processWebhook($payload) {
        return ['success' => false, 'error' => 'Stripe não implementado ainda'];
    }
}

/**
 * Implementação PagSeguro (estrutura base)
 */
class PagSeguroGateway extends BasePaymentGateway {
    protected function getGatewayName() {
        return 'pagseguro';
    }
    
    public function createSubscription($user, $plan, $paymentMethod) {
        // TODO: Implementar integração com PagSeguro
        return ['success' => false, 'error' => 'PagSeguro não implementado ainda'];
    }
    
    public function cancelSubscription($subscriptionId) {
        return ['success' => false, 'error' => 'PagSeguro não implementado ainda'];
    }
    
    public function createPayment($user, $amount, $description, $paymentMethod) {
        return ['success' => false, 'error' => 'PagSeguro não implementado ainda'];
    }
    
    public function getPaymentStatus($paymentId) {
        return ['success' => false, 'error' => 'PagSeguro não implementado ainda'];
    }
    
    public function processWebhook($payload) {
        return ['success' => false, 'error' => 'PagSeguro não implementado ainda'];
    }
}
