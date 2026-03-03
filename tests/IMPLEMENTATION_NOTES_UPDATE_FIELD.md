# Implementation Notes - updateField() Action

## Overview
Implementação da ação `update_field` no ActionExecutor para atualizar campos customizados em registros de contatos.

## Requirements Addressed
- **Requirement 4.8**: WHEN action_type é 'update_field', THE AutomationEngine SHALL atualizar o campo customizado especificado no contato

## Implementation Details

### Database Schema
A implementação utiliza uma coluna JSON chamada `custom_fields` na tabela `contacts` para armazenar campos customizados de forma flexível.

**Auto-Migration:**
- Se a coluna `custom_fields` não existir, ela é criada automaticamente na primeira execução
- Tipo: JSON DEFAULT NULL
- Posição: Após a coluna `email`

### Method Signature
```php
private function updateField(array $config, array $context): array
```

### Configuration Parameters
- **field** (required): Nome do campo customizado a ser atualizado
- **value** (optional): Valor a ser definido (null ou string vazia remove o campo)

### Context Requirements
- **contact_id** (preferred): ID do contato a ser atualizado
- **phone** (fallback): Telefone do contato (usado para buscar o ID se contact_id não estiver disponível)

### Features Implemented

#### 1. Field Validation
- Valida que o nome do campo não está vazio
- Valida que o nome do campo é uma string válida
- Retorna erro descritivo se validação falhar

#### 2. Contact Resolution
- Prioriza `contact_id` do contexto
- Se `contact_id` não disponível, busca contato por `phone`
- Valida que o contato pertence ao usuário correto
- Retorna erro se contato não encontrado

#### 3. Variable Substitution
- Suporta substituição de variáveis no valor usando VariableSubstitutor
- Variáveis disponíveis: {{contact_name}}, {{message}}, {{ai_response}}, etc.
- Exemplo: `"Última mensagem: {{message}}"` → `"Última mensagem: Olá, preciso de ajuda"`

#### 4. Field Operations
- **Update**: Define ou atualiza valor do campo
- **Remove**: Remove campo se valor é null ou string vazia
- Preserva outros campos customizados existentes
- Retorna valor anterior para auditoria

#### 5. Error Isolation
- Erros são capturados e registrados
- Não interrompem execução de outras ações
- Retornam status 'failed' com mensagem de erro descritiva

### Return Structure
```php
[
    'action' => 'update_field',
    'status' => 'success',
    'contact_id' => 123,
    'field' => 'empresa',
    'value' => 'Acme Corp',
    'old_value' => 'Old Corp',
    'operation' => 'updated', // ou 'removed'
    'timestamp' => 1234567890
]
```

### Error Handling

#### Validation Errors
- Campo vazio ou inválido
- Contato não encontrado
- contact_id não disponível e phone não resolve

#### Database Errors
- Falha ao criar coluna custom_fields
- Falha ao atualizar registro
- Contato não pertence ao usuário

#### All Errors
- Registrados em error_log
- Retornam status 'failed'
- Incluem mensagem de erro descritiva
- Não interrompem outras ações

## Usage Examples

### Example 1: Simple Field Update
```php
$action = [
    'type' => 'update_field',
    'config' => [
        'field' => 'empresa',
        'value' => 'Acme Corp'
    ]
];
```

### Example 2: Variable Substitution
```php
$action = [
    'type' => 'update_field',
    'config' => [
        'field' => 'ultima_interacao',
        'value' => 'Contato {{contact_name}} em {{timestamp}}'
    ]
];
```

### Example 3: Remove Field
```php
$action = [
    'type' => 'update_field',
    'config' => [
        'field' => 'campo_temporario',
        'value' => '' // ou null
    ]
];
```

### Example 4: AI Response Storage
```php
$action = [
    'type' => 'update_field',
    'config' => [
        'field' => 'ultima_resposta_ia',
        'value' => '{{ai_response}}'
    ]
];
```

## Testing

### Manual Test File
`tests/manual_test_update_field.php`

### Test Coverage
1. ✓ Adicionar campo customizado simples
2. ✓ Adicionar múltiplos campos
3. ✓ Atualizar campo existente
4. ✓ Substituição de variáveis
5. ✓ Remover campo (valor vazio)
6. ✓ Buscar contato por telefone
7. ✓ Validação de campo vazio
8. ✓ Validação de contato inexistente

### Running Tests
```bash
php tests/manual_test_update_field.php
```

## Integration with Automation Flows

### JSON Configuration Example
```json
{
  "actions": [
    {
      "type": "update_field",
      "config": {
        "field": "status_lead",
        "value": "qualificado"
      }
    },
    {
      "type": "update_field",
      "config": {
        "field": "interesse",
        "value": "{{ai_response}}"
      }
    }
  ]
}
```

### Use Cases
1. **Lead Qualification**: Armazenar status de qualificação
2. **Interaction Tracking**: Registrar última interação
3. **AI Insights**: Salvar análises da IA
4. **Custom Segmentation**: Adicionar tags/categorias customizadas
5. **Business Data**: Armazenar empresa, cargo, setor, etc.

## Performance Considerations

### Database Impact
- Usa índice existente em `user_id` e `phone`
- JSON column permite queries eficientes com JSON_EXTRACT
- Auto-migration executa apenas uma vez

### Optimization Tips
- Evitar campos muito grandes (> 1KB)
- Usar nomes de campos consistentes
- Limpar campos não utilizados periodicamente

## Security Considerations

### Input Validation
- Nome do campo é validado (não vazio, string)
- Valor é sanitizado via JSON encoding
- User_id sempre validado

### Access Control
- Apenas contatos do usuário podem ser atualizados
- Validação de ownership em todas as queries

## Future Enhancements

### Possible Improvements
1. Field type validation (string, number, boolean, date)
2. Field value constraints (min/max, regex)
3. Field history/audit trail
4. Bulk field updates
5. Field templates/presets
6. Custom field definitions UI

## Related Files
- `includes/ActionExecutor.php` - Main implementation
- `includes/VariableSubstitutor.php` - Variable substitution
- `tests/manual_test_update_field.php` - Manual tests
- `database/setup_local_database.sql` - Database schema

## Compliance
- ✓ Follows error isolation pattern
- ✓ Supports variable substitution
- ✓ Validates field existence (via contact lookup)
- ✓ Handles errors gracefully
- ✓ Logs all operations
- ✓ Returns detailed results
