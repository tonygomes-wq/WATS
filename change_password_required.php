<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Se o usuário não precisa alterar a senha, redirecionar
if (!$user || (!$user['must_change_password'] && !$user['first_login'])) {
    header('Location: /dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Por favor, preencha todos os campos.';
    } elseif (!verifyPassword($currentPassword, $user['password'])) {
        $error = 'Senha atual incorreta.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'A nova senha deve ter no mínimo 8 caracteres.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'As senhas não coincidem.';
    } elseif ($currentPassword === $newPassword) {
        $error = 'A nova senha deve ser diferente da senha atual.';
    } else {
        // Validar força da senha
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $newPassword)) {
            $error = 'A nova senha deve conter pelo menos: 1 letra minúscula, 1 maiúscula e 1 número.';
        } else {
            // Atualizar senha e marcar como alterada
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?, 
                    must_change_password = 0, 
                    first_login = 0, 
                    last_login = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([hashPassword($newPassword), $userId])) {
                $success = 'Senha alterada com sucesso! Redirecionando...';
                
                // Redirecionar após 2 segundos
                header("refresh:2;url=/dashboard.php");
            } else {
                $error = 'Erro ao alterar senha. Tente novamente.';
            }
        }
    }
}

$pageTitle = 'Alterar Senha - ' . SITE_NAME;
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
    
    <style>
        /* Estilos para footer fixo */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding-bottom: 60px; /* Espaço para o footer fixo */
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-400 to-blue-600 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md px-4">
        <div class="bg-white rounded-lg shadow-2xl p-8">
            <div class="text-center mb-8">
                <div class="bg-yellow-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-key text-2xl text-yellow-600"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">
                    <?php echo $user['first_login'] ? 'Primeiro Acesso' : 'Alteração Obrigatória'; ?>
                </h1>
                <p class="text-gray-600">
                    <?php if ($user['first_login']): ?>
                        Bem-vindo! Por segurança, você deve definir uma nova senha.
                    <?php else: ?>
                        Por motivos de segurança, você deve alterar sua senha.
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>Senha Atual
                    </label>
                    <input 
                        type="password" 
                        id="current_password" 
                        name="current_password" 
                        required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Digite sua senha atual"
                    >
                </div>
                
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-key mr-2"></i>Nova Senha
                    </label>
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        required 
                        minlength="8"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Digite sua nova senha"
                        onkeyup="checkPasswordStrength()"
                    >
                    <div id="password-strength" class="mt-2 text-sm"></div>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-check-double mr-2"></i>Confirmar Nova Senha
                    </label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Confirme sua nova senha"
                        onkeyup="checkPasswordMatch()"
                    >
                    <div id="password-match" class="mt-2 text-sm"></div>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="font-semibold text-blue-800 mb-2">
                        <i class="fas fa-info-circle mr-2"></i>Requisitos da Senha:
                    </h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li><i class="fas fa-check mr-2"></i>Mínimo de 8 caracteres</li>
                        <li><i class="fas fa-check mr-2"></i>Pelo menos 1 letra minúscula</li>
                        <li><i class="fas fa-check mr-2"></i>Pelo menos 1 letra maiúscula</li>
                        <li><i class="fas fa-check mr-2"></i>Pelo menos 1 número</li>
                        <li><i class="fas fa-check mr-2"></i>Diferente da senha atual</li>
                    </ul>
                </div>
                
                <button 
                    type="submit" 
                    id="submit-btn"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 disabled:bg-gray-400 disabled:cursor-not-allowed"
                    disabled
                >
                    <i class="fas fa-save mr-2"></i>Alterar Senha
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Esta alteração é obrigatória por motivos de segurança
                </p>
            </div>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthDiv = document.getElementById('password-strength');
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 8) strength++;
            else feedback.push('Mínimo 8 caracteres');
            
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('Letra minúscula');
            
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('Letra maiúscula');
            
            if (/\d/.test(password)) strength++;
            else feedback.push('Número');
            
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
            
            let color, text;
            if (strength < 2) {
                color = 'text-red-600';
                text = 'Fraca';
            } else if (strength < 4) {
                color = 'text-yellow-600';
                text = 'Média';
            } else {
                color = 'text-green-600';
                text = 'Forte';
            }
            
            strengthDiv.innerHTML = `
                <span class="${color}">Força: ${text}</span>
                ${feedback.length > 0 ? `<br><span class="text-gray-500">Faltam: ${feedback.join(', ')}</span>` : ''}
            `;
            
            checkFormValidity();
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            
            if (confirm === '') {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirm) {
                matchDiv.innerHTML = '<span class="text-green-600"><i class="fas fa-check mr-1"></i>Senhas coincidem</span>';
            } else {
                matchDiv.innerHTML = '<span class="text-red-600"><i class="fas fa-times mr-1"></i>Senhas não coincidem</span>';
            }
            
            checkFormValidity();
        }
        
        function checkFormValidity() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const current = document.getElementById('current_password').value;
            const submitBtn = document.getElementById('submit-btn');
            
            const isValid = current.length > 0 &&
                           password.length >= 8 &&
                           /[a-z]/.test(password) &&
                           /[A-Z]/.test(password) &&
                           /\d/.test(password) &&
                           password === confirm;
            
            submitBtn.disabled = !isValid;
        }
        
        // Verificar validade em tempo real
        document.getElementById('current_password').addEventListener('input', checkFormValidity);
        document.getElementById('new_password').addEventListener('input', function() {
            checkPasswordStrength();
            checkPasswordMatch();
        });
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
    </script>
    
    <!-- Espaçamento para evitar sobreposição com footer fixo -->
    <div class="pb-16"></div>
    
    <!-- Footer fixo na parte inferior -->
    <footer class="fixed bottom-0 left-0 right-0 bg-gray-800 text-white py-3 z-50 shadow-lg" role="contentinfo">
        <div class="container mx-auto px-4 text-center">
            <p class="text-sm">&copy; <?php echo date('Y'); ?> WhatsApp Sender. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html>
