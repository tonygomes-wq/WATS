<?php

if (!isset($_SESSION)) {

    session_start();

}



// Definir caminho base se não estiver definido

if (!defined('BASE_PATH')) {

    define('BASE_PATH', dirname(__DIR__));

}



require_once BASE_PATH . '/config/database.php';

require_once BASE_PATH . '/includes/functions.php';



// Verificar se o usuário está logado

requireLogin();



// Verificar se precisa mudar senha

requirePasswordChange();



$user_id = $_SESSION['user_id'];

$user_name = $_SESSION['user_name'];

$user_email = $_SESSION['user_email'];

$is_admin = isAdmin();

$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;

$user_type = $_SESSION['user_type'] ?? 'user';

// Atendente APENAS se não for admin ou supervisor

$is_attendant = ($user_type === 'attendant') && !$is_admin && !$is_supervisor;



// Carregar permissões customizadas do atendente

$allowed_menus = [];

$attendant_has_own_instance = false;

$attendant_can_config_instance = false;



if ($is_attendant) {

    $stmt = $pdo->prepare("SELECT allowed_menus, use_own_instance, instance_config_allowed FROM supervisor_users WHERE id = ?");

    $stmt->execute([$user_id]);

    $attendant_data = $stmt->fetch();

    

    if ($attendant_data) {

        if (!empty($attendant_data['allowed_menus'])) {

            $allowed_menus = json_decode($attendant_data['allowed_menus'], true) ?? [];

        }

        

        // Verificar se atendente tem instância própria

        $attendant_has_own_instance = ($attendant_data['use_own_instance'] ?? 0) == 1;

        $attendant_can_config_instance = ($attendant_data['instance_config_allowed'] ?? 0) == 1;

    }

    

    // Menu padrão se não houver permissões customizadas

    if (empty($allowed_menus)) {

        $allowed_menus = [

            'chat' => true,

            'profile' => true

        ];

    }

    

    // Se tem instância própria e pode configurar, adicionar ao menu

    if ($attendant_has_own_instance && $attendant_can_config_instance) {

        $allowed_menus['my_instance'] = true;

    }

}



$logo_path = '/assets/images/logo.png';

$logo_full_path = BASE_PATH . '/assets/images/logo.png';

$logo_version = file_exists($logo_full_path) ? filemtime($logo_full_path) : time();

$logo_data_uri = '';

