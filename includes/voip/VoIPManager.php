<?php
/**
 * VoIP Manager - Gerenciamento de VoIP com FreeSWITCH
 * 
 * @package WATS
 * @subpackage VoIP
 */

class VoIPManager {
    private $pdo;
    private $freeswitchAPI;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->freeswitchAPI = new FreeSwitchAPI();
    }
    
    /**
     * Criar usuário VoIP
     */
    public function createVoIPUser(int $userId, array $data): array {
        try {
            // Gerar extensão única
            $extension = $this->generateExtension();
            
            // Gerar senha SIP segura
            $sipPassword = bin2hex(random_bytes(16));
            
            // Inserir no banco
            $stmt = $this->pdo->prepare("
                INSERT INTO voip_users (
                    user_id, sip_username, sip_password, sip_extension,
                    display_name, voicemail_enabled, voicemail_password
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $data['username'] ?? "user_{$userId}",
                password_hash($sipPassword, PASSWORD_BCRYPT),
                $extension,
                $data['display_name'] ?? '',
                $data['voicemail_enabled'] ?? true,
                $data['voicemail_password'] ?? $extension
            ]);
            
            $voipUserId = $this->pdo->lastInsertId();
            
            // Criar usuário no FreeSWITCH
            $this->freeswitchAPI->createUser($extension, $sipPassword, $data);
            
            return [
                'success' => true,
                'voip_user_id' => $voipUserId,
                'extension' => $extension,
                'sip_password' => $sipPassword
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter credenciais SIP do usuário
     */
    public function getUserCredentials(int $userId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT id, sip_username, sip_extension, sip_domain, display_name
            FROM voip_users
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Registrar chamada
     */
    public function registerCall(array $callData): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO voip_calls (
                user_id, voip_user_id, contact_id, call_id,
                direction, caller_number, caller_name,
                callee_number, callee_name, status, start_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $callData['user_id'],
            $callData['voip_user_id'],
            $callData['contact_id'] ?? null,
            $callData['call_id'],
            $callData['direction'],
            $callData['caller_number'],
            $callData['caller_name'] ?? '',
            $callData['callee_number'],
            $callData['callee_name'] ?? '',
            'initiated'
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Atualizar status da chamada
     */
    public function updateCallStatus(string $callId, string $status, array $data = []): bool {
        $updates = ['status = ?'];
        $params = [$status];
        
        if ($status === 'answered' && !isset($data['answer_time'])) {
            $updates[] = 'answer_time = NOW()';
        }
        
        if ($status === 'ended' && !isset($data['end_time'])) {
            $updates[] = 'end_time = NOW()';
            $updates[] = 'duration = TIMESTAMPDIFF(SECOND, answer_time, NOW())';
        }
        
        if (isset($data['hangup_cause'])) {
            $updates[] = 'hangup_cause = ?';
            $params[] = $data['hangup_cause'];
        }
        
        if (isset($data['quality_score'])) {
            $updates[] = 'quality_score = ?';
            $params[] = $data['quality_score'];
        }
        
        $params[] = $callId;
        
        $stmt = $this->pdo->prepare("
            UPDATE voip_calls
            SET " . implode(', ', $updates) . "
            WHERE call_id = ?
        ");
        
        return $stmt->execute($params);
    }
    
    /**
     * Obter histórico de chamadas
     */
    public function getCallHistory(int $userId, array $filters = []): array {
        $where = ['user_id = ?'];
        $params = [$userId];
        
        if (!empty($filters['direction'])) {
            $where[] = 'direction = ?';
            $params[] = $filters['direction'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'start_time >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'start_time <= ?';
            $params[] = $filters['date_to'];
        }
        
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        
        $stmt = $this->pdo->prepare("
            SELECT 
                vc.*,
                c.name as contact_name,
                c.phone as contact_phone
            FROM voip_calls vc
            LEFT JOIN contacts c ON vc.contact_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY start_time DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Gerar extensão única
     */
    private function generateExtension(): string {
        do {
            $extension = '1' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM voip_users WHERE sip_extension = ?");
            $stmt->execute([$extension]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);
        
        return $extension;
    }
    
    /**
     * Atualizar configurações VoIP do usuário
     */
    public function updateSettings(int $userId, array $settings): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO voip_settings (
                user_id, codec_preference, video_enabled,
                echo_cancellation, noise_suppression, auto_gain_control, ring_timeout
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                codec_preference = VALUES(codec_preference),
                video_enabled = VALUES(video_enabled),
                echo_cancellation = VALUES(echo_cancellation),
                noise_suppression = VALUES(noise_suppression),
                auto_gain_control = VALUES(auto_gain_control),
                ring_timeout = VALUES(ring_timeout)
        ");
        
        return $stmt->execute([
            $userId,
            $settings['codec_preference'] ?? 'OPUS,PCMU,PCMA',
            $settings['video_enabled'] ?? false,
            $settings['echo_cancellation'] ?? true,
            $settings['noise_suppression'] ?? true,
            $settings['auto_gain_control'] ?? true,
            $settings['ring_timeout'] ?? 30
        ]);
    }
}
