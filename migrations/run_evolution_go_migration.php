<?php
/**
 * Script para executar migration Evolution Go de forma segura
 * 
 * Este script executa cada comando SQL individualmente e ignora erros de duplicação
 * Acesse: /migrations/run_evolution_go_migration.php
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration Evolution Go</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #25D366;
            margin-bottom: 10px;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
            border-left: 4px solid #ccc;
        }
        .step.success {
            border-left-color: #25D366;
            background: #d4edda;
        }
        .step.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .step.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .step h3 {
            margin-top: 0;
            font-size: 16px;
        }
        .sql-code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
            margin: 10px 0;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .success-msg {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error-msg {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning-msg {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .info-msg {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Migration Evolution Go API</h1>
        <p>Executando migration para adicionar suporte à Evolution Go...</p>

        <?php
        $steps = [
            [
                'name' => 'Atualizar ENUM whatsapp_provider',
                'sql' => "ALTER TABLE users 
                         MODIFY COLUMN whatsapp_provider 
                         ENUM('evolution', 'zapi', 'meta', 'baileys', 'evolution-go') 
                         DEFAULT 'evolution' 
                         COMMENT 'Provider de WhatsApp (evolution, evolution-go, zapi, meta, baileys)'",
                'ignore_errors' => ['1060', '1061'] // Duplicate column/key
            ],
            [
                'name' => 'Adicionar coluna evolution_go_instance',
                'sql' => "ALTER TABLE users 
                         ADD COLUMN evolution_go_instance VARCHAR(100) NULL 
                         COMMENT 'Instance ID da Evolution Go' 
                         AFTER zapi_client_token",
                'ignore_errors' => ['1060'] // Duplicate column name
            ],
            [
                'name' => 'Adicionar coluna evolution_go_token',
                'sql' => "ALTER TABLE users 
                         ADD COLUMN evolution_go_token VARCHAR(255) NULL 
                         COMMENT 'Token/API Key da Evolution Go' 
                         AFTER evolution_go_instance",
                'ignore_errors' => ['1060'] // Duplicate column name
            ],
            [
                'name' => 'Criar índice idx_evolution_go_instance',
                'sql' => "CREATE INDEX idx_evolution_go_instance ON users(evolution_go_instance)",
                'ignore_errors' => ['1061'] // Duplicate key name
            ]
        ];

        $allSuccess = true;

        foreach ($steps as $index => $step) {
            $stepNum = $index + 1;
            echo '<div class="step">';
            echo '<h3>Passo ' . $stepNum . ': ' . htmlspecialchars($step['name']) . '</h3>';
            echo '<div class="sql-code">' . htmlspecialchars($step['sql']) . '</div>';

            try {
                $pdo->exec($step['sql']);
                echo '<div class="message success-msg">✅ Executado com sucesso!</div>';
                echo '</div>';
            } catch (PDOException $e) {
                $errorCode = $e->getCode();
                $errorMsg = $e->getMessage();
                
                // Verificar se é um erro que pode ser ignorado
                $canIgnore = false;
                foreach ($step['ignore_errors'] as $ignoreCode) {
                    if (strpos($errorMsg, $ignoreCode) !== false || 
                        strpos($errorMsg, 'Duplicate') !== false ||
                        strpos($errorMsg, 'already exists') !== false) {
                        $canIgnore = true;
                        break;
                    }
                }

                if ($canIgnore) {
                    echo '<div class="message warning-msg">⚠️ Já existe (ignorado): ' . htmlspecialchars($errorMsg) . '</div>';
                    echo '</div>';
                } else {
                    echo '<div class="message error-msg">❌ Erro: ' . htmlspecialchars($errorMsg) . '</div>';
                    echo '</div>';
                    $allSuccess = false;
                }
            }
        }

        // Verificação final
        echo '<div class="step">';
        echo '<h3>Verificação Final</h3>';

        try {
            // Verificar colunas
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'evolution_go%'");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($columns) >= 2) {
                echo '<div class="message success-msg">';
                echo '✅ Colunas criadas com sucesso:<br>';
                foreach ($columns as $col) {
                    echo '- ' . htmlspecialchars($col['Field']) . ' (' . htmlspecialchars($col['Type']) . ')<br>';
                }
                echo '</div>';
            } else {
                echo '<div class="message error-msg">❌ Colunas não foram criadas corretamente</div>';
                $allSuccess = false;
            }

            // Verificar ENUM
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'whatsapp_provider'");
            $providerCol = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($providerCol && strpos($providerCol['Type'], 'evolution-go') !== false) {
                echo '<div class="message success-msg">✅ ENUM whatsapp_provider atualizado com sucesso</div>';
            } else {
                echo '<div class="message error-msg">❌ ENUM whatsapp_provider não foi atualizado</div>';
                $allSuccess = false;
            }

            // Verificar índice
            $stmt = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_evolution_go_instance'");
            $index = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($index) {
                echo '<div class="message success-msg">✅ Índice criado com sucesso</div>';
            } else {
                echo '<div class="message warning-msg">⚠️ Índice não foi criado (não crítico)</div>';
            }

        } catch (PDOException $e) {
            echo '<div class="message error-msg">❌ Erro na verificação: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $allSuccess = false;
        }

        echo '</div>';

        // Resumo final
        if ($allSuccess) {
            echo '<div class="step success">';
            echo '<h3>🎉 Migration Concluída com Sucesso!</h3>';
            echo '<div class="message success-msg">';
            echo '<strong>Próximos passos:</strong><br>';
            echo '1. Acesse "Minha Instância" no sistema<br>';
            echo '2. Selecione "Evolution Go API (Alta Performance)"<br>';
            echo '3. Preencha Instance ID e API Key<br>';
            echo '4. Gere o QR Code e conecte seu WhatsApp<br>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="step error">';
            echo '<h3>⚠️ Migration Concluída com Avisos</h3>';
            echo '<div class="message warning-msg">';
            echo 'Alguns passos falharam, mas o sistema pode ainda funcionar.<br>';
            echo 'Verifique os erros acima e tente executar os comandos SQL manualmente se necessário.';
            echo '</div>';
            echo '</div>';
        }
        ?>

        <p style="text-align: center; color: #999; margin-top: 30px; font-size: 12px;">
            Evolution Go Migration Script v1.0 | <?php echo date('d/m/Y H:i:s'); ?>
        </p>
    </div>
</body>
</html>