if (file_exists($logo_full_path)) {

    $logo_contents = @file_get_contents($logo_full_path);

    if ($logo_contents !== false) {

        $logo_data_uri = 'data:image/png;base64,' . base64_encode($logo_contents);

    }

}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>WATS - Atendimento Multicanal</title>

    

    <!-- Favicon -->

    <link rel="icon" type="image/png" href="/assets/images/whatsapp-automation.png">

    <link rel="apple-touch-icon" href="/assets/images/whatsapp-automation.png">

    

    <!-- TailwindCSS -->

    <script src="https://cdn.tailwindcss.com"></script>

    <script>

        tailwind.config = {

            darkMode: 'class',

            theme: {

                extend: {

                    colors: {

                        gray: {

                            50: '#f9fafb',

                            100: '#f3f4f6',

                            200: '#e5e7eb',

                            300: '#d1d5db',

                            400: '#9ca3af',

                            500: '#6b7280',

                            600: '#4b5563',

                            700: '#374151',

                            800: '#1f2937',

                            900: '#111827',

                        }

                    }

                }

            }

        }

    </script>

    

    <!-- Font Awesome -->

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    

    <!-- Chart.js -->

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    

    <!-- Fontes MACIP - DaytonaPro -->

    <link rel="stylesheet" href="/assets/css/fonts.css">

    

    <!-- Design System -->

    <link rel="stylesheet" href="/assets/css/design-tokens.css?v=<?php echo time(); ?>">

    <link rel="stylesheet" href="/assets/css/channels-refined.css?v=<?php echo time(); ?>">

    <link rel="stylesheet" href="/assets/css/channel-badges.css?v=<?php echo time(); ?>">

    

    <!-- Correções de Menu -->

    <link rel="stylesheet" href="/assets/css/menu-fixes.css?v=<?php echo time(); ?>">

    

    <!-- Refined Design System -->

    <link rel="stylesheet" href="/assets/css/refined-system.css?v=<?php echo time(); ?>">
    
    <!-- Visual Improvements 2026 -->
    <link rel="stylesheet" href="/assets/css/visual-improvements.css?v=<?php echo time(); ?>">

    

    <!-- Dark Mode Fixes - IMPORTANTE: Carregar por último -->

    <link rel="stylesheet" href="/assets/css/dark-mode-fixes.css?v=<?php echo time(); ?>">

    

    <script>

        (function() {

            const savedTheme = localStorage.getItem('watsTheme') || 'light';

            document.documentElement.setAttribute('data-theme', savedTheme);

            if (savedTheme === 'dark') {

                document.documentElement.classList.add('dark');

            } else {

                document.documentElement.classList.remove('dark');

            }

        })();

        

        // Toggle submenu - definido no head para estar disponível antes do DOM

        function toggleSubmenu(menuId) {

            const menu = document.getElementById(menuId + '-menu');

            const arrow = document.getElementById(menuId + '-arrow');

            

            if (menu && menu.classList && arrow) {

                menu.classList.toggle('open');

                if (menu.classList.contains('open')) {

                    arrow.style.transform = 'rotate(180deg)';

                } else {

                    arrow.style.transform = 'rotate(0deg)';

                }

            }

        }

        

        // Toggle user menu

        function toggleUserMenu() {

            const menu = document.getElementById('userMenu');

            if (menu && menu.classList) {

                menu.classList.toggle('hidden');

            }

        }

        

        // ==========================================

        // Sistema de Toggle de Tema - GLOBAL

        // ==========================================

        function initializeThemeToggle() {

            const themeToggleBtn = document.querySelector('[data-theme-toggle]');

            

            // Função para atualizar o botão de tema

            function updateThemeButton(theme) {

                if (!themeToggleBtn) return;

                

                const icon = themeToggleBtn.querySelector('i');

                const text = themeToggleBtn.querySelector('span');

                

                if (theme === 'dark') {

                    if (icon) icon.className = 'fas fa-sun';

                    if (text) text.textContent = 'Modo Claro';

                } else {

                    if (icon) icon.className = 'fas fa-moon';

                    if (text) text.textContent = 'Modo Escuro';

                }

            }

            

            // Inicializar estado do botão

            const initialTheme = document.documentElement.getAttribute('data-theme') || 'light';

            updateThemeButton(initialTheme);

            

            // Event listener para toggle

            if (themeToggleBtn) {

                themeToggleBtn.addEventListener('click', function() {

                    const currentTheme = document.documentElement.getAttribute('data-theme');

                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

                    

                    document.documentElement.setAttribute('data-theme', newTheme);

                    localStorage.setItem('watsTheme', newTheme);

                    

                    // Atualizar classe dark no html para compatibilidade com Tailwind

                    if (newTheme === 'dark') {

                        document.documentElement.classList.add('dark');

                    } else {

                        document.documentElement.classList.remove('dark');

                    }

                    

                    // Atualizar botão

                    updateThemeButton(newTheme);

                });

            }

        }

        

        // Executar imediatamente quando o script carregar

        if (document.readyState === 'loading') {

            document.addEventListener('DOMContentLoaded', initializeThemeToggle);

        } else {

            initializeThemeToggle();

        }

    </script>

    

    <style>

        /* Design System - Baseado em skill.md */

        /* Grid 4px, Cool foundations (slate), Precision & Density */

        :root {

            /* Foundations - Cool slate palette */

            --bg-body: #f8fafc;

            --bg-card: #ffffff;

            --bg-sidebar: #ffffff;

            --bg-sidebar-hover: #f8fafc;

            

            /* Typography - 4-level contrast hierarchy */

            --text-primary: #0f172a;      /* Foreground */

            --text-secondary: #475569;    /* Secondary */

            --text-muted: #94a3b8;        /* Muted */

            --text-faint: #cbd5e1;        /* Faint */

            

            /* Borders - Subtle, borders-only approach */

            --border: rgba(15, 23, 42, 0.08);

            --border-subtle: rgba(15, 23, 42, 0.05);

            --border-emphasis: rgba(15, 23, 42, 0.12);

            

            /* Accent - Green for growth/success */

            --accent-primary: #10B981;

            --accent-hover: #059669;

            --accent-subtle: rgba(16, 185, 129, 0.08);

            --accent-emphasis: rgba(16, 185, 129, 0.12);

            

            /* Spacing - 4px grid */

            --space-1: 4px;

            --space-2: 8px;

            --space-3: 12px;

            --space-4: 16px;

            --space-6: 24px;

            --space-8: 32px;

            

            /* Border radius - Sharp system (4px, 6px, 8px) */

            --radius-sm: 4px;

            --radius-md: 6px;

            --radius-lg: 8px;

            

            /* Transitions - 150ms micro, 200ms standard */

            --transition-fast: 150ms cubic-bezier(0.25, 1, 0.5, 1);

            --transition-base: 200ms cubic-bezier(0.25, 1, 0.5, 1);

        }

        

        :root[data-theme="dark"] {

            /* Dark mode - Cool slate palette */

            --bg-body: #0f172a;

            --bg-card: #1e293b;

            --bg-sidebar: #1e293b;

            --bg-sidebar-hover: #334155;

            

            /* Typography - Inverted hierarchy */

            --text-primary: #f1f5f9;

            --text-secondary: #cbd5e1;

            --text-muted: #64748b;

            --text-faint: #475569;

            

            /* Borders - 10-15% white opacity */

            --border: rgba(241, 245, 249, 0.10);

            --border-subtle: rgba(241, 245, 249, 0.06);

            --border-emphasis: rgba(241, 245, 249, 0.15);

            

            /* Accent - Slightly adjusted for dark */

            --accent-primary: #10B981;

            --accent-hover: #34D399;

            --accent-subtle: rgba(16, 185, 129, 0.12);

            --accent-emphasis: rgba(16, 185, 129, 0.18);

        }

        body {

            background-color: var(--bg-body);

            color: var(--text-primary);

        }

        :root[data-theme="dark"] .bg-white {

            background-color: var(--bg-card) !important;

            color: var(--text-primary) !important;

        }

        :root[data-theme="dark"] .bg-gray-50 {

            background-color: var(--bg-body) !important;

            color: var(--text-primary) !important;

        }

        :root[data-theme="dark"] .text-gray-800 {

            color: var(--text-primary) !important;

        }

        :root[data-theme="dark"] .text-gray-600,

        :root[data-theme="dark"] .text-gray-500,

        :root[data-theme="dark"] .text-gray-400 {

            color: var(--text-secondary) !important;

        }

        :root[data-theme="dark"] .sidebar-item {

            color: var(--text-primary);

        }

        :root[data-theme="dark"] .w-64.bg-white,

        :root[data-theme="dark"] .sidebar,

        :root[data-theme="dark"] .sidebar-item.active {

            background-color: var(--bg-sidebar) !important;

        }

        :root[data-theme="dark"] .border-gray-200 {

            border-color: var(--border-color) !important;

        }

        .theme-toggle {

            background: linear-gradient(135deg, #10B981, #059669);

            color: #ffffff !important;

            border: 1px solid rgba(16, 185, 129, 0.4);

            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.25);

            transition: transform 0.2s ease, box-shadow 0.2s ease;

        }

        .theme-toggle i,

        .theme-toggle span {

            color: inherit !important;

        }

        .theme-toggle:hover {

            transform: translateY(-1px);

            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.35);

        }

        :root[data-theme="dark"] .theme-toggle {

            background: linear-gradient(135deg, #047857, #10B981);

            border-color: rgba(16, 185, 129, 0.65);

        }

        .user-menu-dropdown {

            background-color: #111827;

            border: 1px solid rgba(255, 255, 255, 0.1);

            color: #f9fafb;

        }

        .user-menu-item {

            display: flex;

            align-items: center;

            gap: 0.5rem;

            padding: 0.65rem 1.25rem;

            font-size: 0.9rem;

            transition: background-color 0.2s ease;

        }

        .user-menu-item i {

            min-width: 1rem;

            text-align: center;

        }

        .user-menu-item:hover {

            background-color: rgba(255, 255, 255, 0.08);

        }

        .user-menu-item--danger {

            color: #f87171;

        }

        .user-menu-dropdown hr {

            border-color: rgba(255, 255, 255, 0.15);

        }

        :root[data-theme="light"] .user-menu-dropdown {

            background-color: #ffffff;

            border-color: #e5e7eb;

            color: #1f2937;

        }

        :root[data-theme="light"] .user-menu-item {

            color: #1f2937;

        }

        :root[data-theme="light"] .user-menu-item:hover {

            background-color: #f3f4f6;

        }

        :root[data-theme="light"] .user-menu-item--danger {

            color: #dc2626;

        }

        :root[data-theme="light"] .user-menu-dropdown hr {

            border-color: #e5e7eb;

        }

        /* Sidebar Items - Precision & Density approach */

        .sidebar-item {

            position: relative;

            padding: var(--space-2) var(--space-3);

            border-radius: var(--radius-md);

            border-left: 2px solid transparent;

            transition: all var(--transition-fast);

            font-size: 13px;

            font-weight: 500;

            letter-spacing: -0.01em;

            color: var(--text-secondary);

            cursor: pointer;

        }

        

        .sidebar-item i {

            width: 16px;

            height: 16px;

            font-size: 14px;

            color: var(--text-muted);

            transition: color var(--transition-fast);

        }

        

        /* Hover state - Subtle lift */

        .sidebar-item:hover {

            background-color: var(--bg-sidebar-hover) !important;

            border-left-color: var(--accent-primary);

            color: var(--text-primary) !important;

        }

        

        .sidebar-item:hover i {

            color: var(--accent-primary) !important;

        }

        

        /* Active state - Emphasis */

        .sidebar-item.active {

            background-color: var(--accent-subtle) !important;

            border-left-color: var(--accent-primary);

            color: var(--text-primary) !important;

            font-weight: 600;

        }

        

        .sidebar-item.active i {

            color: var(--accent-primary) !important;

        }

        

        /* Secondary text in sidebar items */

        .sidebar-item .text-sm {

            font-size: 11px;

            font-weight: 400;

            letter-spacing: 0;

            color: var(--text-muted);

            margin-top: var(--space-1);

        }

        

        /* Submenu items - Nested hierarchy */

        .sidebar-submenu a {

            font-size: 12px;

            font-weight: 400;

            color: var(--text-secondary);

            padding: var(--space-2) var(--space-3);

            border-radius: var(--radius-sm);

            transition: all var(--transition-fast);

            display: block;

        }

        

        .sidebar-submenu a:hover {

            background-color: var(--accent-subtle);

            color: var(--accent-primary) !important;

        }

        /* Submenu - Smooth expansion */

        .sidebar-submenu {

            max-height: 0;

            overflow: hidden;

            transition: max-height var(--transition-base);

            margin-left: var(--space-6);

            padding-left: var(--space-2);

            border-left: 1px solid var(--border-subtle);

        }

        

        .sidebar-submenu.open {

            max-height: 500px;

            margin-top: var(--space-2);

        }

        

        /* Chevron rotation */

        .sidebar-item i[id$="-arrow"] {

            transition: transform var(--transition-fast);

            font-size: 12px;

            color: var(--text-muted);

        }

        .main-content {

            height: calc(100vh - 80px);

            overflow-y: auto;

        }

        

        /* Quando o chat está ativo, usar altura total sem scroll */

        .main-content:has(.chat-main-container),

        .main-content:has(.chat-page-wrapper) {

            height: calc(100vh - 0px) !important;

            overflow: hidden !important;

            padding: 0 !important;

        }

        

        /* Sidebar container - Borders-only approach */

        .sidebar,

        .w-64.bg-white {

            background-color: var(--bg-sidebar) !important;

            border-right: 0.5px solid var(--border) !important;

        }

        

        /* Logo area - Refined spacing */

        .sidebar .p-4.border-b {

            padding: var(--space-4) !important;

            border-bottom: 0.5px solid var(--border) !important;

        }

        

        /* Sidebar navigation - Precision scrolling */

        .sidebar-nav {

            height: calc(100vh - 120px) !important;

            max-height: calc(100vh - 120px) !important;

            overflow-y: auto !important;

            overflow-x: hidden !important;

            padding: var(--space-3) var(--space-2) !important;

            scrollbar-width: thin !important;

            -webkit-overflow-scrolling: touch !important;

            flex-shrink: 0 !important;

        }

        

        /* Custom scrollbar - Minimal & precise */

        .sidebar-nav::-webkit-scrollbar {

            width: 4px;

        }

        

        .sidebar-nav::-webkit-scrollbar-track {

            background: transparent;

        }

        

        .sidebar-nav::-webkit-scrollbar-thumb {

            background: var(--border-emphasis);

            border-radius: 2px;

        }

        

        .sidebar-nav::-webkit-scrollbar-thumb:hover {

            background: var(--text-muted);

        }

        

        /* Menu section separator - Subtle visual break */

        .sidebar-nav > nav > .space-y-1 {

            gap: var(--space-1);

        }

        

        /* Add subtle separator between menu sections */

        .sidebar-item + .sidebar-item {

            margin-top: var(--space-1);

        }

        

        :root[data-theme="dark"] .sidebar-nav::-webkit-scrollbar-thumb {

            background: #374151;

        }

        

        :root[data-theme="dark"] .sidebar-nav::-webkit-scrollbar-thumb:hover {

            background: #4b5563;

        }

        

        /* Garantir que o container da sidebar também tenha altura correta */

        .sidebar-container {

            height: 100vh !important;

            display: flex !important;

            flex-direction: column !important;

        }

        

        /* Container do nav precisa ter overflow hidden para forçar scroll no nav */

        .sidebar-container > .flex-1 {

            overflow: hidden !important;

            display: block !important;

            height: calc(100vh - 100px) !important;

            max-height: calc(100vh - 100px) !important;

        }

        

        /* Correção de visibilidade para campos select e option em modo escuro */

        :root[data-theme="dark"] select {

            background-color: #1f2937 !important;

            color: #f3f4f6 !important;

            border-color: #374151 !important;

        }

        

        :root[data-theme="dark"] select option {

            background-color: #1f2937 !important;

            color: #f3f4f6 !important;

        }

        

        :root[data-theme="dark"] input[type="text"],

        :root[data-theme="dark"] input[type="email"],

        :root[data-theme="dark"] input[type="password"],

        :root[data-theme="dark"] input[type="number"],

        :root[data-theme="dark"] input[type="tel"],

        :root[data-theme="dark"] textarea {

            background-color: #1f2937 !important;

            color: #f3f4f6 !important;

            border-color: #374151 !important;

        }

        

        :root[data-theme="dark"] input::placeholder,

        :root[data-theme="dark"] textarea::placeholder {

            color: #9ca3af !important;

        }

        

        /* Garantir que o texto seja sempre visível em selects */

        select {

            color: #1f2937;

        }

        

        select option {

            background-color: #ffffff;

            color: #1f2937;

        }

        

        /* Correção de visibilidade para labels e textos em modais no modo escuro */

        :root[data-theme="dark"] label {

            color: #f3f4f6 !important;

        }

        

        :root[data-theme="dark"] .text-sm {

            color: #d1d5db !important;

        }

        

        :root[data-theme="dark"] h3,

        :root[data-theme="dark"] h4,

        :root[data-theme="dark"] .font-medium,

        :root[data-theme="dark"] .font-semibold {

            color: #f3f4f6 !important;

        }

        

        /* Modais em modo escuro */

        :root[data-theme="dark"] .fixed .bg-white {

            background-color: #1f2937 !important;

        }

        

        :root[data-theme="dark"] .fixed .bg-white label,

        :root[data-theme="dark"] .fixed .bg-white h3,

        :root[data-theme="dark"] .fixed .bg-white h4,

        :root[data-theme="dark"] .fixed .bg-white p,

        :root[data-theme="dark"] .fixed .bg-white span {

            color: #f3f4f6 !important;

        }

        

        :root[data-theme="dark"] .fixed .bg-white .text-gray-700,

        :root[data-theme="dark"] .fixed .bg-white .text-gray-600 {

            color: #d1d5db !important;

        }

        

        :root[data-theme="dark"] .fixed .bg-white .text-gray-500,

        :root[data-theme="dark"] .fixed .bg-white .text-gray-400 {

            color: #9ca3af !important;

        }

        

        /* Input time em modo escuro */

        :root[data-theme="dark"] input[type="time"] {

            background-color: #1f2937 !important;

            color: #f3f4f6 !important;

            border-color: #374151 !important;

        }

        

        /* Checkbox labels em modo escuro */

        :root[data-theme="dark"] .flex.items-center label {

            color: #f3f4f6 !important;

        }

        

        /* ===== CORREÇÕES GLOBAIS PARA MODO ESCURO ===== */

        

        /* Inputs, textareas e selects */

        :root[data-theme="dark"] input:not([type="checkbox"]):not([type="radio"]),

        :root[data-theme="dark"] textarea,

        :root[data-theme="dark"] select {

            background-color: var(--bg-card) !important;

            color: var(--text-primary) !important;

            border-color: var(--border) !important;

        }

        

        :root[data-theme="dark"] input::placeholder,

        :root[data-theme="dark"] textarea::placeholder {

            color: var(--text-muted) !important;

        }

        

        :root[data-theme="dark"] input:focus,

        :root[data-theme="dark"] textarea:focus,

        :root[data-theme="dark"] select:focus {

            border-color: var(--accent-primary) !important;

            background-color: var(--bg-card) !important;

        }

        

        /* Datetime inputs específicos */

        :root[data-theme="dark"] input[type="datetime-local"],

        :root[data-theme="dark"] input[type="date"],

        :root[data-theme="dark"] input[type="time"] {

            background-color: var(--bg-card) !important;

            color: var(--text-primary) !important;

            border-color: var(--border) !important;

            color-scheme: dark;

        }

        

        /* Buttons e controles */

        :root[data-theme="dark"] button:not(.theme-toggle):not([class*="bg-"]) {

            background-color: var(--bg-card) !important;

            color: var(--text-primary) !important;

            border-color: var(--border) !important;

        }

        

        /* Cards e containers */

        :root[data-theme="dark"] .bg-white:not(.theme-toggle) {

            background-color: var(--bg-card) !important;

        }

        

        :root[data-theme="dark"] .bg-gray-50 {

            background-color: var(--bg-body) !important;

        }

        

        :root[data-theme="dark"] .bg-gray-100 {

            background-color: var(--bg-sidebar-hover) !important;

        }

        

        /* Borders */

        :root[data-theme="dark"] .border-gray-200,

        :root[data-theme="dark"] .border-gray-300 {

            border-color: var(--border) !important;

        }

        

        /* Text colors */

        :root[data-theme="dark"] .text-gray-900,

        :root[data-theme="dark"] .text-gray-800,

        :root[data-theme="dark"] .text-gray-700 {

            color: var(--text-primary) !important;

        }

        

        :root[data-theme="dark"] .text-gray-600,

        :root[data-theme="dark"] .text-gray-500 {

            color: var(--text-secondary) !important;

        }

        

        :root[data-theme="dark"] .text-gray-400 {

            color: var(--text-muted) !important;

        }

        

        /* Tabelas */

        :root[data-theme="dark"] table,

        :root[data-theme="dark"] thead,

        :root[data-theme="dark"] tbody,

        :root[data-theme="dark"] tr {

            background-color: transparent !important;

        }

        

        :root[data-theme="dark"] th {

            background-color: var(--bg-sidebar-hover) !important;

            color: var(--text-primary) !important;

            border-color: var(--border) !important;

        }

        

        :root[data-theme="dark"] td {

            color: var(--text-secondary) !important;

            border-color: var(--border) !important;

        }

        

        /* Badges e tags */

        :root[data-theme="dark"] .bg-blue-100 { background-color: rgba(59, 130, 246, 0.15) !important; }

        :root[data-theme="dark"] .bg-green-100 { background-color: rgba(16, 185, 129, 0.15) !important; }

        :root[data-theme="dark"] .bg-yellow-100 { background-color: rgba(234, 179, 8, 0.15) !important; }

        :root[data-theme="dark"] .bg-red-100 { background-color: rgba(239, 68, 68, 0.15) !important; }

        :root[data-theme="dark"] .bg-purple-100 { background-color: rgba(139, 92, 246, 0.15) !important; }

        

        :root[data-theme="dark"] .text-blue-600,

        :root[data-theme="dark"] .text-blue-700 { color: #60a5fa !important; }

        

        :root[data-theme="dark"] .text-green-600,

        :root[data-theme="dark"] .text-green-700 { color: #34d399 !important; }

        

        :root[data-theme="dark"] .text-yellow-600,

        :root[data-theme="dark"] .text-yellow-700 { color: #fbbf24 !important; }

        

        :root[data-theme="dark"] .text-red-600,

        :root[data-theme="dark"] .text-red-700 { color: #f87171 !important; }

        

        :root[data-theme="dark"] .text-purple-600,

        :root[data-theme="dark"] .text-purple-700 { color: #a78bfa !important; }

        

        /* Botões em modais */

        :root[data-theme="dark"] .fixed button.text-gray-500 {

            color: #9ca3af !important;

        }

        

        :root[data-theme="dark"] .fixed button.text-gray-500:hover {

            color: #f3f4f6 !important;

        }

        

        /* Header top bar - Refined */

        .main-content > .bg-white.border-b {

            background-color: var(--bg-card) !important;

            border-bottom: 0.5px solid var(--border) !important;

            padding: var(--space-4) var(--space-6) !important;

        }

        

        /* Page title - Typography hierarchy */

        #pageTitle {

            font-size: 18px;

            font-weight: 600;

            letter-spacing: -0.02em;

            color: var(--text-primary);

        }

        

        /* Dropdown de notificações - Refined */

        #notificationDropdown {

            background-color: var(--bg-card);

            border: 0.5px solid var(--border);

            border-radius: var(--radius-lg);

            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);

        }

        

        :root[data-theme="dark"] #notificationDropdown {

            background-color: var(--bg-card) !important;

            border-color: var(--border) !important;

            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);

        }

        

        :root[data-theme="dark"] #notificationDropdown h3 {

            color: var(--text-primary) !important;

        }

        

        :root[data-theme="dark"] #notificationDropdown .border-b,

        :root[data-theme="dark"] #notificationDropdown .border-t {

            border-color: var(--border) !important;

        }

        

        :root[data-theme="dark"] #notificationDropdown .hover\:bg-gray-50:hover {

            background-color: var(--bg-sidebar-hover) !important;

        }

        

        /* Badge/Counter styling - Monospace for data */

        .sidebar-badge {

            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;

            font-size: 11px;

            font-weight: 600;

            padding: 2px 6px;

            border-radius: var(--radius-sm);

            background-color: var(--accent-subtle);

            color: var(--accent-primary);

            font-variant-numeric: tabular-nums;

        }

        

        /* Icon containers - Isolated controls */

        .sidebar-item-icon {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            width: 20px;

            height: 20px;

            border-radius: var(--radius-sm);

            background-color: transparent;

            transition: background-color var(--transition-fast);

        }

        

        .sidebar-item:hover .sidebar-item-icon {

            background-color: var(--accent-subtle);

        }

        

        .sidebar-item.active .sidebar-item-icon {

            background-color: var(--accent-emphasis);

        }

    </style>

    <!-- Visual Enhancements Script -->
    <script src="/assets/js/visual-enhancements.js?v=<?php echo time(); ?>"></script>
