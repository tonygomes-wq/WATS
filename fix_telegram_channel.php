<?php
/**
 * CORREÇÃO: Tabelas de Canais (Telegram)
 * 
 * Este script cria as tabelas necessárias para o sistema de canais
 */

require_once 'config/database.php';

echo "<h1>🔧 Correção: Tabelas de Canais</h1>";

try {
    echo "<h2>1. Verificando tabela 'channels'...</h2>";
    
    // Verificar se tabela channels existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'channels'");
    $channelsExists = $stmt->rowCount() > 0;
    
    if (!$channelsExists) {
        echo "<p>❌ Tabela 'channels' não existe. Criando...</p>";
        
        $pdo->exec("
            CREATE TABLE channels (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                channel_type ENUM('facebook', 'instagram', 'twitter', 'telegram', 'line', 'twilio_sms', 'web_widget', 'custom_api', 'tiktok') NOT NULL,
                name VARCHAR(255),
                status ENUM('active', 'inactive', 'error') DEFAULT 'active',
                last_sync_at DATETIME NULL,
                error_message TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_type (user_id, channel_type),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<p>✅ Tabela 'channels' criada com sucesso!</p>";
    } else {
        echo "<p>✅ Tabela 'channels' já existe.</p>";
        
        // Verificar se coluna last_sync_at existe
        echo "<h3>1.1. Verificando coluna 'last_sync_at'...</h3>";
        $stmt = $pdo->query("SHOW COLUMNS FROM channels LIKE 'last_sync_at'");
        $columnExists = $stmt->rowCount() > 0;
        
        if (!$columnExists) {
            echo "<p>❌ Coluna 'last_sync_at' não existe. Adicionando...</p>";
            $pdo->exec("ALTER TABLE channels ADD COLUMN last_sync_at DATETIME NULL AFTER status");
            echo "<p>✅ Coluna 'last_sync_at' adicionada!</p>";
        } else {
            echo "<p>✅ Coluna 'last_sync_at' já existe.</p>";
        }
        
        // Verificar se coluna error_message existe
        echo "<h3>1.2. Verificando coluna 'error_message'...</h3>";
        $stmt = $pdo->query("SHOW COLUMNS FROM channels LIKE 'error_message'");
        $columnExists = $stmt->rowCount() > 0;
        
        if (!$columnExists) {
            echo "<p>❌ Coluna 'error_message' não existe. Adicionando...</p>";
            $pdo->exec("ALTER TABLE channels ADD COLUMN error_message TEXT NULL AFTER last_sync_at");
            echo "<p>✅ Coluna 'error_message' adicionada!</p>";
        } else {
            echo "<p>✅ Coluna 'error_message' já existe.</p>";
        }
    }
    
    echo "<h2>2. Verificando tabela 'channel_telegram'...</h2>";
    
    // Verificar se tabela channel_telegram existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'channel_telegram'");
    $telegramExists = $stmt->rowCount() > 0;
    
    if (!$telegramExists) {
        echo "<p>❌ Tabela 'channel_telegram' não existe. Criando...</p>";
        
        $pdo->exec("
            CREATE TABLE channel_telegram (
                id INT PRIMARY KEY AUTO_INCREMENT,
                channel_id INT NOT NULL,
                bot_token VARCHAR(255) NOT NULL,
                bot_name VARCHAR(255),
                bot_username VARCHAR(255),
                webhook_url TEXT,
                webhook_verified BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
                UNIQUE KEY unique_bot_token (bot_token),
                INDEX idx_bot_token (bot_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<p>✅ Tabela 'channel_telegram' criada com sucesso!</p>";
    } else {
        echo "<p>✅ Tabela 'channel_telegram' já existe.</p>";
        
        // Verificar se coluna webhook_verified existe
        echo "<h3>2.1. Verificando coluna 'webhook_verified'...</h3>";
        $stmt = $pdo->query("SHOW COLUMNS FROM channel_telegram LIKE 'webhook_verified'");
        $columnExists = $stmt->rowCount() > 0;
        
        if (!$columnExists) {
            echo "<p>❌ Coluna 'webhook_verified' não existe. Adicionando...</p>";
            
            $pdo->exec("
                ALTER TABLE channel_telegram 
                ADD COLUMN webhook_verified BOOLEAN DEFAULT FALSE AFTER webhook_url
            ");
            
            echo "<p>✅ Coluna 'webhook_verified' adicionada!</p>";
        } else {
            echo "<p>✅ Coluna 'webhook_verified' já existe.</p>";
        }
        
        // Verificar se coluna bot_username existe
        echo "<h3>2.2. Verificando coluna 'bot_username'...</h3>";
        $stmt = $pdo->query("SHOW COLUMNS FROM channel_telegram LIKE 'bot_username'");
        $columnExists = $stmt->rowCount() > 0;
        
        if (!$columnExists) {
            echo "<p>❌ Coluna 'bot_username' não existe. Adicionando...</p>";
            
            $pdo->exec("
                ALTER TABLE channel_telegram 
                ADD COLUMN bot_username VARCHAR(255) AFTER bot_name
            ");
            
            echo "<p>✅ Coluna 'bot_username' adicionada!</p>";
        } else {
            echo "<p>✅ Coluna 'bot_username' já existe.</p>";
        }
    }
    
    echo "<h2>3. Estrutura das Tabelas</h2>";
    
    // Mostrar estrutura da tabela channels
    echo "<h3>Tabela: channels</h3>";
    $stmt = $pdo->query("DESCRIBE channels");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Mostrar estrutura da tabela channel_telegram
    echo "<h3>Tabela: channel_telegram</h3>";
    $stmt = $pdo->query("DESCRIBE channel_telegram");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>✅ CORREÇÃO CONCLUÍDA!</h2>";
    echo "<p><strong>Próximo passo:</strong> Tente conectar o Telegram novamente.</p>";
    echo "<p><a href='channels.php'>← Voltar para Canais</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ ERRO</h2>";
    echo "<p style='color: red;'><strong>Erro:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
