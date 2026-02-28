<?php
$page_title = 'Suporte';
require_once 'includes/header_spa.php';

// Apenas Admin pode acessar
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
?>

<div class="p-6">
<div class="max-w-4xl mx-auto">
    
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">
            <i class="fas fa-headset text-blue-600 mr-2"></i>Central de Suporte
        </h1>
        <p class="text-gray-600">Estamos aqui para ajudar! Entre em contato conosco.</p>
    </div>
    
    <!-- Canais de Suporte -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        
        <!-- WhatsApp -->
        <a href="https://wa.me/5511999999999" target="_blank" class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition block">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fab fa-whatsapp text-green-600 text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">WhatsApp</h3>
                    <p class="text-sm text-gray-600">Atendimento rápido</p>
                </div>
            </div>
            <p class="text-gray-700 mb-2">Fale conosco pelo WhatsApp</p>
            <p class="text-green-600 font-medium">(11) 99999-9999</p>
        </a>
        
        <!-- Email -->
        <a href="mailto:suporte@macip.com.br" class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition block">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-envelope text-blue-600 text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Email</h3>
                    <p class="text-sm text-gray-600">Suporte por email</p>
                </div>
            </div>
            <p class="text-gray-700 mb-2">Envie sua dúvida por email</p>
            <p class="text-blue-600 font-medium">suporte@macip.com.br</p>
        </a>
        
        <!-- Telefone -->
        <a href="tel:+551140000000" class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition block">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-phone text-purple-600 text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Telefone</h3>
                    <p class="text-sm text-gray-600">Ligue para nós</p>
                </div>
            </div>
            <p class="text-gray-700 mb-2">Atendimento telefônico</p>
            <p class="text-purple-600 font-medium">(11) 4000-0000</p>
        </a>
        
        <!-- Documentação -->
        <a href="#" class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition block">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-book text-orange-600 text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Documentação</h3>
                    <p class="text-sm text-gray-600">Guias e tutoriais</p>
                </div>
            </div>
            <p class="text-gray-700 mb-2">Acesse nossa base de conhecimento</p>
            <p class="text-orange-600 font-medium">Ver documentação</p>
        </a>
        
    </div>
    
    <!-- FAQ -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-question-circle text-yellow-600 mr-2"></i>Perguntas Frequentes
        </h2>
        
        <div class="space-y-4">
            <!-- FAQ Item 1 -->
            <div class="border-b pb-4">
                <h3 class="font-bold text-gray-800 mb-2">Como configurar minha instância WhatsApp?</h3>
                <p class="text-gray-600 text-sm">Acesse "Telefones" > "Meus números" e siga as instruções para conectar sua instância Evolution API.</p>
            </div>
            
            <!-- FAQ Item 2 -->
            <div class="border-b pb-4">
                <h3 class="font-bold text-gray-800 mb-2">Como importar contatos?</h3>
                <p class="text-gray-600 text-sm">Vá em "Contatos" > "Meus contatos", clique em "Importar CSV" e faça upload do arquivo com nome e telefone.</p>
            </div>
            
            <!-- FAQ Item 3 -->
            <div class="border-b pb-4">
                <h3 class="font-bold text-gray-800 mb-2">Como fazer um disparo em massa?</h3>
                <p class="text-gray-600 text-sm">Acesse "Disparos", selecione os contatos ou categorias, escreva sua mensagem e clique em "Iniciar Disparo".</p>
            </div>
            
            <!-- FAQ Item 4 -->
            <div class="border-b pb-4">
                <h3 class="font-bold text-gray-800 mb-2">Como criar categorias?</h3>
                <p class="text-gray-600 text-sm">Vá em "Categorias", clique em "Nova Categoria", escolha nome, cor e adicione contatos.</p>
            </div>
            
            <!-- FAQ Item 5 -->
            <div class="pb-4">
                <h3 class="font-bold text-gray-800 mb-2">Como alterar meu plano?</h3>
                <p class="text-gray-600 text-sm">Acesse "Financeiro" > "Minha Assinatura" e escolha o plano desejado.</p>
            </div>
        </div>
    </div>
    
</div>
</div>

<?php require_once 'includes/footer_spa.php'; ?>
