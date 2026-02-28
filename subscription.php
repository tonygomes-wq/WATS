<?php
// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir arquivos necessários
require_once 'config/database.php';
require_once 'includes/functions.php';

// Verificar login
requireLogin();

$page_title = 'Minha Assinatura';
require_once 'includes/header_spa.php';

$userId = $_SESSION['user_id'];

// Buscar dados do usuário
$stmt = $pdo->prepare("
    SELECT 
        name,
        email,
        plan,
        plan_limit,
        messages_sent,
        plan_expires_at,
        created_at
    FROM users 
    WHERE id = ?
");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

$pricingPlans = getPricingPlans();
$activePlans = getPricingPlans(true);

$planMeta = [];
foreach ($pricingPlans as $plan) {
    $planMeta[$plan['slug']] = [
        'name' => $plan['name'],
        'price' => (float) $plan['price'],
        'limit' => (int) $plan['message_limit'],
        'color' => getPlanColor($plan['slug'])
    ];
}

$currentPlan = $userData['plan'] ?? ($pricingPlans[0]['slug'] ?? 'free');
if (!isset($planMeta[$currentPlan])) {
    $fallback = $pricingPlans[0] ?? getDefaultPricingPlans()[0];
    $currentPlan = $fallback['slug'];
    $planMeta[$currentPlan] = [
        'name' => $fallback['name'],
        'price' => (float) $fallback['price'],
        'limit' => (int) $fallback['message_limit'],
        'color' => getPlanColor($fallback['slug'])
    ];
}

$currentPlanMeta = $planMeta[$currentPlan];
$plansToDisplay = !empty($activePlans) ? $activePlans : $pricingPlans;

$usage = ($userData['messages_sent'] / max($userData['plan_limit'], 1)) * 100;
$messagesRemaining = $userData['plan_limit'] - $userData['messages_sent'];
?>

<style>
:root {
    /* Grid 4px */
    --space-1: 4px;
    --space-2: 8px;
    --space-3: 12px;
    --space-4: 16px;
    --space-6: 24px;
    --space-8: 32px;
    
    /* Border Radius - Sharp system */
    --radius-sm: 4px;
    --radius-md: 6px;
    --radius-lg: 8px;
    
    /* Transitions */
    --transition-fast: 150ms cubic-bezier(0.25, 1, 0.5, 1);
    --transition-base: 200ms cubic-bezier(0.25, 1, 0.5, 1);
}

:root[data-theme="light"] {
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #94a3b8;
    --text-faint: #cbd5e1;
    --bg-primary: #f8fafc;
    --bg-card: #ffffff;
    --border: rgba(15, 23, 42, 0.08);
    --border-subtle: rgba(15, 23, 42, 0.05);
    --border-emphasis: rgba(15, 23, 42, 0.12);
    --accent-primary: #10b981;
    --accent-hover: #059669;
    --accent-subtle: rgba(16, 185, 129, 0.08);
}

:root[data-theme="dark"] {
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --text-muted: #64748b;
    --text-faint: #475569;
    --bg-primary: #0f172a;
    --bg-card: #1e293b;
    --border: rgba(241, 245, 249, 0.10);
    --border-subtle: rgba(241, 245, 249, 0.06);
    --border-emphasis: rgba(241, 245, 249, 0.15);
    --accent-primary: #10b981;
    --accent-hover: #059669;
    --accent-subtle: rgba(16, 185, 129, 0.12);
}

.subscription-page {
    padding: var(--space-6);
}

.page-header {
    margin-bottom: var(--space-6);
}

.page-title {
    font-size: 18px;
    font-weight: 600;
    letter-spacing: -0.02em;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-2);
}

.page-subtitle {
    font-size: 13px;
    color: var(--text-secondary);
}

.current-plan-card {
    background: var(--bg-card);
    border: 0.5px solid var(--border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    margin-bottom: var(--space-6);
    position: relative;
    overflow: hidden;
}

.current-plan-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--accent-primary);
}

.plan-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-4);
    padding-bottom: var(--space-4);
    border-bottom: 0.5px solid var(--border-subtle);
}

.plan-name-section {
    display: flex;
    flex-direction: column;
    gap: var(--space-1);
}

.plan-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
}

.plan-name {
    font-size: 24px;
    font-weight: 600;
    letter-spacing: -0.02em;
    color: var(--text-primary);
}

.plan-price-section {
    text-align: right;
}

.plan-price {
    font-size: 24px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--accent-primary);
}

.plan-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-4);
    margin-bottom: var(--space-4);
}

.stat-item {
    display: flex;
    flex-direction: column;
    gap: var(--space-1);
}

