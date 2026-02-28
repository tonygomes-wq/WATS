<?php

namespace App\Controllers;

/**
 * API Controller
 * 
 * Despacha requisições baseado no parâmetro ?action
 * Mantém compatibilidade com sistema atual
 */
class ApiController extends BaseController
{
    /**
     * Despacha requisição baseado em ?action
     */
    public function dispatch(): void
    {
        $action = $_GET['action'] ?? '';
        
        // Verificar autenticação
        if (!isset($_SESSION['user_id'])) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Não autorizado. Faça login primeiro.',
                'action' => $action
            ], 401);
            return;
        }
        
        switch ($action) {
            case 'conversations':
                $this->handleConversations();
                break;
                
            case 'messages':
                $this->handleMessages();
                break;
                
            case 'send':
            case 'send_message':
                $this->handleSendMessage();
                break;
                
            default:
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Ação não encontrada',
                    'action' => $action,
                    'available_actions' => [
                        'conversations' => 'Listar conversas',
                        'messages' => 'Listar mensagens',
                        'send' => 'Enviar mensagem'
                    ]
                ], 404);
                break;
        }
    }
    
    /**
     * Listar conversas (sem usar ConversationController)
     */
    private function handleConversations(): void
    {
        // Log de debug
        error_log("[ApiController] handleConversations() iniciado");
        
        // Obter conexão do banco
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        
        if (!isset($pdo)) {
            error_log("[ApiController] ERRO: PDO não está definido");
            $this->jsonResponse([
                'success' => false,
                'message' => 'Erro ao conectar com banco de dados'
            ], 500);
            return;
        }
        
        error_log("[ApiController] PDO OK");
        
        $userId = $_SESSION['user_id'];
        error_log("[ApiController] User ID: " . $userId);
        
        try {
            // Query simples para listar conversas
            $sql = "
                SELECT 
                    c.*,
                    cont.name as contact_name_full,
                    cont.phone as contact_phone_full,
                    (SELECT COUNT(*) 
                     FROM chat_messages 
                     WHERE conversation_id = c.id 
                     AND from_me = 0 
                     AND read_at IS NULL) as unread_count,
                    (SELECT message_text 
                     FROM chat_messages 
                     WHERE conversation_id = c.id 
                     ORDER BY created_at DESC 
                     LIMIT 1) as last_message,
                    (SELECT created_at 
                     FROM chat_messages 
                     WHERE conversation_id = c.id 
                     ORDER BY created_at DESC 
                     LIMIT 1) as last_message_time
                FROM chat_conversations c
                LEFT JOIN contacts cont ON c.contact_id = cont.id
                WHERE c.user_id = :user_id
                ORDER BY c.last_message_at DESC
                LIMIT 100
            ";
            
            error_log("[ApiController] Executando query...");
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $conversations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            error_log("[ApiController] Query OK - Total: " . count($conversations));
            
            $this->jsonResponse([
                'success' => true,
                'data' => $conversations,
                'total' => count($conversations),
                'message' => 'Conversas carregadas com sucesso'
            ]);
            
        } catch (\Exception $e) {
            error_log("[ApiController] ERRO: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Erro ao carregar conversas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Listar mensagens (sem usar MessageController)
     */
    private function handleMessages(): void
    {
        // Obter conexão do banco
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        
        if (!isset($pdo)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Erro ao conectar com banco de dados'
            ], 500);
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $conversationId = $_GET['conversation_id'] ?? 0;
        
        if (empty($conversationId)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'conversation_id é obrigatório'
            ], 400);
            return;
        }
        
        try {
            // Verificar se conversa pertence ao usuário
            $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $conversationId, 'user_id' => $userId]);
            
            if (!$stmt->fetch()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
                return;
            }
            
            // Buscar mensagens
            $sql = "
                SELECT *
                FROM chat_messages
                WHERE conversation_id = :conversation_id
                ORDER BY created_at DESC
                LIMIT 50
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['conversation_id' => $conversationId]);
            $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Inverter ordem (mais antigas primeiro)
            $messages = array_reverse($messages);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $messages,
                'total' => count($messages),
                'message' => 'Mensagens carregadas com sucesso'
            ]);
            
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Erro ao carregar mensagens',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Enviar mensagem (sem usar MessageController)
     */
    private function handleSendMessage(): void
    {
        // Obter conexão do banco
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        
        if (!isset($pdo)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Erro ao conectar com banco de dados'
            ], 500);
            return;
        }
        
        $userId = $_SESSION['user_id'];
        
        // Obter dados do POST
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $conversationId = $input['conversation_id'] ?? 0;
        $message = $input['message'] ?? '';
        
        if (empty($conversationId) || empty($message)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'conversation_id e message são obrigatórios'
            ], 400);
            return;
        }
        
        try {
            // Verificar se conversa pertence ao usuário
            $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $conversationId, 'user_id' => $userId]);
            
            if (!$stmt->fetch()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
                return;
            }
            
            // Inserir mensagem
            $sql = "
                INSERT INTO chat_messages (
                    conversation_id, 
                    user_id,
                    message_text, 
                    from_me,
                    message_type,
                    created_at
                )
                VALUES (
                    :conversation_id,
                    :user_id,
                    :message, 
                    1,
                    'text',
                    NOW()
                )
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'message' => $message
            ]);
            
            $messageId = $pdo->lastInsertId();
            
            // Atualizar last_message_at da conversa
            $updateSql = "UPDATE chat_conversations SET last_message_at = NOW() WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute(['id' => $conversationId]);
            
            $this->jsonResponse([
                'success' => true,
                'message_id' => $messageId,
                'message' => 'Mensagem enviada com sucesso'
            ]);
            
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Erro ao enviar mensagem',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
