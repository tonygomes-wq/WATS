# 🔧 Solução - Erro de Migration Evolution Go

## Problema

Ao executar a migration SQL, você recebeu o erro:
```
#1044 - Acesso negado para o usuário 'root'@'%' ao banco de dados 'information_schema'
```

## Causa

A migration original usava queries condicionais que acessam `INFORMATION_SCHEMA`, mas seu usuário MySQL não tem permissão para acessar esse banco.

## ✅ Soluções (3 opções)

---

### OPÇÃO 1: Via Navegador (MAIS FÁCIL) ⭐ RECOMENDADO

Acesse no seu navegador:
```
http://seu-dominio.com/migrations/run_evolution_go_migration.php
```

Este script PHP irá:
- ✅ Executar cada comando SQL individualmente
- ✅ Ignorar erros de duplicação automaticamente
- ✅ Mostrar resultado visual de cada passo
- ✅ Verificar se tudo foi criado corretamente
- ✅ Não precisa de acesso SSH ou terminal

**Vantagens**:
- Interface visual amigável
- Não precisa de terminal
- Ignora erros automaticamente
- Mostra exatamente o que foi feito

---

### OPÇÃO 2: Via phpMyAdmin (FÁCIL)

1. Acesse seu **phpMyAdmin**
2. Selecione o banco de dados **whatsapp_sender**
3. Clique na aba **SQL**
4. Copie e cole os comandos abaixo:

```sql
-- Passo 1: Atualizar ENUM
ALTER TABLE users 
MODIFY COLUMN whatsapp_provider 
ENUM('evolution', 'zapi', 'meta', 'baileys', 'evolution-go') 
DEFAULT 'evolution';

-- Passo 2: Adicionar coluna instance
ALTER TABLE users 
ADD COLUMN evolution_go_instance VARCHAR(100) NULL 
COMMENT 'Instance ID da Evolution Go' 
AFTER zapi_client_token;

-- Passo 3: Adicionar coluna token
ALTER TABLE users 
ADD COLUMN evolution_go_token VARCHAR(255) NULL 
COMMENT 'Token/API Key da Evolution Go' 
AFTER evolution_go_instance;

-- Passo 4: Criar índice
CREATE INDEX idx_evolution_go_instance ON users(evolution_go_instance);

-- Verificar
SHOW COLUMNS FROM users LIKE 'evolution_go%';
```

5. Clique em **Executar**
6. **IMPORTANTE**: Se aparecer erro "Duplicate column name" ou "Duplicate key name", **IGNORE** - significa que já existe

**Vantagens**:
- Interface visual
- Pode executar comando por comando
- Fácil de verificar erros

---

### OPÇÃO 3: Via Terminal/SSH (AVANÇADO)

Se você tem acesso SSH ao servidor:

```bash
# Conectar ao MySQL
mysql -u root -p whatsapp_sender

# Depois, dentro do MySQL, execute:
```

```sql
ALTER TABLE users 
MODIFY COLUMN whatsapp_provider 
ENUM('evolution', 'zapi', 'meta', 'baileys', 'evolution-go') 
DEFAULT 'evolution';

ALTER TABLE users 
ADD COLUMN evolution_go_instance VARCHAR(100) NULL 
COMMENT 'Instance ID da Evolution Go' 
AFTER zapi_client_token;

ALTER TABLE users 
ADD COLUMN evolution_go_token VARCHAR(255) NULL 
COMMENT 'Token/API Key da Evolution Go' 
AFTER evolution_go_instance;

CREATE INDEX idx_evolution_go_instance ON users(evolution_go_instance);

SHOW COLUMNS FROM users LIKE 'evolution_go%';
```

---

## 🔍 Como Verificar se Funcionou

### Via Navegador
Acesse: `http://seu-dominio.com/test_evolution_go.php`

Verifique a seção "Estrutura do Banco de Dados":
- ✅ Coluna evolution_go_instance: Existe
- ✅ Coluna evolution_go_token: Existe
- ✅ ENUM evolution-go: Existe

### Via phpMyAdmin
1. Selecione tabela `users`
2. Clique em "Estrutura"
3. Procure por:
   - `evolution_go_instance` (VARCHAR 100)
   - `evolution_go_token` (VARCHAR 255)
4. Verifique campo `whatsapp_provider`:
   - Deve conter: evolution, zapi, meta, baileys, **evolution-go**

### Via SQL
Execute no phpMyAdmin ou terminal:

```sql
-- Verificar colunas
SHOW COLUMNS FROM users LIKE 'evolution_go%';

-- Verificar ENUM
SHOW COLUMNS FROM users LIKE 'whatsapp_provider';
```

Resultado esperado:
```
evolution_go_instance | varchar(100) | YES
evolution_go_token    | varchar(255) | YES
```

---

## ⚠️ Erros Comuns e Como Resolver

### Erro: "Duplicate column name 'evolution_go_instance'"
**Significado**: A coluna já existe  
**Solução**: IGNORE - está tudo certo! Continue para o próximo comando

### Erro: "Duplicate key name 'idx_evolution_go_instance'"
**Significado**: O índice já existe  
**Solução**: IGNORE - está tudo certo!

### Erro: "Column 'zapi_client_token' doesn't exist"
**Solução**: Remova o `AFTER zapi_client_token` do comando:
```sql
ALTER TABLE users 
ADD COLUMN evolution_go_instance VARCHAR(100) NULL 
COMMENT 'Instance ID da Evolution Go';
```

### Erro: "Access denied"
**Solução**: Use a **OPÇÃO 1** (via navegador) que não precisa de permissões especiais

---

## 📋 Checklist Pós-Migration

Após executar a migration, verifique:

- [ ] Acessei `/migrations/run_evolution_go_migration.php` OU executei SQL manualmente
- [ ] Todos os passos foram executados (ou mostraram "já existe")
- [ ] Acessei `/test_evolution_go.php` e está tudo ✅
- [ ] Posso acessar "Minha Instância" no sistema
- [ ] Dropdown mostra "Evolution Go API (Alta Performance)"
- [ ] Posso selecionar Evolution Go e ver os campos

---

## 🎯 Próximos Passos

Depois que a migration funcionar:

1. ✅ Acesse "Minha Instância"
2. ✅ Selecione "Evolution Go API (Alta Performance)"
3. ✅ Preencha Instance ID e API Key
4. ✅ Salve as configurações
5. ✅ Gere QR Code e conecte WhatsApp

---

## 💡 Dica

A **OPÇÃO 1** (via navegador) é a mais fácil e recomendada porque:
- Não precisa de terminal ou SSH
- Interface visual clara
- Ignora erros automaticamente
- Mostra exatamente o que aconteceu
- Funciona mesmo sem permissões especiais no MySQL

**Acesse agora**: `http://seu-dominio.com/migrations/run_evolution_go_migration.php`

---

**Última atualização**: 27/03/2026  
**Status**: ✅ Solução Testada e Funcional
