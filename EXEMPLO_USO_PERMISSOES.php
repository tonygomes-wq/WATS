<?php
/**
 * EXEMPLOS DE USO DO SISTEMA DE PERMISSÕES GRANULARES
 * 
 * Este arquivo demonstra como usar as funções de verificação de permissões
 * nos módulos do sistema.
 * 
 * MACIP Tecnologia LTDA
 * Data: 25/02/2026
 */

require_once 'includes/plan_permissions.php';

// ============================================================================
// EXEMPLO 1: Verificar se usuário tem acesso a um módulo
// ============================================================================

// Em flows.php (Automação/Fluxos)
if (!userHasModule($user_id, 'automation')) {
    echo renderUpgradeAlert(
        'Automação/Fluxos',
        'Este recurso não está disponível no seu plano atual.',
        'flows'
    );
    exit;
}

// ============================================================================
// EXEMPLO 2: Verificar limite de recursos antes de criar
// ============================================================================

// Em flows.php - Antes de criar novo fluxo
if (!canUserAddResource($user_id, 'automation_flows')) {
    $limit = getUserLimit($user_id, 'automation_flows');
    echo renderUpgradeAlert(
        'Limite de Fluxos Atingido',
        "Você atingiu o limite de $limit fluxos do seu plano.",
        'flows'
    );
    exit;
}

// ============================================================================
// EXEMPLO 3: Verificar funcionalidade específica
// ============================================================================

// Em supervisor_users.php - Verificar se pode adicionar múltiplos atendentes
if (!userHasFeature($user_id, 'multi_attendant')) {
    echo '<div class="alert alert-warning">
        <i class="fas fa-lock mr-2"></i>
        Seu plano permite apenas 1 atendente. 
        <a href="upgrade.php">Faça upgrade</a> para adicionar mais atendentes.
    </div>';
    exit;
}

// ============================================================================
// EXEMPLO 4: Verificar integração
// ============================================================================

// Em integrations.php - Verificar se pode usar Google Sheets
if (!userHasIntegration($user_id, 'google_sheets')) {
    echo renderUpgradeAlert(
        'Integração Google Sheets',
        'Esta integração não está disponível no seu plano.',
        'integrations'
    );
    exit;
}

// ============================================================================
// EXEMPLO 5: Mostrar informações do plano
// ============================================================================

// Em dashboard.php - Mostrar card com info do plano
$planInfo = getUserPlanInfo($user_id);

echo '<div class="plan-info-card">
    <h3>' . htmlspecialchars($planInfo['plan_name']) . '</h3>
    <p>Mensagens: ' . number_format($planInfo['messages_used']) . ' / ' . 
       ($planInfo['max_messages'] == -1 ? 'Ilimitado' : number_format($planInfo['max_messages'])) . '</p>
    <p>Atendentes: ' . $planInfo['attendants_count'] . ' / ' . 
       ($planInfo['max_attendants'] == -1 ? 'Ilimitado' : $planInfo['max_attendants']) . '</p>
    <p>Fluxos: ' . $planInfo['flows_count'] . ' / ' . 
       ($planInfo['max_automation_flows'] == -1 ? 'Ilimitado' : $planInfo['max_automation_flows']) . '</p>
</div>';

// ============================================================================
// EXEMPLO 6: Verificar múltiplas permissões de uma vez
// ============================================================================

// Em settings.php - Verificar várias permissões
$permissions = checkMultiplePermissions($user_id, [
    'modules' => ['automation', 'api', 'webhooks'],
    'features' => ['white_label', 'priority_support'],
    'integrations' => ['zapier', 'n8n']
]);

if ($permissions['modules']['automation']) {
    echo '<li><a href="flows.php">Automação</a></li>';
}

if ($permissions['modules']['api']) {
    echo '<li><a href="api_docs.php">API</a></li>';
}

if ($permissions['features']['white_label']) {
    echo '<li><a href="branding.php">White Label</a></li>';
}

// ============================================================================
// EXEMPLO 7: Bloquear criação de recurso com mensagem personalizada
// ============================================================================

// Em departments.php - Antes de criar novo departamento
$currentCount = 5; // Buscar do banco
$maxDepartments = getUserLimit($user_id, 'departments');

if ($maxDepartments != -1 && $currentCount >= $maxDepartments) {
    $message = getUpgradeMessage('departments', $maxDepartments);
    echo '<div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        ' . $message . '
        <a href="upgrade.php" class="btn btn-primary ml-3">Ver Planos</a>
    </div>';
    exit;
}

