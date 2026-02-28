<?php
/**
 * DEBUG: Verificar sessão
 */
session_start();
header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? null,
    'user_type' => $_SESSION['user_type'] ?? null,
    'attendant_id' => $_SESSION['attendant_id'] ?? null,
    'supervisor_id' => $_SESSION['supervisor_id'] ?? null,
    'is_logged_in' => isset($_SESSION['user_id']),
    'all_session_keys' => array_keys($_SESSION)
], JSON_PRETTY_PRINT);
