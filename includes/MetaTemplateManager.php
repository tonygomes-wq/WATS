<?php
/**
 * Meta Template Manager
 * Gerencia templates de mensagem da Meta API
 * Permite criar, editar, submeter para aprovação e usar templates
 */

class MetaTemplateManager
{
    private PDO $pdo;
    
    const CATEGORIES = [
        'MARKETING' => 'Marketing',
        'UTILITY' => 'Utilidade',
        'AUTHENTICATION' => 'Autenticação'
    ];
    
    const STATUS = [
        'DRAFT' => 'Rascunho',
        'PENDING' => 'Pendente de Aprovação',
        'APPROVED' => 'Aprovado',
        'REJECTED' => 'Rejeitado'
    ];
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Criar novo template
     */
    public function createTemplate(array $data): array
    {
        $userId = (int)$data['user_id'];
        $name = $this->sanitizeTemplateName($data['name']);
        $language = $data['language'] ?? 'pt_BR';
        $category = $data['category'] ?? 'UTILITY';
        $components = $data['components'] ?? [];
        
        // Validar dados
        $validation = $this->validateTemplate($name, $category, $components);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO meta_message_templates
                (user_id, template_name, template_language, template_category, 
                 template_status, template_components, created_at)
                VALUES (?, ?, ?, ?, 'DRAFT', ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $name,
                $language,
                $category,
                json_encode($components)
            ]);
            
