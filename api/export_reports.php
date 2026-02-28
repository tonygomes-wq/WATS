<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Não autenticado');
}

// Verificar se é admin ou supervisor
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$is_supervisor = isset($_SESSION['is_supervisor']) && $_SESSION['is_supervisor'] == 1;

if (!$is_admin && !$is_supervisor) {
    http_response_code(403);
    exit('Acesso negado');
}

$user_id = $_SESSION['user_id'];

// Obter dados do POST
$input = json_decode(file_get_contents('php://input'), true);

$format = $input['format'] ?? 'excel';
$period = $input['period'] ?? 'last7days';
$status = $input['status'] ?? '';
$start_date = $input['start_date'] ?? '';
$end_date = $input['end_date'] ?? '';
$attendant_id = $input['attendant_id'] ?? '';
$department_id = $input['department_id'] ?? '';
$report_type = $input['report_type'] ?? 'attendants';

try {
    // Calcular datas
    $dates = calculateDates($period, $start_date, $end_date);
    
    // Buscar dados baseado no tipo de relatório
    switch ($report_type) {
        case 'attendants':
            $data = getAttendantsData($pdo, $dates, $user_id, $attendant_id);
            $title = 'Relatório por Atendente';
            break;
        case 'departments':
            $data = getDepartmentsData($pdo, $dates, $department_id);
            $title = 'Relatório por Setor';
            break;
        case 'timeline':
            $data = getTimelineExportData($pdo, $dates, $user_id);
            $title = 'Relatório de Linha do Tempo';
            break;
        case 'peakhours':
            $data = getPeakHoursExportData($pdo, $dates, $user_id);
            $title = 'Relatório de Horários de Pico';
            break;
        default:
            $data = getConversationsData($pdo, $dates, $user_id, $status, $attendant_id);
            $title = 'Relatório de Conversas';
    }
    
    // Exportar no formato solicitado
    switch ($format) {
        case 'excel':
            exportExcel($data, $dates, $title, $report_type);
            break;
        case 'csv':
            exportCSV($data, $dates, $title, $report_type);
            break;
        case 'pdf':
            exportPDF($data, $dates, $title, $report_type);
            break;
        default:
            http_response_code(400);
            exit('Formato inválido');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    exit('Erro: ' . $e->getMessage());
}

// =====================================================
// FUNÇÕES DE BUSCA DE DADOS
// =====================================================

/**
 * Calcular datas baseado no período
 */
function calculateDates($period, $start_date, $end_date) {
    $now = new DateTime();
    
    switch ($period) {
        case 'today':
            $start = $now->format('Y-m-d 00:00:00');
            $end = $now->format('Y-m-d 23:59:59');
            break;
        case 'yesterday':
            $yesterday = $now->modify('-1 day');
            $start = $yesterday->format('Y-m-d 00:00:00');
            $end = $yesterday->format('Y-m-d 23:59:59');
            break;
        case 'last7days':
            $start = $now->modify('-7 days')->format('Y-m-d 00:00:00');
            $end = (new DateTime())->format('Y-m-d 23:59:59');
            break;
        case 'last30days':
            $start = $now->modify('-30 days')->format('Y-m-d 00:00:00');
            $end = (new DateTime())->format('Y-m-d 23:59:59');
            break;
        case 'thismonth':
            $start = (new DateTime('first day of this month'))->format('Y-m-d 00:00:00');
            $end = (new DateTime())->format('Y-m-d 23:59:59');
            break;
        case 'lastmonth':
            $start = (new DateTime('first day of last month'))->format('Y-m-d 00:00:00');
            $end = (new DateTime('last day of last month'))->format('Y-m-d 23:59:59');
            break;
        case 'custom':
            $start = $start_date ? $start_date . ' 00:00:00' : $now->modify('-7 days')->format('Y-m-d 00:00:00');
            $end = $end_date ? $end_date . ' 23:59:59' : (new DateTime())->format('Y-m-d 23:59:59');
            break;
        default:
            $start = $now->modify('-7 days')->format('Y-m-d 00:00:00');
            $end = (new DateTime())->format('Y-m-d 23:59:59');
    }
    
    return ['start' => $start, 'end' => $end];
}

/**
 * Buscar dados de atendentes
 */
function getAttendantsData($pdo, $dates, $supervisor_id, $attendant_id = '') {
    // Verificar se tabela supervisor_users existe
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'supervisor_users'");
        if ($stmt->rowCount() == 0) {
            return [];
        }
    } catch (Exception $e) {
        return [];
    }
    
    $where = ["su.supervisor_id = ?"];
    $params = [$supervisor_id];
    
    if ($attendant_id) {
        $where[] = "su.id = ?";
        $params[] = $attendant_id;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "
        SELECT 
            su.id,
            su.name,
            su.email,
            su.status,
            0 as total_conversations,
            0 as resolved_conversations,
            '0%' as resolution_rate,
            '0min' as avg_time
        FROM supervisor_users su
        WHERE {$whereClause}
        ORDER BY su.name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Buscar dados de departamentos
 */
function getDepartmentsData($pdo, $dates, $department_id = '') {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'departments'");
        if ($stmt->rowCount() == 0) {
            return [];
        }
    } catch (Exception $e) {
        return [];
    }
    
    $sql = "SELECT id, name, color FROM departments";
    if ($department_id) {
        $sql .= " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$department_id]);
    } else {
        $stmt = $pdo->query($sql);
    }
    
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($departments as &$dept) {
        $dept['total_attendants'] = 0;
        $dept['total_conversations'] = 0;
        $dept['resolved_conversations'] = 0;
        $dept['resolution_rate'] = '0%';
        $dept['avg_time'] = '0min';
    }
    
    return $departments;
}

/**
 * Buscar dados de linha do tempo
 */
function getTimelineExportData($pdo, $dates, $user_id) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'chat_conversations'");
        if ($stmt->rowCount() == 0) {
            return [];
        }
    } catch (Exception $e) {
        return [];
    }
    
    $sql = "
        SELECT 
            DATE(c.created_at) as date,
            COUNT(c.id) as total,
            SUM(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN c.status IN ('open', 'pending') THEN 1 ELSE 0 END) as open,
            SUM(CASE WHEN c.status IN ('in_progress', 'active') THEN 1 ELSE 0 END) as in_progress
        FROM chat_conversations c
        WHERE c.user_id = ?
          AND c.created_at BETWEEN ? AND ?
        GROUP BY DATE(c.created_at)
        ORDER BY date ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $dates['start'], $dates['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Buscar dados de horários de pico
 */
function getPeakHoursExportData($pdo, $dates, $user_id) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'chat_messages'");
        if ($stmt->rowCount() == 0) {
            return [];
        }
    } catch (Exception $e) {
        return [];
    }
    
    $sql = "
        SELECT 
            DAYOFWEEK(created_at) as day_of_week,
            HOUR(created_at) as hour_of_day,
            COUNT(*) as count
        FROM chat_messages
        WHERE created_at BETWEEN ? AND ?
        GROUP BY day_of_week, hour_of_day
        ORDER BY day_of_week, hour_of_day
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dates['start'], $dates['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Buscar dados de conversas
 */
function getConversationsData($pdo, $dates, $user_id, $status = '', $attendant_id = '') {
    // Tentar chat_conversations primeiro, depois conversations
    $table = 'chat_conversations';
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'chat_conversations'");
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->query("SHOW TABLES LIKE 'conversations'");
            if ($stmt->rowCount() > 0) {
                $table = 'conversations';
            } else {
                return [];
            }
        }
    } catch (Exception $e) {
        return [];
    }
    
    $where = ["c.created_at BETWEEN ? AND ?"];
    $params = [$dates['start'], $dates['end']];
    
    if ($user_id) {
        $where[] = "c.user_id = ?";
        $params[] = $user_id;
    }
    
    if ($status) {
        $where[] = "c.status = ?";
        $params[] = $status;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "
        SELECT 
            c.id,
            c.contact_name,
            c.phone as contact_number,
            c.status,
            c.created_at,
            c.updated_at,
            TIMESTAMPDIFF(MINUTE, c.created_at, c.updated_at) as duration_minutes
        FROM {$table} c
        WHERE {$whereClause}
        ORDER BY c.created_at DESC
        LIMIT 1000
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =====================================================
// FUNÇÕES DE EXPORTAÇÃO
// =====================================================

/**
 * Exportar para Excel (usando HTML table que Excel reconhece)
 */
function exportExcel($data, $dates, $title = 'Relatório', $report_type = 'default') {
    $filename = 'relatorio_' . $report_type . '_' . date('Y-m-d_His') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    echo 'th { background-color: #10B981; color: white; font-weight: bold; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
    echo '<p><strong>Período:</strong> ' . date('d/m/Y H:i', strtotime($dates['start'])) . ' até ' . date('d/m/Y H:i', strtotime($dates['end'])) . '</p>';
    echo '<p><strong>Total de registros:</strong> ' . count($data) . '</p>';
    echo '<p><strong>Gerado em:</strong> ' . date('d/m/Y H:i:s') . '</p>';
    echo '<br>';
    
    echo '<table>';
    
    // Headers e dados baseados no tipo de relatório
    switch ($report_type) {
        case 'attendants':
            echo '<thead><tr>';
            echo '<th>ID</th><th>Nome</th><th>Email</th><th>Status</th><th>Conversas</th><th>Resolvidas</th><th>Taxa</th><th>Tempo Médio</th>';
            echo '</tr></thead><tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($row['email'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars(getStatusLabel($row['status'])) . '</td>';
                echo '<td>' . htmlspecialchars($row['total_conversations']) . '</td>';
                echo '<td>' . htmlspecialchars($row['resolved_conversations']) . '</td>';
                echo '<td>' . htmlspecialchars($row['resolution_rate']) . '</td>';
                echo '<td>' . htmlspecialchars($row['avg_time']) . '</td>';
                echo '</tr>';
            }
            break;
            
        case 'departments':
            echo '<thead><tr>';
            echo '<th>ID</th><th>Setor</th><th>Atendentes</th><th>Conversas</th><th>Resolvidas</th><th>Taxa</th><th>Tempo Médio</th>';
            echo '</tr></thead><tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($row['total_attendants']) . '</td>';
                echo '<td>' . htmlspecialchars($row['total_conversations']) . '</td>';
                echo '<td>' . htmlspecialchars($row['resolved_conversations']) . '</td>';
                echo '<td>' . htmlspecialchars($row['resolution_rate']) . '</td>';
                echo '<td>' . htmlspecialchars($row['avg_time']) . '</td>';
                echo '</tr>';
            }
            break;
            
        case 'timeline':
            echo '<thead><tr>';
            echo '<th>Data</th><th>Total</th><th>Resolvidas</th><th>Abertas</th><th>Em Andamento</th>';
            echo '</tr></thead><tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td>' . date('d/m/Y', strtotime($row['date'])) . '</td>';
                echo '<td>' . htmlspecialchars($row['total']) . '</td>';
                echo '<td>' . htmlspecialchars($row['resolved']) . '</td>';
                echo '<td>' . htmlspecialchars($row['open']) . '</td>';
                echo '<td>' . htmlspecialchars($row['in_progress']) . '</td>';
                echo '</tr>';
            }
            break;
            
        case 'peakhours':
            $days = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            echo '<thead><tr>';
            echo '<th>Dia</th><th>Hora</th><th>Mensagens</th>';
            echo '</tr></thead><tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($days[$row['day_of_week']] ?? $row['day_of_week']) . '</td>';
                echo '<td>' . htmlspecialchars($row['hour_of_day']) . 'h</td>';
                echo '<td>' . htmlspecialchars($row['count']) . '</td>';
                echo '</tr>';
            }
            break;
            
        default:
            echo '<thead><tr>';
            echo '<th>ID</th><th>Cliente</th><th>Telefone</th><th>Status</th><th>Duração</th><th>Data Início</th><th>Última Atualização</th>';
            echo '</tr></thead><tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['contact_name'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($row['contact_number'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars(getStatusLabel($row['status'])) . '</td>';
                echo '<td>' . htmlspecialchars(($row['duration_minutes'] ?? 0) . 'min') . '</td>';
                echo '<td>' . date('d/m/Y H:i', strtotime($row['created_at'])) . '</td>';
                echo '<td>' . date('d/m/Y H:i', strtotime($row['updated_at'])) . '</td>';
                echo '</tr>';
            }
    }
    
    echo '</tbody></table>';
    echo '</body></html>';
    exit;
}

