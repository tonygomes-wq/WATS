<?php
/**
 * Política de Retenção de Dados
 * Define quanto tempo cada tipo de dado é mantido no sistema
 * 
 * MACIP Tecnologia LTDA
 * Sistema WATS - WhatsApp Sender
 */

if (!defined('DATA_RETENTION_CONFIG')) {
    define('DATA_RETENTION_CONFIG', true);
}

/**
 * POLÍTICA DE RETENÇÃO POR TIPO DE DADO
 * Valores em dias
 */
return [
    // ============================================
    // MENSAGENS E CONVERSAS
    // ============================================
    'messages' => [
        'chat_messages' => 180,           // 6 meses - Mensagens do chat
        'dispatch_history' => 365,        // 1 ano - Histórico de disparos
        'webhook_logs' => 30,             // 30 dias - Logs de webhook
        'failed_messages' => 90,          // 3 meses - Mensagens com falha
    ],
    
    // ============================================
    // LOGS E AUDITORIA
    // ============================================
    'logs' => [
        'audit_logs' => 365,              // 1 ano - Logs de auditoria
        'login_attempts' => 90,           // 3 meses - Tentativas de login
        'api_logs' => 60,                 // 2 meses - Logs de API
        'error_logs' => 30,               // 30 dias - Logs de erro
        'debug_logs' => 7,                // 7 dias - Logs de debug
    ],
    
    // ============================================
    // CAMPANHAS E RELATÓRIOS
    // ============================================
    'campaigns' => [
        'completed_campaigns' => 365,     // 1 ano - Campanhas concluídas
        'draft_campaigns' => 90,          // 3 meses - Rascunhos não usados
        'campaign_responses' => 180,      // 6 meses - Respostas de campanhas
        'dispatch_reports' => 365,        // 1 ano - Relatórios de disparo
    ],
    
    // ============================================
    // MÍDIA E ARQUIVOS
    // ============================================
    'media' => [
        'uploaded_media' => 180,          // 6 meses - Arquivos enviados
        'temp_files' => 1,                // 1 dia - Arquivos temporários
        'qr_codes' => 7,                  // 7 dias - QR Codes de conexão
        'backup_files' => 30,             // 30 dias - Backups locais
    ],
    
    // ============================================
    // SESSÕES E TOKENS
    // ============================================
    'sessions' => [
        'expired_sessions' => 7,          // 7 dias - Sessões expiradas
        'api_tokens' => 90,               // 3 meses - Tokens inativos
        'password_reset_tokens' => 1,     // 1 dia - Tokens de reset
        'verification_codes' => 1,        // 1 dia - Códigos de verificação
    ],
    
    // ============================================
    // NOTIFICAÇÕES
    // ============================================
    'notifications' => [
        'read_notifications' => 30,       // 30 dias - Notificações lidas
        'unread_notifications' => 90,     // 3 meses - Não lidas
    ],
    
    // ============================================
    // CONFIGURAÇÕES DE LIMPEZA
    // ============================================
    'cleanup_settings' => [
        'enabled' => true,                // Ativar limpeza automática
        'run_hour' => 3,                  // Hora de execução (3h da manhã)
        'batch_size' => 1000,             // Registros por lote
        'max_execution_time' => 300,      // 5 minutos máximo
        'log_cleanup' => true,            // Registrar limpezas
    ],
    
    // ============================================
    // LIMITES POR PLANO
    // ============================================
    'plan_limits' => [
        'free' => [
            'max_contacts' => 100,
            'max_messages_month' => 500,
            'max_storage_mb' => 50,
            'max_campaigns' => 5,
            'retention_days' => 30,       // Dados mantidos por 30 dias
        ],
        'basic' => [
            'max_contacts' => 1000,
            'max_messages_month' => 5000,
            'max_storage_mb' => 500,
            'max_campaigns' => 50,
            'retention_days' => 90,       // 3 meses
        ],
        'professional' => [
            'max_contacts' => 10000,
            'max_messages_month' => 50000,
            'max_storage_mb' => 5000,
            'max_campaigns' => 500,
            'retention_days' => 180,      // 6 meses
        ],
        'enterprise' => [
            'max_contacts' => -1,         // Ilimitado
            'max_messages_month' => -1,   // Ilimitado
            'max_storage_mb' => -1,       // Ilimitado
            'max_campaigns' => -1,        // Ilimitado
            'retention_days' => 365,      // 1 ano
        ],
    ],
    
    // ============================================
    // ALERTAS DE STORAGE
    // ============================================
    'storage_alerts' => [
        'warning_threshold' => 80,        // Alerta em 80% de uso
        'critical_threshold' => 95,       // Crítico em 95% de uso
        'check_interval_hours' => 6,      // Verificar a cada 6 horas
        'notify_admin' => true,           // Notificar administrador
        'notify_user' => true,            // Notificar usuário
    ],
];
