<?php
/**
 * Exemplos Práticos de Integração Z-API
 * WATS - Sistema de Automação WhatsApp
 */

require_once '../config/database.php';
require_once '../includes/channels/WhatsAppChannel.php';

// =====================================================
// EXEMPLO 1: Enviar Mensagem de Texto Simples
// =====================================================
function exemplo1_mensagem_texto() {
    $instanceId = 1; // ID da instância no banco
    $phone = '5511999999999';
    $message = 'Olá! Esta é uma mensagem de teste.';
    
    $channel = new WhatsAppChannel($instanceId);
    $result = $channel->sendMessage($phone, $message);
    
    if ($result['success']) {
        echo "✅ Mensagem enviada com sucesso!\n";
        echo "ID da mensagem: " . $result['messageId'] . "\n";
    } else {
        echo "❌ Erro ao enviar mensagem: " . $result['error'] . "\n";
    }
}

// =====================================================
// EXEMPLO 2: Enviar Imagem com Legenda
// =====================================================
function exemplo2_enviar_imagem() {
    $instanceId = 1;
    $phone = '5511999999999';
    $imageUrl = 'https://exemplo.com/imagem.jpg';
    $caption = 'Confira nossa promoção especial!';
    
    $channel = new WhatsAppChannel($instanceId);
    $result = $channel->sendMessage($phone, $caption, [
        'type' => 'image',
        'media_url' => $imageUrl,
        'caption' => $caption
    ]);
    
    if ($result['success']) {
        echo "✅ Imagem enviada com sucesso!\n";
    } else {
        echo "❌ Erro: " . $result['error'] . "\n";
    }
}

// =====================================================
// EXEMPLO 3: Enviar Documento PDF
// =====================================================
function exemplo3_enviar_documento() {
    $instanceId = 1;
    $phone = '5511999999999';
    $documentUrl = 'https://exemplo.com/contrato.pdf';
    $filename = 'Contrato_Servicos.pdf';
    
    $channel = new WhatsAppChannel($instanceId);
    $result = $channel->sendMessage($phone, '', [
        'type' => 'document',
        'media_url' => $documentUrl,
        'filename' => $filename
    ]);
    
    if ($result['success']) {
        echo "✅ Documento enviado com sucesso!\n";
    } else {
        echo "❌ Erro: " . $result['error'] . "\n";
    }
}

// =====================================================
// EXEMPLO 4: Enviar Localização
// =====================================================
function exemplo4_enviar_localizacao() {
    $instanceId = 1;
    $phone = '5511999999999';
    
    $channel = new WhatsAppChannel($instanceId);
    $result = $channel->sendMessage($phone, '', [
        'type' => 'location',
        'latitude' => -23.550520,
        'longitude' => -46.633308,
        'name' => 'Avenida Paulista',
        'address' => 'Av. Paulista, 1578 - Bela Vista, São Paulo - SP'
    ]);
    
    if ($result['success']) {
        echo "✅ Localização enviada com sucesso!\n";
    } else {
        echo "❌ Erro: " . $result['error'] . "\n";
    }
}

// =====================================================
// EXEMPLO 5: Verificar se Número Existe no WhatsApp
// =====================================================
function exemplo5_verificar_numero() {
    $instanceId = 1;
    $phone = '5511999999999';
    
    $channel = new WhatsAppChannel($instanceId);
    $result = $channel->checkPhone($phone);
    
    if ($result['exists']) {
        echo "✅ Número {$phone} existe no WhatsApp\n";
        echo "Nome: " . ($result['name'] ?? 'Não disponível') . "\n";
    } else {
        echo "❌ Número {$phone} NÃO existe no WhatsApp\n";
    }
}

// =====================================================
// EXEMPLO 6: Obter Foto de Perfil
// =====================================================
function exemplo6_foto_perfil() {
    $instanceId = 1;
    $phone = '5511999999999';
    
    $channel = new WhatsAppChannel($instanceId);
    $photoUrl = $channel->getProfilePicture($phone);
    
    if ($photoUrl) {
        echo "✅ Foto de perfil: {$photoUrl}\n";
    } else {
        echo "❌ Foto de perfil não disponível\n";
    }
}

