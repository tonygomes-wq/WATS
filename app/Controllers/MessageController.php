<?php

namespace App\Controllers;

use App\Services\MessageService;
use App\Helpers\InputValidator;
use App\Helpers\RateLimiter;
use App\Helpers\Logger;

/**
 * Controller para Mensagens do Chat
 * 
 * Gerencia requisições HTTP relacionadas a mensagens.
 */
class MessageController extends BaseController
{
    private MessageService $messageService;
    private RateLimiter $rateLimiter;
    
    public function __construct(
        MessageService $messageService,
        RateLimiter $rateLimiter
    ) {
        $this->messageService = $messageService;
        $this->rateLimiter = $rateLimiter;
    }
    
    /**
     * GET /api/messages?conversation_id={id}
     * Listar mensagens de uma conversa
     */
    public function index(): void
    {
        $userId = $this->getUserId();
        
        // Rate limiting
        if (!$this->rateLimiter->allow($userId, 'list_messages', 500, 60)) {
            Logger::warning('Rate limit excedido - listagem de mensagens', [
                'user_id' => $userId
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => 'Muitas requisições. Aguarde 1 minuto.'
            ], 429);
            return;
        }
        
        // Validação
        $idValidation = InputValidator::validateId($_GET['conversation_id'] ?? 0);
        if (!$idValidation['valid']) {
            $this->jsonResponse([
                'success' => false,
                'error' => implode(', ', $idValidation['errors'])
            ], 400);
            return;
        }
        
        $conversationId = $idValidation['sanitized'];
        
        // Opções
        $options = [
            'limit' => min((int) ($_GET['limit'] ?? 50), 500),
            'offset' => (int) ($_GET['offset'] ?? 0),
            'before_id' => (int) ($_GET['before_id'] ?? 0)
        ];
        
        Logger::info('Listando mensagens', [
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'options' => $options
        ]);
        
        try {
            $result = $this->messageService->listMessages($userId, $conversationId, $options);
            $this->jsonResponse($result);
        } catch (\Exception $e) {
            Logger::error('Erro ao listar mensagens', [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * POST /api/messages
     * Enviar mensagem
     */
    public function store(): void
    {
        $userId = $this->getUserId();
        
        // Rate limiting
        if (!$this->rateLimiter->allow($userId, 'send_message', 60, 60)) {
            Logger::warning('Rate limit excedido - enviar mensagem', [
                'user_id' => $userId
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => 'Muitas mensagens enviadas. Aguarde 1 minuto.'
            ], 429);
            return;
        }
        
        $input = $this->getJsonInput();
        
        // Validação
        $idValidation = InputValidator::validateId($input['conversation_id'] ?? 0);
        if (!$idValidation['valid']) {
            $this->jsonResponse([
                'success' => false,
                'error' => implode(', ', $idValidation['errors'])
            ], 400);
            return;
        }
        
        $conversationId = $idValidation['sanitized'];
        
        Logger::info('Enviando mensagem', [
            'user_id' => $userId,
            'conversation_id' => $conversationId
        ]);
        
        try {
            $result = $this->messageService->sendMessage($userId, $conversationId, $input);
            
            Logger::info('Mensagem enviada', [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'message_id' => $result['message_id']
            ]);
            
            $this->jsonResponse($result);
        } catch (\Exception $e) {
            Logger::error('Erro ao enviar mensagem', [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * DELETE /api/messages/{id}
     * Deletar mensagem
     */
    public function destroy(int $id): void
    {
        $userId = $this->getUserId();
        
        // Rate limiting
        if (!$this->rateLimiter->allow($userId, 'delete_message', 10, 60)) {
            Logger::warning('Rate limit excedido - deletar mensagem', [
                'user_id' => $userId
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => 'Muitas deleções. Aguarde 1 minuto.'
            ], 429);
            return;
        }
        
        // Validação
        $idValidation = InputValidator::validateId($id);
        if (!$idValidation['valid']) {
            $this->jsonResponse([
                'success' => false,
                'error' => implode(', ', $idValidation['errors'])
            ], 400);
            return;
        }
        
        Logger::info('Deletando mensagem', [
            'user_id' => $userId,
            'message_id' => $id
        ]);
        
        try {
            $result = $this->messageService->deleteMessage($userId, $id);
            
            Logger::info('Mensagem deletada', [
                'user_id' => $userId,
                'message_id' => $id
            ]);
            
            $this->jsonResponse($result);
        } catch (\Exception $e) {
            Logger::error('Erro ao deletar mensagem', [
                'user_id' => $userId,
                'message_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
