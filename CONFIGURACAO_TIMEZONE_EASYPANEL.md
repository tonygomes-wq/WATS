# ⏰ Configuração de Timezone no Easypanel

**Data:** 27/03/2026  
**Objetivo:** Configurar timezone do Brasil (America/Sao_Paulo) no container Docker

---

## 🎯 Solução Rápida

### No Easypanel - Adicionar Variável de Ambiente

1. Acesse seu projeto no Easypanel
2. Vá em **Environment Variables**
3. Adicione a variável:

```
TZ=America/Sao_Paulo
```

4. Salve e faça **Redeploy** do container

---

## 📝 Configurações Necessárias

### 1. Variável de Ambiente no Docker Compose

Adicione no `docker-compose.yml`:

```yaml
services:
  wats-app:
    environment:
      # ... outras variáveis ...
      
      # Timezone
      TZ: ${TZ:-America/Sao_Paulo}
```

### 2. Variável no arquivo .env

Adicione no `.env`:

```bash
# Timezone
TZ=America/Sao_Paulo
```

### 3. Configuração no PHP (config/database.php)

Adicione no início do arquivo `config/database.php`:

```php
<?php
// Configurar timezone do PHP
date_default_timezone_set(getenv('TZ') ?: 'America/Sao_Paulo');
```

### 4. Configuração no Dockerfile (se usar)

Adicione no `Dockerfile`:

```dockerfile
# Configurar timezone
ENV TZ=America/Sao_Paulo
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
```

---

## 🌍 Timezones Disponíveis

### Brasil
```
America/Sao_Paulo       # Brasília (UTC-3)
America/Manaus          # Amazonas (UTC-4)
America/Rio_Branco      # Acre (UTC-5)
America/Fortaleza       # Ceará (UTC-3)
America/Recife          # Pernambuco (UTC-3)
America/Belem           # Pará (UTC-3)
America/Cuiaba          # Mato Grosso (UTC-4)
America/Porto_Velho     # Rondônia (UTC-4)
America/Boa_Vista       # Roraima (UTC-4)
America/Campo_Grande    # Mato Grosso do Sul (UTC-4)
America/Noronha         # Fernando de Noronha (UTC-2)
```

### Outros Países (Exemplos)
```
America/New_York        # Nova York (UTC-5/-4)
America/Los_Angeles     # Los Angeles (UTC-8/-7)
Europe/London           # Londres (UTC+0/+1)
Europe/Paris            # Paris (UTC+1/+2)
Asia/Tokyo              # Tóquio (UTC+9)
Australia/Sydney        # Sydney (UTC+10/+11)
```

---

## 🔍 Verificar Timezone Atual

### Via PHP
Crie um arquivo `test_timezone.php`:

```php
<?php
echo "Timezone PHP: " . date_default_timezone_get() . "\n";
echo "Data/Hora atual: " . date('Y-m-d H:i:s') . "\n";
echo "Timezone do sistema: " . getenv('TZ') . "\n";
```

### Via Terminal (dentro do container)
```bash
# Ver timezone do sistema
date
echo $TZ
cat /etc/timezone

# Ver timezone do PHP
php -r "echo date_default_timezone_get();"
```

---

## 🐛 Problemas Comuns

### 1. Timezone não muda após adicionar variável

**Solução:** Fazer redeploy completo do container
```bash
# No Easypanel, clicar em "Redeploy"
# Ou via CLI:
docker-compose down
docker-compose up -d
```

### 2. PHP ainda usa UTC

**Solução:** Adicionar `date_default_timezone_set()` no código

Edite `config/database.php` e adicione no início:
```php
date_default_timezone_set(getenv('TZ') ?: 'America/Sao_Paulo');
```

### 3. Horários no banco de dados errados

**Solução:** Configurar timezone do MySQL também

No `docker-compose.yml`:
```yaml
mysql:
  environment:
    TZ: America/Sao_Paulo
  command: --default-time-zone='-03:00'
```

### 4. Cron jobs rodando em horário errado

**Solução:** Garantir que a variável TZ está disponível no cron

No `docker/crontab`:
```cron
TZ=America/Sao_Paulo
*/5 * * * * php /var/www/html/cron/sync_teams_messages.php
```

---

## 📋 Checklist de Implementação

### Passo 1: Adicionar Variável no Easypanel
- [ ] Acessar painel do Easypanel
- [ ] Ir em Environment Variables
- [ ] Adicionar `TZ=America/Sao_Paulo`
- [ ] Salvar

