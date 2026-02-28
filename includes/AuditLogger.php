<?php
/**
 * Sistema de Auditoria (LGPD/GDPR Compliance)
 * Registra todas as ações críticas do sistema
 * 
 * MACIP Tecnologia LTDA
 */

class AuditLogger {
    private $pdo;
    
    public function __construct($pdo = null) {
        global $pdo as $globalPdo;
        $this->pdo = $pdo ?? $globalPdo;
    }
    
    /**
     * Registrar ação no log de auditoria
     */
    public function log($action, $entityType, $entityId = null, $oldValues = null, $newValues = null, $userId = null) {
        try {
            // Obter user_id da sessão se não fornecido
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }
            
            // Obter IP e User Agent
            $ipAddress = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // Definir variável de sessão para triggers
            if ($ipAddress) {
                $this->pdo->exec("SET @user_ip = " . $this->pdo->quote($ipAddress));
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $action,
                $entityType,
                $entityId,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $ipAddress,
                $userAgent
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar login
     */
    public function logLogin($userId, $success = true, $failureReason = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO access_logs (user_id, action, ip_address, user_agent, success, failure_reason)
                VALUES (?, 'login', ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $this->getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $success ? 1 : 0,
                $failureReason
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao registrar login: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar logout
     */
    public function logLogout($userId) {
        return $this->log('logout', 'session', null, null, null, $userId);
    }
    
    /**
     * Registrar criação de entidade
     */
    public function logCreate($entityType, $entityId, $data, $userId = null) {
        return $this->log('create', $entityType, $entityId, null, $data, $userId);
    }
    
    /**
     * Registrar atualização de entidade
     */
    public function logUpdate($entityType, $entityId, $oldData, $newData, $userId = null) {
        return $this->log('update', $entityType, $entityId, $oldData, $newData, $userId);
    }
    
    /**
     * Registrar exclusão de entidade
     */
    public function logDelete($entityType, $entityId, $data, $userId = null) {
        return $this->log('delete', $entityType, $entityId, $data, null, $userId);
    }
    
    /**
     * Registrar exportação de dados (LGPD)
     */
    public function logDataExport($userId, $dataType) {
        return $this->log('data_export', 'user_data', $userId, null, ['data_type' => $dataType], $userId);
    }
    
    /**
     * Registrar solicitação de exclusão (LGPD)
     */
    public function logDataDeletion($userId, $requestId) {
        return $this->log('data_deletion_request', 'user_data', $userId, null, ['request_id' => $requestId], $userId);
    }
    
    /**
     * Buscar logs de auditoria
     */
    public function getLogs($filters = []) {
        $where = [];
        $params = [];
        
        if (isset($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
        }
        
        if (isset($filters['entity_type'])) {
            $where[] = "entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (isset($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $limit = $filters['limit'] ?? 100;
        
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.name as user_name, u.email as user_email
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            $whereClause
            ORDER BY a.created_at DESC
            LIMIT $limit
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter estatísticas de auditoria
     */
    public function getStatistics($days = 30) {
        $stmt = $this->pdo->prepare("
            SELECT 
                action,
                COUNT(*) as count,
                COUNT(DISTINCT user_id) as unique_users
            FROM audit_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY action
            ORDER BY count DESC
        ");
        
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter IP do cliente
     */
    private function getClientIp() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Limpar logs antigos
     */
    public function cleanup($days = 90) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM audit_log 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            
            $stmt = $this->pdo->prepare("
                DELETE FROM access_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao limpar logs: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Classe para gerenciar consentimentos (LGPD/GDPR)
 */
class ConsentManager {
    private $pdo;
    
    public function __construct($pdo = null) {
        global $pdo as $globalPdo;
        $this->pdo = $pdo ?? $globalPdo;
    }
    
    /**
     * Registrar consentimento
     */
    public function recordConsent($userId, $consentType, $consentText, $version = '1.0') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_consents (user_id, consent_type, consent_given, consent_text, consent_version, ip_address, user_agent, consented_at)
                VALUES (?, ?, 1, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    consent_given = 1,
                    consent_text = VALUES(consent_text),
                    consent_version = VALUES(consent_version),
                    consented_at = NOW(),
                    revoked_at = NULL
            ");
            
            $stmt->execute([
                $userId,
                $consentType,
                $consentText,
                $version,
                $this->getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            // Auditar
            $audit = new AuditLogger($this->pdo);
            $audit->log('consent_given', 'consent', null, null, ['type' => $consentType, 'version' => $version], $userId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao registrar consentimento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Revogar consentimento
     */
    public function revokeConsent($userId, $consentType) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_consents 
                SET consent_given = 0, revoked_at = NOW()
                WHERE user_id = ? AND consent_type = ?
            ");
            
            $stmt->execute([$userId, $consentType]);
            
            // Auditar
            $audit = new AuditLogger($this->pdo);
            $audit->log('consent_revoked', 'consent', null, null, ['type' => $consentType], $userId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao revogar consentimento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se usuário deu consentimento
     */
    public function hasConsent($userId, $consentType) {
        $stmt = $this->pdo->prepare("
            SELECT consent_given 
            FROM user_consents 
            WHERE user_id = ? AND consent_type = ? AND consent_given = 1 AND revoked_at IS NULL
        ");
        
        $stmt->execute([$userId, $consentType]);
        return $stmt->fetchColumn() == 1;
    }
    
    /**
     * Obter todos os consentimentos do usuário
     */
    public function getUserConsents($userId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM user_consents 
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getClientIp() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }
}
