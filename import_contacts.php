<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Importar Contatos';
require_once 'includes/header_spa.php';

$user_id = $_SESSION['user_id'];

// Buscar estatísticas
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM contacts WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="p-6">
    <div class="max-w-4xl mx-auto">
        <!-- Cabeçalho -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-download mr-3 text-blue-600"></i>Importar Contatos
                    </h1>
                    <p class="text-gray-600 mt-2">Importe contatos da sua instância WhatsApp para o sistema</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Contatos no sistema</div>
                    <div class="text-3xl font-bold text-blue-600"><?php echo $stats['total']; ?></div>
                </div>
            </div>
        </div>

        <!-- Card de Importação -->
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow-lg p-8 mb-6">
            <div class="text-center mb-6">
                <div class="inline-block p-4 bg-blue-600 rounded-full mb-4">
                    <i class="fas fa-cloud-download-alt text-4xl text-white"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Importar da Evolution API</h2>
                <p class="text-gray-600">Busque todos os contatos salvos no seu WhatsApp e adicione ao sistema automaticamente</p>
            </div>

            <div class="bg-white rounded-lg p-6 mb-6">
                <h3 class="font-bold text-gray-800 mb-3">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>O que será importado?
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li><i class="fas fa-check text-green-600 mr-2"></i>Nome do contato</li>
                    <li><i class="fas fa-check text-green-600 mr-2"></i>Número de telefone</li>
                    <li><i class="fas fa-check text-green-600 mr-2"></i>Foto de perfil (URL)</li>
                    <li><i class="fas fa-check text-green-600 mr-2"></i>Atualização automática de contatos existentes</li>
                </ul>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-3 mt-1"></i>
                    <div class="text-sm text-yellow-800">
                        <strong>Atenção:</strong> Contatos que já existem no sistema serão atualizados apenas se houver um nome diferente. Números duplicados não serão criados novamente.
                    </div>
                </div>
            </div>

            <button onclick="importContacts()" id="import-btn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-lg transition transform hover:scale-105 shadow-lg">
                <i class="fas fa-download mr-2"></i>Iniciar Importação
            </button>
        </div>

        <!-- Resultado da Importação -->
        <div id="import-result" class="hidden bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-check-circle text-green-600 mr-2"></i>Resultado da Importação
            </h3>
            <div id="import-stats" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <!-- Preenchido via JavaScript -->
            </div>
            <div id="import-message" class="text-gray-700 mb-4">
                <!-- Preenchido via JavaScript -->
            </div>
            <div id="import-errors" class="hidden">
                <h4 class="font-bold text-red-600 mb-2">Erros:</h4>
                <ul id="error-list" class="text-sm text-red-700 space-y-1">
                    <!-- Preenchido via JavaScript -->
                </ul>
            </div>
            <button onclick="location.reload()" class="mt-4 bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition">
                <i class="fas fa-sync-alt mr-2"></i>Atualizar Página
            </button>
        </div>

        <!-- Loading -->
        <div id="import-loading" class="hidden bg-white rounded-lg shadow-lg p-8 text-center">
            <i class="fas fa-spinner fa-spin text-6xl text-blue-600 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-800 mb-2">Importando contatos...</h3>
            <p class="text-gray-600">Isso pode levar alguns segundos. Aguarde...</p>
        </div>

        <!-- Voltar -->
        <div class="text-center mt-6">
            <a href="/contacts.php" class="text-blue-600 hover:text-blue-700 font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Voltar para Contatos
            </a>
        </div>
    </div>
</div>

<script>
async function importContacts() {
    const btn = document.getElementById('import-btn');
    const loading = document.getElementById('import-loading');
    const result = document.getElementById('import-result');
    
    // Confirmar
    if (!confirm('Deseja importar todos os contatos da sua instância WhatsApp?')) {
        return;
    }
    
    // Mostrar loading
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Importando...';
    loading.classList.remove('hidden');
    result.classList.add('hidden');
    
    try {
        const response = await fetch('/api/import_contacts.php', {
            method: 'POST'
        });
        
        const data = await response.json();
        
        loading.classList.add('hidden');
        
        if (data.success) {
            // Mostrar resultado
            result.classList.remove('hidden');
            
            // Estatísticas
            document.getElementById('import-stats').innerHTML = `
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <div class="text-3xl font-bold text-blue-600">${data.total_contacts}</div>
                    <div class="text-sm text-gray-600">Total na API</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <div class="text-3xl font-bold text-green-600">${data.imported}</div>
                    <div class="text-sm text-gray-600">Importados</div>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg text-center">
                    <div class="text-3xl font-bold text-yellow-600">${data.updated}</div>
                    <div class="text-sm text-gray-600">Atualizados</div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg text-center">
                    <div class="text-3xl font-bold text-gray-600">${data.skipped}</div>
                    <div class="text-sm text-gray-600">Ignorados</div>
                </div>
            `;
            
            // Mensagem
            document.getElementById('import-message').innerHTML = `
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <strong>${data.message}</strong>
                </div>
            `;
            
            // Erros (se houver)
            if (data.errors && data.errors.length > 0) {
                document.getElementById('import-errors').classList.remove('hidden');
                document.getElementById('error-list').innerHTML = data.errors
                    .map(err => `<li><i class="fas fa-exclamation-circle mr-2"></i>${err}</li>`)
                    .join('');
            }
            
        } else {
            alert('Erro ao importar contatos: ' + data.error);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download mr-2"></i>Iniciar Importação';
        }
        
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao importar contatos: ' + error.message);
        loading.classList.add('hidden');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-download mr-2"></i>Iniciar Importação';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
