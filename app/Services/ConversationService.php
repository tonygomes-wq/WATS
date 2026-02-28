<?php

namespace App\Services;

use App\Repositories\ConversationRepository;
use App\Repositories\ContactRepository;
use InvalidArgumentException;

/**
 * Service para Conversas do Chat
 * 
 * Contém toda a lógica de negócio relacionada a conversas.
 * Usa repositories para acesso a dados.
 */
class ConversationService extends BaseService
{
    private ConversationRepository $conversationRepo;
    private ContactRepository $contactRepo;
    
    public function __construct(
        ConversationRepository $conversationRepo,
        ContactRepository $contactRepo
    ) {
        $this->conversationRepo = $conversationRepo;
        $this->contactRepo = $contactRepo;
    }
    
    /**
     * Listar conversas do usuário
     */
    public function listConversations(int $userId, array $filters = []): array
    {
        $conversations = $this->conversationRepo->findByUser($userId, $filters);
        $total = $this->conversationRepo->countByUser($userId, $filters['archived'] ?? false);
        
        // Formatar conversas
        foreach ($conversations as &$conv) {
            $conv = $this->formatConversation($conv);
        }
        
        return [
            'conversations' => $conversations,
            'total' => $total,
            'limit' => $filters['limit'] ?? 100,
            'offset' => $filters['offset'] ?? 0
        ];
    }
    
    /**
     * Criar nova conversa
     */
    public function createConversation(int $userId, string $phone, ?string $contactName = null): array
    {
        // Validar telefone
        $phone = $this->validatePhone($phone);
        
        // Verificar se já existe
        $existing = $this->conversationRepo->findByPhone($userId, $phone);
        if ($existing) {
            return [
                'success' => true,
                'conversation_id' => (int) $existing['id'],
                'message' => 'Conversa já existe',
                'existing' => true
            ];
        }
        
        // Buscar ou criar contato
        $contact = $this->contactRepo->findByPhone($userId, $phone);
        $contactId = $contact['id'] ?? null;
        $finalName = $contactName ?: ($contact['name'] ?? null);
        
        // Criar conversa
        $conversationId = $this->conversationRepo->create([
            'user_id' => $userId,
            'contact_id' => $contactId,
            'phone' => $phone,
            'contact_name' => $finalName
        ]);
        
        return [
            'success' => true,
            'conversation_id' => $conversationId,
            'message' => 'Conversa criada com sucesso',
            'existing' => false
        ];
    }
    
    /**
     * Atualizar conversa (marcar como lida, arquivar, fixar)
     */
    public function updateConversation(int $userId, int $conversationId, string $action): array
    {
        // Verificar propriedade
        if (!$this->conversationRepo->belongsToUser($conversationId, $userId)) {
            throw new InvalidArgumentException('Conversa não encontrada');
        }
        
        $success = false;
        $message = '';
        
        switch ($action) {
            case 'mark_read':
                $success = $this->conversationRepo->markAsRead($conversationId);
                $message = 'Conversa marcada como lida';
                break;
                
            case 'archive':
                $success = $this->conversationRepo->setArchived($conversationId, true);
                $message = 'Conversa arquivada';
                break;
                
            case 'unarchive':
                $success = $this->conversationRepo->setArchived($conversationId, false);
                $message = 'Conversa desarquivada';
                break;
                
            case 'pin':
                $success = $this->conversationRepo->setPinned($conversationId, true);
                $message = 'Conversa fixada';
                break;
                
            case 'unpin':
                $success = $this->conversationRepo->setPinned($conversationId, false);
                $message = 'Conversa desfixada';
                break;
                
            default:
                throw new InvalidArgumentException('Ação inválida');
        }
        
        return [
            'success' => $success,
            'message' => $message
        ];
    }
    
    /**
     * Deletar conversa
     */
    public function deleteConversation(int $userId, int $conversationId): array
    {
        // Verificar propriedade
        if (!$this->conversationRepo->belongsToUser($conversationId, $userId)) {
            throw new InvalidArgumentException('Conversa não encontrada');
        }
        
        $success = $this->conversationRepo->delete($conversationId, $userId);
        
        return [
            'success' => $success,
            'message' => $success ? 'Conversa deletada' : 'Erro ao deletar conversa'
        ];
    }
    
    /**
     * Formatar conversa para resposta
     */
    private function formatConversation(array $conv): array
    {
        return [
            'id' => (int) $conv['id'],
            'phone' => $conv['phone'] ?? '',
            'contact_name' => $conv['display_name'] ?? $conv['phone'] ?? '',
            'profile_pic_url' => $conv['profile_pic_url'] ?? null,
            'last_message_text' => $conv['last_message_text'] ?? '',
            'last_message_time' => $conv['last_message_time'] ?? null,
            'last_message_time_formatted' => $this->formatMessageTime($conv['last_message_time'] ?? null),
            'unread_count' => (int) ($conv['unread_count'] ?? 0),
            'is_pinned' => (bool) ($conv['is_pinned'] ?? false),
            'is_archived' => (bool) ($conv['is_archived'] ?? false),
            'status' => $conv['status'] ?? 'open',
            'channel_type' => $conv['channel_type'] ?? 'whatsapp',
            'owner_user_id' => (int) ($conv['owner_user_id'] ?? 0)
        ];
    }
    
    /**
     * Validar e formatar telefone
     */
    private function validatePhone(string $phone): string
    {
        // Remover caracteres não numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Validar tamanho
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            throw new InvalidArgumentException('Telefone inválido');
        }
        
        // Adicionar código do Brasil se necessário
        if (strlen($phone) === 11 && substr($phone, 0, 2) !== '55') {
            $phone = '55' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Formatar tempo da mensagem
     */
    private function formatMessageTime(?string $datetime): string
    {
        if (!$datetime) return '';
        
        $time = strtotime($datetime);
        $now = time();
        
        // Hoje
        if (date('Y-m-d', $time) === date('Y-m-d', $now)) {
            return date('H:i', $time);
        }
        
        // Ontem
        if (date('Y-m-d', $time) === date('Y-m-d', $now - 86400)) {
            return 'Ontem';
        }
        
        // Esta semana
        $diff = $now - $time;
        if ($diff < 604800) {
            $days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            return $days[date('w', $time)];
        }
        
        // Mais antigo
        return date('d/m/Y', $time);
    }
}
