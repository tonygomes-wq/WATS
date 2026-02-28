<?php
/**
 * API de Gerenciamento de Assinaturas
 * MACIP Tecnologia LTDA
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/PaymentGateway.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'get_plans':
            $stmt = $pdo->query("
                SELECT * FROM plans 
                WHERE is_active = 1 
                ORDER BY sort_order, price
            ");
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'plans' => $plans]);
            break;
            
        case 'get_current_subscription':
            $stmt = $pdo->prepare("
                SELECT s.*, p.name as plan_name, p.price, p.message_limit
                FROM subscriptions s
                JOIN plans p ON s.plan_id = p.id
                WHERE s.user_id = ? AND s.status IN ('active', 'trial')
                ORDER BY s.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'subscription' => $subscription]);
            break;
            
        case 'create_subscription':
            $planId = $_POST['plan_id'] ?? null;
            $paymentMethod = $_POST['payment_method'] ?? 'credit_card';
            $gateway = $_POST['gateway'] ?? 'mercadopago';
            
            if (!$planId) {
                throw new Exception('Plano não especificado');
            }
            
            // Buscar plano
            $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
            $stmt->execute([$planId]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$plan) {
                throw new Exception('Plano não encontrado');
            }
            
            // Buscar usuário
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Criar assinatura via gateway
            $gatewayInstance = PaymentGatewayFactory::create($gateway);
            $result = $gatewayInstance->createSubscription($user, $plan, $paymentMethod);
            
            echo json_encode($result);
            break;
            
        case 'cancel_subscription':
            $subscriptionId = $_POST['subscription_id'] ?? null;
            $reason = $_POST['reason'] ?? '';
            
            if (!$subscriptionId) {
                throw new Exception('Assinatura não especificada');
            }
            
            // Verificar se a assinatura pertence ao usuário
            $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ? AND user_id = ?");
            $stmt->execute([$subscriptionId, $userId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$subscription) {
                throw new Exception('Assinatura não encontrada');
            }
            
            // Cancelar via gateway
            $gatewayInstance = PaymentGatewayFactory::create($subscription['gateway']);
            $result = $gatewayInstance->cancelSubscription($subscriptionId);
            
            if ($result['success']) {
                // Atualizar motivo do cancelamento
                $stmt = $pdo->prepare("
                    UPDATE subscriptions 
                    SET cancellation_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$reason, $subscriptionId]);
            }
            
            echo json_encode($result);
            break;
            
        case 'get_payment_history':
            $stmt = $pdo->prepare("
                SELECT p.*, pl.name as plan_name
                FROM payments p
                LEFT JOIN plans pl ON p.plan_id = pl.id
                WHERE p.user_id = ?
                ORDER BY p.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$userId]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'payments' => $payments]);
            break;
            
        case 'apply_coupon':
            $couponCode = $_POST['coupon_code'] ?? '';
            $planId = $_POST['plan_id'] ?? null;
            
            if (!$couponCode || !$planId) {
                throw new Exception('Cupom ou plano não especificado');
            }
            
            // Verificar cupom
            $stmt = $pdo->prepare("
                SELECT * FROM coupons 
                WHERE code = ? 
                AND is_active = 1
                AND (valid_from IS NULL OR valid_from <= CURDATE())
                AND (valid_until IS NULL OR valid_until >= CURDATE())
                AND (max_uses IS NULL OR used_count < max_uses)
            ");
            $stmt->execute([$couponCode]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$coupon) {
                throw new Exception('Cupom inválido ou expirado');
            }
            
            // Verificar se cupom já foi usado pelo usuário
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ? AND user_id = ?");
            $stmt->execute([$coupon['id'], $userId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cupom já utilizado');
            }
            
            // Verificar se cupom é aplicável ao plano
            $applicablePlans = json_decode($coupon['applicable_plans'], true);
            if ($applicablePlans && !in_array($planId, $applicablePlans)) {
                throw new Exception('Cupom não aplicável a este plano');
            }
            
            // Buscar preço do plano
            $stmt = $pdo->prepare("SELECT price FROM plans WHERE id = ?");
            $stmt->execute([$planId]);
            $planPrice = $stmt->fetchColumn();
            
            // Calcular desconto
            if ($coupon['type'] === 'percentage') {
                $discount = $planPrice * ($coupon['value'] / 100);
            } else {
                $discount = $coupon['value'];
            }
            
            $finalPrice = max(0, $planPrice - $discount);
            
            echo json_encode([
                'success' => true,
                'coupon' => $coupon,
                'original_price' => $planPrice,
                'discount' => $discount,
                'final_price' => $finalPrice
            ]);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