// =====================================================
// EXEMPLO 7: Enviar Mensagem para Múltiplos Contatos
// =====================================================
function exemplo7_envio_em_massa() {
    $instanceId = 1;
    $phones = [
        '5511999999999',
        '5511888888888',
        '5511777777777'
    ];
    $message = 'Olá! Esta é uma mensagem em massa.';
    
    $channel = new WhatsAppChannel($instanceId);
    $results = [];
    
    foreach ($phones as $phone) {
        $result = $channel->sendMessage($phone, $message);
        $results[$phone] = $result['success'];
        
        // Aguardar 2 segundos entre envios (evitar bloqueio)
        sleep(2);
    }
    
    $success = array_filter($results);
    echo "✅ Enviadas: " . count($success) . " de " . count($phones) . "\n";
}

// =====================================================
// EXEMPLO 8: Criar Grupo
// =====================================================
function exemplo8_criar_grupo() {
    $instanceId = 1;
    $groupName = 'Grupo de Testes';
    $participants = [
        '5511999999999',
        '5511888888888'
    ];
    
    $channel = new WhatsAppChannel($instanceId);
    $result = $channel->createGroup($groupName, $participants);
    
    if ($result['success']) {
        echo "✅ Grupo criado com sucesso!\n";
        echo "ID do grupo: " . $result['groupId'] . "\n";
    } else {
        echo "❌ Erro: " . $result['error'] . "\n";
    }
}

// =====================================================
// EXEMPLO 9: Processar Webhook de Mensagem Recebida
// =====================================================
function exemplo9_processar_webhook() {
    // Simular payload do webhook
    $payload = '{
        "event": "message-received",
        "instanceId": "INSTANCE_ID",
        "data": {
            "messageId": "3EB0123456789ABCDEF",
            "phone": "5511999999999",
            "fromMe": false,
            "momment": 1234567890,
            "status": "RECEIVED",
            "chatName": "João Silva",
            "senderName": "João Silva",
            "text": {
                "message": "Olá, preciso de ajuda"
            }
        }
    }';
    
    $data = json_decode($payload, true);
    
    // Processar mensagem
    $phone = $data['data']['phone'];
    $message = $data['data']['text']['message'];
    $messageId = $data['data']['messageId'];
    
    echo "📩 Mensagem recebida de {$phone}\n";
    echo "Conteúdo: {$message}\n";
    echo "ID: {$messageId}\n";
    
    // Aqui você processaria a mensagem no sistema
    // - Criar/atualizar contato
    // - Criar/atualizar conversa
    // - Salvar mensagem
    // - Executar automações
}

// =====================================================
// EXEMPLO 10: Enviar Mensagem com Template
// =====================================================
function exemplo10_mensagem_template() {
    $instanceId = 1;
    $phone = '5511999999999';
    
    // Template de mensagem
    $template = "Olá {{nome}}!\n\n";
    $template .= "Seu pedido #{{pedido}} foi confirmado.\n";
    $template .= "Valor: R$ {{valor}}\n";
    $template .= "Previsão de entrega: {{data}}\n\n";
    $template .= "Obrigado pela preferência!";
    
    // Variáveis
    $vars = [
        'nome' => 'João Silva',
        'pedido' => '12345',
        'valor' => '150,00',
        'data' => '25/12/2024'
    ];
    
    // Substituir variáveis
    $message = $template;
    foreach ($vars as $key => $value) {
        $message = str_replace("{{" . $key . "}}", $value, $message);
    }
    
    $channel = new WhatsAppChannel($instanceId);
    $result = $channel->sendMessage($phone, $message);
    
    if ($result['success']) {
        echo "✅ Mensagem template enviada com sucesso!\n";
    } else {
        echo "❌ Erro: " . $result['error'] . "\n";
    }
}

