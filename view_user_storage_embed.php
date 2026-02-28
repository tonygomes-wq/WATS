<?php
/**
 * Visualização de Storage por Usuário - Versão Embed (sem header/footer)
 * Para uso em iframe dentro de data_retention_admin.php
 * 
 * MACIP Tecnologia LTDA
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/StorageMonitor.php';

requireLogin();
requireAdmin();

$monitor = new StorageMonitor($pdo);

// Buscar todos os usuários com informações de storage
$stmt = $pdo->query("
    SELECT 
        u.id,
        u.email,
        u.name,
        u.plan,
        u.created_at,
        COUNT(DISTINCT cm.id) as total_messages,
        COUNT(DISTINCT CASE WHEN cm.media_url IS NOT NULL THEN cm.id END) as messages_with_media
    FROM users u
    LEFT JOIN chat_messages cm ON cm.user_id = u.id
    GROUP BY u.id
    ORDER BY u.id ASC
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular storage para cada usuário
foreach ($users as &$user) {
    $user['storage_mb'] = $monitor->getUserStorageUsage($user['id']);
    $user['storage_limit_mb'] = $monitor->getUserStorageLimit($user['plan']);
    $user['storage_percentage'] = $user['storage_limit_mb'] > 0 
        ? round(($user['storage_mb'] / $user['storage_limit_mb']) * 100, 1) 
        : 0;
    
    // Verificar se diretório existe
    $userDir = __DIR__ . "/uploads/user_{$user['id']}/";
    $user['has_directory'] = is_dir($userDir);
    
    // Contar arquivos se diretório existe
    if ($user['has_directory']) {
        $mediaDir = $userDir . 'media/';
        $chatMediaDir = $userDir . 'chat_media/';
        
        $user['media_files'] = is_dir($mediaDir) ? count(array_diff(scandir($mediaDir), ['.', '..'])) : 0;
        $user['chat_media_files'] = is_dir($chatMediaDir) ? count(array_diff(scandir($chatMediaDir), ['.', '..'])) : 0;
        $user['total_files'] = $user['media_files'] + $user['chat_media_files'];
    } else {
        $user['media_files'] = 0;
        $user['chat_media_files'] = 0;
        $user['total_files'] = 0;
    }
}
unset($user);

// Estatísticas gerais
$totalUsers = count($users);
$usersWithFiles = count(array_filter($users, function($u) { return $u['has_directory']; }));
$totalStorageMB = array_sum(array_column($users, 'storage_mb'));
$totalFiles = array_sum(array_column($users, 'total_files'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage por Usuário</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
:root {
    --color-bg: #ffffff;
    --color-surface: #fafafa;
    --color-border: rgba(0, 0, 0, 0.08);
    --color-border-subtle: rgba(0, 0, 0, 0.05);
    --color-text: #0a0a0a;
    --color-text-secondary: #525252;
    --color-text-muted: #a3a3a3;
    --color-accent: #0066ff;
    --radius: 6px;
}

/* Dark Mode */
@media (prefers-color-scheme: dark) {
    :root {
        --color-bg: #1a1d24;
        --color-surface: #22252d;
        --color-border: rgba(255, 255, 255, 0.1);
        --color-border-subtle: rgba(255, 255, 255, 0.06);
        --color-text: #e5e7eb;
        --color-text-secondary: #9ca3af;
        --color-text-muted: #6b7280;
        --color-accent: #3b82f6;
    }
}

body.dark-mode {
    --color-bg: #1a1d24;
    --color-surface: #22252d;
    --color-border: rgba(255, 255, 255, 0.1);
    --color-border-subtle: rgba(255, 255, 255, 0.06);
    --color-text: #e5e7eb;
    --color-text-secondary: #9ca3af;
    --color-text-muted: #6b7280;
    --color-accent: #3b82f6;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--color-bg);
    color: var(--color-text);
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card {
    background: var(--color-surface);
    border: 0.5px solid var(--color-border);
    border-radius: var(--radius);
    padding: 16px;
}

.stat-number {
    font-size: 24px;
    font-weight: 600;
    letter-spacing: -0.02em;
    color: var(--color-text);
    margin-bottom: 4px;
    font-variant-numeric: tabular-nums;
}

.stat-label {
    color: var(--color-text-muted);
    font-size: 12px;
}

.search-wrapper {
    position: relative;
    margin-bottom: 16px;
}

.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    font-size: 14px;
}

.search-input {
    width: 100%;
    padding: 10px 16px 10px 40px;
    border: 0.5px solid var(--color-border);
    border-radius: var(--radius);
    font-size: 14px;
    background: var(--color-bg);
    color: var(--color-text);
}

.search-input:focus {
    outline: none;
    border-color: var(--color-accent);
}

