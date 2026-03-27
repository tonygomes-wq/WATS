<?php
/**
 * Script de Teste - Evolution Go API
 * 
 * Testa a integração com Evolution Go API
 * Acesse: /test_evolution_go.php
 */

require_once 'config/database.php';
require_once 'includes/channels/providers/EvolutionGoProvider.php';
require_once 'includes/channels/WhatsAppChannel.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Evolution Go API</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 900px;
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
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .test-section {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 6px;
            border-left: 4px solid #25D366;
        }
        .test-section h3 {
            margin-top: 0;
            color: #333;
        }
        .success {
            color: #25D366;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .info {
            color: #0066cc;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 13px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .config-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .config-label {
            font-weight: bold;
            color: #666;
            display: inline-block;
            width: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Teste Evolution Go API</h1>
        <p class="subtitle">Verificação de integração e configuração</p>

        <?php
        // Teste 1: Verificar constantes
        echo '<div class="test-section">';
        echo '<h3>1. Configuração do Sistema</h3>';
        
        $configOk = true;
        
        echo '<div class="config-item">';
        echo '<span class="config-label">Evolution Go URL:</span>';
        if (defined('EVOLUTION_GO_API_URL')) {
            echo '<span class="success">' . EVOLUTION_GO_API_URL . '</span>';
        } else {
            echo '<span class="error">❌ Não configurado</span>';
            $configOk = false;
        }
        echo '</div>';
        
        echo '<div class="config-item">';
        echo '<span class="config-label">Evolution Go API Key:</span>';
        if (defined('EVOLUTION_GO_API_KEY')) {
            echo '<span class="success">✅ Configurado (' . substr(EVOLUTION_GO_API_KEY, 0, 10) . '...)</span>';
        } else {
            echo '<span class="error">❌ Não configurado</span>';
            $configOk = false;
        }
        echo '</div>';
        
        echo '<div class="config-item">';
        echo '<span class="config-label">Status:</span>';
        if ($configOk) {
            echo '<span class="status-badge status-ok">✅ Configuração OK</span>';
        } else {
            echo '<span class="status-badge status-error">❌ Configuração Incompleta</span>';
        }
        echo '</div>';
        
        echo '</div>';
        
        // Teste 2: Verificar banco de dados
        echo '<div class="test-section">';
        echo '<h3>2. Estrutura do Banco de Dados</h3>';
        
        try {
            // Verificar se coluna evolution_go_instance existe
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'evolution_go_instance'");
            $hasInstance = $stmt->rowCount() > 0;
            
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'evolution_go_token'");
            $hasToken = $stmt->rowCount() > 0;
            
            // Verificar ENUM whatsapp_provider
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'whatsapp_provider'");
            $providerColumn = $stmt->fetch(PDO::FETCH_ASSOC);
            $hasEvolutionGo = strpos($providerColumn['Type'] ?? '', 'evolution-go') !== false;
            
            echo '<div class="config-item">';
            echo '<span class="config-label">Coluna evolution_go_instance:</span>';
            echo $hasInstance ? '<span class="success">✅ Existe</span>' : '<span class="error">❌ Não existe</span>';
            echo '</div>';
            
            echo '<div class="config-item">';
            echo '<span class="config-label">Coluna evolution_go_token:</span>';
            echo $hasToken ? '<span class="success">✅ Existe</span>' : '<span class="error">❌ Não existe</span>';
            echo '</div>';
            
            echo '<div class="config-item">';
            echo '<span class="config-label">ENUM evolution-go:</span>';
            echo $hasEvolutionGo ? '<span class="success">✅ Existe</span>' : '<span class="error">❌ Não existe</span>';
            echo '</div>';
            
            if ($hasInstance && $hasToken && $hasEvolutionGo) {
                echo '<div class="config-item">';
                echo '<span class="config-label">Status:</span>';
                echo '<span class="status-badge status-ok">✅ Banco de Dados OK</span>';
                echo '</div>';
            } else {
                echo '<div class="config-item">';
                echo '<span class="config-label">Status:</span>';
                echo '<span class="status-badge status-error">❌ Execute a migration</span>';
                echo '</div>';
                echo '<p class="info">Execute: <code>mysql -u root -p whatsapp_sender < migrations/add_evolution_go_support.sql</code></p>';
            }
            
        } catch (Exception $e) {
            echo '<p class="error">Erro ao verificar banco: ' . $e->getMessage() . '</p>';
        }
        
        echo '</div>';
        
        // Teste 3: Verificar classe EvolutionGoProvider
        echo '<div class="test-section">';
        echo '<h3>3. Classes PHP</h3>';
        
        echo '<div class="config-item">';
        echo '<span class="config-label">EvolutionGoProvider:</span>';
        if (class_exists('EvolutionGoProvider')) {
            echo '<span class="success">✅ Carregada</span>';
            
            // Verificar métodos
            $methods = get_class_methods('EvolutionGoProvider');
            $requiredMethods = ['sendText', 'sendImage', 'sendVideo', 'sendAudio', 'sendDocument', 'getStatus'];
            $missingMethods = array_diff($requiredMethods, $methods);
            
            if (empty($missingMethods)) {
                echo ' <span class="info">(Todos os métodos implementados)</span>';
            } else {
                echo ' <span class="error">(Faltam métodos: ' . implode(', ', $missingMethods) . ')</span>';
            }
        } else {
            echo '<span class="error">❌ Não encontrada</span>';
        }
        echo '</div>';
        
        echo '<div class="config-item">';
        echo '<span class="config-label">WhatsAppChannel:</span>';
        if (class_exists('WhatsAppChannel')) {
            echo '<span class="success">✅ Carregada</span>';
        } else {
            echo '<span class="error">❌ Não encontrada</span>';
        }
        echo '</div>';
        
        echo '</div>';
        
        // Teste 4: Testar conexão com Evolution Go API
        echo '<div class="test-section">';
        echo '<h3>4. Conectividade com Evolution Go API</h3>';
        
        if (defined('EVOLUTION_GO_API_URL')) {
            $testUrl = EVOLUTION_GO_API_URL . '/';
            
            echo '<p class="info">Testando conexão com: ' . $testUrl . '</p>';
            
            $ch = curl_init($testUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            echo '<div class="config-item">';
            echo '<span class="config-label">HTTP Status:</span>';
            if ($httpCode > 0) {
                echo '<span class="success">✅ ' . $httpCode . '</span>';
            } else {
                echo '<span class="error">❌ Sem resposta</span>';
            }
            echo '</div>';
            
            if ($error) {
                echo '<div class="config-item">';
                echo '<span class="config-label">Erro:</span>';
                echo '<span class="error">' . $error . '</span>';
                echo '</div>';
            }
            
            if ($response) {
                echo '<div class="config-item">';
                echo '<span class="config-label">Resposta:</span>';
                echo '<pre>' . htmlspecialchars(substr($response, 0, 500)) . '</pre>';
                echo '</div>';
            }
        } else {
            echo '<p class="error">❌ EVOLUTION_GO_API_URL não configurado</p>';
        }
        
        echo '</div>';
        
        // Teste 5: Verificar usuários com Evolution Go configurado
        echo '<div class="test-section">';
        echo '<h3>5. Usuários Configurados</h3>';
        
        try {
            $stmt = $pdo->query("
                SELECT id, name, whatsapp_provider, evolution_go_instance 
                FROM users 
                WHERE whatsapp_provider = 'evolution-go' 
                   OR evolution_go_instance IS NOT NULL
                LIMIT 5
            ");
            
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($users) > 0) {
                echo '<p class="success">✅ Encontrados ' . count($users) . ' usuário(s) com Evolution Go:</p>';
                echo '<ul>';
                foreach ($users as $user) {
                    echo '<li>';
                    echo '<strong>' . htmlspecialchars($user['name']) . '</strong> ';
                    echo '(ID: ' . $user['id'] . ') - ';
                    echo 'Provider: ' . ($user['whatsapp_provider'] ?? 'N/A') . ' - ';
                    echo 'Instance: ' . ($user['evolution_go_instance'] ?? 'N/A');
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="info">ℹ️ Nenhum usuário configurado com Evolution Go ainda.</p>';
                echo '<p>Configure em: <strong>Minha Instância</strong> → Selecione "Evolution Go API"</p>';
            }
            
        } catch (Exception $e) {
            echo '<p class="error">Erro ao buscar usuários: ' . $e->getMessage() . '</p>';
        }
        
        echo '</div>';
        
        // Resumo final
        echo '<div class="test-section" style="border-left-color: #0066cc;">';
        echo '<h3>📋 Resumo</h3>';
        
        $allOk = $configOk && $hasInstance && $hasToken && $hasEvolutionGo && class_exists('EvolutionGoProvider');
        
        if ($allOk) {
            echo '<p class="success" style="font-size: 18px;">✅ Sistema pronto para usar Evolution Go API!</p>';
            echo '<p>Próximos passos:</p>';
            echo '<ol>';
            echo '<li>Acesse <strong>Minha Instância</strong></li>';
            echo '<li>Selecione <strong>Evolution Go API (Alta Performance)</strong></li>';
            echo '<li>Preencha Instance ID e API Key</li>';
            echo '<li>Gere o QR Code e conecte seu WhatsApp</li>';
            echo '</ol>';
        } else {
            echo '<p class="error" style="font-size: 18px;">❌ Configuração incompleta</p>';
            echo '<p>Ações necessárias:</p>';
            echo '<ul>';
            if (!$configOk) {
                echo '<li>Adicionar constantes EVOLUTION_GO_API_URL e EVOLUTION_GO_API_KEY em config/database.php</li>';
            }
            if (!$hasInstance || !$hasToken || !$hasEvolutionGo) {
                echo '<li>Executar migration: <code>mysql -u root -p whatsapp_sender < migrations/add_evolution_go_support.sql</code></li>';
            }
            if (!class_exists('EvolutionGoProvider')) {
                echo '<li>Verificar se arquivo includes/channels/providers/EvolutionGoProvider.php existe</li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';
        ?>
        
        <p style="text-align: center; color: #999; margin-top: 30px; font-size: 12px;">
            Evolution Go Integration Test v1.0 | <?php echo date('d/m/Y H:i:s'); ?>
        </p>
    </div>
</body>
</html>
