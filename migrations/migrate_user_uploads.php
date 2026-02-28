<?php
/**
 * Script de Migração - Separar Uploads por Usuário
 * 
 * Este script organiza os arquivos de upload existentes em diretórios
 * separados por usuário para melhor controle de storage e segurança.
 * 
 * ATENÇÃO: Execute este script apenas UMA VEZ após atualizar o código.
 * 
 * MACIP Tecnologia LTDA
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config/database.php';

echo "=== MIGRAÇÃO DE UPLOADS POR USUÁRIO ===\n\n";

// Estatísticas
$stats = [
    'total_files' => 0,
    'migrated' => 0,
    'skipped' => 0,
    'errors' => 0,
    'orphaned' => 0
];

// Função para mover arquivo com segurança
function moveFileToUserDir($sourcePath, $userId, $subdir = 'media') {
    global $stats;
    
    if (!file_exists($sourcePath)) {
        $stats['errors']++;
        return false;
    }
    
    // Criar diretório do usuário
    $userDir = __DIR__ . "/../uploads/user_{$userId}/{$subdir}/";
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
    }
    
    $filename = basename($sourcePath);
    $destPath = $userDir . $filename;
    
    // Se arquivo já existe no destino, pular
    if (file_exists($destPath)) {
        $stats['skipped']++;
        return true;
    }
    
    // Mover arquivo
    if (rename($sourcePath, $destPath)) {
        $stats['migrated']++;
        return true;
    } else {
        $stats['errors']++;
        return false;
    }
}

// 1. Migrar arquivos de chat_media baseado em mensagens do banco
echo "1. Migrando arquivos de chat_media...\n";

try {
    $stmt = $pdo->query("
        SELECT DISTINCT 
            cm.user_id,
            cm.media_url,
            cm.media_filename
        FROM chat_messages cm
        WHERE cm.media_url IS NOT NULL
        AND cm.media_url LIKE '/uploads/chat_media/%'
    ");
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Encontradas " . count($messages) . " mensagens com mídia\n";
    
    foreach ($messages as $msg) {
        $stats['total_files']++;
        $userId = $msg['user_id'];
        
        // Extrair nome do arquivo da URL
        $filename = basename($msg['media_url']);
        $sourcePath = __DIR__ . "/../uploads/chat_media/" . $filename;
        
        if (moveFileToUserDir($sourcePath, $userId, 'chat_media')) {
            // Atualizar URL no banco
            $newUrl = "/uploads/user_{$userId}/chat_media/" . $filename;
            $updateStmt = $pdo->prepare("
                UPDATE chat_messages 
                SET media_url = ? 
                WHERE media_url = ?
            ");
            $updateStmt->execute([$newUrl, $msg['media_url']]);
        }
    }
    
    echo "   ✓ Concluído\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n\n";
}

// 2. Migrar arquivos de media/ baseado em mensagens
echo "2. Migrando arquivos de media/...\n";

try {
    $stmt = $pdo->query("
        SELECT DISTINCT 
            cm.user_id,
            cm.media_url,
            cm.media_filename
        FROM chat_messages cm
        WHERE cm.media_url IS NOT NULL
        AND cm.media_url LIKE '/uploads/media/%'
    ");
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Encontradas " . count($messages) . " mensagens com mídia\n";
    
    foreach ($messages as $msg) {
        $stats['total_files']++;
        $userId = $msg['user_id'];
        
        $filename = basename($msg['media_url']);
        $sourcePath = __DIR__ . "/../uploads/media/" . $filename;
        
        if (moveFileToUserDir($sourcePath, $userId, 'media')) {
            // Atualizar URL no banco
            $newUrl = "/uploads/user_{$userId}/media/" . $filename;
            $updateStmt = $pdo->prepare("
                UPDATE chat_messages 
                SET media_url = ? 
                WHERE media_url = ?
            ");
            $updateStmt->execute([$newUrl, $msg['media_url']]);
        }
    }
    
    echo "   ✓ Concluído\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n\n";
}

// 3. Listar arquivos órfãos (sem dono no banco)
echo "3. Verificando arquivos órfãos...\n";

$orphanedDirs = [
    __DIR__ . '/../uploads/media/',
    __DIR__ . '/../uploads/chat_media/'
];

foreach ($orphanedDirs as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $dir . $file;
        if (is_file($filePath)) {
            $stats['orphaned']++;
            echo "   - Órfão: $file\n";
        }
    }
}

if ($stats['orphaned'] === 0) {
    echo "   ✓ Nenhum arquivo órfão encontrado\n";
}
echo "\n";

// 4. Criar diretório de arquivos órfãos
if ($stats['orphaned'] > 0) {
    echo "4. Movendo arquivos órfãos para diretório especial...\n";
    
    $orphanedDir = __DIR__ . '/../uploads/orphaned/';
    if (!is_dir($orphanedDir)) {
        mkdir($orphanedDir, 0755, true);
    }
    
    foreach ($orphanedDirs as $dir) {
        if (!is_dir($dir)) continue;
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $sourcePath = $dir . $file;
            $destPath = $orphanedDir . $file;
            
            if (is_file($sourcePath) && !file_exists($destPath)) {
                rename($sourcePath, $destPath);
            }
        }
    }
    
    echo "   ✓ Arquivos órfãos movidos para: uploads/orphaned/\n";
    echo "   (Você pode revisar e deletar manualmente se necessário)\n\n";
}

// Relatório Final
echo "=== RELATÓRIO FINAL ===\n";
echo "Total de arquivos processados: {$stats['total_files']}\n";
echo "Migrados com sucesso: {$stats['migrated']}\n";
echo "Já existiam (pulados): {$stats['skipped']}\n";
echo "Órfãos (sem dono): {$stats['orphaned']}\n";
echo "Erros: {$stats['errors']}\n";
echo "\n";

if ($stats['errors'] === 0) {
    echo "✓ Migração concluída com sucesso!\n";
} else {
    echo "⚠ Migração concluída com alguns erros. Verifique os logs.\n";
}

echo "\n=== PRÓXIMOS PASSOS ===\n";
echo "1. Verifique se o sistema está funcionando corretamente\n";
echo "2. Teste upload de novos arquivos\n";
echo "3. Verifique se as mídias antigas aparecem corretamente no chat\n";
echo "4. Após confirmar que tudo funciona, você pode deletar:\n";
echo "   - uploads/media/ (se vazio)\n";
echo "   - uploads/chat_media/ (se vazio)\n";
echo "   - uploads/orphaned/ (após revisar)\n";
echo "\n";
?>