.users-table {
    background: var(--color-bg);
    border: 0.5px solid var(--color-border);
    border-radius: var(--radius);
    overflow: hidden;
}

.users-table table {
    width: 100%;
    border-collapse: collapse;
}

.users-table th {
    background: var(--color-surface);
    padding: 10px 12px;
    text-align: left;
    font-weight: 500;
    font-size: 11px;
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 0.5px solid var(--color-border);
}

.users-table td {
    padding: 10px 12px;
    border-bottom: 0.5px solid var(--color-border-subtle);
    font-size: 13px;
}

.users-table tbody tr:last-child td {
    border-bottom: none;
}

.users-table tr:hover {
    background: var(--color-surface);
}

.user-id {
    font-weight: 500;
    color: var(--color-accent);
    font-family: 'SF Mono', monospace;
    font-size: 12px;
}

.user-email {
    color: var(--color-text);
    font-size: 13px;
}

.user-name {
    font-size: 11px;
    color: var(--color-text-muted);
    margin-top: 2px;
}

.plan-badge {
    display: inline-flex;
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.plan-free { background: rgba(0, 0, 0, 0.04); color: var(--color-text-secondary); }
.plan-basic { background: rgba(0, 102, 255, 0.08); color: #0052cc; }
.plan-professional { background: rgba(16, 185, 129, 0.1); color: #047857; }
.plan-enterprise { background: rgba(139, 92, 246, 0.1); color: #6d28d9; }

@media (prefers-color-scheme: dark) {
    .plan-free { background: rgba(255, 255, 255, 0.08); }
    .plan-basic { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
    .plan-professional { background: rgba(16, 185, 129, 0.15); color: #34d399; }
    .plan-enterprise { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }
}

.storage-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.storage-numbers {
    font-size: 12px;
    color: var(--color-text);
    font-variant-numeric: tabular-nums;
}

.storage-bar {
    width: 100%;
    height: 3px;
    background: rgba(0, 0, 0, 0.06);
    border-radius: 2px;
    overflow: hidden;
}

.storage-bar-fill {
    height: 100%;
    border-radius: 2px;
}

.storage-ok { background: #10b981; }
.storage-warning { background: #f59e0b; }
.storage-critical { background: #ef4444; }

.storage-percentage {
    font-size: 10px;
    color: var(--color-text-muted);
}

.file-count {
    font-size: 11px;
    color: var(--color-text-muted);
    line-height: 1.4;
}

.file-count strong {
    color: var(--color-text);
    font-weight: 500;
}

.directory-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
}

.directory-exists { color: #10b981; }
.directory-missing { color: #ef4444; }

.action-buttons {
    display: flex;
    gap: 4px;
    justify-content: center;
}

.action-btn {
    width: 28px;
    height: 28px;
    border: 0.5px solid var(--color-border);
    background: var(--color-bg);
    color: var(--color-text-secondary);
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    transition: all 150ms;
}

.action-btn:hover {
    background: var(--color-surface);
    color: var(--color-accent);
    border-color: var(--color-accent);
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--color-bg);
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.modal-header {
    padding: 16px 20px;
    border-bottom: 0.5px solid var(--color-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--color-text);
}

.modal-close {
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    color: var(--color-text-muted);
    cursor: pointer;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.modal-close:hover {
    background: var(--color-surface);
    color: var(--color-text);
}

.modal-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text);
    margin-bottom: 6px;
}

.form-input {
    width: 100%;
    padding: 8px 12px;
    border: 0.5px solid var(--color-border);
    border-radius: 4px;
    font-size: 14px;
    background: var(--color-bg);
    color: var(--color-text);
}

.form-input:focus {
    outline: none;
    border-color: var(--color-accent);
}

.form-select {
    width: 100%;
    padding: 8px 12px;
    border: 0.5px solid var(--color-border);
    border-radius: 4px;
    font-size: 14px;
    background: var(--color-bg);
    color: var(--color-text);
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 150ms;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background: var(--color-accent);
    color: white;
}

.btn-primary:hover {
    opacity: 0.9;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-secondary {
    background: var(--color-surface);
    color: var(--color-text);
    border: 0.5px solid var(--color-border);
}

.btn-secondary:hover {
    background: var(--color-border);
}

.action-section {
    padding: 16px;
    background: var(--color-surface);
    border-radius: 6px;
    margin-bottom: 12px;
}

.action-section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-text);
    margin-bottom: 8px;
}

.action-section-desc {
    font-size: 12px;
    color: var(--color-text-muted);
    margin-bottom: 12px;
}

.alert {
    padding: 12px;
    border-radius: 4px;
    font-size: 12px;
    margin-bottom: 16px;
}

.alert-warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fbbf24;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #f87171;
}
    </style>
</head>
<body>
    <!-- Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalUsers; ?></div>
            <div class="stat-label">Usuários</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $usersWithFiles; ?></div>
            <div class="stat-label">Com Arquivos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($totalStorageMB, 1); ?> MB</div>
            <div class="stat-label">Storage Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($totalFiles); ?></div>
            <div class="stat-label">Arquivos</div>
        </div>
    </div>

    <!-- Busca -->
    <div class="search-wrapper">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="searchInput" class="search-input" placeholder="Buscar por ID, email ou nome...">
    </div>

    <!-- Tabela -->
    <div class="users-table">
        <table id="usersTable">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Email / Nome</th>
                    <th>Plano</th>
                    <th>Storage</th>
                    <th>Arquivos</th>
                    <th>Dir</th>
                    <th>Mensagens</th>
                    <th style="text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <span class="user-id">user_<?php echo $user['id']; ?></span>
                    </td>
                    <td>
                        <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                        <?php if ($user['name']): ?>
                        <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="plan-badge plan-<?php echo strtolower($user['plan']); ?>">
                            <?php echo $user['plan']; ?>
                        </span>
                    </td>
                    <td>
                        <div class="storage-info">
                            <div class="storage-numbers">
                                <?php echo number_format($user['storage_mb'], 1); ?> / <?php echo number_format($user['storage_limit_mb']); ?> MB
                            </div>
                            <div class="storage-bar">
                                <?php
                                $percentage = $user['storage_percentage'];
                                $barClass = 'storage-ok';
                                if ($percentage >= 95) $barClass = 'storage-critical';
                                elseif ($percentage >= 80) $barClass = 'storage-warning';
                                ?>
                                <div class="storage-bar-fill <?php echo $barClass; ?>" 
                                     style="width: <?php echo min($percentage, 100); ?>%"></div>
                            </div>
                            <div class="storage-percentage"><?php echo $percentage; ?>%</div>
                        </div>
                    </td>
                    <td>
                        <div class="file-count">
                            <strong><?php echo $user['total_files']; ?></strong> total
                        </div>
                        <?php if ($user['total_files'] > 0): ?>
                        <div class="file-count">
                            <?php echo $user['media_files']; ?> media, <?php echo $user['chat_media_files']; ?> chat
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['has_directory']): ?>
                        <span class="directory-status directory-exists">✓</span>
                        <?php else: ?>
                        <span class="directory-status directory-missing">✗</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="file-count">
                            <strong><?php echo number_format($user['total_messages']); ?></strong>
                        </div>
                        <div class="file-count">
                            <?php echo number_format($user['messages_with_media']); ?> c/ mídia
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <div class="action-buttons">
                            <button onclick="openManageModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')" 
                                    class="action-btn" title="Gerenciar usuário">
                                <i class="fas fa-cog"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal de Gerenciamento -->
    <div id="manageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Gerenciar Usuário</h3>
                <button class="modal-close" onclick="closeManageModal()">×</button>
            </div>
            <div class="modal-body">
                <div id="modalUserInfo" style="margin-bottom: 20px; padding: 12px; background: var(--color-surface); border-radius: 6px;">
                    <div style="font-size: 13px; color: var(--color-text-muted);">Usuário:</div>
                    <div id="modalUserEmail" style="font-size: 14px; font-weight: 500; color: var(--color-text);"></div>
                </div>

                <!-- Seção: Alterar Limite de Storage -->
                <div class="action-section">
                    <div class="action-section-title">
                        <i class="fas fa-hdd"></i> Limite de Storage
                    </div>
                    <div class="action-section-desc">
                        Defina um limite customizado de storage para este usuário (em MB).
                    </div>
                    <div class="form-group">
                        <label class="form-label">Limite de Storage (MB)</label>
                        <input type="number" id="storageLimit" class="form-input" placeholder="Ex: 500" min="0" step="50">
                    </div>
                    <button onclick="updateStorageLimit()" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Limite
                    </button>
                </div>

                <!-- Seção: Excluir Mensagens -->
                <div class="action-section">
                    <div class="action-section-title">
                        <i class="fas fa-trash-alt"></i> Excluir Mensagens
                    </div>
                    <div class="action-section-desc">
                        Remova mensagens do banco de dados para liberar espaço.
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Esta ação não pode ser desfeita!
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo de Exclusão</label>
                        <select id="deleteType" class="form-select">
                            <option value="old">Mensagens antigas (mais de 90 dias)</option>
                            <option value="media_only">Apenas mensagens com mídia</option>
                            <option value="all">Todas as mensagens</option>
                        </select>
                    </div>
                    <button onclick="deleteUserMessages()" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Excluir Mensagens
                    </button>
                </div>

                <!-- Seção: Excluir Arquivos -->
                <div class="action-section">
                    <div class="action-section-title">
                        <i class="fas fa-file-image"></i> Excluir Arquivos de Mídia
                    </div>
                    <div class="action-section-desc">
                        Remova arquivos de mídia do servidor para liberar espaço em disco.
                    </div>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Arquivos serão permanentemente deletados!
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo de Arquivo</label>
                        <select id="fileType" class="form-select">
                            <option value="chat_media">Apenas mídia de chat</option>
                            <option value="media">Apenas mídia de envios</option>
                            <option value="all">Todos os arquivos</option>
                        </select>
                    </div>
                    <button onclick="deleteUserFiles()" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Excluir Arquivos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    let currentUserId = null;
    let currentUserEmail = null;

    // Busca em tempo real
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#usersTable tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    // Abrir modal de gerenciamento
    function openManageModal(userId, userEmail) {
        currentUserId = userId;
        currentUserEmail = userEmail;
        
        document.getElementById('modalUserEmail').textContent = userEmail;
        document.getElementById('manageModal').classList.add('active');
        
        // Limpar campos
        document.getElementById('storageLimit').value = '';
        document.getElementById('deleteType').value = 'old';
        document.getElementById('fileType').value = 'chat_media';
    }

    // Fechar modal
    function closeManageModal() {
        document.getElementById('manageModal').classList.remove('active');
        currentUserId = null;
        currentUserEmail = null;
    }

    // Fechar modal ao clicar fora
    document.getElementById('manageModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeManageModal();
        }
    });

    // Atualizar limite de storage
    async function updateStorageLimit() {
        const storageLimit = document.getElementById('storageLimit').value;
        
        if (!storageLimit || storageLimit <= 0) {
            alert('Por favor, informe um limite válido de storage.');
            return;
        }

        if (!confirm(`Definir limite de ${storageLimit} MB para ${currentUserEmail}?`)) {
            return;
        }

        try {
            const response = await fetch('api/user_storage_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'update_storage_limit',
                    user_id: currentUserId,
                    storage_limit: parseInt(storageLimit)
                })
            });

            const data = await response.json();

            if (data.success) {
                alert('✓ ' + data.message);
                closeManageModal();
                location.reload();
            } else {
                alert('✗ Erro: ' + data.message);
            }
        } catch (error) {
            alert('✗ Erro ao atualizar limite: ' + error.message);
        }
    }

    // Excluir mensagens
    async function deleteUserMessages() {
        const deleteType = document.getElementById('deleteType').value;
        const deleteTypeText = {
            'old': 'mensagens antigas (mais de 90 dias)',
            'media_only': 'mensagens com mídia',
            'all': 'TODAS as mensagens'
        };

        const confirmText = `ATENÇÃO: Você está prestes a excluir ${deleteTypeText[deleteType]} de ${currentUserEmail}.\n\nEsta ação NÃO PODE ser desfeita!\n\nDeseja continuar?`;

        if (!confirm(confirmText)) {
            return;
        }

        // Segunda confirmação para "all"
        if (deleteType === 'all') {
            if (!confirm('CONFIRMAÇÃO FINAL: Excluir TODAS as mensagens?')) {
                return;
            }
        }

        try {
            const response = await fetch('api/user_storage_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'delete_user_messages',
                    user_id: currentUserId,
                    delete_type: deleteType,
                    days_old: 90
                })
            });

            const data = await response.json();

            if (data.success) {
                alert(`✓ ${data.message}\n\nMensagens excluídas: ${data.deleted_count}`);
                closeManageModal();
                location.reload();
            } else {
                alert('✗ Erro: ' + data.message);
            }
        } catch (error) {
            alert('✗ Erro ao excluir mensagens: ' + error.message);
        }
    }

    // Excluir arquivos
    async function deleteUserFiles() {
        const fileType = document.getElementById('fileType').value;
        const fileTypeText = {
            'chat_media': 'arquivos de mídia de chat',
            'media': 'arquivos de mídia de envios',
            'all': 'TODOS os arquivos de mídia'
        };

        const confirmText = `ATENÇÃO: Você está prestes a excluir ${fileTypeText[fileType]} de ${currentUserEmail}.\n\nArquivos serão PERMANENTEMENTE deletados do servidor!\n\nDeseja continuar?`;

        if (!confirm(confirmText)) {
            return;
        }

        try {
            const response = await fetch('api/user_storage_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'delete_user_files',
                    user_id: currentUserId,
                    file_type: fileType
                })
            });

            const data = await response.json();

            if (data.success) {
                alert(`✓ ${data.message}\n\nArquivos excluídos: ${data.deleted_files}\nEspaço liberado: ${data.freed_space_mb} MB`);
                closeManageModal();
                location.reload();
            } else {
                alert('✗ Erro: ' + data.message);
            }
        } catch (error) {
            alert('✗ Erro ao excluir arquivos: ' + error.message);
        }
    }
    </script>
</body>
</html>
