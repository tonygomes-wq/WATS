/**
 * JavaScript para Relatórios de Atendimento
 */

let currentTab = 'attendants';
let timelineChart = null;
let statusChart = null;
let satisfactionChart = null;

// Carregar dados ao iniciar
document.addEventListener('DOMContentLoaded', function () {
    loadAttendantsList();
    loadDepartmentsList();
    applyFilters();

    // Mostrar/ocultar campos de data personalizada
    document.getElementById('period-filter').addEventListener('change', function () {
        const customDates = document.getElementById('custom-dates');
        const customDatesEnd = document.getElementById('custom-dates-end');

        if (this.value === 'custom') {
            customDates.classList.remove('hidden');
            customDatesEnd.classList.remove('hidden');
        } else {
            customDates.classList.add('hidden');
            customDatesEnd.classList.add('hidden');
        }
    });
});

/**
 * Aplicar filtros e carregar dados
 */
async function applyFilters() {
    const period = document.getElementById('period-filter').value;
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    const attendantId = document.getElementById('attendant-filter').value;
    const departmentId = document.getElementById('department-filter').value;
    const status = document.getElementById('status-filter').value;

    const params = new URLSearchParams({
        period,
        ...(startDate && { start_date: startDate }),
        ...(endDate && { end_date: endDate }),
        ...(attendantId && { attendant_id: attendantId }),
        ...(departmentId && { department_id: departmentId }),
        ...(status && { status })
    });

    // Carregar métricas gerais
    await loadOverviewMetrics(params);

    // Carregar dados da tab atual
    switch (currentTab) {
        case 'attendants':
            await loadAttendantsReport(params);
            break;
        case 'departments':
            await loadDepartmentsReport(params);
            break;
        case 'timeline':
            await loadTimelineData(params);
            break;
        case 'performance':
            await loadPerformanceData(params);
            break;
        case 'peakhours':
            await loadPeakHoursData(params);
            break;
    }
}

/**
 * Carregar métricas gerais
 */
async function loadOverviewMetrics(params) {
    try {
        const response = await fetch(`/api/reports.php?action=overview&${params}`);
        const data = await response.json();

        if (data.success) {
            const metrics = data.metrics;

            // Atualizar cards
            document.getElementById('total-conversations').textContent = metrics.total_conversations;
            document.getElementById('avg-response-time').textContent = metrics.avg_resolution_time;
            document.getElementById('resolution-rate').textContent = metrics.resolution_rate + '%';
            document.getElementById('avg-satisfaction').textContent = metrics.avg_satisfaction;

            // Adicionar indicadores de mudança (opcional)
            // updateChangeIndicators(metrics);
        }
    } catch (error) {
        console.error('Erro ao carregar métricas:', error);
    }
}

/**
 * Carregar relatório por atendente
 */