// =====================================================
// EXEMPLO 11: Enviar Áudio
// =====================================================
function exemplo11_enviar_audio() {
    $instanceId = 1;
    $phone = '5511999999999';
    $audioUrl = 'https://exemplo.com/audio.mp3';
    
    $channel = new WhatsAppChannel($instanceId);
    $result = $channel->sendMessage($phone, '', [
        'type' => 'audio',
        'media_url' => $audioUrl
    ]);
    
    if ($result['success']) {
        echo "✅ Áudio enviado com sucesso!\n";
    } else {
        echo "❌ Erro: " . $result['error'] . "\n";
    }
}

// =====================================================
// EXEMPLO 12: Enviar Vídeo
// =====================================================
function exemplo12_enviar_video() {
    $instanceId = 1;
    $phone = '5511999999999';
    $videoUrl = 'https://exemplo.com/video.mp4';
    $caption = 'Confira nosso tutorial!';
    
    $channel = new WhatsAppChannel($instanceId);
    $result = $channel->sendMessage($phone, '', [
        'type' => 'video',
        'media_url' => $videoUrl,
        'caption' => $caption
    ]);
    
    if ($result['success']) {
        echo "✅ Vídeo enviado com sucesso!\n";
    } else {
        echo "❌ Erro: " . $result['error'] . "\n";
    }
}

// =====================================================
// EXEMPLO 13: Listar Conversas Recentes
// =====================================================
function exemplo13_listar_conversas() {
    global $pdo;
    
    $instanceId = 1;
    
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.phone,
            c.name,
            c.last_message,
            c.last_message_at,
            c.unread_count
        FROM chat_conversations c
        WHERE c.instance_id = ?
        ORDER BY c.last_message_at DESC
        LIMIT 10
    ");
    $stmt->execute([$instanceId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📱 Conversas recentes:\n\n";
    foreach ($conversations as $conv) {
        echo "- {$conv['name']} ({$conv['phone']})\n";
        echo "  Última mensagem: {$conv['last_message']}\n";
        echo "  Não lidas: {$conv['unread_count']}\n\n";
    }
}

// =====================================================
// EXEMPLO 14: Marcar Mensagens como Lidas
// =====================================================
function exemplo14_marcar_como_lida() {
    global $pdo;
    
    $conversationId = 1;
    
    $stmt = $pdo->prepare("
        UPDATE chat_messages 
        SET read_at = NOW() 
        WHERE conversation_id = ? 
        AND direction = 'received' 
        AND read_at IS NULL
    ");
    $stmt->execute([$conversationId]);
    
    $count = $stmt->rowCount();
    echo "✅ {$count} mensagens marcadas como lidas\n";
}

// =====================================================
// EXEMPLO 15: Obter Histórico de Mensagens
// =====================================================
function exemplo15_historico_mensagens() {
    global $pdo;
    
    $conversationId = 1;
    $limit = 50;
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            message,
            direction,
            status,
            created_at,
            media_type,
            media_url
        FROM chat_messages
        WHERE conversation_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$conversationId, $limit]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "💬 Histórico de mensagens:\n\n";
    foreach (array_reverse($messages) as $msg) {
        $direction = $msg['direction'] === 'sent' ? '→' : '←';
        $time = date('H:i', strtotime($msg['created_at']));
        echo "{$direction} [{$time}] {$msg['message']}\n";
        
        if ($msg['media_type']) {
            echo "   📎 {$msg['media_type']}: {$msg['media_url']}\n";
        }
    }
}

// =====================================================
// EXECUTAR EXEMPLOS
// =====================================================

// Descomente a linha do exemplo que deseja executar:

// exemplo1_mensagem_texto();
// exemplo2_enviar_imagem();
// exemplo3_enviar_documento();
// exemplo4_enviar_localizacao();
// exemplo5_verificar_numero();
// exemplo6_foto_perfil();
// exemplo7_envio_em_massa();
// exemplo8_criar_grupo();
// exemplo9_processar_webhook();
// exemplo10_mensagem_template();
// exemplo11_enviar_audio();
// exemplo12_enviar_video();
// exemplo13_listar_conversas();
// exemplo14_marcar_como_lida();
// exemplo15_historico_mensagens();

echo "\n✅ Exemplos carregados com sucesso!\n";
echo "Descomente a linha do exemplo que deseja executar.\n";
