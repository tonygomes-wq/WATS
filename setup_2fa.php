<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/totp.php';

requireLogin();

$pageTitle = 'Configurar 2FA - ' . SITE_NAME;
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';
$isAttendant = ($userType === 'attendant');

// Buscar dados do usuário (users ou supervisor_users)
if ($isAttendant) {
    // Atendente: buscar na tabela supervisor_users
    $stmt = $pdo->prepare("SELECT * FROM supervisor_users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $tableName = 'supervisor_users';
} else {
    // Supervisor/Admin: buscar na tabela users
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $tableName = 'users';
}

if (!$user) {
    setError('Usuário não encontrado');
    header('Location: ' . ($isAttendant ? 'chat.php' : 'dashboard.php'));
    exit;
}

$message = '';
$error = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'enable_2fa') {
        $code = trim($_POST['code'] ?? '');
        $secret = $_POST['secret'] ?? '';
        
        if (empty($code) || empty($secret)) {
            $error = 'Código e secret são obrigatórios';
        } else if (!TOTP::verifyCode($secret, $code)) {
            $error = 'Código inválido. Verifique se o horário do seu dispositivo está correto.';
        } else {
            // Gerar códigos de backup
            $backupCodes = TOTP::generateBackupCodes();
            
            // Salvar no banco (tabela correta)
            $stmt = $pdo->prepare("UPDATE {$tableName} SET two_factor_enabled = 1, two_factor_secret = ?, backup_codes = ? WHERE id = ?");
            $success = $stmt->execute([$secret, json_encode($backupCodes), $userId]);
            
            if ($success) {
                $_SESSION['2fa_backup_codes'] = $backupCodes;
                header('Location: setup_2fa.php?step=backup');
                exit;
            } else {
                $error = 'Erro ao salvar configurações';
            }
        }
    } else if ($action === 'disable_2fa') {
        // Verificar se é atendente com 2FA obrigatório
        if ($isAttendant && isset($user['two_factor_enabled_by_supervisor']) && $user['two_factor_enabled_by_supervisor']) {
            $error = 'Você não pode desativar o 2FA. Ele foi configurado como obrigatório pelo seu supervisor.';
        } else {
            $password = $_POST['password'] ?? '';
            
            if (empty($password)) {
                $error = 'Senha é obrigatória para desabilitar 2FA';
            } else if (!password_verify($password, $user['password'])) {
                $error = 'Senha incorreta';
            } else {
                $stmt = $pdo->prepare("UPDATE {$tableName} SET two_factor_enabled = 0, two_factor_secret = NULL, backup_codes = NULL WHERE id = ?");
                $success = $stmt->execute([$userId]);
                
                if ($success) {
                    $message = '2FA desabilitado com sucesso';
                    $user['two_factor_enabled'] = 0;
                    $user['two_factor_secret'] = null;
                } else {
                    $error = 'Erro ao desabilitar 2FA';
                }
            }
        }
    }
}

$step = $_GET['step'] ?? 'main';

