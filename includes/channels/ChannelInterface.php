<?php
/**
 * Interface base para todos os canais de comunicação
 * Baseado na arquitetura do Chatwoot 4.10.0
 */

interface ChannelInterface
{
    /**
     * Envia uma mensagem através do canal
     * 
     * @param array $message Dados da mensagem a ser enviada
     * @return array Resultado do envio com status e informações
     */
    public function sendMessage(array $message): array;
    
    /**
     * Processa webhook recebido do canal
     * 
     * @param array $payload Dados recebidos do webhook
     * @return array Resultado do processamento
     */
    public function receiveWebhook(array $payload): array;
    
    /**
     * Configura webhook no serviço externo
     * 
     * @return bool Sucesso da configuração
     */
    public function setupWebhook(): bool;
    
    /**
     * Valida credenciais do canal
     * 
     * @return bool Credenciais válidas
     */
    public function validateCredentials(): bool;
    
    /**
     * Retorna nome amigável do canal
     * 
     * @return string Nome do canal
     */
    public function getName(): string;
    
    /**
     * Retorna tipo/identificador do canal
     * 
     * @return string Tipo do canal
     */
    public function getType(): string;
    
    /**
     * Envia anexo (imagem, vídeo, documento)
     * 
     * @param array $attachment Dados do anexo
     * @return array Resultado do envio
     */
    public function sendAttachment(array $attachment): array;
    
    /**
     * Marca mensagem como lida
     * 
     * @param string $externalId ID externo da mensagem
     * @return bool Sucesso da operação
     */
    public function markAsRead(string $externalId): bool;
}
