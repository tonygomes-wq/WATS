<?php
/**
 * Visualização de Storage por Usuário
 * Mostra a relação entre user_id, email e uso de storage
 * 
 * MACIP Tecnologia LTDA
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/StorageMonitor.php';

requireLogin();
requireAdmin();

$page_title = 'Storage por Usuário';
require_once 'includes/header_spa.php';

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

/* Forçar dark mode se body tiver classe dark */
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

.storage-container {
    padding: 32px;
    max-width: 1400px;
    margin: 0 auto;
}

.page-header {
    margin-bottom: 32px;
}

.page-title {
    font-size: 24px;
    font-weight: 600;
    letter-spacing: -0.02em;
    color: var(--color-text);
    margin-bottom: 4px;
}

.page-subtitle {
    font-size: 14px;
    color: var(--color-text-muted);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--color-bg);
    border: 0.5px solid var(--color-border);
    border-radius: var(--radius);
    padding: 16px;
}

.stat-number {
    font-size: 28px;
    font-weight: 600;
    letter-spacing: -0.02em;
    color: var(--color-text);
    margin-bottom: 4px;
    font-variant-numeric: tabular-nums;
}

.stat-label {
    color: var(--color-text-muted);
    font-size: 13px;
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
    padding: 12px 16px;
    text-align: left;
    font-weight: 500;
    font-size: 12px;
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 0.5px solid var(--color-border);
}

.users-table td {
    padding: 12px 16px;
    border-bottom: 0.5px solid var(--color-border-subtle);
    font-size: 14px;
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
    font-family: 'SF Mono', 'Monaco', 'Cascadia Code', monospace;
    font-size: 13px;
    font-variant-numeric: tabular-nums;
}

.user-email {
    color: var(--color-text);
    font-size: 14px;
}

.user-name {
    font-size: 12px;
    color: var(--color-text-muted);
    margin-top: 2px;
}

.plan-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.plan-free { 
    background: rgba(0, 0, 0, 0.04);
    color: var(--color-text-secondary);
}
.plan-basic { 
    background: rgba(0, 102, 255, 0.08);
    color: #0052cc;
}
.plan-professional { 
    background: rgba(16, 185, 129, 0.1);
    color: #047857;
}
.plan-enterprise { 
    background: rgba(139, 92, 246, 0.1);
    color: #6d28d9;
}

/* Dark mode badges */
@media (prefers-color-scheme: dark) {
    .plan-free { 
        background: rgba(255, 255, 255, 0.08);
        color: var(--color-text-secondary);
    }
    .plan-basic { 
        background: rgba(59, 130, 246, 0.15);
        color: #60a5fa;
    }
    .plan-professional { 
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
    }
    .plan-enterprise { 
        background: rgba(139, 92, 246, 0.15);
        color: #a78bfa;
    }
}

body.dark-mode .plan-free { 
    background: rgba(255, 255, 255, 0.08);
    color: var(--color-text-secondary);
}
body.dark-mode .plan-basic { 
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
}
body.dark-mode .plan-professional { 
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
}
body.dark-mode .plan-enterprise { 
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
}

.storage-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.storage-numbers {
    font-size: 13px;
    color: var(--color-text);
    font-variant-numeric: tabular-nums;
}

.storage-bar {
    width: 100%;
    height: 4px;
    background: rgba(0, 0, 0, 0.06);
    border-radius: 2px;
    overflow: hidden;
}

.storage-bar-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 200ms cubic-bezier(0.25, 1, 0.5, 1);
}

.storage-ok { background: #10b981; }
.storage-warning { background: #f59e0b; }
.storage-critical { background: #ef4444; }

.storage-percentage {
    font-size: 11px;
    color: var(--color-text-muted);
    font-variant-numeric: tabular-nums;
}

.directory-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
}

.directory-exists {
    color: #10b981;
}

.directory-missing {
    color: #ef4444;
}

.search-box {
    margin-bottom: 16px;
}

.search-input {
    width: 100%;
    padding: 12px 16px 12px 40px;
    border: 0.5px solid var(--color-border);
    border-radius: var(--radius);
    font-size: 14px;
    background: var(--color-bg);
    color: var(--color-text);
    transition: border-color 150ms;
}

.search-input:focus {
    outline: none;
    border-color: var(--color-accent);
}

.search-input::placeholder {
    color: var(--color-text-muted);
}

.search-wrapper {
    position: relative;
}

.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    font-size: 14px;
    pointer-events: none;
}

.file-count {
    font-size: 12px;
    color: var(--color-text-muted);
    line-height: 1.5;
}

.file-count strong {
    color: var(--color-text);
    font-weight: 500;
}

.tooltip {
    position: relative;
    display: inline-block;
    cursor: help;
}

.tooltip:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 6px 10px;
    background: rgba(0, 0, 0, 0.9);
    color: white;
    border-radius: 4px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 1000;
    margin-bottom: 8px;
    font-family: 'SF Mono', monospace;
}

.messages-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
</style>

<div class="storage-container">
    <div class="page-header">
        <h1 class="page-title">Storage por Usuário</h1>
        <p class="page-subtitle">Visualize a relação entre user_id, email e uso de armazenamento</p>
    </div>

    <!-- Estatísticas Gerais -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalUsers; ?></div>
            <div class="stat-label">Total de Usuários</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $usersWithFiles; ?></div>
            <div class="stat-label">Com Arquivos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($totalStorageMB, 2); ?> MB</div>
            <div class="stat-label">Storage Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($totalFiles); ?></div>
            <div class="stat-label">Total de Arquivos</div>
        </div>
    </div>

    <!-- Busca -->
    <div class="search-box">
        <div class="search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar por ID, email ou nome...">
        </div>
    </div>

    <!-- Tabela de Usuários -->
    <div class="users-table">
        <table id="usersTable">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Email / Nome</th>
                    <th>Plano</th>
                    <th>Storage</th>
                    <th>Arquivos</th>
                    <th>Diretório</th>
                    <th>Mensagens</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <span class="user-id tooltip" data-tooltip="uploads/user_<?php echo $user['id']; ?>/">
                            user_<?php echo $user['id']; ?>
                        </span>
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
                                <?php echo number_format($user['storage_mb'], 2); ?> / <?php echo number_format($user['storage_limit_mb']); ?> MB
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
                            <div class="storage-percentage">
                                <?php echo $percentage; ?>% usado
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="file-count">
                            <strong><?php echo $user['total_files']; ?></strong> total
                        </div>
                        <?php if ($user['total_files'] > 0): ?>
                        <div class="file-count">
                            📁 <?php echo $user['media_files']; ?> media
                        </div>
                        <div class="file-count">
                            💬 <?php echo $user['chat_media_files']; ?> chat
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['has_directory']): ?>
                        <span class="directory-status directory-exists">
                            ✓ Existe
                        </span>
                        <?php else: ?>
                        <span class="directory-status directory-missing">
                            ✗ Não criado
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="messages-info">
                            <div class="file-count">
                                <strong><?php echo number_format($user['total_messages']); ?></strong> total
                            </div>
                            <div class="file-count">
                                <?php echo number_format($user['messages_with_media']); ?> com mídia
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Busca em tempo real
document.getElementById('searchInput').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
