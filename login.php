<?php
require_once __DIR__ . '/includes/init.php';

if (isLoggedIn()) {
    header('Location: /landing_page.php');
    exit;
}

$pageTitle = 'Login - ' . SITE_NAME;
$error = getError();
$show2FA = $_SESSION['show_login_2fa'] ?? false;
unset($_SESSION['show_login_2fa']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Login - Sistema de disparo em massa de mensagens WhatsApp">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#16a34a">
    
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- Preconnect para melhor performance -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    
    <!-- Fontes MACIP - DaytonaPro -->
    <link rel="stylesheet" href="/assets/css/fonts.css">
</head>
<body class="bg-gradient-to-br from-green-400 to-green-600 min-h-screen flex items-center justify-center">
    <main role="main" class="w-full max-w-md px-4">
        <article class="bg-white rounded-lg shadow-2xl p-8">
            <header class="text-center mb-8">
                <img src="/assets/images/logo.png" alt="MAC-IP Tecnologia" class="mx-auto mb-4" style="max-width: 280px; height: auto;">
                <p class="text-gray-600 mt-2">Faça login para continuar</p>
            </header>
            
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert" aria-live="assertive">
                <i class="fas fa-exclamation-circle mr-2" aria-hidden="true"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($show2FA): ?>
            <!-- Formulário 2FA -->
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h2 class="text-lg font-semibold text-blue-800 mb-2">
                    <i class="fas fa-shield-alt mr-2"></i>Verificação em Duas Etapas
                </h2>
                <p class="text-blue-700 text-sm mb-4">
                    Digite o código de 6 dígitos do seu app authenticator ou use um código de backup.
                </p>
            </div>
            
            <form method="POST" action="" novalidate>
                <input type="hidden" name="action" value="verify_2fa">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="code">
                        <i class="fas fa-mobile-alt mr-2" aria-hidden="true"></i>Código do Authenticator
                    </label>
                    <input 
                        type="text" 
                        id="code" 
                        name="code" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-center text-lg font-mono"
                        placeholder="000000"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        autocomplete="one-time-code"
                        inputmode="numeric"
                    >
                </div>
                
                <div class="mb-4 text-center">
                    <span class="text-gray-500 text-sm">ou</span>
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="backup_code">
                        <i class="fas fa-key mr-2" aria-hidden="true"></i>Código de Backup
                    </label>
                    <input 
                        type="text" 
                        id="backup_code" 
                        name="backup_code" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-center font-mono"
                        placeholder="0000-0000"
                        maxlength="9"
                        pattern="[0-9]{4}-[0-9]{4}"
                    >
                </div>
                
                <div class="flex gap-3">
                    <button 
                        type="submit" 
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200"
                    >
                        <i class="fas fa-check mr-2" aria-hidden="true"></i>Verificar
                    </button>
                    <a href="login.php" 
                       class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 text-center">
                        <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i>Voltar
                    </a>
                </div>
            </form>
            
            <?php else: ?>
            <!-- Formulário de Login Normal -->
            <form method="POST" action="" novalidate>
                <input type="hidden" name="action" value="login">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                        <i class="fas fa-envelope mr-2" aria-hidden="true"></i>Email
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        placeholder="seu@email.com"
                        required
                        autocomplete="email"
                        aria-required="true"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                        <i class="fas fa-lock mr-2" aria-hidden="true"></i>Senha
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                        aria-required="true"
                    >
                </div>
                
                <button 
                    type="submit" 
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200"
                    aria-label="Fazer login"
                >
                    <i class="fas fa-sign-in-alt mr-2" aria-hidden="true"></i>Entrar
                </button>
                
                <div class="mt-4 text-center">
                    <button 
                        type="button" 
                        onclick="openForgotPasswordModal()"
                        class="text-green-600 hover:text-green-800 text-sm font-medium transition duration-200"
                    >
                        <i class="fas fa-key mr-1" aria-hidden="true"></i>Esqueci minha senha
                    </button>
                </div>
            </form>
            <?php endif; ?>
            
        </article>
    </main>
    
    <!-- Modal Esqueci Minha Senha -->
    <div id="forgotPasswordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="closeForgotPasswordModal(event)">
        <div class="bg-white rounded-lg shadow-2xl p-8 max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-key text-green-600 mr-2"></i>Recuperar Senha
                </h2>
                <button onclick="closeForgotPasswordModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="forgotPasswordContent">
                <p class="text-gray-600 mb-6">
                    Digite seu email cadastrado e enviaremos um link para redefinir sua senha.
                </p>
                
                <form id="forgotPasswordForm" onsubmit="submitForgotPassword(event)">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="forgot_email">
                            <i class="fas fa-envelope mr-2"></i>Email
                        </label>
                        <input 
                            type="email" 
                            id="forgot_email" 
                            name="email" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="seu@email.com"
                            required
                        >
                    </div>
                    
                    <div id="forgotPasswordMessage" class="hidden mb-4"></div>
                    
                    <div class="flex gap-3">
                        <button 
                            type="submit" 
                            id="forgotPasswordBtn"
                            class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200"
                        >
                            <i class="fas fa-paper-plane mr-2"></i>Enviar Link
                        </button>
                        <button 
                            type="button" 
                            onclick="closeForgotPasswordModal()"
                            class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200"
                        >
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.remove('hidden');
            document.getElementById('forgot_email').focus();
        }

        function closeForgotPasswordModal(event) {
            if (!event || event.target.id === 'forgotPasswordModal') {
                document.getElementById('forgotPasswordModal').classList.add('hidden');
                document.getElementById('forgotPasswordForm').reset();
                document.getElementById('forgotPasswordMessage').classList.add('hidden');
            }
        }

        async function submitForgotPassword(event) {
            event.preventDefault();

            const form = event.target;
            const email = form.email.value;
            const btn = document.getElementById('forgotPasswordBtn');
            const messageDiv = document.getElementById('forgotPasswordMessage');

            if (!btn || !messageDiv) {
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enviando...';

            try {
                const response = await fetch('api/forgot_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email })
                });

                const data = await response.json();

                messageDiv.classList.remove('hidden');

                if (data.success) {
                    messageDiv.className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4';
                    messageDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + data.message;
                    form.reset();

                    setTimeout(() => {
                        closeForgotPasswordModal();
                    }, 3000);
                } else {
                    messageDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
                    messageDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + data.message;
                }
            } catch (error) {
                messageDiv.classList.remove('hidden');
                messageDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>Erro ao enviar email. Tente novamente.';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Enviar Link';
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeForgotPasswordModal();
            }
        });
    </script>
</body>
</html>
