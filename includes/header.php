<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Sistema de disparo em massa de mensagens WhatsApp - Gerencie contatos e envie mensagens personalizadas">
    <meta name="keywords" content="whatsapp, disparo em massa, mensagens, automação, marketing">
    <meta name="author" content="MAC-IP TECNOLOGIA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#16a34a">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo $pageTitle ?? SITE_NAME; ?>">
    <meta property="og:description" content="Sistema de disparo em massa de mensagens WhatsApp">
    
    <title><?php echo $pageTitle ?? SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/whatsapp-automation.png">
    <link rel="apple-touch-icon" href="/assets/images/whatsapp-automation.png">
    
    <!-- Preconnect para melhor performance -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    
    <style>
        @keyframes confetti {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            z-index: 9999;
            animation: confetti 3s ease-out forwards;
        }
        .progress-bar {
            transition: width 0.1s linear;
        }
        .whatsapp-input {
            background-color: #f0f2f5;
            border-radius: 8px;
            padding: 12px;
            min-height: 100px;
        }
        .connection-alert {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
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
        
        main {
            flex: 1;
        }
        
    </style>
    
    <!-- BLOQUEADOR DEFINITIVO - Elimina TODOS os alertas de conexão -->
    <script src="/ultimate_alert_blocker.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-green-600 text-white px-4 py-2 rounded">
        Pular para o conteúdo principal
    </a>
    
    <?php if (isLoggedIn()): ?>
    <header role="banner">
        <nav class="bg-green-600 text-white shadow-lg" role="navigation" aria-label="Menu principal">
            <div class="container mx-auto px-4">
                <div class="flex items-center justify-between py-4">
                    <div class="flex items-center space-x-4">
                    <img src="/assets/images/logo.png" alt="MAC-IP Tecnologia" style="max-height: 40px; width: auto;">
                </div>
                    <nav class="flex items-center space-x-6" role="navigation" aria-label="Menu de navegação">
                        <a href="/dashboard.php" class="hover:text-green-200 transition" aria-label="Ir para Dashboard">
                            <i class="fas fa-home mr-2" aria-hidden="true"></i>Dashboard
                        </a>
                        <a href="/contacts.php" class="hover:text-green-200 transition" aria-label="Gerenciar Contatos">
                            <i class="fas fa-address-book mr-2" aria-hidden="true"></i>Contatos
                        </a>
                        <a href="/categories.php" class="hover:text-green-200 transition" aria-label="Gerenciar Categorias">
                            <i class="fas fa-tags mr-2" aria-hidden="true"></i>Categorias
                        </a>
                        <a href="/dispatch.php" class="hover:text-green-200 transition" aria-label="Disparar Mensagens">
                            <i class="fas fa-paper-plane mr-2" aria-hidden="true"></i>Disparar
                        </a>
                        <a href="/my_instance.php" class="hover:text-green-200 transition" aria-label="Minha Instância">
                            <i class="fas fa-cog mr-2" aria-hidden="true"></i>Minha Instância
                        </a>
                        <?php if (isAdmin()): ?>
                        <a href="/admin_dashboard.php" class="hover:text-green-200 transition" aria-label="Dashboard Admin">
                            <i class="fas fa-chart-line mr-2" aria-hidden="true"></i>Admin
                        </a>
                        <a href="/users.php" class="hover:text-green-200 transition" aria-label="Gerenciar Usuários">
                            <i class="fas fa-users mr-2" aria-hidden="true"></i>Usuários
                        </a>
                        <a href="/admin_system_diagnostics.php" class="hover:text-green-200 transition" aria-label="Diagnóstico do Sistema">
                            <i class="fas fa-stethoscope mr-2" aria-hidden="true"></i>Diagnóstico
                        </a>
                        <?php endif; ?>
                        <a href="/logout.php" class="hover:text-green-200 transition flex items-center" aria-label="Sair do sistema">
                            <i class="fas fa-user-circle mr-2" aria-hidden="true"></i>
                            <span class="mr-3"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                        </a>
                    </nav>
                </div>
            </div>
        </nav>
    </header>
    <?php endif; ?>
    
    <main id="main-content" class="container mx-auto px-4 py-8" role="main">
        <?php
        $success = getSuccess();
        $error = getError();
        if ($success):
        ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert" aria-live="polite">
            <i class="fas fa-check-circle mr-2" aria-hidden="true"></i><?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert" aria-live="assertive">
            <i class="fas fa-exclamation-circle mr-2" aria-hidden="true"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
