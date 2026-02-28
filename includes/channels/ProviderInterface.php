<?php
/**
 * Interface base para providers WhatsApp
 * Define métodos que todos os providers devem implementar
 */
interface ProviderInterface {
    /**
     * Enviar mensagem de texto
     * 
     * @param string $identifier Identificador do destinatário (phone/jid/lid)
     * @param string $message Texto da mensagem
     * @return array Resultado com messageId e dados
     */
    public function sendText($identifier, $message);
    
    /**
     * Enviar imagem
     * 
     * @param string $identifier Identificador do destinatário
     * @param string $imageUrl URL da imagem
     * @param string $caption Legenda opcional
     * @return array Resultado com messageId e dados
     */
    public function sendImage($identifier, $imageUrl, $caption = '');
    
    /**
     * Enviar vídeo
     * 
     * @param string $identifier Identificador do destinatário
     * @param string $videoUrl URL do vídeo
     * @param string $caption Legenda opcional
     * @return array Resultado com messageId e dados
     */
    public function sendVideo($identifier, $videoUrl, $caption = '');
    
    /**
     * Enviar áudio
     * 
     * @param string $identifier Identificador do destinatário
     * @param string $audioUrl URL do áudio
     * @return array Resultado com messageId e dados
     */
    public function sendAudio($identifier, $audioUrl);
    
    /**
     * Enviar documento
     * 
     * @param string $identifier Identificador do destinatário
     * @param string $documentUrl URL do documento
     * @param string $filename Nome do arquivo
     * @return array Resultado com messageId e dados
     */
    public function sendDocument($identifier, $documentUrl, $filename = '');
    
    /**
     * Enviar localização
     * 
     * @param string $identifier Identificador do destinatário
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param string $name Nome do local
     * @param string $address Endereço
     * @return array Resultado com messageId e dados
     */
    public function sendLocation($identifier, $latitude, $longitude, $name = '', $address = '');
    
    /**
     * Verificar status da instância
     * 
     * @return array Status com 'connected' e 'phone'
     */
    public function getStatus();
    
    /**
     * Verificar se identificador existe no WhatsApp
     * 
     * @param string $identifier Identificador a verificar
     * @return array Resultado com 'exists' e 'name'
     */
    public function checkIdentifier($identifier);
    
    /**
     * Obter foto de perfil
     * 
     * @param string $identifier Identificador
     * @return string|null URL da foto ou null
     */
    public function getProfilePicture($identifier);
    
    /**
     * Criar grupo
     * 
     * @param string $name Nome do grupo
     * @param array $participants Lista de participantes
     * @return array Resultado com dados do grupo
     */
    public function createGroup($name, $participants);
    
    /**
     * Verificar se provider suporta LID
     * 
     * @return bool True se suporta LID
     */
    public function supportsLID();
}
