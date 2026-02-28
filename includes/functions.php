<?php
// Funções auxiliares do sistema

// Verificar se o usuário está logado
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Verificar se o usuário é admin
function isAdmin()
{
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Verificar se o usuário é supervisor
function isSupervisor()
{
    return isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;
}

// Verificar se o usuário é atendente
function isAttendant()
{
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'attendant';
}

// Redirecionar se não estiver logado
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: /landing_page.php');
        exit;
    }
}

// Redirecionar se não for admin
function requireAdmin()
{
    requireLogin();
    if (!isAdmin()) {
        header('Location: /dashboard.php');
        exit;
    }
}

// Redirecionar atendentes para o chat (páginas que não devem acessar)
function redirectAttendantToChat()
{
    if (isAttendant()) {
        header('Location: /chat.php');
        exit;
    }
}

// Verificar se atendente tem permissão para um menu específico
function attendantHasMenuPermission($menuKey)
{
    if (!isAttendant()) {
        return true; // Não é atendente, tem acesso total
    }

    global $pdo;
    $userId = $_SESSION['user_id'] ?? 0;

    $stmt = $pdo->prepare("SELECT allowed_menus FROM supervisor_users WHERE id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data || empty($data['allowed_menus'])) {
        // Menus padrão para atendentes
        $defaultMenus = ['chat' => true, 'profile' => true];
        return isset($defaultMenus[$menuKey]) && $defaultMenus[$menuKey];
    }

    $allowedMenus = json_decode($data['allowed_menus'], true) ?? [];
    return isset($allowedMenus[$menuKey]) && $allowedMenus[$menuKey];
}

// Sanitizar entrada
function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// Formatar telefone (remover caracteres especiais)
function formatPhone($phone)
{
    return preg_replace('/[^0-9]/', '', $phone);
}

// Validar telefone brasileiro
function validatePhone($phone)
{
    $phone = formatPhone($phone);
    // Aceita números com:
    // - 10 ou 11 dígitos (sem código do país: DDD + número)
    // - 12 ou 13 dígitos (com código do país 55: 55 + DDD + número)
    // - 12 dígitos sem código 55 (alguns casos especiais)
    
    // Padrão 10 ou 11 dígitos: (XX) XXXXX-XXXX ou (XX) XXXX-XXXX
    $pattern10_11 = '/^[1-9]{2}9?[0-9]{8}$/';
    
    // Padrão 12 ou 13 dígitos com código 55: 55 (XX) XXXXX-XXXX
    $pattern12_13 = '/^55[1-9]{2}9?[0-9]{8}$/';
    
    // Padrão 12 dígitos sem código 55 (aceita qualquer combinação de 12 dígitos)
    $pattern12 = '/^[0-9]{12}$/';

    return preg_match($pattern10_11, $phone) || 
           preg_match($pattern12_13, $phone) || 
           preg_match($pattern12, $phone);
}

// Gerar senha hash
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verificar senha
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

// Mensagem de sucesso
function setSuccess($message)
{
    $_SESSION['success'] = $message;
}

// Mensagem de erro
function setError($message)
{
    $_SESSION['error'] = $message;
}

function notifyUser(string $email, string $subject, string $body): bool
{
    $headers = "From: " . (defined('SITE_NAME') ? SITE_NAME : 'WATS') . " <no-reply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($email, $subject, $body, $headers);
}

// Obter e limpar mensagem de sucesso
function getSuccess()
{
    if (isset($_SESSION['success'])) {
        $message = $_SESSION['success'];
        unset($_SESSION['success']);
        return $message;
    }
    return null;
}

// Obter e limpar mensagem de erro
function getError()
{
    if (isset($_SESSION['error'])) {
        $message = $_SESSION['error'];
        unset($_SESSION['error']);
        return $message;
    }
    return null;
}

// Processar macros na mensagem
function processMacros($message, $contact)
{
    $macros = [
        '{nome}' => $contact['name'] ?? 'Cliente',
        '{telefone}' => $contact['phone'] ?? '',
    ];

    return str_replace(array_keys($macros), array_values($macros), $message);
}

// Validar email
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Gerar cor aleatória para categoria
function randomColor()
{
    $colors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316'];
    return $colors[array_rand($colors)];
}

// Verificar se o usuário precisa alterar a senha
function mustChangePassword()
{
    global $pdo;

    if (!isLoggedIn()) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT must_change_password, first_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user && ($user['must_change_password'] || $user['first_login']);
}

// Redirecionar se precisar alterar senha
function requirePasswordChange()
{
    if (mustChangePassword()) {
        header('Location: /change_password_required.php');
        exit;
    }
}

// Forçar usuário a alterar senha no próximo login
function forcePasswordChange($userId)
{
    global $pdo;

    $stmt = $pdo->prepare("UPDATE users SET must_change_password = 1 WHERE id = ?");
    return $stmt->execute([$userId]);
}

// Formatar data
function formatDate($date)
{
    return date('d/m/Y H:i', strtotime($date));
}

function getDefaultPricingPlans()
{
    return [
        [
            'slug' => 'free',
            'name' => 'Gratuito',
            'price' => 0.00,
            'message_limit' => 500,
            'features' => json_encode([
                '500 mensagens/mês',
                'Suporte por email'
            ]),
            'is_active' => 1,
            'is_popular' => 0,
            'sort_order' => 1
        ],
        [
            'slug' => 'basic',
            'name' => 'Básico',
            'price' => 29.90,
            'message_limit' => 2000,
            'features' => json_encode([
                '2.000 mensagens/mês',
                '1 instância Evolution'
            ]),
            'is_active' => 1,
            'is_popular' => 0,
            'sort_order' => 2
        ],
        [
            'slug' => 'pro',
            'name' => 'Pro',
            'price' => 59.90,
            'message_limit' => 10000,
            'features' => json_encode([
                '10.000 mensagens/mês',
                '2 instâncias Evolution'
            ]),
            'is_active' => 1,
            'is_popular' => 1,
            'sort_order' => 3
        ],
        [
            'slug' => 'enterprise',
            'name' => 'Enterprise',
            'price' => 149.90,
            'message_limit' => 999999,
            'features' => json_encode([
                'Mensagens ilimitadas',
                'Suporte prioritário'
            ]),
            'is_active' => 1,
            'is_popular' => 0,
            'sort_order' => 4
        ],
    ];
}

/**
 * Retorna planos cadastrados (com fallback para os padrões) já ordenados.
 */
function getPricingPlans($onlyActive = false)
{
    static $cachedPlans = [];
    $cacheKey = $onlyActive ? 'active' : 'all';

    if (isset($cachedPlans[$cacheKey])) {
        return $cachedPlans[$cacheKey];
    }

    global $pdo;
    $plans = [];

    if (isset($pdo)) {
        try {
            $query = "SELECT slug, name, price, message_limit, features, is_active, is_popular, sort_order FROM pricing_plans";
            if ($onlyActive) {
                $query .= " WHERE is_active = 1";
            }
            $query .= " ORDER BY sort_order ASC, id ASC";
            $stmt = $pdo->query($query);
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $plans = [];
        }
    }

    if (empty($plans)) {
        $plans = getDefaultPricingPlans();
    }

    return $cachedPlans[$cacheKey] = $plans;
}

function getPlanColor($slug)
{
    $colors = [
        'free' => 'gray',
        'basic' => 'blue',
        'pro' => 'purple',
        'enterprise' => 'green'
    ];

    return $colors[$slug] ?? 'green';
}