async function loadAttendantsReport(params) {
    try {
        const response = await fetch(`/api/reports.php?action=by_attendant&${params}`);
        const data = await response.json();

        if (data.success) {
            const tbody = document.getElementById('attendants-table-body');
            tbody.innerHTML = '';

            if (data.attendants.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            Nenhum dado encontrado para o período selecionado
                        </td>
                    </tr>
                `;
                return;
            }

            data.attendants.forEach(attendant => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';

                const statusBadge = getStatusBadge(attendant.status);
                const satisfactionStars = getSatisfactionStars(attendant.avg_satisfaction);

                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-blue-600 dark:text-blue-400"></i>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(attendant.name)}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">${escapeHtml(attendant.email)}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">${attendant.total_conversations}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">${attendant.resolved_conversations}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: ${attendant.resolution_rate}%"></div>
                            </div>
                            <span class="text-sm text-gray-900 dark:text-white">${attendant.resolution_rate}%</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">${attendant.avg_time_formatted}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-1">
                            ${satisfactionStars}
                            <span class="text-sm text-gray-600 dark:text-gray-400 ml-1">${attendant.satisfaction_formatted}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">${statusBadge}</td>
                `;

                tbody.appendChild(row);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar relatório por atendente:', error);
    }
}

/**
 * Carregar relatório por setor
 */
async function loadDepartmentsReport(params) {
    try {
        const response = await fetch(`/api/reports.php?action=by_department&${params}`);
        const data = await response.json();

        if (data.success) {
            const tbody = document.getElementById('departments-table-body');
            tbody.innerHTML = '';

            if (data.departments.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            Nenhum dado encontrado para o período selecionado
                        </td>
                    </tr>
                `;
                return;
            }

            data.departments.forEach(dept => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';

                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-4 h-4 rounded-full mr-3" style="background-color: ${dept.color}"></div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(dept.name)}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">${dept.total_attendants}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">${dept.total_conversations}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">${dept.resolved_conversations}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: ${dept.resolution_rate}%"></div>
                            </div>
                            <span class="text-sm text-gray-900 dark:text-white">${dept.resolution_rate}%</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">${dept.avg_time_formatted}</td>
                `;

                tbody.appendChild(row);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar relatório por setor:', error);
    }
}

/**
 * Carregar dados de linha do tempo
 */
async function loadTimelineData(params) {
    try {
        const response = await fetch(`/api/reports.php?action=timeline&${params}`);
        const data = await response.json();

        if (data.success) {
            const labels = data.timeline.map(item => formatDate(item.date));
            const totalData = data.timeline.map(item => item.total);
            const resolvedData = data.timeline.map(item => item.resolved);
            const openData = data.timeline.map(item => item.open);
            const inProgressData = data.timeline.map(item => item.in_progress);

            // Destruir gráfico anterior se existir
            if (timelineChart) {
                timelineChart.destroy();
            }

            const ctx = document.getElementById('timeline-chart').getContext('2d');
            timelineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total',
                            data: totalData,
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Resolvidas',
                            data: resolvedData,
                            borderColor: 'rgb(34, 197, 94)',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Em Andamento',
                            data: inProgressData,
                            borderColor: 'rgb(234, 179, 8)',
                            backgroundColor: 'rgba(234, 179, 8, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Abertas',
                            data: openData,
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Evolução de Conversas no Tempo'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Erro ao carregar dados de linha do tempo:', error);
    }
}

/**
 * Carregar dados de desempenho
 */
async function loadPerformanceData(params) {
    try {
        const response = await fetch(`/api/reports.php?action=performance&${params}`);
        const data = await response.json();

        if (data.success) {
            // Gráfico de Status
            const statusLabels = data.status_distribution.map(item => translateStatus(item.status));
            const statusData = data.status_distribution.map(item => item.count);

            if (statusChart) {
                statusChart.destroy();
            }

            const ctx1 = document.getElementById('status-chart').getContext('2d');
            statusChart = new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: [
                            'rgb(239, 68, 68)',
                            'rgb(234, 179, 8)',
                            'rgb(34, 197, 94)',
                            'rgb(156, 163, 175)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        title: {
                            display: true,
                            text: 'Distribuição por Status'
                        }
                    }
                }
            });

            // Gráfico de Satisfação
            const satisfactionLabels = data.satisfaction_distribution.map(item => item.rating + ' estrelas');
            const satisfactionData = data.satisfaction_distribution.map(item => item.count);

            if (satisfactionChart) {
                satisfactionChart.destroy();
            }

            const ctx2 = document.getElementById('satisfaction-chart').getContext('2d');
            satisfactionChart = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: satisfactionLabels,
                    datasets: [{
                        label: 'Avaliações',
                        data: satisfactionData,
                        backgroundColor: 'rgb(168, 85, 247)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Distribuição de Satisfação'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Erro ao carregar dados de desempenho:', error);
    }
}

/**
 * Carregar dados de horários de pico (Heatmap)
 */
async function loadPeakHoursData(params) {
    try {
        const response = await fetch(`/api/reports.php?action=peak_hours&${params}`);
        const data = await response.json();

        if (data.success) {
            renderHeatmap(data.peak_hours);
        }
    } catch (error) {
        console.error('Erro ao carregar horários de pico:', error);
        document.getElementById('heatmap-container').innerHTML = '<p class="text-red-500">Erro ao carregar dados.</p>';
    }
}

function renderHeatmap(data) {
    const container = document.getElementById('heatmap-container');
    const days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    const hours = Array.from({ length: 24 }, (_, i) => i); // 0 to 23

    // Encontrar valor máximo para escala de cores
    let max = 0;
    for (let d = 1; d <= 7; d++) {
        if (data[d]) {
            for (let h = 0; h < 24; h++) {
                if (data[d][h] > max) max = data[d][h];
            }
        }
    }

    let html = '<table class="w-full border-collapse text-xs">';

    // Header (Horas)
    html += '<thead><tr><th class="p-2 border dark:border-gray-700 bg-gray-50 dark:bg-gray-800">Dia/Hora</th>';
    hours.forEach(h => {
        html += `<th class="p-1 border dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-center w-8">${h}h</th>`;
    });
    html += '</tr></thead><tbody>';

    // Rows (Dias)
    days.forEach((day, index) => {
        const dayIndex = index + 1; // MySQL DAYOFWEEK: 1=Sunday
        html += `<tr><td class="p-2 border dark:border-gray-700 font-medium bg-gray-50 dark:bg-gray-800">${day}</td>`;

        hours.forEach(h => {
            const value = data[dayIndex] ? (data[dayIndex][h] || 0) : 0;
            const intensity = max > 0 ? (value / max) : 0;

            // Cor baseada na intensidade (Azul)
            // intensity 0 -> bg-white
            // intensity 1 -> bg-blue-600
            let bgStyle = '';
            let textClass = 'text-gray-600 dark:text-gray-400';

            if (value > 0) {
                // Calcular cor HSL para degradê azul
                // Lightness: 95% (fraco) a 50% (forte)
                const lightness = 95 - (intensity * 45);
                bgStyle = `background-color: hsl(217, 90%, ${lightness}%);`;
                if (intensity > 0.5) textClass = 'text-white font-bold';
                else textClass = 'text-blue-900 font-medium';
            }

            html += `<td class="p-1 border dark:border-gray-700 text-center ${textClass}" style="${bgStyle}" title="${value} mensagens">
                ${value > 0 ? value : ''}
            </td>`;
        });
        html += '</tr>';
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

/**
 * Carregar lista de atendentes para filtro
 */
async function loadAttendantsList() {
    try {
        const response = await fetch('/api/reports.php?action=attendants_list');
        const data = await response.json();

        if (data.success) {
            const select = document.getElementById('attendant-filter');

            data.attendants.forEach(attendant => {
                const option = document.createElement('option');
                option.value = attendant.id;
                option.textContent = attendant.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar lista de atendentes:', error);
    }
}

/**
 * Carregar lista de setores para filtro
 */
async function loadDepartmentsList() {
    try {
        const response = await fetch('/api/reports.php?action=departments_list');
        const data = await response.json();

        if (data.success) {
            const select = document.getElementById('department-filter');

            data.departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar lista de setores:', error);
    }
}

/**
 * Trocar de tab
 */
function switchTab(tab) {
    currentTab = tab;

    // Atualizar botões
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active', 'border-blue-600', 'text-blue-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });

    const activeBtn = document.getElementById(`tab-${tab}`);
    activeBtn.classList.add('active', 'border-blue-600', 'text-blue-600');
    activeBtn.classList.remove('border-transparent', 'text-gray-500');

    // Atualizar conteúdo
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });

    document.getElementById(`content-${tab}`).classList.remove('hidden');

    // Carregar dados da tab
    applyFilters();
}

/**
 * Exportar relatório
 */
async function exportReport(format = 'excel') {
    const period = document.getElementById('period-filter').value;
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    const attendantId = document.getElementById('attendant-filter').value;
    const departmentId = document.getElementById('department-filter').value;
    const status = document.getElementById('status-filter').value;

    // Mostrar loading
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Exportando...';
    btn.disabled = true;

    try {
        const response = await fetch('/api/export_reports.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                format: format,
                period: period,
                start_date: startDate,
                end_date: endDate,
                attendant_id: attendantId,
                department_id: departmentId,
                status: status,
                report_type: currentTab
            })
        });

        if (response.ok) {
            // Criar blob e fazer download
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            
            // Definir nome do arquivo baseado no formato
            const extensions = { excel: 'xls', csv: 'csv', pdf: 'html' };
            a.download = `relatorio_${currentTab}_${new Date().toISOString().split('T')[0]}.${extensions[format] || 'xls'}`;
            
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        } else {
            const error = await response.text();
            alert('Erro ao exportar: ' + error);
        }
    } catch (error) {
        console.error('Erro ao exportar:', error);
        alert('Erro ao exportar relatório');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

/**
 * Mostrar modal de exportação
 */
function showExportModal() {
    const modal = document.createElement('div');
    modal.id = 'export-modal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-sm w-full mx-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <i class="fas fa-download text-green-600 mr-2"></i>Exportar Relatório
            </h3>
            <div class="space-y-3">
                <button onclick="exportReport('excel'); closeExportModal();" 
                        class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg flex items-center justify-center gap-2">
                    <i class="fas fa-file-excel"></i> Excel (.xls)
                </button>
                <button onclick="exportReport('csv'); closeExportModal();" 
                        class="w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center justify-center gap-2">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
                <button onclick="exportReport('pdf'); closeExportModal();" 
                        class="w-full px-4 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg flex items-center justify-center gap-2">
                    <i class="fas fa-file-pdf"></i> PDF (HTML)
                </button>
            </div>
            <button onclick="closeExportModal()" class="mt-4 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                Cancelar
            </button>
        </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeExportModal();
    });
}

function closeExportModal() {
    const modal = document.getElementById('export-modal');
    if (modal) modal.remove();
}

/**
 * Funções auxiliares
 */
function getStatusBadge(status) {
    const badges = {
        'active': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Ativo</span>',
        'inactive': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inativo</span>',
        'blocked': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Bloqueado</span>'
    };

    return badges[status] || badges['inactive'];
}

function getSatisfactionStars(rating) {
    if (!rating || rating === 'N/A') {
        return '<span class="text-gray-400">-</span>';
    }

    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    let stars = '';

    for (let i = 0; i < fullStars; i++) {
        stars += '<i class="fas fa-star text-yellow-400"></i>';
    }

    if (hasHalfStar) {
        stars += '<i class="fas fa-star-half-alt text-yellow-400"></i>';
    }

    const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
    for (let i = 0; i < emptyStars; i++) {
        stars += '<i class="far fa-star text-gray-300"></i>';
    }

    return stars;
}

function translateStatus(status) {
    const translations = {
        'open': 'Aberto',
        'in_progress': 'Em Andamento',
        'resolved': 'Resolvido',
        'closed': 'Fechado'
    };

    return translations[status] || status;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