.stat-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
}

.stat-value {
    font-size: 20px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--text-primary);
}

.usage-bar-container {
    margin-top: var(--space-4);
}

.usage-bar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-2);
}

.usage-bar-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-secondary);
}

.usage-bar-value {
    font-size: 12px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--text-primary);
}

.usage-bar-track {
    width: 100%;
    height: 6px;
    background: var(--border-subtle);
    border-radius: 3px;
    overflow: hidden;
}

.usage-bar-fill {
    height: 100%;
    background: var(--accent-primary);
    border-radius: 3px;
    transition: width var(--transition-base);
}

.section-card {
    background: var(--bg-card);
    border: 0.5px solid var(--border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    margin-bottom: var(--space-6);
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: var(--space-2);
    margin-bottom: var(--space-6);
}

.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: var(--space-4);
}

.plan-card {
    background: var(--bg-card);
    border: 0.5px solid var(--border);
    border-radius: var(--radius-lg);
    padding: var(--space-4);
    transition: all var(--transition-fast);
    position: relative;
}

.plan-card:hover {
    border-color: var(--border-emphasis);
    transform: translateY(-2px);
}

.plan-card.current {
    border-color: var(--accent-primary);
    background: var(--accent-subtle);
}

.plan-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: var(--space-1) var(--space-2);
    background: var(--accent-primary);
    color: white;
    border-radius: var(--radius-sm);
    margin-bottom: var(--space-3);
}

.plan-card-name {
    font-size: 18px;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--text-primary);
    margin-bottom: var(--space-3);
}

.plan-card-price {
    font-size: 28px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--text-primary);
    margin-bottom: var(--space-1);
}

.plan-card-period {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: var(--space-4);
}

.plan-features {
    list-style: none;
    padding: 0;
    margin: 0 0 var(--space-4) 0;
}

.plan-feature {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: var(--space-2);
}

.plan-feature i {
    color: var(--accent-primary);
    font-size: 12px;
}

.plan-button {
    width: 100%;
    padding: var(--space-2) var(--space-4);
    border: none;
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.plan-button.primary {
    background: var(--accent-primary);
    color: white;
}

.plan-button.primary:hover {
    background: var(--accent-hover);
}

.plan-button.disabled {
    background: var(--border);
    color: var(--text-muted);
    cursor: not-allowed;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: var(--space-4);
}

.info-card {
    border: 0.5px solid var(--border);
    border-radius: var(--radius-md);
    padding: var(--space-4);
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

.info-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-md);
    background: var(--accent-subtle);
    color: var(--accent-primary);
    font-size: 18px;
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    margin-bottom: var(--space-1);
}

.info-value {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
}

.alert-box {
    border-left: 3px solid;
    border-radius: var(--radius-md);
    padding: var(--space-4);
    margin-top: var(--space-6);
    display: flex;
    gap: var(--space-3);
}

.alert-box.warning {
    background: rgba(251, 191, 36, 0.1);
    border-color: #fbbf24;
}

.alert-box.danger {
    background: rgba(239, 68, 68, 0.1);
    border-color: #ef4444;
}

.alert-icon {
    font-size: 18px;
}

.alert-box.warning .alert-icon {
    color: #fbbf24;
}

.alert-box.danger .alert-icon {
    color: #ef4444;
}

.alert-content h3 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: var(--space-1);
}

.alert-box.warning .alert-content h3 {
    color: #d97706;
}

.alert-box.danger .alert-content h3 {
    color: #dc2626;
}

.alert-content p {
    font-size: 13px;
}

.alert-box.warning .alert-content p {
    color: #92400e;
}

.alert-box.danger .alert-content p {
    color: #991b1b;
}

#invoiceModal {
    display: none;
}

#invoiceModal:not(.hidden) {
    display: flex !important;
}
</style>

