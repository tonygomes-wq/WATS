<?php
/**
 * Script de Sincronização - Status de Templates
 * Sincroniza status dos templates com Meta API
 * Executar via cron a cada 6 horas
 */

if (php_sapi_name() !== 'cli') {
    die('Este script deve ser executado via CLI');
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/MetaTemplateManager.php';

echo "===========================================\n";
echo "  Sincronização de Templates - WATS\n";
echo "===========================================\n\n";

try {
    $templateManager = new MetaTemplateManager($pdo);
    
    echo "[" . date('Y-m-d H:i:s') . "] Buscando templates pendentes...\n";
    
    // Buscar templates com status PENDING
    $stmt = $pdo->query("
        SELECT id, user_id, template_name, meta_template_id
        FROM meta_message_templates
        WHERE template_status = 'PENDING'
        AND meta_template_id IS NOT NULL
    ");
    
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($templates);
    
    if ($total === 0) {
        echo "  Nenhum template pendente encontrado.\n\n";
        exit(0);
    }
    
    echo "  Encontrados: $total templates\n\n";
    
    $synced = 0;
    $errors = 0;
    
    foreach ($templates as $template) {
        echo "  Sincronizando: {$template['template_name']} (ID: {$template['id']})... ";
        
        $result = $templateManager->syncTemplateStatus($template['id']);
        
        if ($result['success']) {
            echo "✅ {$result['status']}\n";
            $synced++;
        } else {
            echo "❌ Erro\n";
            $errors++;
        }
        
        // Delay para evitar rate limiting
        usleep(500000); // 0.5 segundos
    }
    
    echo "\n";
    echo "===========================================\n";
    echo "  Resumo:\n";
    echo "  - Total: $total\n";
    echo "  - Sincronizados: $synced\n";
    echo "  - Erros: $errors\n";
    echo "===========================================\n\n";
    
    exit($errors > 0 ? 1 : 0);
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
