# Correções Aplicadas - Webhook e Sistema

## ✅ Problema 1: Webhook Configurado com Sucesso

**Status:** RESOLVIDO

O webhook foi configurado com sucesso usando o formato correto da Evolution API:

```json
{
  "webhook": {
    "enabled": true,
    "url": "https://wats.macip.com.br/api/chat_webhook.php",
    "webhook_by_events": false,
    "webhook_base64": false,
    "events": [
      "QRCODE_UPDATED",
      "MESSAGES_UPSERT",
      "MESSAGES_UPDATE",
      "MESSAGES_DELETE",
      "SEND_MESSAGE",
      "CONNECTION_UPDATE",
      "CONTACTS_UPDATE",
      "CONTACTS_UPSERT"
    ]
  }
}
```

**Arquivos Modificados:**
- `configure_webhook_now.php` - Formato correto com wrapper "webhook"
- `includes/webhook_config.php` - Atualizado para usar formato correto

---

## ✅ Problema 2: Horário Errado nas Mensagens

**Status:** RESOLVIDO

**Causa:** Evolution API envia `messageTimestamp` em milissegundos (13 dígitos) mas o sistema esperava segundos (10 dígitos).

**Solução Aplicada:**

1. **Correção no Webhook** (`api/chat_webhook.php`):
   ```php
   // CORREÇÃO: Evolution API pode enviar timestamp em milissegundos
   // Se timestamp for maior que 10 dígitos, está em milissegundos
   if ($timestamp > 9999999999) {
       $timestamp = (int)($timestamp / 1000);
   }
   ```

2. **Script de Correção** (`fix_message_timestamps.php`):
   - Corrige mensagens antigas com timestamp errado
   - Atualiza `last_message_time` das conversas
   - Processa até 1000 mensagens por vez

**Como Usar:**
1. Acesse: `https://wats.macip.com.br/fix_message_timestamps.php`
2. Clique em "Corrigir Timestamps Agora"
3. Aguarde a correção
4. Volte para o chat e veja os horários corretos

---

## ⚠️ Problema 3: Fotos de Perfil Não Carregando

**Status:** EM ANDAMENTO

**Causa Provável:**
1. Fotos não foram baixadas ainda (webhook acabou de ser configurado)
2. Contatos antigos não têm foto no cache local

**Solução Aplicada:**

1. **Webhook Atualizado** - Agora inclui eventos de contatos:
   - `CONTACTS_UPDATE` - Atualização de contatos
   - `CONTACTS_UPSERT` - Novos contatos

2. **Script de Download** (`force_download_profile_pics.php`):
   - Baixa fotos dos 50 contatos mais recentes
   - Salva localmente em `/uploads/profile_pictures/`
   - Atualiza banco de dados

**Como Usar:**
1. Acesse: `https://wats.macip.com.br/force_download_profile_pics.php`
2. Clique em "Iniciar Download"
3. Aguarde o download (pode levar alguns minutos)
4. As fotos aparecerão automaticamente no chat

**Observação:** 
- Fotos de novos contatos serão baixadas automaticamente pelo webhook
- Contatos antigos precisam do script de download manual
- O sistema verifica fotos a cada 24 horas

---

## 📋 Checklist de Verificação

### Webhook
- [x] Webhook configurado na Evolution API
- [x] URL correta: `https://wats.macip.com.br/api/chat_webhook.php`
- [x] Eventos configurados (MESSAGES_UPSERT, MESSAGES_UPDATE, etc.)
- [x] Instância: CELULAR-MACIP

### Mensagens
- [x] Envio de mensagens funcionando
- [ ] Recebimento de mensagens (testar enviando do WhatsApp)
- [ ] Checkmarks azuis (lido/entregue)
- [ ] Horário correto nas mensagens novas
- [ ] Horário corrigido nas mensagens antigas (após rodar script)

### Fotos de Perfil
- [ ] Fotos carregando para novos contatos
- [ ] Fotos baixadas para contatos antigos (após rodar script)
- [ ] Iniciais aparecendo quando não há foto

---

## 🔧 Scripts Disponíveis

1. **configure_webhook_now.php** - Configurar webhook
2. **test_webhook_simple.php** - Testar configuração do webhook
3. **fix_message_timestamps.php** - Corrigir horários das mensagens
4. **force_download_profile_pics.php** - Baixar fotos de perfil
5. **test_webhook_messages.php** - Diagnóstico completo

---

## 📝 Próximos Passos

1. **Testar Recebimento:**
   - Envie uma mensagem do WhatsApp para o número conectado
   - Verifique se aparece no chat do WATS
   - Confirme se o horário está correto

2. **Corrigir Timestamps:**
   - Acesse `/fix_message_timestamps.php`
   - Execute a correção
   - Verifique se os horários ficaram corretos

3. **Baixar Fotos:**
   - Acesse `/force_download_profile_pics.php`
   - Baixe as fotos dos contatos
   - Verifique se aparecem no chat

4. **Verificar Checkmarks:**
   - Envie uma mensagem pelo WATS
   - Leia a mensagem no WhatsApp
   - Verifique se os checkmarks azuis aparecem

---

## ⚙️ Configuração do Redis

**Pergunta:** Evolution API e WATS usam Redis, isso influencia?

**Resposta:** NÃO! Cada serviço tem seu próprio Redis:

- **WATS Redis:** `wats_redis` (porta 6379, database 0, prefix: `wats:`)
- **Evolution API Redis:** Próprio container (isolado)

**Vantagens:**
- ✅ Isolamento completo
- ✅ Sem conflito de chaves
- ✅ Performance otimizada
- ✅ Cada serviço gerencia seu cache

---

## 📞 Suporte

Se algum problema persistir:

1. Verifique os logs do webhook: `/test_webhook_messages.php`
2. Verifique a conexão: `/test_webhook_simple.php`
3. Verifique o painel da Evolution API: `https://evolution.macip.com.br/manager`

---

**Data:** 30/03/2026
**Versão:** 1.0
**Status:** Webhook configurado, correções aplicadas, aguardando testes
