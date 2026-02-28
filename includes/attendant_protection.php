<?php
/**
 * Proteção contra acesso de atendentes a páginas administrativas
 * Incluir este arquivo no topo de páginas que atendentes NÃO devem acessar
 * 
 * Exemplo de uso:
 * require_once 'includes/attendant_protection.php';
 * 
 * MACIP Tecnologia LTDA
 */

if (!isset($_SESSION)) {
    session_start();
}

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Verificar se é atendente
$user_type = $_SESSION['user_type'] ?? 'user';

if ($user_type === 'attendant') {
    // Atendente tentando acessar página administrativa
    // Redirecionar para chat
    header('Location: /chat.php?error=access_denied');
    exit;
}

// Se chegou aqui, é supervisor ou admin - pode continuar
?>
