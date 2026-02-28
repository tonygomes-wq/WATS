<?php
/**
 * Página de Redefinição de Senha
 * Permite ao usuário criar uma nova senha usando o token recebido por email
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/SecurityHelpers.php';
require_once 'includes/CSRFProtection.php';

// Adicionar security headers
SecurityHelpers::setSecurityHeaders();

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$tokenValid = false;
$user = null;

// Validar formato do token
if (!empty($token) && !SecurityHelpers::validateTokenFormat($token)) {
    $error = 'Token inválido.';
    $tokenValid = false;
    $token = ''; // Limpar token inválido
}

// Verificar token no banco apenas se formato válido
if (!empty($token)) {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.id as user_id, u.name, u.email 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? 
        AND pr.expires_at > NOW()
        AND pr.used_at IS NULL
    ");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch();
    
    if ($resetData) {
        $tokenValid = true;
        $user = $resetData;
    } else {
        $error = 'Link inválido ou expirado. Solicite um novo link de redefinição.';
    }
}

// Processar redefinição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    // Validar CSRF
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido. Recarregue a página e tente novamente.';
    } else {
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validações
        if (empty($newPassword) || empty($confirmPassword)) {
            $error = 'Por favor, preencha todos os campos.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'As senhas não coincidem.';
        } else {
            // Validar força da senha
            $passwordValidation = SecurityHelpers::validatePasswordStrength($newPassword, 8);
            if (!$passwordValidation['valid']) {
                $error = implode(' ', $passwordValidation['errors']);
            } else {
        try {
            // Atualizar senha
            $hashedPassword = hashPassword($newPassword);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
            $stmt->execute([$hashedPassword, $user['user_id']]);
            
            // Marcar token como usado
            $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = ?");
            $stmt->execute([$token]);
            
            // Invalidar outros tokens do usuário
            $stmt = $pdo->prepare("
                UPDATE password_resets 
                SET used_at = NOW() 
                WHERE user_id = ? AND token != ? AND used_at IS NULL
            ");
            $stmt->execute([$user['user_id'], $token]);
            
            // Registrar na auditoria
            if (file_exists('includes/AuditLogger.php')) {
                require_once 'includes/AuditLogger.php';
                $audit = new AuditLogger();
                $audit->log('password_reset_completed', 'user', $user['user_id'], null, null, $user['user_id']);
            }
            
            $success = 'Senha redefinida com sucesso! Você será redirecionado para o login...';
            
            // Redirecionar após 3 segundos
            header("refresh:3;url=login.php");
            
        } catch (Exception $e) {
            error_log("Erro ao redefinir senha: " . $e->getMessage());
            $error = 'Erro ao redefinir senha. Tente novamente.';
        }
            }
        }
    }
}

$pageTitle = 'Redefinir Senha - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Fontes MACIP - DaytonaPro -->
    <link rel="stylesheet" href="/assets/css/fonts.css">
</head>
<body class="bg-gradient-to-br from-green-400 to-green-600 min-h-screen flex items-center justify-center">
    <main class="w-full max-w-md px-4">
        <article class="bg-white rounded-lg shadow-2xl p-8">
            <header class="text-center mb-8">
                <img src="/assets/images/logo.png" alt="MAC-IP Tecnologia" class="mx-auto mb-4" style="max-width: 280px; height: auto;">
                <h1 class="text-2xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-key text-green-600 mr-2"></i>Redefinir Senha
                </h1>
                <?php if ($tokenValid): ?>
                <p class="text-gray-600">Olá, <strong><?php echo htmlspecialchars($user['name']); ?></strong></p>
                <p class="text-sm text-gray-500">Crie uma nova senha para sua conta</p>
                <?php endif; ?>
            </header>
            
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($tokenValid && !$success): ?>
            <form method="POST" action="" id="resetPasswordForm">
                <?php echo CSRFProtection::getTokenField(); ?>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                        <i class="fas fa-lock mr-2"></i>Nova Senha
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="Mínimo 6 caracteres"
                            required
                            minlength="6"
                        >
                        <button 
                            type="button" 
                            onclick="togglePassword('password')"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                        >
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>Mínimo de 8 caracteres, incluindo letras maiúsculas, minúsculas e números
                    </p>
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">
                        <i class="fas fa-lock mr-2"></i>Confirmar Nova Senha
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="Digite a senha novamente"
                            required
                            minlength="6"
                        >
                        <button 
                            type="button" 
                            onclick="togglePassword('confirm_password')"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                        >
                            <i class="fas fa-eye" id="confirm_password-icon"></i>
                        </button>
                    </div>
                </div>
                
                <div id="passwordMatch" class="hidden mb-4"></div>
                
                <button 
                    type="submit" 
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200"
                >
                    <i class="fas fa-check mr-2"></i>Redefinir Senha
                </button>
            </form>
            
            <?php elseif (!$tokenValid): ?>
            <div class="text-center">
                <a href="login.php" class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar ao Login
                </a>
            </div>
            <?php endif; ?>
            
            <div class="mt-6 text-center">
                <a href="login.php" class="text-green-600 hover:text-green-800 text-sm font-medium">
                    <i class="fas fa-sign-in-alt mr-1"></i>Voltar ao Login
                </a>
            </div>
        </article>
    </main>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Verificar se senhas coincidem
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword.length > 0) {
                matchDiv.classList.remove('hidden');
                
                if (password === confirmPassword) {
                    matchDiv.className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4 text-sm';
                    matchDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>As senhas coincidem';
                } else {
                    matchDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-sm';
                    matchDiv.innerHTML = '<i class="fas fa-times-circle mr-2"></i>As senhas não coincidem';
                }
            } else {
                matchDiv.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
