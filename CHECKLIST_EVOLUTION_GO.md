# ✅ Checklist - Implementação Evolution Go API

Use este checklist para garantir que a implementação foi concluída corretamente.

---

## 📋 Pré-Requisitos

- [ ] Evolution Go está rodando no Easypanel em `https://evogo.macip.com.br`
- [ ] Você tem acesso ao banco de dados MySQL
- [ ] Você tem acesso ao sistema via navegador
- [ ] Você tem um número de WhatsApp para conectar

---

## 🗄️ Banco de Dados

### Executar Migration

- [ ] Abrir terminal/SSH no servidor
- [ ] Executar comando:
  ```bash
  mysql -u root -p whatsapp_sender < migrations/add_evolution_go_support.sql
  ```
- [ ] Verificar se não houve erros
- [ ] Confirmar mensagem: "✅ Migration Evolution Go concluída!"

### Verificar Estrutura

Execute no MySQL:
```sql
-- Verificar coluna evolution_go_instance
SHOW COLUMNS FROM users LIKE 'evolution_go_instance';

-- Verificar coluna evolution_go_token
SHOW COLUMNS FROM users LIKE 'evolution_go_token';

-- Verificar ENUM whatsapp_provider
SHOW COLUMNS FROM users LIKE 'whatsapp_provider';
```

- [ ] Coluna `evolution_go_instance` existe
- [ ] Coluna `evolution_go_token` existe
- [ ] ENUM `whatsapp_provider` contém 'evolution-go'

---

## 🔧 Configuração do Sistema

### Constantes PHP

Verificar em `config/database.php`:

- [ ] Constante `EVOLUTION_GO_API_URL` definida
- [ ] Constante `EVOLUTION_GO_API_KEY` definida
- [ ] Valores corretos:
  - URL: `https://evogo.macip.com.br`
  - API Key: `a9F3kLm8Qz2XvP7rT1bYcN6dE4uHsJ5W`

### Arquivos Criados

- [ ] `includes/channels/providers/EvolutionGoProvider.php` existe
- [ ] `migrations/add_evolution_go_support.sql` existe
- [ ] `test_evolution_go.php` existe
- [ ] `EVOLUTION_GO_INTEGRATION.md` existe
- [ ] `RESUMO_EVOLUTION_GO.md` existe

### Arquivos Modificados

- [ ] `config/database.php` - constantes adicionadas
- [ ] `includes/channels/WhatsAppChannel.php` - import e factory atualizados
- [ ] `api/send_media.php` - suporte Evolution Go adicionado
- [ ] `api/chat_send.php` - função sendMessageViaEvolutionGo adicionada
- [ ] `my_instance.php` - UI e processamento Evolution Go adicionados

---

## 🧪 Testes

### Teste 1: Script de Verificação

- [ ] Acessar `http://seu-dominio.com/test_evolution_go.php`
- [ ] Seção "Configuração do Sistema": ✅ Configuração OK
- [ ] Seção "Estrutura do Banco de Dados": ✅ Banco de Dados OK
- [ ] Seção "Classes PHP": ✅ Todas carregadas
- [ ] Seção "Conectividade": HTTP Status 200 ou 404 (normal)
- [ ] Resumo final: "✅ Sistema pronto para usar Evolution Go API!"

### Teste 2: Interface do Usuário

- [ ] Fazer login no sistema
- [ ] Acessar "Minha Instância"
- [ ] Dropdown de provider contém "Evolution Go API (Alta Performance)"
- [ ] Selecionar Evolution Go mostra seção de configuração
- [ ] Campos visíveis: Instance ID e API Key
- [ ] Informações sobre Evolution Go aparecem

### Teste 3: Configuração

- [ ] Selecionar "Evolution Go API (Alta Performance)"
- [ ] Preencher Instance ID: `teste-evolution-go`
- [ ] Preencher API Key: `a9F3kLm8Qz2XvP7rT1bYcN6dE4uHsJ5W`
- [ ] Clicar em "Salvar Configurações"
- [ ] Mensagem de sucesso aparece
- [ ] Página recarrega automaticamente

### Teste 4: Conexão WhatsApp

- [ ] Botão "Gerar QR Code para Conectar" aparece
- [ ] Clicar no botão
- [ ] QR Code é exibido
- [ ] Abrir WhatsApp no celular
- [ ] Menu > Dispositivos conectados > Conectar dispositivo
- [ ] Escanear QR Code
- [ ] Aguardar conexão (página recarrega automaticamente)
- [ ] Status muda para "Conectado e pronto para enviar mensagens!"

### Teste 5: Envio de Mensagens

- [ ] Acessar chat/conversas
- [ ] Selecionar ou criar uma conversa
- [ ] Enviar mensagem de texto
- [ ] Mensagem aparece no chat
- [ ] Mensagem chega no WhatsApp do destinatário
- [ ] Verificar logs: `[CHAT_SEND_EVOGO]` aparece

### Teste 6: Envio de Mídia

- [ ] Na mesma conversa, clicar em anexar arquivo
- [ ] Enviar uma imagem
- [ ] Imagem aparece no chat
- [ ] Imagem chega no WhatsApp do destinatário
- [ ] Enviar um documento PDF
- [ ] Documento aparece no chat
- [ ] Documento chega no WhatsApp do destinatário

### Teste 7: Fallback

