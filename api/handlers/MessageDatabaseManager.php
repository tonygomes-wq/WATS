<?php
/**
 * Message Database Manager
 * 
 * Gerencia a persistência de mensagens de mídia no banco de dados.
 * 
 * @package MediaHandlers
 * @version 1.0
 * @since 2026-01-29
 */

class MessageDatabaseManager {
    
    /**
     * Salvar mensagem de mídia
     * 
     * Insere um registro de mensagem de mídia na tabela correta
     * (chat_messages para WhatsApp, messages para Teams)
     * com todos os campos necessários.
     * 
     * @param PDO $pdo Conexão com banco de dados
     * @param int $conversationId ID da conversa
     * @param int $userId ID do usuário
     * @param string $messageType Tipo de mensagem ('image', 'audio', 'document')
     * @param array $mediaData Dados da mídia ['media_url', 'filename', 'mimetype', 'size', 'caption']
     * @return int ID da mensagem inserida
     * @throws Exception Se falhar ao inserir
     * 
     * @example
     * $messageId = MessageDatabaseManager::saveMediaMessage(
     *     $pdo,
     *     123,
     *     456,
     *     'image',
     *     [
     *         'media_url' => '/uploads/user_456/media/abc123.jpg',
     *         'filename' => 'foto.jpg',
     *         'mimetype' => 'image/jpeg',
     *         'size' => 1024000,
     *         'caption' => 'Minha foto'
     *     ]
     * );
     */
    public static function saveMediaMessage(
        PDO $pdo,
        int $conversationId,
        int $userId,
        string $messageType,
        array $mediaData
    ): int {
        try {
            // ========================================
            // 1. DETECTAR CANAL DA CONVERSA
            // ========================================
            
            $stmt = $pdo->prepare("SELECT channel_type FROM chat_conversations WHERE id = ?");
            $stmt->execute([$conversationId]);
            $conv = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$conv) {
                throw new Exception("Conversa não encontrada: ID {$conversationId}");
            }
            
            $channelType = $conv['channel_type'] ?? 'whatsapp';
            
            error_log("[MessageDatabaseManager] Salvando mensagem de mídia:");
            error_log("  - Conversation ID: " . $conversationId);
            error_log("  - Channel Type: " . $channelType);
            error_log("  - Message Type: " . $messageType);
            
            // ========================================
            // 2. SALVAR NA TABELA CORRETA
            // ========================================
            
            // ✅ CORREÇÃO: Sempre salvar em chat_messages
            // O sistema usa chat_messages para exibir mensagens no frontend
            return self::saveToChatMessagesTable($pdo, $conversationId, $userId, $messageType, $mediaData, $channelType);
            
        } catch (PDOException $e) {
            error_log("[MessageDatabaseManager] Erro ao salvar mensagem: " . $e->getMessage());
            throw new Exception("Erro ao salvar mensagem no banco de dados: " . $e->getMessage());
        }
    }
    
    /**
     * Salvar na tabela chat_messages (WhatsApp e Teams)
     */
    private static function saveToChatMessagesTable(
        PDO $pdo,
        int $conversationId,
        int $userId,
        string $messageType,
        array $mediaData,
        string $channelType = 'whatsapp'
    ): int {
        // Preparar statement SQL
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (
                conversation_id,
                user_id,
                message_id,
                from_me,
                message_type,
                message_text,
                media_url,
                media_mimetype,
                media_filename,
                media_size,
                caption,
                status,
                timestamp
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Gerar message_id único
        $messageId = uniqid('msg_', true);
        
        // Timestamp atual
        $timestamp = time();
        
        // Extrair dados da mídia
        $mediaUrl = $mediaData['media_url'] ?? null;
        $filename = $mediaData['filename'] ?? null;
        $mimetype = $mediaData['mimetype'] ?? null;
        $size = $mediaData['size'] ?? 0;
        $caption = $mediaData['caption'] ?? '';
        
        // ✅ CORREÇÃO: Para mídia, message_text deve ser vazio ou indicador de mídia
        // Não usar caption como message_text para evitar duplicação
        $messageText = ''; // Vazio para não aparecer "[Mensagem de texto]"
        
        // Executar inserção
        $stmt->execute([
            $conversationId,
            $userId,
            $messageId,
            1, // from_me = 1 (mensagem enviada pelo usuário)
            $messageType,
            $messageText, // ← Vazio para não duplicar
            $mediaUrl,
            $mimetype,
            $filename,
            $size,
            $caption, // Caption separado
            'sent', // status
            $timestamp
        ]);
        
        // Retornar ID da mensagem inserida
        $insertedId = (int) $pdo->lastInsertId();
        
        if ($insertedId === 0) {
            throw new Exception("Falha ao obter ID da mensagem inserida");
        }
        
        error_log("[MessageDatabaseManager] Mensagem salva em chat_messages, ID: " . $insertedId);
        
        return $insertedId;
    }
    
    /**
     * Salvar na tabela messages (Teams)
     */
    private static function saveToMessagesTable(
        PDO $pdo,
        int $conversationId,
        int $userId,
        string $messageType,
        array $mediaData
    ): int {
        // Preparar statement SQL
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                conversation_id,
                external_id,
                sender_type,
                sender_name,
                message_text,
                media_url,
                channel_type,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Gerar external_id único
        $externalId = uniqid('teams_media_', true);
        
        // Extrair dados da mídia
        $mediaUrl = $mediaData['media_url'] ?? null;
        $caption = $mediaData['caption'] ?? '';
        
        // Buscar nome do usuário
        $stmtUser = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $senderName = $user['name'] ?? 'Usuário';
        
        // Executar inserção
        $stmt->execute([
            $conversationId,
            $externalId,
            'user', // sender_type = user (mensagem enviada pelo usuário)
            $senderName,
            $caption, // message_text = caption
            $mediaUrl,
            'teams'
        ]);
        
        // Retornar ID da mensagem inserida
        $insertedId = (int) $pdo->lastInsertId();
        
        if ($insertedId === 0) {
            throw new Exception("Falha ao obter ID da mensagem inserida");
        }
        
        error_log("[MessageDatabaseManager] Mensagem salva em messages, ID: " . $insertedId);
        
        return $insertedId;
    }
    
    /**
     * Atualizar última mensagem da conversa
     * 
     * Atualiza os campos last_message_text e last_message_time na tabela
     * chat_conversations para refletir a última mensagem enviada.
     * 
     * @param PDO $pdo Conexão com banco de dados
     * @param int $conversationId ID da conversa
     * @param string $lastMessage Texto da última mensagem
     * @return void
     * @throws Exception Se falhar ao atualizar
     * 
     * @example
     * MessageDatabaseManager::updateLastMessage($pdo, 123, "[Imagem enviada]");
     */
    public static function updateLastMessage(
        PDO $pdo,
        int $conversationId,
        string $lastMessage
    ): void {
        try {
            // Preparar statement SQL
            $stmt = $pdo->prepare("
                UPDATE chat_conversations 
                SET last_message_text = ?, 
                    last_message_time = NOW()
                WHERE id = ?
            ");
            
            // Limitar tamanho da mensagem para evitar overflow
            $truncatedMessage = substr($lastMessage, 0, 255);
            
            // Executar atualização
            $stmt->execute([
                $truncatedMessage,
                $conversationId
            ]);
            
            // Verificar se alguma linha foi afetada
            if ($stmt->rowCount() === 0) {
                error_log("[MessageDatabaseManager] Aviso: Nenhuma conversa atualizada para ID {$conversationId}");
            }
            
        } catch (PDOException $e) {
            error_log("[MessageDatabaseManager] Erro ao atualizar última mensagem: " . $e->getMessage());
            throw new Exception("Erro ao atualizar última mensagem da conversa: " . $e->getMessage());
        }
    }
}
