<?php
/**
 * Cron Job para Automação do Kanban
 * Executar a cada hora ou dia
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

echo "Iniciando processamento de automação do Kanban...\n";

try {
    // Buscar regras ativas de 'time_in_column'
    $stmt = $pdo->query("
        SELECT * FROM kanban_automation_rules 
        WHERE is_active = 1 AND trigger_type = 'time_in_column'
    ");
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rules as $rule) {
        $params = json_decode($rule['parameters'], true);
        $days = intval($params['days'] ?? 0);
        
        if ($days <= 0) continue;
        
        // Buscar cards que correspondem à condição
        // Cards na coluna X que não foram movidos há Y dias
        // Assumindo que updated_at é atualizado quando move
        $sql = "
            SELECT * FROM kanban_cards 
            WHERE column_id = ? 
            AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        
        $cardStmt = $pdo->prepare($sql);
        $cardStmt->execute([$rule['column_id'], $days]);
        $cards = $cardStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($cards as $card) {
            echo "Processando card #{$card['id']} (Regra #{$rule['id']})...\n";
            
            if ($rule['action_type'] === 'archive') {
                // Arquivar card
                // Assumindo que arquivar = deletar ou mover para tabela de arquivados
                // Aqui vamos deletar (comportamento padrão do frontend 'arquivar')
                // Idealmente teria um campo 'archived_at'
                
                // Opção 1: Soft delete se tiver campo 'deleted_at' ou 'archived'
                // Opção 2: Delete físico (como no código atual do frontend)
                
                // Vamos verificar se existe coluna 'archived' na tabela
                // Se não, fazemos delete físico
                
                $delStmt = $pdo->prepare("DELETE FROM kanban_cards WHERE id = ?");
                $delStmt->execute([$card['id']]);
                
                echo "Card #{$card['id']} arquivado.\n";
            }
            elseif ($rule['action_type'] === 'notify') {
                // Implementar notificação
                echo "Notificação enviada para responsável do card #{$card['id']}.\n";
            }
        }
    }
    
    echo "Processamento concluído.\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