- [ ] Desconectar Evolution Go (ou parar serviço no Easypanel)
- [ ] Configurar Evolution API normal em "Minha Instância"
- [ ] Tentar enviar mensagem
- [ ] Sistema deve usar Evolution API automaticamente
- [ ] Mensagem de aviso: "Enviado via Evolution API (fallback)"

---

## 📊 Verificação de Logs

### Logs do Evolution Go (Easypanel)

```bash
docker logs -f evolution-go-container
```

Verificar:
- [ ] Servidor iniciou corretamente
- [ ] Porta 8090 está escutando
- [ ] Não há erros críticos

### Logs do PHP

```bash
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/php-fpm/error.log
```

Verificar:
- [ ] Logs `[EVOLUTION_GO]` aparecem ao enviar mensagens
- [ ] Logs `[CHAT_SEND_EVOGO]` aparecem ao enviar texto
- [ ] Logs `[SEND_MEDIA]` aparecem ao enviar mídia
- [ ] Não há erros PHP (syntax error, fatal error, etc)

---

## 🔍 Verificação no Banco de Dados

Execute no MySQL:

```sql
-- Verificar usuário configurado
SELECT 
    id, name, whatsapp_provider, 
    evolution_go_instance, evolution_go_token
FROM users 
WHERE whatsapp_provider = 'evolution-go';
```

- [ ] Usuário aparece na lista
- [ ] `whatsapp_provider` = 'evolution-go'
- [ ] `evolution_go_instance` preenchido
- [ ] `evolution_go_token` preenchido

```sql
-- Verificar mensagens enviadas
SELECT 
    id, conversation_id, message_text, 
    from_me, created_at
FROM chat_messages 
WHERE from_me = 1 
ORDER BY created_at DESC 
LIMIT 5;
```

- [ ] Mensagens de teste aparecem
- [ ] `from_me` = 1 (mensagens enviadas)
- [ ] Timestamps corretos

---

## ⚠️ Troubleshooting

### Problema: Migration falha

**Sintomas**: Erro ao executar migration SQL

**Soluções**:
- [ ] Verificar credenciais do MySQL
- [ ] Verificar se banco `whatsapp_sender` existe
- [ ] Executar migration novamente (é seguro re-executar)
- [ ] Verificar permissões do usuário MySQL

### Problema: QR Code não aparece

**Sintomas**: Botão clicado mas QR Code não exibe

**Soluções**:
- [ ] Verificar se Evolution Go está rodando
- [ ] Testar URL: `curl https://evogo.macip.com.br/`
- [ ] Verificar logs do Evolution Go no Easypanel
- [ ] Verificar API Key está correta
- [ ] Verificar logs do PHP (error_log)

### Problema: Mensagens não enviam

**Sintomas**: Erro ao tentar enviar mensagem

**Soluções**:
- [ ] Verificar se WhatsApp está conectado (status "open")
- [ ] Verificar formato do número (5511999999999)
- [ ] Verificar logs: `[CHAT_SEND_EVOGO]` ou `[SEND_MEDIA]`
- [ ] Testar com Evolution API (fallback)
- [ ] Verificar se Evolution Go está respondendo

### Problema: Erro "Instância não configurada"

**Sintomas**: Erro ao tentar enviar mensagem

**Soluções**:
- [ ] Verificar se salvou configurações em "Minha Instância"
- [ ] Verificar se Instance ID e API Key estão preenchidos
- [ ] Verificar no banco: `SELECT evolution_go_instance FROM users WHERE id = X`
- [ ] Reconfigurar Evolution Go

---

## ✅ Checklist Final

Marque todos os itens abaixo para confirmar implementação completa:

### Banco de Dados
- [ ] Migration executada com sucesso
- [ ] Colunas `evolution_go_instance` e `evolution_go_token` existem
- [ ] ENUM `whatsapp_provider` contém 'evolution-go'

### Arquivos
- [ ] Todos os arquivos criados estão presentes
- [ ] Todos os arquivos modificados foram atualizados
- [ ] Sem erros de sintaxe PHP

### Configuração
- [ ] Constantes definidas em `config/database.php`
- [ ] Evolution Go configurado em "Minha Instância"
- [ ] WhatsApp conectado via QR Code

### Testes
- [ ] Script `/test_evolution_go.php` passa em todas as verificações
- [ ] Mensagem de texto enviada com sucesso
- [ ] Mídia (imagem/documento) enviada com sucesso
- [ ] Fallback funciona corretamente

### Logs
- [ ] Logs do Evolution Go sem erros críticos
- [ ] Logs do PHP mostram `[EVOLUTION_GO]` e `[CHAT_SEND_EVOGO]`
- [ ] Sem erros PHP (syntax, fatal, etc)

---

## 🎉 Conclusão

Se todos os itens acima estão marcados, a implementação da Evolution Go API está **COMPLETA E FUNCIONAL**!

Você agora tem:
- ✅ Suporte completo a Evolution Go API
- ✅ Fallback automático entre providers
- ✅ Interface amigável para configuração
- ✅ Melhor performance e estabilidade
- ✅ Documentação completa

**Próximos passos**:
1. Monitorar performance em produção
2. Configurar webhooks para recebimento de mensagens
3. Ajustar configurações conforme necessidade
4. Treinar equipe no uso do novo provider

---

**Data**: 27/03/2026  
**Versão**: 1.0  
**Status**: ✅ PRONTO PARA PRODUÇÃO