### Passo 2: Atualizar Código
- [ ] Editar `config/database.php`
- [ ] Adicionar `date_default_timezone_set()`
- [ ] Commit e push para Git

### Passo 3: Atualizar Docker Compose
- [ ] Editar `docker-compose.yml`
- [ ] Adicionar variável TZ
- [ ] Commit e push

### Passo 4: Redeploy
- [ ] Fazer redeploy no Easypanel
- [ ] Aguardar container reiniciar

### Passo 5: Testar
- [ ] Acessar `test_timezone.php`
- [ ] Verificar logs com data/hora
- [ ] Testar criação de registros no banco
- [ ] Verificar horário dos cron jobs

---

## 🧪 Script de Teste Completo

Crie `test_timezone.php` na raiz do projeto:

```php
<?php
require_once 'config/database.php';

echo "<h1>Teste de Timezone</h1>";

echo "<h2>1. Configuração do Sistema</h2>";
echo "<pre>";
echo "Variável TZ: " . (getenv('TZ') ?: 'não definida') . "\n";
echo "Timezone PHP: " . date_default_timezone_get() . "\n";
echo "</pre>";

echo "<h2>2. Data e Hora Atual</h2>";
echo "<pre>";
echo "date('Y-m-d H:i:s'): " . date('Y-m-d H:i:s') . "\n";
echo "date('c'): " . date('c') . "\n";
echo "date('r'): " . date('r') . "\n";
echo "time(): " . time() . "\n";
echo "</pre>";

echo "<h2>3. Teste com Banco de Dados</h2>";
echo "<pre>";
try {
    // Inserir registro de teste
    $stmt = $pdo->prepare("INSERT INTO test_timezone (created_at) VALUES (NOW())");
    $stmt->execute();
    $id = $pdo->lastInsertId();
    
    // Buscar registro
    $stmt = $pdo->prepare("SELECT created_at FROM test_timezone WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    echo "Horário no banco: " . $row['created_at'] . "\n";
    
    // Limpar teste
    $stmt = $pdo->prepare("DELETE FROM test_timezone WHERE id = ?");
    $stmt->execute([$id]);
    
    echo "✅ Teste concluído com sucesso!\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Nota: Tabela test_timezone pode não existir. Criar com:\n";
    echo "CREATE TABLE test_timezone (id INT AUTO_INCREMENT PRIMARY KEY, created_at DATETIME);\n";
}
echo "</pre>";

echo "<h2>4. Comparação de Fusos</h2>";
echo "<pre>";
$timezones = [
    'UTC',
    'America/Sao_Paulo',
    'America/New_York',
    'Europe/London'
];

foreach ($timezones as $tz) {
    $dt = new DateTime('now', new DateTimeZone($tz));
    echo "$tz: " . $dt->format('Y-m-d H:i:s P') . "\n";
}
echo "</pre>";
?>
```

---

## 📊 Impacto da Mudança

### Antes (UTC)
```
Horário do servidor: 2026-03-27 15:30:00 (UTC)
Horário no Brasil:   2026-03-27 12:30:00 (BRT)
Diferença: -3 horas
```

### Depois (America/Sao_Paulo)
```
Horário do servidor: 2026-03-27 12:30:00 (BRT)
Horário no Brasil:   2026-03-27 12:30:00 (BRT)
Diferença: 0 horas ✅
```

### Áreas Afetadas
- ✅ Timestamps de mensagens no chat
- ✅ Logs do sistema
- ✅ Horário de execução dos cron jobs
- ✅ Relatórios e analytics
- ✅ Backups automáticos
- ✅ Notificações agendadas

---

## 🔗 Referências

- [PHP Timezones](https://www.php.net/manual/en/timezones.php)
- [Docker Timezone](https://wiki.alpinelinux.org/wiki/Setting_the_timezone)
- [MySQL Timezone](https://dev.mysql.com/doc/refman/8.0/en/time-zone-support.html)
- [Easypanel Docs](https://easypanel.io/docs)

---

## ✅ Conclusão

Adicionar a variável `TZ=America/Sao_Paulo` no Easypanel é simples e resolve o problema de timezone. Lembre-se de:

1. ✅ Adicionar no Easypanel (Environment Variables)
2. ✅ Adicionar no código PHP (`date_default_timezone_set()`)
3. ✅ Fazer redeploy do container
4. ✅ Testar com o script de teste

**Tempo estimado:** 5-10 minutos

---

**Precisa de ajuda?** Verifique os logs do container ou execute o script de teste para diagnosticar problemas.