<div class="subscription-page">
    <!-- Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-credit-card" style="color: var(--accent-primary);"></i>
            Minha Assinatura
        </h1>
        <p class="page-subtitle">Gerencie seu plano e acompanhe seu uso</p>
    </div>

    <!-- Plano Atual -->
    <div class="current-plan-card">
        <div class="plan-header">
            <div class="plan-name-section">
                <span class="plan-label">Plano Atual</span>
                <h2 class="plan-name"><?php echo htmlspecialchars($currentPlanMeta['name']); ?></h2>
            </div>
            <div class="plan-price-section">
                <span class="plan-label">Valor Mensal</span>
                <div class="plan-price">R$ <?php echo number_format($currentPlanMeta['price'], 2, ',', '.'); ?></div>
            </div>
        </div>
        
        <div class="plan-stats">
            <div class="stat-item">
                <span class="stat-label">Mensagens Enviadas</span>
                <span class="stat-value"><?php echo number_format($userData['messages_sent']); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Limite do Plano</span>
                <span class="stat-value"><?php echo number_format($userData['plan_limit']); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Restantes</span>
                <span class="stat-value"><?php echo number_format($messagesRemaining); ?></span>
            </div>
        </div>
        
        <!-- Barra de Progresso -->
        <div class="usage-bar-container">
            <div class="usage-bar-header">
                <span class="usage-bar-label">Uso do Plano</span>
                <span class="usage-bar-value"><?php echo number_format($usage, 1); ?>%</span>
            </div>
            <div class="usage-bar-track">
                <div class="usage-bar-fill" style="width: <?php echo min($usage, 100); ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Planos Disponíveis -->
    <div class="section-card">
        <h2 class="section-title">
            <i class="fas fa-rocket" style="color: var(--accent-primary);"></i>
            Planos Disponíveis
        </h2>
        
        <div class="plans-grid">
            <?php foreach ($plansToDisplay as $plan): 
                $slug = $plan['slug'];
                $meta = $planMeta[$slug];
                $isCurrent = $slug === $currentPlan;
            ?>
            <div class="plan-card <?php echo $isCurrent ? 'current' : ''; ?>">
                <?php if ($isCurrent): ?>
                <span class="plan-badge">Plano Atual</span>
                <?php endif; ?>
                
                <h3 class="plan-card-name"><?php echo htmlspecialchars($meta['name']); ?></h3>
                
                <div class="plan-card-price">R$ <?php echo number_format($meta['price'], 2, ',', '.'); ?></div>
                <div class="plan-card-period">/mês</div>
                
                <ul class="plan-features">
                    <li class="plan-feature">
                        <i class="fas fa-check"></i>
                        <span><?php echo number_format($meta['limit']); ?> mensagens/mês</span>
                    </li>
                    <li class="plan-feature">
                        <i class="fas fa-check"></i>
                        <span>Suporte por email</span>
                    </li>
                    <?php if ($slug !== 'free'): ?>
                    <li class="plan-feature">
                        <i class="fas fa-check"></i>
                        <span>Sem anúncios</span>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array($slug, ['pro', 'enterprise'])): ?>
                    <li class="plan-feature">
                        <i class="fas fa-check"></i>
                        <span>Suporte prioritário</span>
                    </li>
                    <?php endif; ?>
                    <?php if ($slug === 'enterprise'): ?>
                    <li class="plan-feature">
                        <i class="fas fa-check"></i>
                        <span>Mensagens ilimitadas</span>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <?php if (!$isCurrent): ?>
                <button class="plan-button primary">
                    <?php echo $meta['price'] > $currentPlanMeta['price'] ? 'Fazer Upgrade' : 'Selecionar Plano'; ?>
                </button>
                <?php else: ?>
                <button class="plan-button disabled" disabled>
                    Plano Atual
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Histórico de Uso -->
    <div class="section-card">
        <h2 class="section-title">
            <i class="fas fa-user-circle" style="color: var(--accent-primary);"></i>
            Informações da Conta
        </h2>
        
        <div class="info-grid">
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="info-content">
                    <div class="info-label">Nome</div>
                    <div class="info-value"><?php echo htmlspecialchars($userData['name']); ?></div>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="info-content">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($userData['email']); ?></div>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="info-content">
                    <div class="info-label">Membro desde</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($userData['created_at'])); ?></div>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="info-content">
                    <div class="info-label">Renovação</div>
                    <div class="info-value">
                        <?php 
                        if ($userData['plan_expires_at']) {
                            echo date('d/m/Y', strtotime($userData['plan_expires_at']));
                        } else {
                            echo 'Não aplicável';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas -->
    <?php if ($usage >= 90): ?>
    <div class="alert-box danger">
        <i class="fas fa-exclamation-triangle alert-icon"></i>
        <div class="alert-content">
            <h3>Limite Quase Atingido!</h3>
            <p>Você usou <?php echo number_format($usage, 1); ?>% do seu plano. Considere fazer upgrade para continuar enviando mensagens.</p>
        </div>
    </div>
    <?php elseif ($usage >= 70): ?>
    <div class="alert-box warning">
        <i class="fas fa-exclamation-circle alert-icon"></i>
        <div class="alert-content">
            <h3>Atenção ao Uso</h3>
            <p>Você já usou <?php echo number_format($usage, 1); ?>% do seu plano. Monitore seu uso para não ficar sem mensagens.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

    <!-- Faturas -->
    <div class="section-card">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4);">
            <h2 class="section-title" style="margin-bottom: 0;">
                <i class="fas fa-file-invoice-dollar" style="color: var(--accent-primary);"></i>
                Suas Faturas
            </h2>
            <?php if (isAdmin()): ?>
            <button onclick="openInvoiceModal()" class="plan-button primary" style="width: auto;">
                <i class="fas fa-plus" style="margin-right: var(--space-2);"></i>Gerar Fatura
            </button>
            <?php endif; ?>
        </div>
        <div id="invoiceList" style="font-size: 13px; color: var(--text-secondary);">Carregando...</div>
    </div>

    <!-- Modal (admin) -->
    <?php if (isAdmin()): ?>
    <div id="invoiceModal" class="hidden" style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); align-items: center; justify-content: center; z-index: 50; padding: var(--space-4);">
        <div style="background: var(--bg-card); border-radius: var(--radius-lg); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); width: 100%; max-width: 640px;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: var(--space-4) var(--space-6); border-bottom: 0.5px solid var(--border);">
                <h3 style="font-size: 16px; font-weight: 600; color: var(--text-primary);">Gerar Fatura Manual</h3>
                <button onclick="closeInvoiceModal()" style="color: var(--text-muted); background: none; border: none; cursor: pointer; padding: var(--space-2);">
                    <i class="fas fa-times" style="font-size: 16px;"></i>
                </button>
            </div>
            <form id="invoiceForm" style="padding: var(--space-6); display: flex; flex-direction: column; gap: var(--space-4);">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: var(--space-2);">Usuário (ID)</label>
                    <input type="number" name="user_id" id="invoiceUserId" value="<?php echo (int)$userId; ?>" style="width: 100%; border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" required>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: var(--space-2);">Descrição</label>
                    <input type="text" id="invoiceDescription" style="width: 100%; border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" placeholder="Plano Profissional - Novembro" required>
                </div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-4);">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: var(--space-2);">Valor (R$)</label>
                        <input type="number" step="0.01" id="invoiceAmount" style="width: 100%; border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" required>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: var(--space-2);">Imposto</label>
                        <input type="number" step="0.01" id="invoiceTax" style="width: 100%; border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" value="0">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: var(--space-2);">Desconto</label>
                        <input type="number" step="0.01" id="invoiceDiscount" style="width: 100%; border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" value="0">
                    </div>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: var(--space-2);">Vencimento</label>
                    <input type="date" id="invoiceDueDate" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" style="width: 100%; border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" required>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: var(--space-2);">Observações</label>
                    <textarea id="invoiceNotes" rows="3" style="width: 100%; border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" placeholder="Detalhes adicionais da cobrança"></textarea>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: var(--space-2);">Itens</label>
                    <div id="invoiceItems" style="display: flex; flex-direction: column; gap: var(--space-3);">
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-2);">
                            <input type="text" style="border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" placeholder="Descrição" value="Mensalidade do plano">
                            <input type="number" step="0.01" style="border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" placeholder="Qtd" value="1">
                            <input type="number" step="0.01" style="border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" placeholder="Valor" value="0">
                        </div>
                    </div>
                    <button type="button" onclick="addInvoiceItem()" style="color: var(--accent-primary); font-size: 13px; font-weight: 600; margin-top: var(--space-2); background: none; border: none; cursor: pointer; padding: 0;">
                        <i class="fas fa-plus" style="margin-right: var(--space-1);"></i>Adicionar item
                    </button>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); padding-top: var(--space-4); border-top: 0.5px solid var(--border);">
                    <button type="button" onclick="closeInvoiceModal()" style="padding: var(--space-2) var(--space-4); border-radius: var(--radius-md); border: 0.5px solid var(--border); background: var(--bg-card); color: var(--text-primary); font-size: 13px; font-weight: 600; cursor: pointer;">Cancelar</button>
                    <button type="submit" class="plan-button primary">Gerar Fatura</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const invoiceListEl = document.getElementById('invoiceList');

