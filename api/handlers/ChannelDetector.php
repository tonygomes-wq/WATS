<?php
/**
 * Channel Detector
 * 
 * Detecta o canal de comunicação (WhatsApp ou Teams) de uma conversa.
 * 
 * @package MediaHandlers
 * @version 1.0
 * @since 2026-01-29
 */

class ChannelDetector {
    
    /**
     * Detectar canal pela conversa
     * 
     * Identifica se a conversa é WhatsApp ou Teams baseado no campo
     * channel_type da tabela chat_conversations.
     * 
     * @param PDO $pdo Conexão com banco de dados
     * @param int $conversationId ID da conversa
     * @return string 'whatsapp' ou 'teams'
     * @throws Exception Se conversa não encontrada ou channel_type inválido
     * 
     * @example
     * $channel = ChannelDetector::detect($pdo, 123);
     * // Returns: 'whatsapp' ou 'teams'
     */
    public static function detect(PDO $pdo, int $conversationId): string {
        // Query para buscar channel_type da conversa
        $stmt = $pdo->prepare("SELECT channel_type FROM chat_conversations WHERE id = ?");
        $stmt->execute([$conversationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se conversa não encontrada, lançar exceção
        if (!$result) {
            throw new Exception("Conversa não encontrada (ID: {$conversationId})");
        }
        
        // Extrair channel_type
        $channelType = $result['channel_type'];
        
        // Se channel_type é NULL ou vazio, assumir 'whatsapp' (compatibilidade)
        if (empty($channelType)) {
            return 'whatsapp';
        }
        
        // Normalizar para lowercase
        $channelType = strtolower(trim($channelType));
        
        // Validar valores permitidos
        if ($channelType === 'whatsapp') {
            return 'whatsapp';
        } else if ($channelType === 'teams') {
            return 'teams';
        } else {
            throw new Exception("Canal não suportado: {$channelType}");
        }
    }
}