// ============================================================================
// EXEMPLO 8: Mostrar badge "PRO" em recursos bloqueados
// ============================================================================

// Em menu lateral - Mostrar badge PRO
$hasAutomation = userHasModule($user_id, 'automation');
$hasKanban = userHasModule($user_id, 'kanban');
$hasReports = userHasModule($user_id, 'reports');

echo '<ul class="sidebar-menu">
    <li><a href="chat.php">Atendimento</a></li>
    <li>
        <a href="flows.php">
            Automação 
            ' . (!$hasAutomation ? '<span class="badge badge-pro">PRO</span>' : '') . '
        </a>
    </li>
    <li>
        <a href="kanban.php">
            Kanban 
            ' . (!$hasKanban ? '<span class="badge badge-pro">PRO</span>' : '') . '
        </a>
    </li>
    <li>
        <a href="reports.php">
            Relatórios 
            ' . (!$hasReports ? '<span class="badge badge-pro">PRO</span>' : '') . '
        </a>
    </li>
</ul>';

// ============================================================================
// EXEMPLO 9: API - Verificar permissão antes de processar
// ============================================================================

// Em api/create_flow.php
header('Content-Type: application/json');

if (!userHasModule($user_id, 'automation')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Seu plano não tem acesso ao módulo de Automação',
        'upgrade_required' => true
    ]);
    exit;
}

if (!canUserAddResource($user_id, 'automation_flows')) {
    $limit = getUserLimit($user_id, 'automation_flows');
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => "Você atingiu o limite de $limit fluxos do seu plano",
        'upgrade_required' => true
    ]);
    exit;
}

// Continuar com criação do fluxo...

// ============================================================================
// EXEMPLO 10: Mostrar progresso de uso de recursos
// ============================================================================

// Em dashboard.php - Mostrar barras de progresso
$planInfo = getUserPlanInfo($user_id);

function renderProgressBar($label, $current, $max, $icon) {
    $percentage = $max == -1 ? 0 : ($current / $max) * 100;
    $color = $percentage >= 90 ? 'red' : ($percentage >= 70 ? 'yellow' : 'green');
    $maxLabel = $max == -1 ? 'Ilimitado' : number_format($max);
    
    return '<div class="resource-progress">
        <div class="flex justify-between mb-2">
            <span><i class="' . $icon . ' mr-2"></i>' . $label . '</span>
            <span>' . number_format($current) . ' / ' . $maxLabel . '</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill bg-' . $color . '" style="width: ' . min($percentage, 100) . '%"></div>
        </div>
    </div>';
}

echo '<div class="usage-stats">';
echo renderProgressBar('Mensagens', $planInfo['messages_used'], $planInfo['max_messages'], 'fas fa-paper-plane');
echo renderProgressBar('Atendentes', $planInfo['attendants_count'], $planInfo['max_attendants'], 'fas fa-users');
echo renderProgressBar('Fluxos', $planInfo['flows_count'], $planInfo['max_automation_flows'], 'fas fa-robot');
echo renderProgressBar('Campanhas', $planInfo['campaigns_count'], $planInfo['max_dispatch_campaigns'], 'fas fa-bullhorn');
echo '</div>';

// ============================================================================
// EXEMPLO 11: Verificar antes de permitir upload de arquivo
// ============================================================================

// Em upload.php - Verificar feature e limite de storage
if (!userHasFeature($user_id, 'file_upload')) {
    echo json_encode([
        'success' => false,
        'message' => 'Upload de arquivos não disponível no seu plano'
    ]);
    exit;
}

$currentStorage = 85; // MB - buscar do banco
$maxStorage = getUserLimit($user_id, 'file_storage_mb');

if ($maxStorage != -1 && $currentStorage >= $maxStorage) {
    echo json_encode([
        'success' => false,
        'message' => "Você atingiu o limite de {$maxStorage}MB de armazenamento"
    ]);
    exit;
}

// ============================================================================
// EXEMPLO 12: Desabilitar botões de recursos bloqueados
// ============================================================================

// Em contacts.php - Desabilitar botão de exportar se não tiver permissão
$canExport = userHasFeature($user_id, 'export_data');

echo '<button 
    class="btn btn-primary" 
    onclick="exportContacts()"
    ' . (!$canExport ? 'disabled title="Recurso não disponível no seu plano"' : '') . '>
    <i class="fas fa-download mr-2"></i>
    Exportar Contatos
    ' . (!$canExport ? '<i class="fas fa-lock ml-2"></i>' : '') . '
</button>';

?>
