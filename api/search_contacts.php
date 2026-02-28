<?php
/**
 * API para Buscar Contatos
 * Busca contatos na tabela contacts por nome ou telefone
 */

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'contacts' => []]);
    exit;
}

try {
    // Buscar contatos por nome ou telefone
    $searchTerm = '%' . $query . '%';
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            name,
            phone,
            profile_picture_url
        FROM contacts 
        WHERE user_id = ? 
        AND (
            name LIKE ? 
            OR phone LIKE ?
        )
        ORDER BY 
            CASE WHEN name LIKE ? THEN 0 ELSE 1 END,
            name ASC
        LIMIT 20
    ");
    
    $stmt->execute([$user_id, $searchTerm, $searchTerm, $query . '%']);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'contacts' => $contacts,
        'total' => count($contacts)
    ]);
    
} catch (Exception $e) {
    error_log("[SEARCH_CONTACTS] Erro: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar contatos'
    ]);
}