require_once 'includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">
            <i class="fas fa-shield-alt mr-2 text-blue-600"></i>Autenticação de Dois Fatores (2FA)
        </h1>
        
        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($step === 'backup' && isset($_SESSION['2fa_backup_codes'])): ?>
        <!-- Mostrar códigos de backup -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-yellow-800 mb-4">
                <i class="fas fa-key mr-2"></i>Códigos de Backup
            </h2>
            <div class="bg-yellow-100 border border-yellow-300 rounded p-4 mb-4">
                <p class="text-yellow-800 font-semibold mb-2">
                    <i class="fas fa-exclamation-triangle mr-2"></i>IMPORTANTE: Salve estes códigos em local seguro!
                </p>
                <p class="text-yellow-700 text-sm">
                    Use estes códigos se perder acesso ao seu dispositivo. Cada código só pode ser usado uma vez.
                </p>
            </div>
            
            <div class="grid grid-cols-2 gap-2 mb-4">
                <?php foreach ($_SESSION['2fa_backup_codes'] as $code): ?>
                <div class="bg-white border rounded p-2 text-center font-mono text-sm">
                    <?php echo htmlspecialchars($code); ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="flex gap-4">
                <button onclick="printCodes()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-print mr-2"></i>Imprimir
                </button>
                <button onclick="copyCodes()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-copy mr-2"></i>Copiar
                </button>
                <a href="setup_2fa.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-check mr-2"></i>Concluído
                </a>
            </div>
        </div>
        
        <?php unset($_SESSION['2fa_backup_codes']); ?>
        
        <?php elseif ($user['two_factor_enabled']): ?>
        <!-- 2FA já habilitado -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-green-800 mb-4">
                <i class="fas fa-check-circle mr-2"></i>2FA Ativado
                <?php if ($isAttendant && isset($user['two_factor_enabled_by_supervisor']) && $user['two_factor_enabled_by_supervisor']): ?>
                    <span class="ml-2 px-2 py-1 bg-purple-100 text-purple-800 text-xs font-medium rounded">
                        <i class="fas fa-lock mr-1"></i>Obrigatório
                    </span>
                <?php endif; ?>
            </h2>
            <p class="text-green-700 mb-4">
                A autenticação de dois fatores está ativa em sua conta. Você precisará do código do seu app authenticator para fazer login.
            </p>
            
            <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-4">
                <h3 class="font-semibold text-blue-800 mb-2">Apps Compatíveis:</h3>
                <ul class="text-blue-700 text-sm space-y-1">
                    <li><i class="fab fa-google mr-2"></i>Google Authenticator</li>
                    <li><i class="fab fa-microsoft mr-2"></i>Microsoft Authenticator</li>
                    <li><i class="fas fa-mobile-alt mr-2"></i>Authy</li>
                    <li><i class="fas fa-mobile-alt mr-2"></i>1Password</li>
                </ul>
            </div>
            
            <?php if ($isAttendant && isset($user['two_factor_enabled_by_supervisor']) && $user['two_factor_enabled_by_supervisor']): ?>
                <!-- 2FA obrigatório - atendente não pode desativar -->
                <div class="bg-purple-50 border border-purple-200 rounded p-4">
                    <p class="text-purple-800 font-semibold mb-2">
                        <i class="fas fa-lock mr-2"></i>2FA Obrigatório
                    </p>
                    <p class="text-purple-700 text-sm">
                        O 2FA foi ativado como obrigatório pelo seu supervisor. Você não pode desativá-lo sozinho. 
                        Entre em contato com seu supervisor se precisar de ajuda.
                    </p>
                </div>
            <?php else: ?>
                <!-- Permitir desativar 2FA -->
                <form method="POST" onsubmit="return confirm('Tem certeza que deseja desabilitar o 2FA? Isso tornará sua conta menos segura.')">
                    <input type="hidden" name="action" value="disable_2fa">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Confirme sua senha para desabilitar:
                        </label>
                        <input type="password" name="password" required 
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-times mr-2"></i>Desabilitar 2FA
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- Configurar 2FA -->
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-mobile-alt mr-2"></i>Configurar 2FA
                </h2>
                
                <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-4">
                    <h3 class="font-semibold text-blue-800 mb-2">O que é 2FA?</h3>
                    <p class="text-blue-700 text-sm">
                        A autenticação de dois fatores adiciona uma camada extra de segurança, 
                        exigindo um código do seu celular além da senha.
                    </p>
                </div>
                
                <div class="space-y-3 mb-6">
                    <h3 class="font-semibold text-gray-800">Passo 1: Instale um app</h3>
                    <div class="space-y-2">
                        <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" 
                           target="_blank" class="flex items-center text-blue-600 hover:text-blue-800">
                            <i class="fab fa-google mr-2"></i>Google Authenticator (Android)
                        </a>
                        <a href="https://apps.apple.com/app/google-authenticator/id388497605" 
                           target="_blank" class="flex items-center text-blue-600 hover:text-blue-800">
                            <i class="fab fa-apple mr-2"></i>Google Authenticator (iOS)
                        </a>
                        <a href="https://play.google.com/store/apps/details?id=com.azure.authenticator" 
                           target="_blank" class="flex items-center text-blue-600 hover:text-blue-800">
                            <i class="fab fa-microsoft mr-2"></i>Microsoft Authenticator (Android)
                        </a>
                        <a href="https://apps.apple.com/app/microsoft-authenticator/id983156458" 
                           target="_blank" class="flex items-center text-blue-600 hover:text-blue-800">
                            <i class="fab fa-apple mr-2"></i>Microsoft Authenticator (iOS)
                        </a>
                    </div>
                </div>
            </div>
            
            <div>
                <?php
                $secret = TOTP::generateSecret();
                $qrUrls = TOTP::getQRCodeUrls($secret, $user['email'], 'WATS - ' . SITE_NAME);
                ?>
                
                <h3 class="font-semibold text-gray-800 mb-4">Passo 2: Escaneie o QR Code</h3>
                
                <div class="text-center mb-4">
                    <div id="qr-container">
                        <img id="qr-image" src="<?php echo htmlspecialchars($qrUrls['qrserver']); ?>" 
                             alt="QR Code 2FA" class="mx-auto border rounded" 
                             onerror="tryNextQRCode()" style="max-width: 200px;">
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <span id="qr-status">Carregando QR Code...</span>
                    </p>
                </div>
                
                <div class="bg-gray-50 border rounded p-3 mb-4">
                    <p class="text-xs text-gray-600 mb-1">Ou digite manualmente:</p>
                    <code class="text-sm break-all"><?php echo htmlspecialchars($secret); ?></code>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="enable_2fa">
                    <input type="hidden" name="secret" value="<?php echo htmlspecialchars($secret); ?>">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Passo 3: Digite o código de 6 dígitos:
                        </label>
                        <input type="text" name="code" required maxlength="6" pattern="[0-9]{6}"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-center text-lg font-mono"
                               placeholder="000000">
                    </div>
                    
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg">
                        <i class="fas fa-shield-alt mr-2"></i>Ativar 2FA
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mt-6 pt-6 border-t">
            <a href="dashboard.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Voltar ao Dashboard
            </a>
        </div>
    </div>
