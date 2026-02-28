<?php
/**
 * Sistema de Onboarding - Primeiro Acesso
 * Guia o usuário após criar a instância
 */

$page_title = 'Bem-vindo ao WATS';
require_once 'includes/header_spa.php';

$userId = $_SESSION['user_id'];

// Verificar se já completou o onboarding
$stmt = $pdo->prepare("SELECT onboarding_completed FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($user['onboarding_completed']) {
    header('Location: /dashboard.php');
    exit;
}

// Verificar etapa atual
$step = $_GET['step'] ?? 1;
$step = max(1, min(3, intval($step))); // Entre 1 e 3

// Verificar se tem instância configurada
$stmt = $pdo->prepare("SELECT evolution_instance, evolution_token FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userInstance = $stmt->fetch();
$hasInstance = !empty($userInstance['evolution_instance']) && !empty($userInstance['evolution_token']);

// Contar contatos
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM contacts WHERE user_id = ?");
$stmt->execute([$userId]);
$contactCount = $stmt->fetch()['total'];

// Contar categorias
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM categories WHERE user_id = ?");
$stmt->execute([$userId]);
$categoryCount = $stmt->fetch()['total'];

// Processar conclusão do onboarding
if (isset($_POST['complete_onboarding'])) {
    $stmt = $pdo->prepare("UPDATE users SET onboarding_completed = 1 WHERE id = ?");
    $stmt->execute([$userId]);
    header('Location: /dashboard.php');
    exit;
}
?>

<div class="min-h-screen bg-gradient-to-br from-green-50 to-blue-50 p-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">
                <i class="fas fa-rocket mr-3 text-green-600"></i>Bem-vindo ao WATS!
            </h1>
            <p class="text-gray-600 text-lg">Vamos configurar sua conta em 3 passos simples</p>
        </div>

        <!-- Progress Bar -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="flex items-center <?php echo $i < 3 ? 'flex-1' : ''; ?>">
                    <div class="flex flex-col items-center">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center <?php echo $step >= $i ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600'; ?> font-bold text-lg">
                            <?php if ($step > $i): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                <?php echo $i; ?>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs mt-2 font-medium <?php echo $step >= $i ? 'text-green-600' : 'text-gray-500'; ?>">
                            <?php 
                            $stepNames = [1 => 'Instância', 2 => 'Contatos', 3 => 'Categorias'];
                            echo $stepNames[$i];
                            ?>
                        </span>
                    </div>
                    <?php if ($i < 3): ?>
                    <div class="flex-1 h-1 mx-4 <?php echo $step > $i ? 'bg-green-600' : 'bg-gray-300'; ?>"></div>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Content -->
        <div class="bg-white rounded-lg shadow-lg p-8">
            <?php if ($step == 1): ?>
                <!-- Passo 1: Configurar Instância -->
                <div class="text-center">
                    <i class="fas fa-whatsapp text-6xl text-green-600 mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Configure sua Instância WhatsApp</h2>
                    
                    <?php if ($hasInstance): ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                            <i class="fas fa-check-circle text-green-600 text-3xl mb-3"></i>
                            <p class="text-green-800 font-medium">Instância configurada com sucesso!</p>
                            <p class="text-green-700 text-sm mt-2">Você já pode enviar mensagens pelo WhatsApp</p>
                        </div>
                        <a href="/onboarding.php?step=2" class="inline-block bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-medium text-lg">
                            Próximo Passo <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    <?php else: ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                            <i class="fas fa-exclamation-triangle text-yellow-600 text-3xl mb-3"></i>
                            <p class="text-yellow-800 font-medium mb-3">Você precisa configurar sua instância WhatsApp</p>
                            <p class="text-yellow-700 text-sm">Conecte seu WhatsApp para começar a enviar mensagens em massa</p>
                        </div>
                        <a href="/my_instance.php" class="inline-block bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-medium text-lg">
                            Configurar Instância <i class="fas fa-cog ml-2"></i>
                        </a>
                    <?php endif; ?>
                </div>

            <?php elseif ($step == 2): ?>
                <!-- Passo 2: Importar Contatos -->
                <div class="text-center">
                    <i class="fas fa-users text-6xl text-blue-600 mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Adicione seus Contatos</h2>
                    
                    <?php if ($contactCount > 0): ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                            <i class="fas fa-check-circle text-green-600 text-3xl mb-3"></i>
                            <p class="text-green-800 font-medium">Você já tem <?php echo $contactCount; ?> contato(s) cadastrado(s)!</p>
                            <p class="text-green-700 text-sm mt-2">Ótimo! Você pode adicionar mais depois</p>
                        </div>
                        <div class="flex gap-4 justify-center">
                            <a href="/contacts.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                                <i class="fas fa-plus mr-2"></i>Adicionar Mais
                            </a>
                            <a href="/onboarding.php?step=3" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium">
                                Próximo Passo <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                            <p class="text-blue-800 font-medium mb-3">Importe seus contatos para começar</p>
                            <p class="text-blue-700 text-sm mb-4">Você pode importar via CSV ou adicionar manualmente</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl mx-auto">
                                <div class="bg-white border-2 border-blue-200 rounded-lg p-4">
                                    <i class="fas fa-file-csv text-3xl text-blue-600 mb-2"></i>
                                    <h3 class="font-bold mb-2">Importar CSV</h3>
                                    <p class="text-sm text-gray-600 mb-3">Importe vários contatos de uma vez</p>
                                </div>
                                <div class="bg-white border-2 border-blue-200 rounded-lg p-4">
                                    <i class="fas fa-user-plus text-3xl text-blue-600 mb-2"></i>
                                    <h3 class="font-bold mb-2">Adicionar Manual</h3>
                                    <p class="text-sm text-gray-600 mb-3">Adicione contatos um por um</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-4 justify-center">
                            <a href="/contacts.php" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg font-medium text-lg">
                                Ir para Contatos <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                            <a href="/onboarding.php?step=3" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-3 rounded-lg font-medium">
                                Pular <i class="fas fa-forward ml-2"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Passo 3: Criar Categorias -->
                <div class="text-center">
                    <i class="fas fa-folder-open text-6xl text-purple-600 mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Organize com Categorias</h2>
                    
                    <?php if ($categoryCount > 0): ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                            <i class="fas fa-check-circle text-green-600 text-3xl mb-3"></i>
                            <p class="text-green-800 font-medium">Você já tem <?php echo $categoryCount; ?> categoria(s) criada(s)!</p>
                            <p class="text-green-700 text-sm mt-2">Perfeito! Você está pronto para começar</p>
                        </div>
                    <?php else: ?>
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-6 mb-6">
                            <p class="text-purple-800 font-medium mb-3">Organize seus contatos em categorias</p>
                            <p class="text-purple-700 text-sm mb-4">Categorias facilitam o envio de mensagens segmentadas</p>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 max-w-3xl mx-auto mb-4">
                                <div class="bg-white border-l-4 border-red-500 rounded p-3">
                                    <div class="text-xs font-bold">DIRETORIA</div>
                                </div>
                                <div class="bg-white border-l-4 border-orange-500 rounded p-3">
                                    <div class="text-xs font-bold">GERENCIA</div>
                                </div>
                                <div class="bg-white border-l-4 border-green-500 rounded p-3">
                                    <div class="text-xs font-bold">CLIENTES</div>
                                </div>
                                <div class="bg-white border-l-4 border-blue-500 rounded p-3">
                                    <div class="text-xs font-bold">SUPERVISORES</div>
                                </div>
                                <div class="bg-white border-l-4 border-purple-500 rounded p-3">
                                    <div class="text-xs font-bold">COLABORADORES</div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">Categorias padrão já criadas para você!</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex gap-4 justify-center">
                        <a href="/categories.php" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-medium">
                            <i class="fas fa-folder-open mr-2"></i>Ver Categorias
                        </a>
                        <form method="POST" class="inline">
                            <button type="submit" name="complete_onboarding" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-medium text-lg">
                                Começar a Usar <i class="fas fa-check ml-2"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div class="mt-6 text-center">
            <?php if ($step > 1): ?>
                <a href="/onboarding.php?step=<?php echo $step - 1; ?>" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            <?php endif; ?>
            
            <span class="mx-4 text-gray-400">|</span>
            
            <form method="POST" class="inline">
                <button type="submit" name="complete_onboarding" class="text-gray-600 hover:text-gray-800">
                    Pular Tutorial <i class="fas fa-times ml-2"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer_spa.php'; ?>