/**
 * Exportar para CSV
 */
function exportCSV($data, $dates, $title = 'Relatório', $report_type = 'default') {
    $filename = 'relatorio_' . $report_type . '_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Headers baseados no tipo de relatório
    switch ($report_type) {
        case 'attendants':
            fputcsv($output, ['ID', 'Nome', 'Email', 'Status', 'Conversas', 'Resolvidas', 'Taxa', 'Tempo Médio'], ';');
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['name'] ?? 'N/A',
                    $row['email'] ?? 'N/A',
                    getStatusLabel($row['status']),
                    $row['total_conversations'],
                    $row['resolved_conversations'],
                    $row['resolution_rate'],
                    $row['avg_time']
                ], ';');
            }
            break;
            
        case 'departments':
            fputcsv($output, ['ID', 'Setor', 'Atendentes', 'Conversas', 'Resolvidas', 'Taxa', 'Tempo Médio'], ';');
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['name'] ?? 'N/A',
                    $row['total_attendants'],
                    $row['total_conversations'],
                    $row['resolved_conversations'],
                    $row['resolution_rate'],
                    $row['avg_time']
                ], ';');
            }
            break;
            
        case 'timeline':
            fputcsv($output, ['Data', 'Total', 'Resolvidas', 'Abertas', 'Em Andamento'], ';');
            foreach ($data as $row) {
                fputcsv($output, [
                    date('d/m/Y', strtotime($row['date'])),
                    $row['total'],
                    $row['resolved'],
                    $row['open'],
                    $row['in_progress']
                ], ';');
            }
            break;
            
        case 'peakhours':
            $days = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            fputcsv($output, ['Dia', 'Hora', 'Mensagens'], ';');
            foreach ($data as $row) {
                fputcsv($output, [
                    $days[$row['day_of_week']] ?? $row['day_of_week'],
                    $row['hour_of_day'] . 'h',
                    $row['count']
                ], ';');
            }
            break;
            
        default:
            fputcsv($output, ['ID', 'Cliente', 'Telefone', 'Status', 'Duração', 'Data Início', 'Última Atualização'], ';');
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['contact_name'] ?? 'N/A',
                    $row['contact_number'] ?? 'N/A',
                    getStatusLabel($row['status']),
                    ($row['duration_minutes'] ?? 0) . 'min',
                    date('d/m/Y H:i', strtotime($row['created_at'])),
                    date('d/m/Y H:i', strtotime($row['updated_at']))
                ], ';');
            }
    }
    
    fclose($output);
    exit;
}

