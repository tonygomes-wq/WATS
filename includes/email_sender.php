<?php
/**
 * Classe para Envio de Emails
 * Suporta SMTP tradicional e OAuth 2.0 (Microsoft 365)
 */

// Carregar PHPMailer manualmente (estrutura: /vendor/phpmailer/src/)
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

// Carregar configuração OAuth
if (file_exists(__DIR__ . '/../config/oauth_config.php')) {
    require_once __DIR__ . '/../config/oauth_config.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private $pdo;
    private $user_id;
    private $settings;
    
    public function __construct($pdo = null, $user_id = null) {
        global $pdo;
        $this->pdo = $pdo ?? $GLOBALS['pdo'];
        $this->user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        
        if ($this->user_id) {
            $this->loadSettings();
        }
    }
    
    /**
     * Carregar configurações de email do usuário
     */
    private function loadSettings() {
        $stmt = $this->pdo->prepare("SELECT * FROM email_settings WHERE user_id = ?");
        $stmt->execute([$this->user_id]);
        $this->settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Enviar notificação usando template
     */
    public function sendNotification($template_type, $recipient_email, $variables = []) {
        try {
            // Verificar se notificações estão habilitadas
            if (!$this->settings || !$this->settings['is_enabled']) {
                return ['success' => false, 'error' => 'Notificações por email desabilitadas'];
            }
            
            // Buscar template
            $stmt = $this->pdo->prepare("
                SELECT * FROM email_templates 
                WHERE user_id = ? AND type = ? AND is_active = 1
                ORDER BY is_default DESC
                LIMIT 1
            ");
            $stmt->execute([$this->user_id, $template_type]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                return ['success' => false, 'error' => 'Template não encontrado'];
            }
            
            // Substituir variáveis
            $subject = $this->replaceVariables($template['subject'], $variables);
            $body = $this->replaceVariables($template['body'], $variables);
            
            // Enviar email
            $result = $this->send($recipient_email, $subject, $body);
            
            // Registrar log
            $this->logEmail($recipient_email, $subject, $template_type, $result['success'], $result['error'] ?? null);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logEmail($recipient_email, '', $template_type, false, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Enviar email direto
     */
    public function send($to, $subject, $body, $isHtml = true) {
        // Verificar se está usando OAuth
        if ($this->isOAuthEnabled()) {
            return $this->sendWithOAuth($to, $subject, $body, $isHtml);
        }
        
        // Envio tradicional via SMTP
        return $this->sendWithSMTP($to, $subject, $body, $isHtml);
    }
    
    /**
     * Verificar se OAuth está habilitado
     */
    private function isOAuthEnabled() {
        return isset($this->settings['oauth_provider']) && 
               !empty($this->settings['oauth_provider']) &&
               $this->settings['smtp_encryption'] === 'oauth2';
    }
    
    /**
     * Enviar email usando OAuth 2.0 (Microsoft Graph API)
     */
    private function sendWithOAuth($to, $subject, $body, $isHtml = true) {
        try {
            // Obter tokens
            $tokens = json_decode($this->settings['oauth_tokens'], true);
            
            if (!$tokens || !isset($tokens['access_token'])) {
                return ['success' => false, 'error' => 'Tokens OAuth não encontrados. Reconecte sua conta Microsoft.'];
            }
            
            // Verificar se token expirou
            $accessToken = $this->getValidAccessToken($tokens);
            
            if (!$accessToken) {
                return ['success' => false, 'error' => 'Não foi possível obter token válido. Reconecte sua conta Microsoft.'];
            }
            
            // Enviar usando Microsoft Graph
            $result = sendEmailWithGraph($accessToken, $to, $subject, $body, $isHtml);
            
            if ($result['success']) {
                return ['success' => true, 'message' => 'Email enviado com sucesso via Microsoft 365'];
            } else {
                return ['success' => false, 'error' => 'Falha ao enviar: HTTP ' . $result['http_code']];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Obter access token válido (renovar se necessário)
     */
    private function getValidAccessToken($tokens) {
        $obtainedAt = $tokens['obtained_at'] ?? 0;
        $expiresIn = $tokens['expires_in'] ?? 3600;
        $expiresAt = $obtainedAt + $expiresIn - 300; // 5 minutos de margem
        
        // Se token ainda é válido, retornar
        if (time() < $expiresAt) {
            return $tokens['access_token'];
        }
        
        // Token expirado, tentar renovar
        if (empty($tokens['refresh_token'])) {
            return null;
        }
        
        $newTokens = refreshAccessToken($tokens['refresh_token']);
        
        if (isset($newTokens['error'])) {
            return null;
        }
        
        // Salvar novos tokens
        $tokenData = [
            'access_token' => $newTokens['access_token'],
            'refresh_token' => $newTokens['refresh_token'] ?? $tokens['refresh_token'],
            'expires_in' => $newTokens['expires_in'] ?? 3600,
            'token_type' => $newTokens['token_type'] ?? 'Bearer',
            'obtained_at' => time()
        ];
        
        $stmt = $this->pdo->prepare("UPDATE email_settings SET oauth_tokens = ? WHERE user_id = ?");
        $stmt->execute([json_encode($tokenData), $this->user_id]);
        
        return $newTokens['access_token'];
    }
    
    /**
     * Enviar email usando SMTP tradicional
     */
    private function sendWithSMTP($to, $subject, $body, $isHtml = true) {
        try {
            $mail = new PHPMailer(true);
            
            // Configurações SMTP
            $mail->isSMTP();
            $mail->Host = $this->settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->settings['smtp_username'];
            $mail->Password = $this->settings['smtp_password'];
            $mail->Port = $this->settings['smtp_port'];
            
            // Encryption
            if ($this->settings['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->settings['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            
            // Charset
            $mail->CharSet = 'UTF-8';
            
            // Remetente
            $mail->setFrom(
                $this->settings['from_email'] ?? $this->settings['smtp_username'],
                $this->settings['from_name'] ?? 'Sistema de Atendimento'
            );
            
            // Destinatário
            $mail->addAddress($to);
            
            // Conteúdo
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            // Versão texto alternativa
            if ($isHtml) {
                $mail->AltBody = strip_tags($body);
            }
            
            // Enviar
            $mail->send();
            
            return ['success' => true, 'message' => 'Email enviado com sucesso'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Substituir variáveis no template
     */
    private function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        return $text;
    }
    
    /**
     * Registrar log de email
     */
    private function logEmail($recipient, $subject, $template_type, $success, $error = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_logs 
                (user_id, recipient_email, subject, template_type, status, error_message, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->user_id,
                $recipient,
                $subject,
                $template_type,
                $success ? 'sent' : 'failed',
                $error,
                $success ? date('Y-m-d H:i:s') : null
            ]);
        } catch (Exception $e) {
            error_log("Erro ao registrar log de email: " . $e->getMessage());
        }
    }
    
    /**
     * Enviar resumo diário
     */
    public function sendDailySummary($supervisor_id, $date = null) {
        $date = $date ?? date('Y-m-d');
        
        // Buscar dados do supervisor
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$supervisor_id]);
        $supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$supervisor) {
            return ['success' => false, 'error' => 'Supervisor não encontrado'];
        }
        
        // Buscar estatísticas do dia
        $stats = $this->getDailyStats($supervisor_id, $date);
        
        // Preparar variáveis
        $variables = [
            'supervisor_nome' => $supervisor['name'],
            'data' => date('d/m/Y', strtotime($date)),
            'total_conversas' => $stats['total_conversations'],
            'conversas_resolvidas' => $stats['resolved_conversations'],
            'taxa_resolucao' => number_format($stats['resolution_rate'], 1),
            'tempo_medio' => $this->formatTime($stats['avg_time']),
            'satisfacao_media' => number_format($stats['avg_satisfaction'], 1),
            'top1_nome' => $stats['top_attendants'][0]['name'] ?? '-',
            'top1_conversas' => $stats['top_attendants'][0]['count'] ?? 0,
            'top2_nome' => $stats['top_attendants'][1]['name'] ?? '-',
            'top2_conversas' => $stats['top_attendants'][1]['count'] ?? 0,
            'top3_nome' => $stats['top_attendants'][2]['name'] ?? '-',
            'top3_conversas' => $stats['top_attendants'][2]['count'] ?? 0,
            'link_relatorios' => 'https://' . $_SERVER['HTTP_HOST'] . '/reports.php'
        ];
        
        return $this->sendNotification('daily_summary', $supervisor['email'], $variables);
    }
    
    /**
     * Obter estatísticas diárias
     */
    private function getDailyStats($supervisor_id, $date) {
        // Total de conversas
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total
            FROM chat_conversations
            WHERE supervisor_id = ? AND DATE(created_at) = ?
        ");
        $stmt->execute([$supervisor_id, $date]);
        $total = $stmt->fetch()['total'];
        
        // Conversas resolvidas
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as resolved
            FROM chat_conversations
            WHERE supervisor_id = ? AND DATE(created_at) = ? AND status = 'closed'
        ");
        $stmt->execute([$supervisor_id, $date]);
        $resolved = $stmt->fetch()['resolved'];
        
        // Taxa de resolução
        $resolution_rate = $total > 0 ? ($resolved / $total) * 100 : 0;
        
        // Tempo médio
        $stmt = $this->pdo->prepare("
            SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_time
            FROM chat_conversations
            WHERE supervisor_id = ? AND DATE(created_at) = ? AND status = 'closed'
        ");
        $stmt->execute([$supervisor_id, $date]);
        $avg_time = $stmt->fetch()['avg_time'] ?? 0;
        
        // Satisfação média (assumindo que existe uma tabela de avaliações)
        $avg_satisfaction = 4.5; // Placeholder
        
        // Top 3 atendentes
        $stmt = $this->pdo->prepare("
            SELECT su.name, COUNT(*) as count
            FROM chat_conversations cc
            JOIN supervisor_users su ON cc.assigned_to = su.id
            WHERE cc.supervisor_id = ? AND DATE(cc.created_at) = ?
            GROUP BY su.id, su.name
            ORDER BY count DESC
            LIMIT 3
        ");
        $stmt->execute([$supervisor_id, $date]);
        $top_attendants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'total_conversations' => $total,
            'resolved_conversations' => $resolved,
            'resolution_rate' => $resolution_rate,
            'avg_time' => $avg_time,
            'avg_satisfaction' => $avg_satisfaction,
            'top_attendants' => $top_attendants
        ];
    }
    
    /**
     * Formatar tempo em segundos
     */
    private function formatTime($seconds) {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        
        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return $minutes . 'min';
        }
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return $hours . 'h ' . $mins . 'min';
    }
    
    /**
     * Testar configurações de email
     */
    public function testConnection($to = null) {
        $to = $to ?? $this->settings['smtp_username'];
        
        $subject = 'Teste de Configuração de Email';
        $body = '<h2>Teste de Email</h2><p>Se você recebeu este email, suas configurações estão corretas!</p>';
        
        return $this->send($to, $subject, $body);
    }
}
