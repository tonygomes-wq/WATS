<?php

namespace App\Controllers;

use App\Services\ConversationService;
use App\Helpers\InputValidator;
use App\Helpers\RateLimiter;
use App\Helpers\Logger;

/**
 * Controller para Conversas do Chat
 * 
 * Gerencia requisições HTTP relacionadas a conversas.
 */
class ConversationController extends BaseController
{
    private ConversationService $conversationService;
    private RateLimiter $rateLimiter;
    
    public function __construct(
        ConversationService $conversationService,
        RateLimiter $rateLimiter
    ) {
        $this->conversationService = $conversationService;
        $this->rateLimiter = $rateLimiter;
    }
    
    /**
     * GET /api/conversations
     * Listar conversas do usuário
     */
    public function index(): void
    {
        $userId = $this->getUserId();
        
        // Rate limiting
        if (!$this->rateLimiter->allow($userId, 'list_conversations', 300, 60)) {
            Logger::warning('Rate limit excedido - listagem de conversas', [
                'user_id' => $userId
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => 'Muitas requisições. Aguarde 1 minuto.'
            ], 429);
            return;
        }
        
        // Filtros
        $filters = [
            'search' => $_GET['search'] ?? '',
            'archived' => isset($_GET['archived']) ? (int) $_GET['archived'] : 0,
            'filter' => $_GET['filter'] ?? 'all',
            'limit' => min((int) ($_GET['limit'] ?? 100), 200),
            'offset' => (int) ($_GET['offset'] ?? 0)
        ];
        
        Logger::info('Listando conversas', [
            'user_id' => $userId,
            'filters' => $filters
        ]);
        
        $result = $this->conversationService->listConversations($userId, $filters);
        
        $this->jsonResponse([
            'success' => true,
            'conversations' => $result['conversations'],
            'total' => $result['total'],
            'limit' => $result['limit'],
            'offset' => $result['offset']
        ]);
    }
    
    /**
     * POST /api/conversations
     * Criar nova conversa
     */
    public function store(): void
    {
        $userId = $this->getUserId();
        
        // Rate limiting
        if (!$this->rateLimiter->allow($userId, 'create_conversation', 10, 60)) {
            Logger::warning('Rate limit excedido - criar conversa', [
                'user_id' => $userId
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => 'Muitas conversas criadas. Aguarde 1 minuto.'
            ], 429);
            return;
        }
        
        $input = $this->getJsonInput();
        
        // Validação
        $phoneValidation = InputValidator::validatePhone($input['phone'] ?? '');
        if (!$phoneValidation['valid']) {
            Logger::warning('Validação falhou - criar conversa', [
                'user_id' => $userId,
                'errors' => $phoneValidation['errors']
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => implode(', ', $phoneValidation['errors'])
            ], 400);
            return;
        }
        
        $phone = $phoneValidation['sanitized'];
        $contactName = $input['contact_name'] ?? null;
        
        Logger::info('Criando conversa', [
            'user_id' => $userId,
            'phone' => $phone
        ]);
        
        try {
            $result = $this->conversationService->createConversation($userId, $phone, $contactName);
            
            Logger::info('Conversa criada', [
                'user_id' => $userId,
                'conversation_id' => $result['conversation_id'],
                'existing' => $result['existing']
            ]);
            
            $this->jsonResponse($result);
        } catch (\Exception $e) {
            Logger::error('Erro ao criar conversa', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * PUT /api/conversations/{id}
     * Atualizar conversa (marcar como lida, arquivar, fixar)
     */
    public function update(int $id): void
    {
        $userId = $this->getUserId();
        
        // Rate limiting
        if (!$this->rateLimiter->allow($userId, 'update_conversation', 30, 60)) {
            Logger::warning('Rate limit excedido - atualizar conversa', [
                'user_id' => $userId
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => 'Muitas atualizações. Aguarde 1 minuto.'
            ], 429);
            return;
        }
        
        $input = $this->getJsonInput();
        
        // Validação
        $idValidation = InputValidator::validateId($id);
        if (!$idValidation['valid']) {
            $this->jsonResponse([
                'success' => false,
                'error' => implode(', ', $idValidation['errors'])
            ], 400);
            return;
        }
        
        $action = trim($input['action'] ?? '');
        $validActions = ['mark_read', 'archive', 'unarchive', 'pin', 'unpin'];
        
        if (!in_array($action, $validActions)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Ação inválida'
            ], 400);
            return;
        }
        
        Logger::info('Atualizando conversa', [
            'user_id' => $userId,
            'conversation_id' => $id,
            'action' => $action
        ]);
        
        try {
            $result = $this->conversationService->updateConversation($userId, $id, $action);
            
            Logger::info('Conversa atualizada', [
                'user_id' => $userId,
                'conversation_id' => $id,
                'action' => $action
            ]);
            
            $this->jsonResponse($result);
        } catch (\Exception $e) {
            Logger::error('Erro ao atualizar conversa', [
                'user_id' => $userId,
                'conversation_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * DELETE /api/conversations/{id}
     * Deletar conversa
     */
    public function destroy(int $id): void
    {
        $userId = $this->getUserId();
        
        // Rate limiting
        if (!$this->rateLimiter->allow($userId, 'delete_conversation', 10, 60)) {
            Logger::warning('Rate limit excedido - deletar conversa', [
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
        
        Logger::info('Deletando conversa', [
            'user_id' => $userId,
            'conversation_id' => $id
        ]);
        
        try {
            $result = $this->conversationService->deleteConversation($userId, $id);
            
            Logger::info('Conversa deletada', [
                'user_id' => $userId,
                'conversation_id' => $id
            ]);
            
            $this->jsonResponse($result);
        } catch (\Exception $e) {
            Logger::error('Erro ao deletar conversa', [
                'user_id' => $userId,
                'conversation_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
