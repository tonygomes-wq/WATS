<?php
/**
 * Painel Administrativo - Retenção de Dados e Storage
 * Interface visual para gerenciar políticas de retenção e monitorar storage
 * 
 * MACIP Tecnologia LTDA
 */

// Iniciar sessão
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/StorageMonitor.php';

requireLogin();
requireAdmin();

$page_title = 'Gerenciamento de Dados e Storage';

// Carregar política de retenção
$retentionPolicy = require 'config/data_retention.php';

// Inicializar monitor
$monitor = new StorageMonitor($pdo);

// Obter estatísticas com tratamento de erro
try {
    $systemStats = $monitor->getSystemStats();
} catch (Exception $e) {
    error_log("Erro ao obter estatísticas: " . $e->getMessage());
    $systemStats = [
        'total_users' => 0,
        'total_size_mb' => 0,
        'avg_size_mb' => 0,
        'users_over_limit' => 0
    ];
}

try {
    $alerts = $monitor->checkAllUsers();
} catch (Exception $e) {
    error_log("Erro ao verificar alertas: " . $e->getMessage());
    $alerts = [];
}

// Obter histórico de limpezas (últimas 10)
try {
    $stmt = $pdo->query("
        SELECT * FROM cleanup_history 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $cleanupHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar histórico de limpezas: " . $e->getMessage());
    $cleanupHistory = [];
}

// Obter alertas ativos
try {
    $stmt = $pdo->query("
        SELECT * FROM v_active_storage_alerts 
        ORDER BY percentage_used DESC
    ");
    $activeAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar alertas ativos: " . $e->getMessage());
    $activeAlerts = [];
}

// Obter uso por usuário (top 10)
try {
    $stmt = $pdo->query("
        SELECT * FROM v_user_storage_summary 
        ORDER BY percentage_used DESC 
        LIMIT 10
    ");
    $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar top usuários: " . $e->getMessage());
    $topUsers = [];
}

require_once 'includes/header_spa.php';

// Verificar se há erros
$hasErrors = ($systemStats['total_users'] === 0 && $systemStats['total_size_mb'] === 0);
?>

<div class="refined-container">
    
    <?php if ($hasErrors): ?>
    <!-- Aviso de Tabelas Faltando -->
    <div class="refined-card" style="background: #fef3c7; border-left: 4px solid #f59e0b; margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: start; gap: 12px;">
            <i class="fas fa-exclamation-triangle" style="color: #f59e0b; font-size: 20px; margin-top: 2px;"></i>
            <div>
                <h3 style="font-size: 16px; font-weight: 600; color: #92400e; margin-bottom: 8px;">
                    ⚠️ Tabelas do Sistema Não Encontradas
                </h3>
                <p style="font-size: 14px; color: #78350f; margin-bottom: 12px;">
                    As tabelas necessárias para o sistema de retenção de dados não foram criadas no banco de dados.
                </p>
                <div style="background: white; border-radius: 4px; padding: 12px; margin-bottom: 12px;">
                    <p style="font-size: 13px; color: #78350f; margin-bottom: 8px;">
                        <strong>Para corrigir, execute o seguinte SQL no banco de dados:</strong>
                    </p>
                    <code style="display: block; background: #f9fafb; padding: 8px; border-radius: 4px; font-size: 12px; color: #1f2937; font-family: monospace;">
                        SOURCE migrations/data_retention_tables.sql;
                    </code>
                </div>
                <p style="font-size: 13px; color: #78350f;">
                    Ou acesse o arquivo <code style="background: white; padding: 2px 6px; border-radius: 3px;">migrations/data_retention_tables.sql</code> 
                    e execute-o manualmente no phpMyAdmin ou outro gerenciador de banco de dados.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Header com Título e Ações -->
    <div class="refined-card" style="background: linear-gradient(135deg, #6366f1, #4f46e5); border: none; color: white; margin-bottom: var(--space-6);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="font-size: 24px; font-weight: 600; margin-bottom: 8px; color: white;">
                    <i class="fas fa-database"></i> Gerenciamento de Dados e Storage
                </h1>
                <p style="font-size: 13px; opacity: 0.9;">
                    Monitore uso de storage, configure políticas de retenção e gerencie limpeza de dados
                </p>
            </div>
            <div style="display: flex; gap: 8px;">
                <button onclick="forceCleanup()" class="refined-btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                    <i class="fas fa-broom"></i> Executar Limpeza
                </button>
                <button onclick="refreshStats()" class="refined-btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                    <i class="fas fa-sync"></i> Atualizar
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs de Navegação -->
    <div class="refined-tabs" style="margin-bottom: var(--space-6);">
        <button class="refined-tab active" onclick="switchTab('dashboard')">
            <i class="fas fa-chart-line"></i> Dashboard
        </button>
        <button class="refined-tab" onclick="switchTab('config')">
            <i class="fas fa-cog"></i> Configurações
        </button>
        <button class="refined-tab" onclick="switchTab('users')">
            <i class="fas fa-users"></i> Usuários
        </button>
        <button class="refined-tab" onclick="switchTab('logs')">
            <i class="fas fa-file-alt"></i> Logs
        </button>
    </div>

    <!-- TAB: Dashboard -->
    <div id="tab-dashboard" class="tab-content active">
        
        <!-- Cards de Estatísticas -->
        <div class="refined-grid refined-grid-4" style="margin-bottom: var(--space-6);">
            
            <!-- Total Storage -->
            <div class="refined-card" style="border-left: 3px solid #6366f1;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(99, 102, 241, 0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-database" style="color: #6366f1; font-size: 18px;"></i>
                    </div>
                </div>
                <div style="font-size: 28px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">
                    <?php echo number_format($systemStats['total_size_mb'], 2); ?> MB
                </div>
                <div style="font-size: 13px; color: var(--text-secondary);">
                    Storage Total Usado
                </div>
            </div>

            <!-- Total Usuários -->
            <div class="refined-card" style="border-left: 3px solid #10b981;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-users" style="color: #10b981; font-size: 18px;"></i>
                    </div>
                </div>
                <div style="font-size: 28px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">
                    <?php echo number_format($systemStats['total_users']); ?>
                </div>
                <div style="font-size: 13px; color: var(--text-secondary);">
                    Usuários Ativos
                </div>
            </div>

            <!-- Alertas Ativos -->
            <div class="refined-card" style="border-left: 3px solid <?php echo count($activeAlerts) > 0 ? '#ef4444' : '#10b981'; ?>;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-exclamation-triangle" style="color: #ef4444; font-size: 18px;"></i>
                    </div>
                </div>
                <div style="font-size: 28px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">
                    <?php echo count($activeAlerts); ?>
                </div>
                <div style="font-size: 13px; color: var(--text-secondary);">
                    Alertas de Storage
                </div>
            </div>

            <!-- Média por Usuário -->
            <div class="refined-card" style="border-left: 3px solid #f59e0b;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-chart-bar" style="color: #f59e0b; font-size: 18px;"></i>
                    </div>
                </div>
                <div style="font-size: 28px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">
                    <?php echo number_format($systemStats['avg_per_user_mb'], 2); ?> MB
                </div>
                <div style="font-size: 13px; color: var(--text-secondary);">
                    Média por Usuário
                </div>
            </div>

        </div>

        <!-- Alertas Críticos -->
        <?php if (count($activeAlerts) > 0): ?>
        <div class="refined-card" style="margin-bottom: var(--space-6); border-left: 3px solid #ef4444;">
            <h2 class="refined-title" style="font-size: 16px; margin-bottom: var(--space-4);">
                <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> 
                Alertas de Storage Ativos
            </h2>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Usuário</th>
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Plano</th>
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Uso</th>
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Nível</th>
                            <th style="padding: 12px; text-align: right; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeAlerts as $alert): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 12px;">
                                <div style="font-size: 14px; font-weight: 500; color: var(--text-primary);">
                                    <?php echo htmlspecialchars($alert['name']); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">
                                    <?php echo htmlspecialchars($alert['email']); ?>
                                </div>
                            </td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: var(--bg-secondary); color: var(--text-primary);">
                                    <?php echo strtoupper($alert['plan']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-size: 14px; font-weight: 500; color: var(--text-primary);">
                                    <?php echo number_format($alert['percentage_used'], 1); ?>%
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">
                                    <?php echo number_format($alert['used_mb'], 2); ?> / <?php echo number_format($alert['limit_mb'], 2); ?> MB
                                </div>
                                <div style="margin-top: 4px; height: 4px; background: var(--bg-secondary); border-radius: 2px; overflow: hidden;">
                                    <div style="height: 100%; width: <?php echo min($alert['percentage_used'], 100); ?>%; background: <?php echo $alert['alert_level'] === 'critical' ? '#ef4444' : '#f59e0b'; ?>; transition: width 0.3s;"></div>
                                </div>
                            </td>
                            <td style="padding: 12px;">
                                <?php if ($alert['alert_level'] === 'critical'): ?>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                        🔴 CRÍTICO
                                    </span>
                                <?php else: ?>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                                        ⚠️ AVISO
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: right;">
                                <button onclick="cleanupUser(<?php echo $alert['id']; ?>)" class="refined-btn refined-btn-sm">
                                    <i class="fas fa-broom"></i> Limpar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Top 10 Usuários por Storage -->
        <div class="refined-card" style="margin-bottom: var(--space-6);">
            <h2 class="refined-title" style="font-size: 16px; margin-bottom: var(--space-4);">
                <i class="fas fa-chart-pie"></i> Top 10 Usuários por Uso de Storage
            </h2>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Usuário</th>
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Plano</th>
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Uso de Storage</th>
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topUsers as $user): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 12px;">
                                <div style="font-size: 14px; font-weight: 500; color: var(--text-primary);">
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </div>
                            </td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: var(--bg-secondary); color: var(--text-primary);">
                                    <?php echo strtoupper($user['plan']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-size: 14px; font-weight: 500; color: var(--text-primary); margin-bottom: 4px;">
                                    <?php echo number_format($user['used_mb'], 2); ?> MB
                                    <?php if ($user['limit_mb'] > 0): ?>
                                        / <?php echo number_format($user['limit_mb'], 2); ?> MB
                                    <?php else: ?>
                                        / Ilimitado
                                    <?php endif; ?>
                                </div>
                                <?php if ($user['limit_mb'] > 0): ?>
                                <div style="height: 6px; background: var(--bg-secondary); border-radius: 3px; overflow: hidden;">
                                    <div style="height: 100%; width: <?php echo min($user['percentage_used'], 100); ?>%; background: <?php 
                                        if ($user['percentage_used'] >= 95) echo '#ef4444';
                                        elseif ($user['percentage_used'] >= 80) echo '#f59e0b';
                                        else echo '#10b981';
                                    ?>; transition: width 0.3s;"></div>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                                    <?php echo number_format($user['percentage_used'], 1); ?>% usado
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php 
                                $status = $user['status'];
                                if ($status === 'critical'): ?>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                        🔴 Crítico
                                    </span>
                                <?php elseif ($status === 'warning'): ?>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                                        ⚠️ Aviso
                                    </span>
                                <?php else: ?>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                        ✓ OK
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Histórico de Limpezas -->
        <div class="refined-card">
            <h2 class="refined-title" style="font-size: 16px; margin-bottom: var(--space-4);">
                <i class="fas fa-history"></i> Histórico de Limpezas Recentes
            </h2>
            
            <?php if (empty($cleanupHistory)): ?>
                <div class="refined-empty">
                    <i class="fas fa-broom"></i>
                    <p>Nenhuma limpeza executada ainda</p>
                </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Data/Hora</th>
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Registros Deletados</th>
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Tempo de Execução</th>
                            <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary);">Storage Final</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cleanupHistory as $cleanup): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 12px; font-size: 14px; color: var(--text-primary);">
                                <?php echo date('d/m/Y H:i', strtotime($cleanup['created_at'])); ?>
                            </td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 13px; font-weight: 500; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                    <?php echo number_format($cleanup['total_deleted']); ?> registros
                                </span>
                            </td>
                            <td style="padding: 12px; font-size: 14px; color: var(--text-secondary);">
                                <?php echo number_format($cleanup['execution_time'], 2); ?>s
                            </td>
                            <td style="padding: 12px; font-size: 14px; color: var(--text-secondary);">
                                <?php echo number_format($cleanup['storage_size_mb'], 2); ?> MB
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- TAB: Configurações -->
    <div id="tab-config" class="tab-content" style="display: none;">
        <div class="refined-card">
            <h2 class="refined-title" style="font-size: 18px; margin-bottom: var(--space-6);">
                <i class="fas fa-cog"></i> Configurações de Retenção de Dados
            </h2>

            <form id="configForm" onsubmit="saveConfig(event)">
                
                <!-- Mensagens -->
                <div style="margin-bottom: var(--space-6);">
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        📨 Mensagens e Conversas
                    </h3>
                    <div class="refined-grid refined-grid-2">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">
                                Mensagens do Chat (dias)
                            </label>
                            <input type="number" name="chat_messages" value="<?php echo $retentionPolicy['messages']['chat_messages']; ?>" 
                                   class="refined-input" min="1" required>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">
                                Histórico de Disparos (dias)
                            </label>
                            <input type="number" name="dispatch_history" value="<?php echo $retentionPolicy['messages']['dispatch_history']; ?>" 
                                   class="refined-input" min="1" required>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">
                                Logs de Webhook (dias)
                            </label>
                            <input type="number" name="webhook_logs" value="<?php echo $retentionPolicy['messages']['webhook_logs']; ?>" 
                                   class="refined-input" min="1" required>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">
                                Mensagens com Falha (dias)
                            </label>
                            <input type="number" name="failed_messages" value="<?php echo $retentionPolicy['messages']['failed_messages']; ?>" 
                                   class="refined-input" min="1" required>
                        </div>
                    </div>
                </div>

                <!-- Logs -->
                <div style="margin-bottom: var(--space-6);">
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        📝 Logs e Auditoria
                    </h3>
                    <div class="refined-grid refined-grid-2">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">
                                Logs de Auditoria (dias)
                            </label>
                            <input type="number" name="audit_logs" value="<?php echo $retentionPolicy['logs']['audit_logs']; ?>" 
                                   class="refined-input" min="1" required>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">
                                Tentativas de Login (dias)
                            </label>
                            <input type="number" name="login_attempts" value="<?php echo $retentionPolicy['logs']['login_attempts']; ?>" 
                                   class="refined-input" min="1" required>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">
                                Logs de API (dias)
                            </label>
                            <input type="number" name="api_logs" value="<?php echo $retentionPolicy['logs']['api_logs']; ?>" 
                                   class="refined-input" min="1" required>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">
                                Logs de Erro (dias)
                            </label>
                            <input type="number" name="error_logs" value="<?php echo $retentionPolicy['logs']['error_logs']; ?>" 
                                   class="refined-input" min="1" required>
                        </div>
                    </div>
                </div>

                <!-- Alertas de Storage -->
                <div style="margin-bottom: var(--space-6);">
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        🔔 Alertas de Storage
                    </h3>
                    <div class="refined-grid refined-grid-2">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">
                                Threshold de Aviso (%)
                            </label>
                            <input type="number" name="warning_threshold" value="<?php echo $retentionPolicy['storage_alerts']['warning_threshold']; ?>" 
                                   class="refined-input" min="1" max="100" required>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">
                                Threshold Crítico (%)
                            </label>
                            <input type="number" name="critical_threshold" value="<?php echo $retentionPolicy['storage_alerts']['critical_threshold']; ?>" 
                                   class="refined-input" min="1" max="100" required>
                        </div>
                    </div>
                </div>

                <!-- Limites por Plano -->
                <div style="margin-bottom: var(--space-6);">
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        💎 Limites de Storage e Retenção por Plano
                    </h3>
                    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: var(--space-4);">
                        Configure os limites de armazenamento e tempo de retenção de dados para cada plano de usuário
                    </p>
                    
                    <!-- Plano Free -->
                    <div style="margin-bottom: var(--space-4); padding: var(--space-4); border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary);">
                        <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-3);">
                            🆓 Plano FREE
                        </h4>
                        <div class="refined-grid refined-grid-3">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Storage Máximo (MB)
                                </label>
                                <input type="number" name="free_storage" value="<?php echo $retentionPolicy['plan_limits']['free']['max_storage_mb']; ?>" 
                                       class="refined-input" min="1" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Retenção de Dados (dias)
                                </label>
                                <input type="number" name="free_retention" value="<?php echo $retentionPolicy['plan_limits']['free']['retention_days']; ?>" 
                                       class="refined-input" min="1" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Mensagens/Mês
                                </label>
                                <input type="number" name="free_messages" value="<?php echo $retentionPolicy['plan_limits']['free']['max_messages_month']; ?>" 
                                       class="refined-input" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Plano Basic -->
                    <div style="margin-bottom: var(--space-4); padding: var(--space-4); border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary);">
                        <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-3);">
                            📦 Plano BASIC
                        </h4>
                        <div class="refined-grid refined-grid-3">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Storage Máximo (MB)
                                </label>
                                <input type="number" name="basic_storage" value="<?php echo $retentionPolicy['plan_limits']['basic']['max_storage_mb']; ?>" 
                                       class="refined-input" min="1" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Retenção de Dados (dias)
                                </label>
                                <input type="number" name="basic_retention" value="<?php echo $retentionPolicy['plan_limits']['basic']['retention_days']; ?>" 
                                       class="refined-input" min="1" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Mensagens/Mês
                                </label>
                                <input type="number" name="basic_messages" value="<?php echo $retentionPolicy['plan_limits']['basic']['max_messages_month']; ?>" 
                                       class="refined-input" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Plano Professional -->
                    <div style="margin-bottom: var(--space-4); padding: var(--space-4); border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary);">
                        <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-3);">
                            ⭐ Plano PROFESSIONAL
                        </h4>
                        <div class="refined-grid refined-grid-3">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Storage Máximo (MB)
                                </label>
                                <input type="number" name="professional_storage" value="<?php echo $retentionPolicy['plan_limits']['professional']['max_storage_mb']; ?>" 
                                       class="refined-input" min="1" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Retenção de Dados (dias)
                                </label>
                                <input type="number" name="professional_retention" value="<?php echo $retentionPolicy['plan_limits']['professional']['retention_days']; ?>" 
                                       class="refined-input" min="1" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Mensagens/Mês
                                </label>
                                <input type="number" name="professional_messages" value="<?php echo $retentionPolicy['plan_limits']['professional']['max_messages_month']; ?>" 
                                       class="refined-input" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Plano Enterprise -->
                    <div style="margin-bottom: var(--space-4); padding: var(--space-4); border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary);">
                        <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-3);">
                            🏢 Plano ENTERPRISE
                        </h4>
                        <div class="refined-grid refined-grid-3">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Storage Máximo (MB) <small style="color: #10b981;">(-1 = ilimitado)</small>
                                </label>
                                <input type="number" name="enterprise_storage" value="<?php echo $retentionPolicy['plan_limits']['enterprise']['max_storage_mb']; ?>" 
                                       class="refined-input" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Retenção de Dados (dias)
                                </label>
                                <input type="number" name="enterprise_retention" value="<?php echo $retentionPolicy['plan_limits']['enterprise']['retention_days']; ?>" 
                                       class="refined-input" min="1" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">
                                    Mensagens/Mês <small style="color: #10b981;">(-1 = ilimitado)</small>
                                </label>
                                <input type="number" name="enterprise_messages" value="<?php echo $retentionPolicy['plan_limits']['enterprise']['max_messages_month']; ?>" 
                                       class="refined-input" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Limpeza Automática -->
                <div style="margin-bottom: var(--space-6);">
                    <h3 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        🧹 Limpeza Automática
                    </h3>
                    <div class="refined-grid refined-grid-2">
                        <div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="cleanup_enabled" value="1" 
                                       <?php echo $retentionPolicy['cleanup_settings']['enabled'] ? 'checked' : ''; ?>
                                       style="width: 18px; height: 18px; cursor: pointer;">
                                <span style="font-size: 14px; font-weight: 500; color: var(--text-primary);">
                                    Ativar Limpeza Automática
                                </span>
                            </label>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 8px;">
                                Hora de Execução (0-23)
                            </label>
                            <input type="number" name="run_hour" value="<?php echo $retentionPolicy['cleanup_settings']['run_hour']; ?>" 
                                   class="refined-input" min="0" max="23" required>
                        </div>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div style="display: flex; gap: 12px; padding-top: var(--space-4); border-top: 1px solid var(--border);">
                    <button type="submit" class="refined-btn refined-btn-primary">
                        <i class="fas fa-save"></i> Salvar Configurações
                    </button>
                    <button type="button" onclick="resetConfig()" class="refined-btn">
                        <i class="fas fa-undo"></i> Restaurar Padrões
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- TAB: Usuários -->
    <div id="tab-users" class="tab-content" style="display: none;">
        <div class="refined-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
                <h2 class="refined-title" style="font-size: 18px; margin: 0;">
                    <i class="fas fa-users"></i> Gerenciamento de Storage por Usuário
                </h2>
                <a href="view_user_storage.php" class="refined-btn" style="font-size: 13px;">
                    <i class="fas fa-external-link-alt"></i> Abrir em Página Completa
                </a>
            </div>
            
            <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: var(--space-4);">
                Visualize o uso de storage detalhado por usuário, incluindo arquivos, mensagens e limites de plano.
            </p>
            
            <iframe 
                src="view_user_storage_embed.php" 
                style="width: 100%; height: calc(100vh - 400px); min-height: 600px; border: 0.5px solid var(--border); border-radius: 6px; background: var(--bg-primary);"
                frameborder="0"
            ></iframe>
        </div>
    </div>

    <!-- TAB: Logs -->
    <div id="tab-logs" class="tab-content" style="display: none;">
        <div class="refined-card">
            <h2 class="refined-title" style="font-size: 18px; margin-bottom: var(--space-6);">
                <i class="fas fa-file-alt"></i> Logs do Sistema
            </h2>
            
            <div id="systemLogs">
                <!-- Será carregado via AJAX -->
                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i>
                    <p style="margin-top: 12px;">Carregando logs...</p>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// Gerenciamento de Tabs
