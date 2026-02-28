<?php
/**
 * API - Buscar Contatos do Sistema
 */

header('Content-Type: application/json');
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar autenticação
requireLogin();

$userId = $_SESSION['user_id'];

try {
    // Buscar contatos do sistema (tabela contacts)
    $stmt = $pdo->prepare("
        SELECT 
            id,
            name,
            phone,
            created_at
        FROM contacts 
        WHERE user_id = ?
        ORDER BY name ASC
    ");
    $stmt->execute([$userId]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Processar contatos
    $processedContacts = [];
    
    foreach ($contacts as $contact) {
        $phone = cleanPhone($contact['phone']);
        $name = $contact['name'] ?: $phone;
        
        if ($phone) {
            $processedContacts[] = [
                'id' => $contact['id'],
                'phone' => $phone,
                'name' => $name
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'contacts' => $processedContacts,
        'total' => count($processedContacts)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function cleanPhone($phone) {
    // Remover caracteres não numéricos
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return $phone;
}