</div>

<script>
// URLs de QR Code para fallback
const qrUrls = <?php echo json_encode($qrUrls ?? []); ?>;
let currentQRIndex = 0;
const qrApiNames = ['QR Server', 'Google Charts', 'QuickChart'];

function tryNextQRCode() {
    currentQRIndex++;
    const qrImage = document.getElementById('qr-image');
    const qrStatus = document.getElementById('qr-status');
    
    if (currentQRIndex === 1 && qrUrls.google) {
        qrImage.src = qrUrls.google;
        qrStatus.textContent = 'Tentando Google Charts...';
    } else if (currentQRIndex === 2 && qrUrls.quickchart) {
        qrImage.src = qrUrls.quickchart;
        qrStatus.textContent = 'Tentando QuickChart...';
    } else {
        // Se todas as APIs falharam, mostrar opção manual
        showManualOption();
    }
}

function showManualOption() {
    const qrContainer = document.getElementById('qr-container');
    const qrStatus = document.getElementById('qr-status');
    
    qrContainer.innerHTML = `
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="text-center mb-3">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
            </div>
            <p class="text-yellow-800 font-semibold mb-2">QR Code indisponível</p>
            <p class="text-yellow-700 text-sm mb-3">
                Não foi possível carregar o QR Code. Use a configuração manual abaixo.
            </p>
            <button onclick="generateQRCodeJS()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                <i class="fas fa-qrcode mr-2"></i>Gerar QR Code Local
            </button>
        </div>
    `;
    qrStatus.textContent = 'Use a configuração manual ou clique para gerar QR Code local';
}

function generateQRCodeJS() {
    if (typeof QRCode !== 'undefined' && qrUrls.otpauth) {
        const qrContainer = document.getElementById('qr-container');
        qrContainer.innerHTML = '<div id="qrcode-js"></div>';
        
        new QRCode(document.getElementById("qrcode-js"), {
            text: qrUrls.otpauth,
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff"
        });
        
        document.getElementById('qr-status').textContent = 'QR Code gerado localmente';
    } else {
        // Carregar biblioteca QRCode.js dinamicamente
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js';
        script.onload = function() {
            generateQRCodeJS();
        };
        document.head.appendChild(script);
    }
}

// Verificar se QR Code carregou com sucesso
document.addEventListener('DOMContentLoaded', function() {
    const qrImage = document.getElementById('qr-image');
    if (qrImage) {
        qrImage.onload = function() {
            document.getElementById('qr-status').textContent = 'QR Code carregado com sucesso';
        };
        
        // Timeout para detectar falha de carregamento
        setTimeout(function() {
            if (qrImage.naturalWidth === 0) {
                tryNextQRCode();
            }
        }, 5000);
    }
});

function copyCodes() {
    const codes = <?php echo json_encode($_SESSION['2fa_backup_codes'] ?? []); ?>;
    const text = codes.join('\n');
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Códigos copiados para a área de transferência!');
        });
    } else {
        // Fallback para navegadores antigos
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Códigos copiados para a área de transferência!');
    }
}

function printCodes() {
    const codes = <?php echo json_encode($_SESSION['2fa_backup_codes'] ?? []); ?>;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Códigos de Backup 2FA - <?php echo SITE_NAME; ?></title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .codes { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
                .code { border: 1px solid #ccc; padding: 10px; text-align: center; font-family: monospace; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php echo SITE_NAME; ?></h1>
                <h2>Códigos de Backup 2FA</h2>
                <p>Usuário: <?php echo htmlspecialchars($user['email']); ?></p>
                <p>Data: ${new Date().toLocaleDateString('pt-BR')}</p>
            </div>
            
            <div class="warning">
                <strong>IMPORTANTE:</strong> Guarde estes códigos em local seguro. 
                Cada código só pode ser usado uma vez.
            </div>
            
            <div class="codes">
                ${codes.map(code => `<div class="code">${code}</div>`).join('')}
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php require_once 'includes/footer.php'; ?>