function switchTab(tabName) {
    // Remover active de todas as tabs
    document.querySelectorAll('.refined-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = 'none';
    });
    
    // Ativar tab selecionada
    event.target.classList.add('active');
    document.getElementById('tab-' + tabName).style.display = 'block';
    
    // Carregar dados específicos da tab
    if (tabName === 'logs') {
        loadSystemLogs();
    }
}

// Forçar limpeza manual
function forceCleanup() {
    if (!confirm('Deseja executar a limpeza de dados agora? Esta ação pode levar alguns minutos.')) {
        return;
    }
    
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Executando...';
    btn.disabled = true;
    
    fetch('api/data_retention_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'force_cleanup'})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Limpeza executada com sucesso!\n\nRegistros deletados: ' + data.total_deleted + '\nTempo: ' + data.execution_time + 's');
            location.reload();
        } else {
            alert('Erro ao executar limpeza: ' + data.message);
        }
    })
    .catch(err => {
        alert('Erro ao executar limpeza: ' + err.message);
    })
    .finally(() => {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    });
}

// Atualizar estatísticas
function refreshStats() {
    location.reload();
}

// Limpar dados de usuário específico
function cleanupUser(userId) {
    if (!confirm('Deseja limpar os dados antigos deste usuário?')) {
        return;
    }
    
    fetch('api/data_retention_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'cleanup_user', user_id: userId})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Dados do usuário limpos com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    });
}

// Salvar configurações
function saveConfig(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const config = {};
    
    for (let [key, value] of formData.entries()) {
        config[key] = value;
    }
    
    fetch('api/data_retention_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'save_config', config: config})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Configurações salvas com sucesso!');
            location.reload();
        } else {
            alert('Erro ao salvar: ' + data.message);
        }
    });
}

// Restaurar configurações padrão
function resetConfig() {
    if (!confirm('Deseja restaurar as configurações padrão? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    fetch('api/data_retention_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'reset_config'})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Configurações restauradas!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    });
}

// Carregar logs do sistema
function loadSystemLogs() {
    fetch('api/data_retention_actions.php?action=get_logs')
        .then(r => r.json())
        .then(data => {
            // Implementar renderização dos logs
            console.log('System logs:', data);
        });
}
</script>

<style>
.refined-tabs {
    display: flex;
    gap: 4px;
    border-bottom: 1px solid var(--border);
}

.refined-tab {
    padding: 12px 20px;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-secondary);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 150ms;
    display: flex;
    align-items: center;
    gap: 8px;
}

.refined-tab:hover {
    color: var(--text-primary);
    background: var(--bg-secondary);
}

.refined-tab.active {
    color: var(--accent);
    border-bottom-color: var(--accent);
}

.tab-content {
    animation: fadeIn 0.2s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php require_once 'includes/footer.php'; ?>