            $templateId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'template_id' => $templateId,
                'message' => 'Template criado com sucesso'
            ];
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                return [
                    'success' => false,
                    'error' => 'Já existe um template com este nome'
                ];
            }
            throw $e;
        }
    }
    
    /**
     * Atualizar template existente
     */
    public function updateTemplate(int $templateId, array $data): array
    {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            return ['success' => false, 'error' => 'Template não encontrado'];
        }
        
        // Só pode editar se estiver em DRAFT ou REJECTED
        if (!in_array($template['template_status'], ['DRAFT', 'REJECTED'])) {
            return [
                'success' => false,
                'error' => 'Apenas templates em rascunho ou rejeitados podem ser editados'
            ];
        }
        
        $components = $data['components'] ?? json_decode($template['template_components'], true);
        $category = $data['category'] ?? $template['template_category'];
        
        $validation = $this->validateTemplate($template['template_name'], $category, $components);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE meta_message_templates
            SET template_category = ?,
                template_components = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $category,
            json_encode($components),
            $templateId
        ]);
        
        return [
            'success' => true,
            'message' => 'Template atualizado com sucesso'
        ];
    }
    
    /**
     * Submeter template para aprovação na Meta
     */
    public function submitForApproval(int $templateId): array
    {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            return ['success' => false, 'error' => 'Template não encontrado'];
        }
        
        if ($template['template_status'] !== 'DRAFT' && $template['template_status'] !== 'REJECTED') {
            return [
                'success' => false,
                'error' => 'Template já foi submetido'
            ];
        }
        
        // Buscar configurações da Meta API do usuário
        $stmt = $this->pdo->prepare("
            SELECT meta_phone_number_id, meta_business_account_id, 
                   meta_permanent_token, meta_api_version
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$template['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || empty($user['meta_business_account_id']) || empty($user['meta_permanent_token'])) {
            return [
                'success' => false,
                'error' => 'Configurações da Meta API não encontradas'
            ];
        }
        
        // Enviar para Meta API
        $result = $this->submitToMetaAPI($template, $user);
        
        if ($result['success']) {
            // Atualizar status para PENDING
            $stmt = $this->pdo->prepare("
                UPDATE meta_message_templates
                SET template_status = 'PENDING',
                    meta_template_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$result['template_id'], $templateId]);
            
            return [
                'success' => true,
                'message' => 'Template submetido para aprovação',
                'meta_template_id' => $result['template_id']
            ];
        }
        
        return $result;
    }
    
    /**
     * Enviar template para Meta API
     */
    private function submitToMetaAPI(array $template, array $user): array
    {
        $apiVersion = $user['meta_api_version'] ?? 'v19.0';
        $businessAccountId = $user['meta_business_account_id'];
        $token = $user['meta_permanent_token'];
        
        $url = "https://graph.facebook.com/{$apiVersion}/{$businessAccountId}/message_templates";
        
        $components = json_decode($template['template_components'], true);
        
        $payload = [
            'name' => $template['template_name'],
            'language' => $template['template_language'],
            'category' => $template['template_category'],
            'components' => $components
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['id'])) {
            return [
                'success' => true,
                'template_id' => $responseData['id']
            ];
        }
        
        return [
            'success' => false,
            'error' => $responseData['error']['message'] ?? 'Erro ao submeter template'
        ];
    }
    
    /**
     * Sincronizar status do template com Meta API
     */
    public function syncTemplateStatus(int $templateId): array
    {
        $template = $this->getTemplate($templateId);
        if (!$template || empty($template['meta_template_id'])) {
            return ['success' => false, 'error' => 'Template não encontrado ou não submetido'];
        }
        
        // Buscar configurações do usuário
        $stmt = $this->pdo->prepare("
            SELECT meta_business_account_id, meta_permanent_token, meta_api_version
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$template['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Usuário não encontrado'];
        }
        
        $apiVersion = $user['meta_api_version'] ?? 'v19.0';
        $businessAccountId = $user['meta_business_account_id'];
        $token = $user['meta_permanent_token'];
        $metaTemplateId = $template['meta_template_id'];
        
        $url = "https://graph.facebook.com/{$apiVersion}/{$metaTemplateId}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            $status = strtoupper($data['status'] ?? 'PENDING');
            
            // Atualizar status no banco
            $stmt = $this->pdo->prepare("
                UPDATE meta_message_templates
                SET template_status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $templateId]);
            
            return [
                'success' => true,
                'status' => $status
            ];
        }
        
        return ['success' => false, 'error' => 'Erro ao sincronizar status'];
    }
    
    /**
     * Listar templates do usuário
     */
    public function listTemplates(int $userId, ?string $status = null): array
    {
        $sql = "
            SELECT id, template_name, template_language, template_category,
                   template_status, meta_template_id, created_at, updated_at
            FROM meta_message_templates
            WHERE user_id = ?
        ";
        
        $params = [$userId];
        
        if ($status) {
            $sql .= " AND template_status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter templates aprovados
     */
    public function getApprovedTemplates(int $userId): array
    {
        return $this->listTemplates($userId, 'APPROVED');
    }
    
    /**
     * Obter template por ID
     */
    public function getTemplate(int $templateId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM meta_message_templates
            WHERE id = ?
        ");
        $stmt->execute([$templateId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Deletar template
     */
    public function deleteTemplate(int $templateId): array
    {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            return ['success' => false, 'error' => 'Template não encontrado'];
        }
        
        // Só pode deletar se estiver em DRAFT ou REJECTED
        if (!in_array($template['template_status'], ['DRAFT', 'REJECTED'])) {
            return [
                'success' => false,
                'error' => 'Apenas templates em rascunho ou rejeitados podem ser deletados'
            ];
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM meta_message_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        
        return ['success' => true, 'message' => 'Template deletado com sucesso'];
    }
    
    /**
     * Validar estrutura do template
     */
    private function validateTemplate(string $name, string $category, array $components): array
    {
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Nome do template é obrigatório'];
        }
        
        if (!isset(self::CATEGORIES[$category])) {
            return ['valid' => false, 'error' => 'Categoria inválida'];
        }
        
        if (empty($components)) {
            return ['valid' => false, 'error' => 'Template deve ter pelo menos um componente'];
        }
        
        // Validar que tem pelo menos um componente BODY
        $hasBody = false;
        foreach ($components as $component) {
            if (isset($component['type']) && $component['type'] === 'BODY') {
                $hasBody = true;
                break;
            }
        }
        
        if (!$hasBody) {
            return ['valid' => false, 'error' => 'Template deve ter um componente BODY'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Sanitizar nome do template (Meta API exige lowercase, underscore)
     */
    private function sanitizeTemplateName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        return $name;
    }
    
    /**
     * Criar componente HEADER
     */
    public static function createHeaderComponent(string $type, string $content): array
    {
        return [
            'type' => 'HEADER',
            'format' => strtoupper($type), // TEXT, IMAGE, VIDEO, DOCUMENT
            'text' => $type === 'TEXT' ? $content : null,
            'example' => $type !== 'TEXT' ? ['header_handle' => [$content]] : null
        ];
    }
    
    /**
     * Criar componente BODY
     */
    public static function createBodyComponent(string $text, array $variables = []): array
    {
        $component = [
            'type' => 'BODY',
            'text' => $text
        ];
        
        if (!empty($variables)) {
            $component['example'] = ['body_text' => [$variables]];
        }
        
        return $component;
    }
    
    /**
     * Criar componente FOOTER
     */
    public static function createFooterComponent(string $text): array
    {
        return [
            'type' => 'FOOTER',
            'text' => $text
        ];
    }
    
    /**
     * Criar componente BUTTONS
     */
    public static function createButtonsComponent(array $buttons): array
    {
        return [
            'type' => 'BUTTONS',
            'buttons' => $buttons
        ];
    }
}