function renderInvoices(invoices) {
    if (!invoices.length) {
        invoiceListEl.innerHTML = '<p style="color: var(--text-muted);">Nenhuma fatura encontrada.</p>';
        return;
    }

    const rows = invoices.map(inv => `
        <tr style="border-bottom: 0.5px solid var(--border-subtle);">
            <td style="padding: var(--space-3) var(--space-4); font-weight: 600; font-variant-numeric: tabular-nums; color: var(--text-primary);">${inv.invoice_number}</td>
            <td style="padding: var(--space-3) var(--space-4); font-variant-numeric: tabular-nums; color: var(--text-secondary);">R$ ${Number(inv.total_amount).toFixed(2)}</td>
            <td style="padding: var(--space-3) var(--space-4);">
                <span style="padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); font-size: 11px; font-weight: 600; ${getStatusStyle(inv.status)}">${formatStatus(inv.status)}</span>
            </td>
            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">${formatDate(inv.due_date)}</td>
            <td style="padding: var(--space-3) var(--space-4); text-align: right;">
                <a href="/api/invoices/download.php?id=${inv.id}" target="_blank" style="color: var(--accent-primary); font-weight: 600; text-decoration: none; transition: color var(--transition-fast);">
                    <i class="fas fa-download" style="margin-right: var(--space-1);"></i>Baixar
                </a>
            </td>
        </tr>
    `).join('');

    invoiceListEl.innerHTML = `
        <div style="overflow-x: auto;">
            <table style="width: 100%; font-size: 13px;">
                <thead>
                    <tr style="text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">
                        <th style="padding: var(--space-2) var(--space-4);">Fatura</th>
                        <th style="padding: var(--space-2) var(--space-4);">Valor</th>
                        <th style="padding: var(--space-2) var(--space-4);">Status</th>
                        <th style="padding: var(--space-2) var(--space-4);">Vencimento</th>
                        <th style="padding: var(--space-2) var(--space-4); text-align: right;">Ação</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
    `;
}

