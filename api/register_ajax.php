<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/RateLimiter.php';
require_once '../includes/SecurityHelpers.php';

// Adicionar security headers
SecurityHelpers::setSecurityHeaders();

// Rate limiting
$rateLimiter = new RateLimiter($pdo);
$clientIP = RateLimiter::getClientIP();

// Rate limit: 3 registros por hora por IP
$ipCheck = $rateLimiter->check($clientIP, 'register', 3, 60, 120);
if (!$ipCheck['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Muitas tentativas de registro. Tente novamente mais tarde.'
    ]);
    exit;
}

// Receber dados JSON
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    if ($action === 'register') {
        // ===== ETAPA 1: REGISTRO DO USUÁRIO =====
        $name = sanitize($input['name'] ?? '');
        $company_name = sanitize($input['company_name'] ?? '');
        $email = sanitize($input['email'] ?? '');
        $phone = sanitize($input['phone'] ?? '');
        $document = sanitize($input['document'] ?? ''); // CPF ou CNPJ
        $document_type = sanitize($input['document_type'] ?? 'cpf'); // cpf ou cnpj
        $password = $input['password'] ?? '';
        $password_confirm = $input['password_confirm'] ?? '';
        
        // Validações
        if (empty($name) || empty($email) || empty($password)) {
            echo json_encode([
                'success' => false,
                'message' => 'Por favor, preencha todos os campos obrigatórios.'
            ]);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode([
                'success' => false,
                'message' => 'Email inválido.'
            ]);
            exit;
        }
        
        // Validação de força da senha
        $passwordValidation = SecurityHelpers::validatePasswordStrength($password, 8);
        if (!$passwordValidation['valid']) {
            echo json_encode([
                'success' => false,
                'message' => implode(' ', $passwordValidation['errors'])
            ]);
            exit;
        }
        
        if ($password !== $password_confirm) {
            echo json_encode([
                'success' => false,
                'message' => 'As senhas não coincidem.'
            ]);
            exit;
        }
        
        // Verificar se email já existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            // Não revelar que email existe - usar mensagem genérica
            echo json_encode([
                'success' => false,
                'message' => 'Se este email não estiver cadastrado, você receberá um link de confirmação.'
            ]);
            exit;
        }
        
        // Validar documento (CPF ou CNPJ)
        $document_clean = preg_replace('/[^0-9]/', '', $document);
        if ($document_type === 'cpf') {
            if (strlen($document_clean) !== 11) {
                echo json_encode([
                    'success' => false,
                    'message' => 'CPF inválido. Deve conter 11 dígitos.'
                ]);
                exit;
            }
            if (!SecurityHelpers::validateCPF($document_clean)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'CPF inválido.'
                ]);
                exit;
            }
        }
        if ($document_type === 'cnpj') {
            if (strlen($document_clean) !== 14) {
                echo json_encode([
                    'success' => false,
                    'message' => 'CNPJ inválido. Deve conter 14 dígitos.'
                ]);
                exit;
            }
            if (!SecurityHelpers::validateCNPJ($document_clean)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'CNPJ inválido.'
                ]);
                exit;
            }
        }
        
        // Hash da senha
        $password_hash = hashPassword($password);
        
        // Inserir usuário
        // Todo usuário que se registra no SaaS é supervisor por padrão
        // (pode gerenciar atendentes, ver relatórios, etc)
        $stmt = $pdo->prepare("
            INSERT INTO users (
                name, 
                email, 
                password, 
                company_name, 
                phone, 
                document, 
                document_type,
                is_active, 
                is_admin,
                is_supervisor,
                user_type,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, 1, 'supervisor', NOW())
        ");
        
        $stmt->execute([
            $name,
            $email,
            $password_hash,
            $company_name,
            $phone,
            $document_clean,
            $document_type
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Resetar rate limit após registro bem-sucedido
        $rateLimiter->reset($clientIP, 'register');
        
        // Armazenar dados temporários na sessão para seleção de plano
        $_SESSION['temp_registration'] = [
            'user_id' => $userId,
            'name' => $name,
            'email' => $email
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'Cadastro realizado com sucesso!',
            'step' => 'select_plan',
            'user_id' => $userId
        ]);
        exit;
        
    } elseif ($action === 'select_plan') {
        // ===== ETAPA 2: SELEÇÃO DE PLANO =====
        
        if (empty($_SESSION['temp_registration'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Sessão expirada. Por favor, faça o cadastro novamente.'
            ]);
            exit;
        }
        
        $userId = $_SESSION['temp_registration']['user_id'];
        $planId = intval($input['plan_id'] ?? 0);
        
        // Buscar informações do plano
        $stmt = $pdo->prepare("SELECT * FROM pricing_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            echo json_encode([
                'success' => false,
                'message' => 'Plano não encontrado.'
            ]);
            exit;
        }
        
        $isFree = (
            $plan['price'] == 0 || 
            strtolower($plan['name']) === 'grátis' || 
            strtolower($plan['name']) === 'gratuito' ||
            (isset($plan['slug']) && strtolower($plan['slug']) === 'gratis')
        );
        
        if ($isFree) {
            // PLANO GRÁTIS - 15 dias de trial
            $trialEndDate = date('Y-m-d H:i:s', strtotime('+15 days'));
            
            // Criar assinatura trial
            $stmt = $pdo->prepare("
                INSERT INTO user_subscriptions (
                    user_id,
                    plan_id,
                    status,
                    trial_end_date,
                    started_at,
                    created_at
                ) VALUES (?, ?, 'trial', ?, NOW(), NOW())
            ");
            $stmt->execute([$userId, $planId, $trialEndDate]);
            
            // Fazer login automático
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $_SESSION['temp_registration']['name'];
            $_SESSION['user_email'] = $_SESSION['temp_registration']['email'];
            $_SESSION['is_admin'] = 0;
            $_SESSION['is_supervisor'] = 1;  // Usuário SaaS é supervisor por padrão
            $_SESSION['user_type'] = 'supervisor';
            
            // Atualizar último login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
            
            unset($_SESSION['temp_registration']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Bem-vindo! Você tem 15 dias de teste grátis.',
                'redirect' => '/dashboard.php',
                'plan_type' => 'trial'
            ]);
            exit;
            
        } else {
            // PLANO PAGO - Redirecionar para pagamento
            
            // Criar assinatura pendente
            $stmt = $pdo->prepare("
                INSERT INTO user_subscriptions (
                    user_id,
                    plan_id,
                    status,
                    created_at
                ) VALUES (?, ?, 'pending_payment', NOW())
            ");
            $stmt->execute([$userId, $planId]);
            
            $subscriptionId = $pdo->lastInsertId();
            
            // Armazenar na sessão para página de pagamento
            $_SESSION['pending_payment'] = [
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'plan_id' => $planId,
                'plan_name' => $plan['name'],
                'plan_price' => $plan['price']
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Redirecionando para pagamento...',
                'redirect' => '/payment.php',
                'plan_type' => 'paid',
                'plan_price' => $plan['price']
            ]);
            exit;
        }
        
    }
    
} catch (Exception $e) {
    error_log("Erro no registro AJAX: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar cadastro. Tente novamente.'
    ]);
    exit;
}

// Ação inválida
echo json_encode([
    'success' => false,
    'message' => 'Ação inválida.'
]);
