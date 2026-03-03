<?php
/**
 * Manual Test - updateField() Action
 * 
 * Testa a ação de atualizar campos customizados em contatos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/VariableSubstitutor.php';
require_once __DIR__ . '/../includes/ActionExecutor.php';

echo "=== TESTE: updateField() Action ===\n\n";

// Configuração de teste
$userId = 1;
$instanceConfig = [
    'name' => 'test_instance',
    'api_key' => 'test_key',
    'api_url' => 'https://api.evolution.test'
];

try {
    // Obter conexão com banco
    $pdo = getDBConnection();
    echo "✓ Conexão com banco estabelecida\n\n";
    
    // Criar ActionExecutor
    $executor = new ActionExecutor($pdo, $userId, $instanceConfig);
    echo "✓ ActionExecutor criado\n\n";
    
    // Criar contato de teste
    $testPhone = '5511999887766';
    $stmt = $pdo->prepare("
        INSERT INTO contacts (user_id, name, phone, email, source)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
    ");
    $stmt->execute([
        $userId,
        'Contato Teste UpdateField',
        $testPhone,
        'teste@updatefield.com',
        'whatsapp'
    ]);
    $contactId = $pdo->lastInsertId();
    echo "✓ Contato de teste criado (ID: {$contactId})\n\n";
    
    // Contexto de teste
    $context = [
        'contact_id' => $contactId,
        'phone' => $testPhone,
        'contact_name' => 'Contato Teste UpdateField',
        'contact_email' => 'teste@updatefield.com',
        'message' => 'Mensagem de teste',
        'ai_response' => 'Resposta da IA de teste',
        'conversation_id' => 999,
        'channel' => 'whatsapp',
        'timestamp' => time()
    ];
    
    // Teste 1: Adicionar campo customizado simples
    echo "--- Teste 1: Adicionar campo customizado ---\n";
    $action1 = [
        'type' => 'update_field',
        'config' => [
            'field' => 'empresa',
            'value' => 'Acme Corp'
        ]
    ];
    
    $result1 = $executor->executeActions([$action1], $context);
    echo "Resultado: " . json_encode($result1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if ($result1[0]['status'] === 'success') {
        echo "✓ Campo 'empresa' adicionado com sucesso\n\n";
    } else {
        echo "✗ Falha ao adicionar campo: " . ($result1[0]['error'] ?? 'unknown') . "\n\n";
    }
    
    // Teste 2: Adicionar outro campo
    echo "--- Teste 2: Adicionar campo 'cargo' ---\n";
    $action2 = [
        'type' => 'update_field',
        'config' => [
            'field' => 'cargo',
            'value' => 'Gerente de Vendas'
        ]
    ];
    
    $result2 = $executor->executeActions([$action2], $context);
    echo "Resultado: " . json_encode($result2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if ($result2[0]['status'] === 'success') {
        echo "✓ Campo 'cargo' adicionado com sucesso\n\n";
    }
    
    // Teste 3: Atualizar campo existente
    echo "--- Teste 3: Atualizar campo existente ---\n";
    $action3 = [
        'type' => 'update_field',
        'config' => [
            'field' => 'empresa',
            'value' => 'Nova Empresa Ltda'
        ]
    ];
    
    $result3 = $executor->executeActions([$action3], $context);
    echo "Resultado: " . json_encode($result3, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if ($result3[0]['status'] === 'success') {
        echo "✓ Campo 'empresa' atualizado com sucesso\n";
        echo "  Valor anterior: " . ($result3[0]['data']['old_value'] ?? 'null') . "\n";
        echo "  Novo valor: " . ($result3[0]['data']['value'] ?? 'null') . "\n\n";
    }
    
    // Teste 4: Substituição de variáveis
    echo "--- Teste 4: Substituição de variáveis ---\n";
    $action4 = [
        'type' => 'update_field',
        'config' => [
            'field' => 'ultima_mensagem',
            'value' => 'Contato {{contact_name}} disse: {{message}}'
        ]
    ];
    
    $result4 = $executor->executeActions([$action4], $context);
    echo "Resultado: " . json_encode($result4, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if ($result4[0]['status'] === 'success') {
        echo "✓ Variáveis substituídas corretamente\n";
        echo "  Valor final: " . ($result4[0]['data']['value'] ?? 'null') . "\n\n";
    }
    
    // Teste 5: Remover campo (valor vazio)
    echo "--- Teste 5: Remover campo ---\n";
    $action5 = [
        'type' => 'update_field',
        'config' => [
            'field' => 'cargo',
            'value' => ''
        ]
    ];
    
    $result5 = $executor->executeActions([$action5], $context);
    echo "Resultado: " . json_encode($result5, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if ($result5[0]['status'] === 'success' && $result5[0]['data']['operation'] === 'removed') {
        echo "✓ Campo 'cargo' removido com sucesso\n\n";
    }
    
    // Teste 6: Buscar contato por telefone (sem contact_id)
    echo "--- Teste 6: Buscar contato por telefone ---\n";
    $contextWithoutId = $context;
    unset($contextWithoutId['contact_id']);
    
    $action6 = [
        'type' => 'update_field',
        'config' => [
            'field' => 'teste_sem_id',
            'value' => 'Valor de teste'
        ]
    ];
    
    $result6 = $executor->executeActions([$action6], $contextWithoutId);
    echo "Resultado: " . json_encode($result6, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if ($result6[0]['status'] === 'success') {
        echo "✓ Contato encontrado por telefone e campo atualizado\n\n";
    }
    
    // Verificar estado final dos custom_fields
    echo "--- Estado Final dos Custom Fields ---\n";
    $stmt = $pdo->prepare("
        SELECT custom_fields 
        FROM contacts 
        WHERE id = ?
    ");
    $stmt->execute([$contactId]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($contact) {
        $customFields = json_decode($contact['custom_fields'], true);
        echo "Custom Fields: " . json_encode($customFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }
    
    // Teste 7: Validação - campo vazio
    echo "--- Teste 7: Validação - campo vazio ---\n";
    $action7 = [
        'type' => 'update_field',
        'config' => [
            'field' => '',
            'value' => 'teste'
        ]
    ];
    
    $result7 = $executor->executeActions([$action7], $context);
    echo "Resultado: " . json_encode($result7, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if ($result7[0]['status'] === 'failed') {
        echo "✓ Validação funcionou: campo vazio rejeitado\n\n";
    }
    
    // Teste 8: Validação - contato não encontrado
    echo "--- Teste 8: Validação - contato não encontrado ---\n";
    $contextInvalid = $context;
    $contextInvalid['contact_id'] = 999999;
    
    $action8 = [
        'type' => 'update_field',
        'config' => [
            'field' => 'teste',
            'value' => 'valor'
        ]
    ];
    
    $result8 = $executor->executeActions([$action8], $contextInvalid);
    echo "Resultado: " . json_encode($result8, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if ($result8[0]['status'] === 'failed') {
        echo "✓ Validação funcionou: contato inexistente rejeitado\n\n";
    }
    
    echo "=== TODOS OS TESTES CONCLUÍDOS ===\n";
    
} catch (Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