/**
 * Exportar para PDF (HTML simples que pode ser convertido)
 */
function exportPDF($data, $dates, $title = 'Relatório', $report_type = 'default') {
    header('Content-Type: text/html; charset=UTF-8');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
    echo 'h1 { color: #10B981; }';
    echo 'table { border-collapse: collapse; width: 100%; margin-top: 20px; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }';
    echo 'th { background-color: #10B981; color: white; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo '.info { margin: 10px 0; padding: 10px; background: #f0f0f0; border-radius: 5px; }';
    echo '@media print { button { display: none; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h1>📊 ' . htmlspecialchars($title) . '</h1>';
    
    echo '<div class="info">';
    echo '<p><strong>Período:</strong> ' . date('d/m/Y H:i', strtotime($dates['start'])) . ' até ' . date('d/m/Y H:i', strtotime($dates['end'])) . '</p>';
    echo '<p><strong>Total de registros:</strong> ' . count($data) . '</p>';
    echo '<p><strong>Gerado em:</strong> ' . date('d/m/Y H:i:s') . '</p>';
    echo '</div>';
    
    echo '<button onclick="window.print()" style="padding: 10px 20px; background: #10B981; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 10px 0;">🖨️ Imprimir / Salvar como PDF</button>';
    
    echo '<table>';
    
    // Headers e dados baseados no tipo de relatório
    switch ($report_type) {
        case 'attendants':
            echo '<thead><tr>';
            echo '<th>ID</th><th>Nome</th><th>Email</th><th>Status</th><th>Conversas</th><th>Resolvidas</th><th>Taxa</th><th>Tempo Médio</th>';
            echo '</tr></thead><tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($row['email'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars(getStatusLabel($row['status'])) . '</td>';
                echo '<td>' . htmlspecialchars($row['total_conversations']) . '</td>';
                echo '<td>' . htmlspecialchars($row['resolved_conversations']) . '</td>';
                echo '<td>' . htmlspecialchars($row['resolution_rate']) . '</td>';
                echo '<td>' . htmlspecialchars($row['avg_time']) . '</td>';
                echo '</tr>';
            }
            break;
            
        case 'departments':
            echo '<thead><tr>';
            echo '<th>ID</th><th>Setor</th><th>Atendentes</th><th>Conversas</th><th>Resolvidas</th><th>Taxa</th><th>Tempo Médio</th>';
            echo '</tr></thead><tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($row['total_attendants']) . '</td>';
                echo '<td>' . htmlspecialchars($row['total_conversations']) . '</td>';
                echo '<td>' . htmlspecialchars($row['resolved_conversations']) . '</td>';
                echo '<td>' . htmlspecialchars($row['resolution_rate']) . '</td>';
                echo '<td>' . htmlspecialchars($row['avg_time']) . '</td>';
                echo '</tr>';
            }
            break;
            
        case 'timeline':
            echo '<thead><tr>';
            echo '<th>Data</th><th>Total</th><th>Resolvidas</th><th>Abertas</th><th>Em Andamento</th>';
            echo '</tr></thead><tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td>' . date('d/m/Y', strtotime($row['date'])) . '</td>';
                echo '<td>' . htmlspecialchars($row['total']) . '</td>';
                echo '<td>' . htmlspecialchars($row['resolved']) . '</td>';
                echo '<td>' . htmlspecialchars($row['open']) . '</td>';
                echo '<td>' . htmlspecialchars($row['in_progress']) . '</td>';
                echo '</tr>';
            }
            break;
            
        case 'peakhours':
            $days = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            echo '<thead><tr>';
            echo '<th>Dia</th><th>Hora</th><th>Mensagens</th>';
            echo '</tr></thead><tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($days[$row['day_of_week']] ?? $row['day_of_week']) . '</td>';
                echo '<td>' . htmlspecialchars($row['hour_of_day']) . 'h</td>';
                echo '<td>' . htmlspecialchars($row['count']) . '</td>';
                echo '</tr>';
            }
            break;
            
        default:
            echo '<thead><tr>';
            echo '<th>ID</th><th>Cliente</th><th>Telefone</th><th>Status</th><th>Duração</th><th>Data</th>';
            echo '</tr></thead><tbody>';
            foreach ($data as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['contact_name'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($row['contact_number'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars(getStatusLabel($row['status'])) . '</td>';
                echo '<td>' . htmlspecialchars(($row['duration_minutes'] ?? 0) . 'min') . '</td>';
                echo '<td>' . date('d/m/Y H:i', strtotime($row['created_at'])) . '</td>';
                echo '</tr>';
            }
    }
    
    echo '</tbody></table>';
    echo '</body></html>';
    exit;
}

/**
 * Obter label do status
 */
function getStatusLabel($status) {
    $labels = [
        'active' => 'Ativo',
        'resolved' => 'Resolvido',
        'closed' => 'Encerrado'
    ];
    return $labels[$status] ?? $status;
}
