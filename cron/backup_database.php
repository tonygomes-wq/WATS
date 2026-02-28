<?php
/**
 * Script de Backup Automático do Banco de Dados
 * Execução via CRON diário
 * 
 * MACIP Tecnologia LTDA
 * 
 * Configurar no cPanel:
 * 0 2 * * * /usr/bin/php /home/usuario/public_html/wats/cron/backup_database.php
 */

// Carregar configurações
require_once dirname(__DIR__) . '/config/database.php';

class DatabaseBackup {
    private $pdo;
    private $backupDir;
    private $retentionDays = 30;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        
        // Diretório de backup (fora do public_html por segurança)
        $this->backupDir = dirname(__DIR__) . '/backups';
        
        // Criar diretório se não existir
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0750, true);
        }
        
        // Proteger com .htaccess
        $htaccess = $this->backupDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all");
        }
    }
    
    /**
     * Executar backup completo
     */
    public function run() {
        $startTime = microtime(true);
        $backupId = $this->logBackupStart();
        
        try {
            echo "🔄 Iniciando backup do banco de dados...\n";
            
            // Gerar nome do arquivo
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $this->backupDir . '/' . $filename;
            
            // Executar backup
            $tables = $this->getTables();
            $this->createBackup($filepath, $tables);
            
            // Comprimir
            $compressedFile = $this->compress($filepath);
            
            // Obter tamanho
            $fileSize = filesize($compressedFile);
            
            // Registrar sucesso
            $this->logBackupComplete($backupId, $compressedFile, $fileSize, $tables);
            
            // Limpar backups antigos
            $this->cleanOldBackups();
            
            $duration = round(microtime(true) - $startTime, 2);
            
            echo "✅ Backup concluído com sucesso!\n";
            echo "📁 Arquivo: $compressedFile\n";
            echo "📊 Tamanho: " . $this->formatBytes($fileSize) . "\n";
            echo "⏱️ Duração: {$duration}s\n";
            echo "🗂️ Tabelas: " . count($tables) . "\n";
            
            return true;
            
        } catch (Exception $e) {
            $this->logBackupFailed($backupId, $e->getMessage());
            echo "❌ Erro no backup: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Obter lista de tabelas
     */
    private function getTables() {
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        return $tables;
    }
    
    /**
     * Criar arquivo de backup
     */
    private function createBackup($filepath, $tables) {
        $handle = fopen($filepath, 'w');
        
        if (!$handle) {
            throw new Exception("Não foi possível criar arquivo de backup");
        }
        
        // Cabeçalho
        fwrite($handle, "-- ============================================\n");
        fwrite($handle, "-- WATS Database Backup\n");
        fwrite($handle, "-- Data: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- ============================================\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
        
        // Backup de cada tabela
        foreach ($tables as $table) {
            echo "  📋 Fazendo backup da tabela: $table\n";
            
            // Estrutura da tabela
            $stmt = $this->pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            
            fwrite($handle, "-- Tabela: $table\n");
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($handle, $row[1] . ";\n\n");
            
            // Dados da tabela
            $stmt = $this->pdo->query("SELECT * FROM `$table`");
            $rowCount = 0;
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($rowCount == 0) {
                    $columns = array_keys($row);
                    fwrite($handle, "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n");
                }
                
                $values = array_map(function($value) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $this->pdo->quote($value);
                }, array_values($row));
                
                $comma = ($rowCount > 0) ? ",\n" : "";
                fwrite($handle, $comma . "(" . implode(', ', $values) . ")");
                
                $rowCount++;
                
                // Flush a cada 1000 registros
                if ($rowCount % 1000 == 0) {
                    fwrite($handle, ";\n\nINSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n");
                    $rowCount = 0;
                }
            }
            
            if ($rowCount > 0) {
                fwrite($handle, ";\n\n");
            }
        }
        
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
        
        return $filepath;
    }
    
    /**
     * Comprimir arquivo
     */
    private function compress($filepath) {
        $compressedFile = $filepath . '.gz';
        
        $fp = fopen($filepath, 'rb');
        $gzfp = gzopen($compressedFile, 'wb9');
        
        while (!feof($fp)) {
            gzwrite($gzfp, fread($fp, 1024 * 512));
        }
        
        fclose($fp);
        gzclose($gzfp);
        
        // Remover arquivo não comprimido
        unlink($filepath);
        
        return $compressedFile;
    }
    
    /**
     * Limpar backups antigos
     */
    private function cleanOldBackups() {
        $files = glob($this->backupDir . '/backup_*.sql.gz');
        $cutoffTime = time() - ($this->retentionDays * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                echo "🗑️ Backup antigo removido: " . basename($file) . "\n";
            }
        }
    }
    
    /**
     * Registrar início do backup
     */
    private function logBackupStart() {
        $stmt = $this->pdo->prepare("
            INSERT INTO backup_history (backup_type, status)
            VALUES ('full', 'started')
        ");
        $stmt->execute();
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Registrar conclusão do backup
     */
    private function logBackupComplete($backupId, $filepath, $fileSize, $tables) {
        $stmt = $this->pdo->prepare("
            UPDATE backup_history 
            SET status = 'completed',
                backup_path = ?,
                file_size = ?,
                tables_backed_up = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $filepath,
            $fileSize,
            json_encode($tables),
            $backupId
        ]);
    }
    
    /**
     * Registrar falha no backup
     */
    private function logBackupFailed($backupId, $error) {
        $stmt = $this->pdo->prepare("
            UPDATE backup_history 
            SET status = 'failed',
                error_message = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$error, $backupId]);
    }
    
    /**
     * Formatar bytes
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// Executar backup
$backup = new DatabaseBackup();
$backup->run();