</head>

<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-200">

    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar -->

        <div class="w-64 bg-white border-r border-gray-200 flex flex-col sidebar-container">

            <!-- Logo -->

            <div class="p-4 border-b border-gray-200">

                <div class="flex items-center justify-center">

                    <img src="<?php echo $logo_data_uri ?: $logo_path . '?v=' . $logo_version; ?>" alt="MAC-IP TECNOLOGIA" style="width: 220px; height: auto;">

                </div>

            </div>

            

            <!-- Navegação -->

            <div class="flex-1" style="overflow: hidden; height: calc(100vh - 100px);">

                <nav class="p-4 space-y-2 sidebar-nav" style="height: calc(100vh - 120px); overflow-y: scroll; overflow-x: hidden; padding-bottom: 100px;">

                

                <?php if ($is_attendant): ?>

                <!-- MENU CUSTOMIZADO PARA ATENDENTES -->

                

                <!-- Chat/Atendimento (sempre visível) -->

                <?php if (isset($allowed_menus['chat']) && $allowed_menus['chat']): ?>

                <a href="/chat.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-comments text-gray-600"></i>

                        <span class="font-medium">Atendimento</span>

                    </div>

                    <div class="ml-6 mt-2 text-sm text-green-600">

                        Minhas conversas

                    </div>

                </a>

                <?php endif; ?>

                

                <!-- Dashboard (se permitido) -->

                <?php if (isset($allowed_menus['dashboard']) && $allowed_menus['dashboard']): ?>

                <a href="/dashboard.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-chart-bar text-gray-600"></i>

                        <span class="font-medium">Dashboard</span>

                    </div>

                </a>

                <?php endif; ?>

                

                <!-- Disparo (se permitido) -->

                <?php if (isset($allowed_menus['dispatch']) && $allowed_menus['dispatch']): ?>

                <a href="/dispatch.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-paper-plane text-gray-600"></i>

                        <span class="font-medium">Disparo</span>

                    </div>

                </a>

                <?php endif; ?>

                

                <!-- Mensagens (se permitido) -->

                <?php if (isset($allowed_menus['messages']) && $allowed_menus['messages']): ?>

                <a href="/messages.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-envelope text-gray-600"></i>

                        <span class="font-medium">Mensagens</span>

                    </div>

                </a>

                <?php endif; ?>

                

                <!-- Kanban (se permitido) -->

                <?php if (isset($allowed_menus['kanban']) && $allowed_menus['kanban']): ?>

                <a href="/kanban.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-columns text-purple-600"></i>

                        <span class="font-medium">Kanban</span>

                    </div>

                    <div class="ml-6 mt-2 text-sm text-purple-600">

                        Pipeline de vendas

                    </div>

                </a>

                <?php endif; ?>

                

                <!-- Meu Perfil (sempre visível) -->

                <?php if (isset($allowed_menus['profile']) && $allowed_menus['profile']): ?>

                <a href="/profile.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-user-circle text-gray-600"></i>

                        <span class="sidebar-text font-medium">Meu Perfil</span>

                    </div>

                </a>

                <?php endif; ?>

                

                <!-- Minha Instância WhatsApp (apenas para atendentes com instância própria) -->

                <?php if (isset($allowed_menus['my_instance']) && $allowed_menus['my_instance']): ?>

                <a href="/attendant_instance.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fab fa-whatsapp text-green-600"></i>

                        <span class="font-medium">Minha Instância</span>

                    </div>

                </a>

                <?php endif; ?>

                

                <?php else: ?>

                <!-- MENU COMPLETO PARA SUPERVISORES E ADMINS -->

                

                <!-- Dashboard -->

                <a href="/dashboard.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-chart-bar text-gray-600"></i>

                        <span class="font-medium">Dashboard</span>

                    </div>

                </a>

                

                <!-- Disparo -->

                <div class="sidebar-item p-3 rounded-lg cursor-pointer" onclick="toggleSubmenu('dispatch')">

                    <div class="flex items-center justify-between">

                        <div class="flex items-center space-x-3">

                            <i class="fas fa-paper-plane text-gray-600"></i>

                            <span class="font-medium">Disparo</span>

                        </div>

                        <i class="fas fa-chevron-down text-gray-400 transition-transform" id="dispatch-arrow"></i>

                    </div>

                    <div class="sidebar-submenu ml-6 mt-2 space-y-1" id="dispatch-menu">

                        <a href="/dispatch.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">Enviar Mensagens</a>

                        <a href="/message_templates.php" data-spa-page="message_templates" class="block text-sm text-gray-600 hover:text-green-600 py-1">Modelos de mensagem</a>

                        <a href="/dispatch_reports.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">Relatórios</a>

                        <a href="/dispatch_settings.php" class="block text-sm text-blue-600 hover:text-blue-700 py-1 font-medium">⚙️ Configurações Avançadas</a>

                    </div>

                </div>

                

                <!-- Automação (Apenas Admin) -->

                <?php if ($is_admin): ?>

                <div class="sidebar-item p-3 rounded-lg cursor-pointer" onclick="toggleSubmenu('automation')">

                    <div class="flex items-center justify-between">

                        <div class="flex items-center space-x-3">

                            <i class="fas fa-robot text-gray-600"></i>

                            <span class="font-medium">Automação</span>

                        </div>

                        <i class="fas fa-chevron-down text-gray-400 transition-transform" id="automation-arrow"></i>

                    </div>

                    <div class="sidebar-submenu ml-6 mt-2 space-y-1" id="automation-menu">

                        <a href="/campaigns.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">Campanhas</a>

                        <a href="#" onclick="alert('Funcionalidade em desenvolvimento'); return false;" class="block text-sm text-gray-600 hover:text-green-600 py-1">Gatilhos</a>

                        <a href="/flows.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">Fluxos</a>

                    </div>

                </div>

                <?php endif; ?>

                

                <!-- Telefones (unificado com Contatos) -->

                <div class="sidebar-item p-3 rounded-lg cursor-pointer" onclick="toggleSubmenu('phones')">

                    <div class="flex items-center justify-between">

                        <div class="flex items-center space-x-3">

                            <i class="fas fa-phone text-gray-600"></i>

                            <span class="sidebar-text font-medium">Telefones</span>

                        </div>

                        <i class="fas fa-chevron-down chevron-icon text-gray-400 transition-transform" id="phones-arrow"></i>

                    </div>

                    <div class="sidebar-submenu ml-6 mt-2 space-y-1" id="phones-menu">

                        <!-- Seção: Meus Números -->

                        <a href="/my_instance.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">

                            <i class="fas fa-mobile-alt mr-2"></i>Meus números

                        </a>

                        <?php if ($is_admin): ?>

                        <a href="/diagnostico_midia_supervisor.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">

                            <i class="fas fa-stethoscope mr-2"></i>Diagnóstico

                        </a>

                        <?php endif; ?>

                        

                        <!-- Divisor -->

                        <div style="border-top: 1px solid #e5e7eb; margin: 8px 0;"></div>

                        

                        <!-- Seção: Contatos -->

                        <a href="/contacts.php" data-spa-page="contacts" class="block text-sm text-gray-600 hover:text-green-600 py-1">

                            <i class="fas fa-users mr-2"></i>Meus contatos

                        </a>

                        <a href="/categories.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">

                            <i class="fas fa-folder mr-2"></i>Categorias

                        </a>

                        <a href="/scheduled_dispatches.php" data-spa-page="scheduled_dispatches" class="block text-sm text-gray-600 hover:text-green-600 py-1">

                            <i class="fas fa-clock mr-2"></i>Agendamentos

                        </a>

                        <?php if ($is_admin): ?>

                        <a href="#" onclick="alert('Funcionalidade em desenvolvimento'); return false;" class="block text-sm text-gray-600 hover:text-green-600 py-1">

                            <i class="fas fa-tags mr-2"></i>Tags

                        </a>

                        <a href="#" onclick="alert('Funcionalidade em desenvolvimento'); return false;" class="block text-sm text-gray-600 hover:text-green-600 py-1">

                            <i class="fas fa-list mr-2"></i>Campos Personalizados

                        </a>

                        <?php endif; ?>

                    </div>

                </div>

                

                <!-- Kanban -->

                <a href="/kanban.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-purple-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-columns text-purple-600"></i>

                        <span class="font-medium">Kanban</span>

                    </div>

                    <div class="ml-6 mt-2 text-sm text-purple-600">

                        Pipeline de vendas

                    </div>

                </a>

                

                <!-- Atendimentos (Chat unificado) -->

                <a href="/chat.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-headset text-green-600"></i>

                        <span class="font-medium">Atendimentos</span>

                    </div>

                    <div class="ml-6 mt-2 text-sm text-green-600">

                        Conversas e suporte

                    </div>

                </a>

                

                <?php if ($is_admin): ?>

                <!-- Email (Apenas Admin) -->

                <a href="/email_chat.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-red-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-envelope text-red-600"></i>

                        <span class="font-medium">Email</span>

                    </div>

                    <div class="ml-6 mt-2 text-sm text-red-600">

                        Caixa de entrada

                    </div>

                </a>

                <?php endif; ?>

                

                <?php

                // Verificar se usuário é supervisor para mostrar menus de gestão

                // Usar variável $is_supervisor já definida no início do arquivo

                if ($is_admin || $is_supervisor):

                ?>

                <!-- Gerenciar Atendentes (Apenas Supervisor/Admin) -->

                <a href="/supervisor_users.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-blue-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-users-cog text-blue-600"></i>

                        <span class="font-medium">Gerenciar Atendentes</span>

                    </div>

                </a>

                

                <!-- Canais de Comunicação (Apenas Supervisor/Admin) -->

                <a href="/channels.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-purple-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-broadcast-tower text-purple-600"></i>

                        <span class="font-medium">Canais</span>

                    </div>

                    <div class="ml-6 mt-1 text-xs text-purple-600">

                        Telegram, Facebook, etc

                    </div>

                </a>

                

                <?php if ($is_admin): ?>

                <!-- Monitoramento Meta API (Apenas Admin) -->

                <a href="/meta_monitoring.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-blue-50">

                    <div class="flex items-center space-x-3">

                        <i class="fab fa-meta text-blue-600"></i>

                        <span class="font-medium">Monitoramento Meta</span>

                    </div>

                    <div class="ml-6 mt-1 text-xs text-blue-600">

                        Dashboard completo com analytics

                    </div>

                </a>

                <?php endif; ?>

                

                <!-- Setores (Apenas Supervisor) -->

                <a href="/departments.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-building text-gray-600"></i>

                        <span class="font-medium">Setores</span>

                    </div>

                </a>

                

                

                <!-- Respostas Rápidas (Apenas Supervisor) -->

                <a href="/quick_replies.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-bolt text-gray-600"></i>

                        <span class="font-medium">Respostas Rápidas</span>

                    </div>

                </a>

                

                <!-- Distribuição Automática (Apenas Supervisor) -->

                <a href="/distribution_settings.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-random text-gray-600"></i>

                        <span class="font-medium">Distribuição Automática</span>

                    </div>

                </a>

                

                <?php if ($is_admin): ?>

                <!-- Notificações Email (Apenas Admin) -->

                <a href="/email_settings.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-envelope text-gray-600"></i>

                        <span class="font-medium">Notificações Email</span>

                    </div>

                </a>

                <?php endif; ?>

                <?php endif; ?>

                

                <!-- Financeiro -->

                <div class="sidebar-item p-3 rounded-lg cursor-pointer" onclick="toggleSubmenu('financial')">

                    <div class="flex items-center justify-between">

                        <div class="flex items-center space-x-3">

                            <i class="fas fa-dollar-sign text-gray-600"></i>

                            <span class="font-medium">Financeiro</span>

                        </div>

                        <i class="fas fa-chevron-down text-gray-400 transition-transform" id="financial-arrow"></i>

                    </div>

                    <div class="sidebar-submenu ml-6 mt-2 space-y-1" id="financial-menu">

                        <?php if ($user_email === 'suporte@macip.com.br'): ?>

                        <a href="/financial.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">Dashboard Financeiro</a>

                        <?php endif; ?>

                        <a href="/subscription.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">Minha Assinatura</a>

                    </div>

                </div>

                

                <?php if ($is_admin): ?>

                <!-- Usuários (Apenas Admin) -->

                <a href="/users.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-users-cog text-gray-600"></i>

                        <span class="font-medium">Usuários</span>

                    </div>

                </a>

                

                <!-- Diagnóstico do Sistema (Apenas Admin) -->

                <a href="/admin_system_diagnostics.php" class="sidebar-item p-3 rounded-lg cursor-pointer block hover:bg-green-50">

                    <div class="flex items-center space-x-3">

                        <i class="fas fa-stethoscope text-blue-600"></i>

                        <span class="font-medium">Diagnóstico</span>

                    </div>

                </a>

                <?php endif; ?>

                

                <?php if ($is_admin || (isset($is_supervisor) && $is_supervisor)): ?>

                <!-- Relatórios & Análises (Admin e Supervisor) -->

                <div class="sidebar-item p-3 rounded-lg cursor-pointer" onclick="toggleSubmenu('reports')">

                    <div class="flex items-center justify-between">

                        <div class="flex items-center space-x-3">

                            <i class="fas fa-chart-line text-gray-600"></i>

                            <span class="font-medium">Relatórios & Análises</span>

                        </div>

                        <i class="fas fa-chevron-down text-gray-400 transition-transform" id="reports-arrow"></i>

                    </div>

                    <div class="sidebar-submenu ml-6 mt-2 space-y-1" id="reports-menu">

                        <a href="/supervisor_reports.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">Relatórios de Atendimento</a>

                        <a href="/dispatch_reports.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">Relatórios de Disparo</a>

                        <a href="/supervisor_conversation_summaries.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">Resumo de Conversas</a>

                        <a href="/dashboard.php?page=backups" onclick="return loadPage('backups', event);" class="block text-sm text-gray-600 hover:text-green-600 py-1">Backup de Conversas</a>

                    </div>

                </div>

                <?php endif; ?>

                

                <?php if ($is_admin): ?>

                <!-- Configurações (Apenas Admin) -->

                <div class="sidebar-item p-3 rounded-lg cursor-pointer" onclick="toggleSubmenu('settings')">

                    <div class="flex items-center justify-between">

                        <div class="flex items-center space-x-3">

                            <i class="fas fa-cog text-gray-600"></i>

                            <span class="font-medium">Configurações</span>

                        </div>

                        <i class="fas fa-chevron-down text-gray-400 transition-transform" id="settings-arrow"></i>

                    </div>

                    <div class="sidebar-submenu ml-6 mt-2 space-y-1" id="settings-menu">

                        <a href="/data_retention_admin.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">

                            <i class="fas fa-database"></i> Dados e Storage

                        </a>

                        <a href="/view_user_storage.php" class="block text-sm text-gray-600 hover:text-green-600 py-1">

                            <i class="fas fa-users"></i> Storage por Usuário

                        </a>

                        <a href="/settings_apis.php" onclick="return loadPage('settings_apis', event);" class="block text-sm text-gray-600 hover:text-green-600 py-1">APIs</a>

                        <a href="/settings_colors.php" onclick="return loadPage('settings_colors', event);" class="block text-sm text-gray-600 hover:text-green-600 py-1">Cores</a>

                        <a href="/settings_support.php" onclick="return loadPage('settings_support', event);" class="block text-sm text-gray-600 hover:text-green-600 py-1">Suporte</a>

                    </div>

                </div>

                <?php endif; ?>

                

                <?php endif; // Fim do if is_attendant ?>

                </nav>

            </div>

        </div>

        

        <!-- Área Principal -->

        <div class="flex-1 flex flex-col main-content">

            <!-- Header Superior -->

            <div class="bg-white border-b border-gray-200 p-6">

                <div class="flex items-center justify-between">

                    <div>

                        <h1 class="text-2xl font-bold text-gray-800" id="pageTitle"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>

                    </div>

                    

                    <!-- Menu do Usuário -->

                    <div class="flex items-center space-x-4">

                        <!-- Botão Notificações -->

                        <div class="relative" id="notificationBell">

                            <button onclick="toggleNotifications()" class="relative p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-full transition">

                                <i class="fas fa-bell text-xl"></i>

                                <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>

                            </button>

                            

                            <!-- Dropdown de Notificações -->

                            <div id="notificationDropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 hidden">

                                <div class="p-3 border-b border-gray-200 flex justify-between items-center">

                                    <h3 class="font-semibold text-gray-800">Notificações</h3>

                                    <button onclick="markAllAsRead()" class="text-xs text-blue-600 hover:text-blue-800">Marcar todas como lidas</button>

                                </div>

                                <div id="notificationList" class="max-h-80 overflow-y-auto">

                                    <div class="p-4 text-center text-gray-500 text-sm">

                                        <i class="fas fa-bell-slash text-2xl mb-2"></i>

                                        <p>Nenhuma notificação</p>

                                    </div>

                                </div>

                                <div class="p-2 border-t border-gray-200 text-center">

                                    <a href="/dispatch_reports.php" class="text-sm text-blue-600 hover:text-blue-800">Ver todas as notificações</a>

                                </div>

                            </div>

                        </div>

                        

                        <!-- Botão Alternar Tema -->

                        <button data-theme-toggle class="theme-toggle flex items-center space-x-2 px-4 py-2 rounded-lg transition">

                            <i class="fas fa-moon"></i>

                            <span class="font-medium">Modo Escuro</span>

                        </button>

                        

                        <?php if ($is_admin): ?>

                        <!-- Botão Suporte (Apenas Admin) -->

                        <a href="/support.php" class="flex items-center space-x-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">

                            <i class="fas fa-headset"></i>

                            <span class="font-medium">Suporte</span>

                        </a>

                        <?php endif; ?>

                        <div class="flex items-center space-x-3 bg-gray-900/70 px-4 py-2 rounded-full shadow text-white">

                            <div class="text-right leading-tight">

                                <p class="text-sm font-semibold"><?php echo htmlspecialchars($user_name); ?></p>

                                <p class="text-xs opacity-90"><?php 

                                    if ($is_admin) {

                                        echo 'Administrador';

                                    } elseif ($is_supervisor) {

                                        echo 'Supervisor';

                                    } elseif ($is_attendant) {

                                        echo 'Atendente';

                                    } else {

                                        echo 'Usuário';

                                    }

                                ?></p>

                                <p class="text-xs opacity-80"><?php echo htmlspecialchars($user_email); ?></p>

                            </div>

                            <?php

                            // Buscar foto do perfil

                            $profile_photo = '';

                            try {

                                $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");

                                $stmt->execute([$user_id]);

                                $user_data = $stmt->fetch();

                                if ($user_data && !empty($user_data['profile_photo']) && file_exists($user_data['profile_photo'])) {

                                    $profile_photo = $user_data['profile_photo'];

                                }

                            } catch (Exception $e) {

                                // Ignorar erro

                            }

                            ?>

                            <?php if (!empty($profile_photo)): ?>

                                <img src="/<?php echo htmlspecialchars($profile_photo); ?>?v=<?php echo time(); ?>" 

                                     alt="Avatar" 

                                     class="w-10 h-10 rounded-full object-cover border-2 border-white"

                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">

                                <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center" style="display: none;">

                                    <i class="fas fa-user text-white"></i>

                                </div>

                            <?php else: ?>

                                <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center">

                                    <i class="fas fa-user text-white"></i>

                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="relative">

                            <button class="text-white hover:text-gray-200 focus:outline-none" onclick="toggleUserMenu()">

                                <i class="fas fa-chevron-down"></i>

                            </button>

                            <div id="userMenu" class="user-menu-dropdown absolute right-0 mt-2 w-56 rounded-xl shadow-2xl py-2 z-50 hidden">

                                <a href="/profile.php" class="user-menu-item">

                                    <i class="fas fa-user-circle"></i>

                                    <span>Meu Perfil</span>

                                </a>

                                <a href="/setup_2fa.php" class="user-menu-item" onclick="return loadPage('setup_2fa', event);">

                                    <i class="fas fa-shield-alt"></i>

                                    <span>Configurar 2FA</span>

                                </a>

                                <a href="/my_instance.php" class="user-menu-item" onclick="return loadPage('my_instance', event);">

                                    <i class="fas fa-plug"></i>

                                    <span>Minha Instância</span>

                                </a>

                                <hr class="my-2">

                                <a href="/logout.php" class="user-menu-item user-menu-item--danger">

                                    <i class="fas fa-sign-out-alt"></i>

                                    <span>Sair</span>

                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            

            <!-- Conteúdo da Página -->

            <div class="flex-1 overflow-y-auto bg-gray-50">

            

            <script>

            // Forçar recálculo do scroll do menu ao carregar a página

            document.addEventListener('DOMContentLoaded', function() {

                const sidebarNav = document.querySelector('.sidebar-nav');

                if (sidebarNav) {

                    // Forçar recálculo da altura

                    sidebarNav.style.display = 'none';

                    sidebarNav.offsetHeight; // Trigger reflow

                    sidebarNav.style.display = '';

                    

                    // Garantir que o scroll seja visível se necessário

                    setTimeout(function() {

                        if (sidebarNav.scrollHeight > sidebarNav.clientHeight) {

                            sidebarNav.style.overflowY = 'auto';

                        }

                    }, 100);

                }

                

                // Inicializar toggle de tema - executar imediatamente

                initializeThemeToggle();

            });

            

            // ==========================================

            // Sistema de Notificações

            // ==========================================

            let notificationsLoaded = false;

            

            function toggleNotifications() {

                const dropdown = document.getElementById('notificationDropdown');

                dropdown.classList.toggle('hidden');

                

                if (!dropdown.classList.contains('hidden') && !notificationsLoaded) {

                    loadNotifications();

                }

            }

            

            async function loadNotifications() {

                try {

                    const res = await fetch('/api/notifications.php?action=get_unread&limit=10');

                    const data = await res.json();

                    

                    if (data.success) {

                        renderNotifications(data.notifications);

                        updateBadge(data.unread_count);

                        notificationsLoaded = true;

                    }

                } catch (error) {

                    console.error('Erro ao carregar notificações:', error);

                }

            }

            

            function renderNotifications(notifications) {

                const list = document.getElementById('notificationList');

                

                if (!notifications || notifications.length === 0) {

                    list.innerHTML = '<div class="p-4 text-center text-gray-500 text-sm"><i class="fas fa-bell-slash text-2xl mb-2"></i><p>Nenhuma notificação</p></div>';

                    return;

                }

                

                list.innerHTML = notifications.map(n => `

                    <div class="p-3 border-b border-gray-100 hover:bg-gray-50 cursor-pointer ${n.is_read ? 'opacity-60' : ''}" onclick="markAsRead(${n.id})">

                        <div class="flex items-start gap-3">

                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${getNotificationColor(n.type)}">

                                <i class="${getNotificationIcon(n.type)} text-white text-sm"></i>

                            </div>

                            <div class="flex-1 min-w-0">

                                <p class="text-sm font-medium text-gray-800 truncate">${n.title}</p>

                                <p class="text-xs text-gray-500 truncate">${n.message}</p>

                                <p class="text-xs text-gray-400 mt-1">${formatTimeAgo(n.created_at)}</p>

                            </div>

                            ${!n.is_read ? '<span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0"></span>' : ''}

                        </div>

                    </div>

                `).join('');

            }

            

            function getNotificationColor(type) {

                const colors = { 'negative_response': 'bg-red-500', 'positive_response': 'bg-green-500', 'campaign_complete': 'bg-blue-500', 'new_response': 'bg-yellow-500', 'system': 'bg-gray-500' };

                return colors[type] || 'bg-gray-500';

            }

            

            function getNotificationIcon(type) {

                const icons = { 'negative_response': 'fas fa-exclamation-triangle', 'positive_response': 'fas fa-smile', 'campaign_complete': 'fas fa-check-circle', 'new_response': 'fas fa-reply', 'system': 'fas fa-info-circle' };

                return icons[type] || 'fas fa-bell';

            }

            

            function formatTimeAgo(dateStr) {

                const date = new Date(dateStr);

                const now = new Date();

                const diff = Math.floor((now - date) / 1000);

                if (diff < 60) return 'Agora mesmo';

                if (diff < 3600) return Math.floor(diff / 60) + ' min atrás';

                if (diff < 86400) return Math.floor(diff / 3600) + 'h atrás';

                return Math.floor(diff / 86400) + 'd atrás';

            }

            

            function updateBadge(count) {

                const badge = document.getElementById('notificationBadge');

                if (count > 0) {

                    badge.textContent = count > 99 ? '99+' : count;

                    badge.classList.remove('hidden');

                } else {

                    badge.classList.add('hidden');

                }

            }

            

            async function markAsRead(id) {

                try {

                    const formData = new FormData();

                    formData.append('action', 'mark_read');

                    formData.append('notification_id', id);

                    await fetch('/api/notifications.php', { method: 'POST', body: formData });

                    loadNotifications();

                } catch (error) { console.error('Erro:', error); }

            }

            

            async function markAllAsRead() {

                try {

                    const formData = new FormData();

                    formData.append('action', 'mark_all_read');

                    await fetch('/api/notifications.php', { method: 'POST', body: formData });

                    loadNotifications();

                } catch (error) { console.error('Erro:', error); }

            }

            

            // Fechar dropdown ao clicar fora

            document.addEventListener('click', function(e) {

                const bell = document.getElementById('notificationBell');

                const dropdown = document.getElementById('notificationDropdown');

                if (bell && dropdown && !bell.contains(e.target)) {

                    dropdown.classList.add('hidden');

                }

            });

            

            // Carregar contagem inicial e atualizar a cada 30s

            setTimeout(loadNotifications, 1000);

            setInterval(loadNotifications, 30000);

            </script>