function getStatusStyle(status) {
    switch (status) {
        case 'paid': return 'background: rgba(16, 185, 129, 0.1); color: #059669;';
        case 'overdue': return 'background: rgba(239, 68, 68, 0.1); color: #dc2626;';
        case 'draft':
        case 'sent':
        default:
            return 'background: rgba(251, 191, 36, 0.1); color: #d97706;';
    }
}

function formatStatus(status) {
    const map = {
        paid: 'Pago',
        overdue: 'Vencido',
        sent: 'Enviado',
        draft: 'Rascunho',
        canceled: 'Cancelado'
    };
    return map[status] || status;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR');
}

async function loadInvoices() {
    try {
        const response = await fetch('/api/invoices/list.php');
        const data = await response.json();
        if (data.success) {
            renderInvoices(data.invoices || []);
        } else {
            invoiceListEl.innerHTML = `<p style="color: #ef4444;">${data.error || 'Erro ao carregar faturas'}</p>`;
        }
    } catch (error) {
        console.error('Erro ao carregar faturas:', error);
        invoiceListEl.innerHTML = '<p style="color: #ef4444;">Erro ao carregar faturas.</p>';
    }
}

function openInvoiceModal() {
    document.getElementById('invoiceModal').classList.remove('hidden');
}

function closeInvoiceModal() {
    document.getElementById('invoiceModal').classList.add('hidden');
}

function addInvoiceItem() {
    const container = document.getElementById('invoiceItems');
    const block = document.createElement('div');
    block.style.cssText = 'display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-2);';
    block.innerHTML = `
        <input type="text" style="border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" placeholder="Descrição">
        <input type="number" step="0.01" style="border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" placeholder="Qtd" value="1">
        <input type="number" step="0.01" style="border: 0.5px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 13px; background: var(--bg-card); color: var(--text-primary);" placeholder="Valor" value="0">
    `;
    container.appendChild(block);
}

<?php if (isAdmin()): ?>
document.getElementById('invoiceForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = {
        user_id: document.getElementById('invoiceUserId').value,
        description: document.getElementById('invoiceDescription').value,
        amount: parseFloat(document.getElementById('invoiceAmount').value),
        tax: parseFloat(document.getElementById('invoiceTax').value),
        discount: parseFloat(document.getElementById('invoiceDiscount').value),
        due_date: document.getElementById('invoiceDueDate').value,
        notes: document.getElementById('invoiceNotes').value,
        items: []
    };
    
    const itemRows = document.querySelectorAll('#invoiceItems > div');
    itemRows.forEach(row => {
        const inputs = row.querySelectorAll('input');
        if (inputs.length === 3) {
            formData.items.push({
                description: inputs[0].value,
                quantity: parseFloat(inputs[1].value),
                price: parseFloat(inputs[2].value)
            });
        }
    });
    
    try {
        const response = await fetch('/api/invoices/create.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Fatura gerada com sucesso!');
            closeInvoiceModal();
            loadInvoices();
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível gerar a fatura'));
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao gerar fatura');
    }
});
<?php endif; ?>

loadInvoices();
</script>

<?php require_once 'includes/footer.php'; ?>
