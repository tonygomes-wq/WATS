<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Se já estiver logado, redirecionar para dashboard
if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

// Mostrar landing page moderna
header('Location: /landing_page.php');
exit;
