<?php
/**
 * API para Atualizar Nome do Contato
 * Cria contato se não existir, atualiza se já existir
 */

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);
$phone = $input['phone'] ?? '';
$name = $input['name'] ?? '';

// Validar
if (empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Telefone não informado']);
    exit;
}

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Nome não informado']);
    exit;
}

// Limpar telefone
$cleanPhone = preg_replace('/[^0-9]/', '', $phone);

try {
    // Verificar se contato já existe
    $stmt = $pdo->prepare("SELECT id FROM contacts WHERE phone = ? AND user_id = ?");
    $stmt->execute([$cleanPhone, $user_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Atualizar contato existente
        $stmt = $pdo->prepare("
            UPDATE contacts 
            SET name = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $existing['id']]);
        
        $contact_id = $existing['id'];
        $action = 'updated';
        
    } else {
        // Criar novo contato
        $stmt = $pdo->prepare("
            INSERT INTO contacts (
                user_id,
                phone,
                name,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$user_id, $cleanPhone, $name]);
        
        $contact_id = $pdo->lastInsertId();
        $action = 'created';
    }
    
    // Atualizar também na tabela chat_conversations se existir
    $stmt = $pdo->prepare("
        UPDATE chat_conversations 
        SET contact_name = ?
        WHERE phone = ? AND user_id = ?
    ");
    $stmt->execute([$name, $cleanPhone, $user_id]);
    
    echo json_encode([
        'success' => true,
        'contact_id' => $contact_id,
        'action' => $action,
        'message' => $action === 'created' ? 'Contato criado com sucesso' : 'Contato atualizado com sucesso'
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao atualizar contato: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao salvar contato: ' . $e->getMessage()
    ]);
}
?>
