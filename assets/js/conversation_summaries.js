/**
 * Conversation Summaries - Frontend
 * Sistema de Resumo de Conversas para Supervisores
 */

const ConversationSummariesApp = {
    selectedConversations: [],
    conversations: [],
    summaries: [],
    filters: {},
    
    init() {
        console.log('[SUMMARIES] Inicializando aplicação...');
        this.loadAttendants();
        this.loadConversations();
        this.bindEvents();
        
        // Definir datas padrão (últimos 7 dias)
        const today = new Date().toISOString().split('T')[0];
        const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        document.getElementById('filter-date-from').value = weekAgo;
        document.getElementById('filter-date-to').value = today;
    },
    
    bindEvents() {
        // Fechar modal ao clicar fora
        document.getElementById('modal-summary').addEventListener('click', (e) => {
            if (e.target.id === 'modal-summary') {
                this.closeModal();
            }
        });
    },
    
    async loadAttendants() {
        try {
            const response = await fetch('/api/supervisor_users_manager.php?action=list');
            const data = await response.json();
            
            if (data.success && data.users) {
                const select = document.getElementById('filter-attendant');
                data.users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('[SUMMARIES] Erro ao carregar atendentes:', error);
        }
    },
    
    async loadConversations() {
        try {
            const params = new URLSearchParams({
                action: 'list_conversations',
                ...this.filters
            });
            
            const response = await fetch(`/api/conversation_summaries.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.conversations = data.conversations;
                this.renderConversations();
            } else {
                this.showError('Erro ao carregar conversas: ' + data.message);
            }
        } catch (error) {
            console.error('[SUMMARIES] Erro:', error);
            this.showError('Erro ao carregar conversas');
        }
    },
    
    renderConversations() {
        const container = document.getElementById('conversations-list');
        const countEl = document.getElementById('conversations-count');
        
        if (this.conversations.length === 0) {
            container.innerHTML = `
                <div class="p-12 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p class="text-lg">Nenhuma conversa encontrada</p>
                    <p class="text-sm mt-2">Ajuste os filtros para ver mais resultados</p>
                </div>
            `;
            countEl.textContent = '0 conversas encontradas';
            return;
        }
        
        countEl.textContent = `${this.conversations.length} conversas encontradas`;
        
        container.innerHTML = this.conversations.map(conv => {
            const isSelected = this.selectedConversations.includes(conv.id);
            const hasSummary = conv.summary_id !== null;
            const sentimentIcon = this.getSentimentIcon(conv.summary_sentiment);
            
            return `
                <div class="p-4 hover:bg-gray-50 transition ${isSelected ? 'bg-green-50' : ''}">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 pt-1">
                            <input type="checkbox" 
                                   ${isSelected ? 'checked' : ''}
                                   onchange="ConversationSummariesApp.toggleConversation(${conv.id})"
                                   class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-900">
                                        ${conv.contact_name || conv.phone}
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        Atendente: ${conv.attendant_name || 'Não atribuído'}
                                    </p>
                                </div>
                                ${hasSummary ? `<span class="text-2xl">${sentimentIcon}</span>` : ''}
                            </div>
                            <div class="flex items-center gap-4 text-sm text-gray-500 mb-3">
                                <span><i class="far fa-calendar mr-1"></i>${this.formatDate(conv.created_at)}</span>
                                <span><i class="far fa-comments mr-1"></i>${conv.message_count} mensagens</span>
                                <span class="px-2 py-1 rounded-full text-xs ${this.getStatusClass(conv.status)}">
                                    ${this.getStatusLabel(conv.status)}
                                </span>
                            </div>
                            <div class="flex gap-2">
                                ${hasSummary ? `
                                    <button onclick="ConversationSummariesApp.viewSummary(${conv.summary_id})" 
                                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                                        <i class="fas fa-eye mr-2"></i>Ver Resumo
                                    </button>
                                ` : `
                                    <button onclick="ConversationSummariesApp.generateSummary(${conv.id})" 
                                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium">
                                        <i class="fas fa-magic mr-2"></i>Gerar Resumo
                                    </button>
                                `}
                                <button onclick="ConversationSummariesApp.viewConversation(${conv.id})" 
                                        class="px-4 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg text-sm">
                                    <i class="fas fa-comments mr-2"></i>Ver Conversa
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        this.updateBatchButton();
    },
    
    toggleConversation(id) {
        const index = this.selectedConversations.indexOf(id);
        if (index > -1) {
            this.selectedConversations.splice(index, 1);
        } else {
            this.selectedConversations.push(id);
        }
        this.renderConversations();
    },
    
    selectAllConversations() {
        if (this.selectedConversations.length === this.conversations.length) {
            this.selectedConversations = [];
        } else {
            this.selectedConversations = this.conversations.map(c => c.id);
        }
        this.renderConversations();
    },
    
    updateBatchButton() {
        const btn = document.getElementById('btn-batch-generate');
        const count = this.selectedConversations.length;
        
        if (count > 0) {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-magic mr-2"></i>Gerar ${count} Resumo${count > 1 ? 's' : ''}`;
        } else {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-magic mr-2"></i>Gerar Resumos em Lote';
        }
    },
    
    async generateSummary(conversationId) {
        try {
            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Gerando...';
            
            const response = await fetch('/api/conversation_summaries.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate',
                    conversation_id: conversationId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Resumo gerado com sucesso!');
                this.viewSummary(data.summary.id);
                this.loadConversations();
            } else {
                this.showError('Erro: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic mr-2"></i>Gerar Resumo';
            }
        } catch (error) {
            console.error('[SUMMARIES] Erro:', error);
            this.showError('Erro ao gerar resumo');
        }
    },
    
    async generateBatchSummaries() {
        if (this.selectedConversations.length === 0) {
            this.showError('Selecione pelo menos uma conversa');
            return;
        }
        
        if (this.selectedConversations.length > 50) {
            this.showError('Máximo de 50 conversas por lote');
            return;
        }
        
        this.showBatchModal();
        
        try {
            const response = await fetch('/api/conversation_summaries.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_batch',
                    conversation_ids: this.selectedConversations
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateBatchProgress(data);
                setTimeout(() => {
                    this.closeBatchModal();
                    this.showSuccess(`${data.completed} resumos gerados com sucesso!`);
                    this.selectedConversations = [];
                    this.loadConversations();
                }, 2000);
            } else {
                this.showError('Erro: ' + data.message);
                this.closeBatchModal();
            }
        } catch (error) {
            console.error('[SUMMARIES] Erro:', error);
            this.showError('Erro ao gerar resumos em lote');
            this.closeBatchModal();
        }
    },
    
    showBatchModal() {
        const modal = document.getElementById('modal-batch-progress');
        const list = document.getElementById('batch-progress-list');
        
        list.innerHTML = this.selectedConversations.map(id => {
            const conv = this.conversations.find(c => c.id === id);
            return `
                <div id="batch-item-${id}" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                    <i class="fas fa-spinner fa-spin text-gray-400"></i>
                    <span class="text-sm text-gray-700">${conv.contact_name || conv.phone}</span>
                    <span class="ml-auto text-xs text-gray-500">Aguardando...</span>
                </div>
            `;
        }).join('');
        
        modal.classList.remove('hidden');
    },
    
    updateBatchProgress(data) {
        const progressBar = document.getElementById('batch-progress-bar');
        const progressText = document.getElementById('batch-progress-text');
        
        const percentage = (data.completed / data.total) * 100;
        progressBar.style.width = percentage + '%';
        progressText.textContent = `${data.completed}/${data.total}`;
        
        data.results.forEach(result => {
            const item = document.getElementById(`batch-item-${result.conversation_id}`);
            if (item) {
                if (result.status === 'completed') {
                    item.innerHTML = `
                        <i class="fas fa-check-circle text-green-600"></i>
                        <span class="text-sm text-gray-700">${item.querySelector('span').textContent}</span>
                        <span class="ml-auto text-xs text-green-600">Concluído</span>
                    `;
                } else {
                    item.innerHTML = `
                        <i class="fas fa-times-circle text-red-600"></i>
                        <span class="text-sm text-gray-700">${item.querySelector('span').textContent}</span>
                        <span class="ml-auto text-xs text-red-600">Erro</span>
                    `;
                }
            }
        });
    },
    
    closeBatchModal() {
        document.getElementById('modal-batch-progress').classList.add('hidden');
    },
    
    async viewSummary(summaryId) {
        try {
            const response = await fetch(`/api/conversation_summaries.php?action=get&id=${summaryId}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderSummaryModal(data.summary);
            } else {
                this.showError('Erro ao carregar resumo');
            }
        } catch (error) {
            console.error('[SUMMARIES] Erro:', error);
            this.showError('Erro ao carregar resumo');
        }
    },
    
    renderSummaryModal(summary) {
        const content = document.getElementById('modal-summary-content');
        const parsed = summary.summary_json || {};
        
        content.innerHTML = `
            <div class="space-y-6">
                <!-- Informações da Conversa -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Contato:</span>
                            <span class="font-medium ml-2">${summary.contact_name || summary.phone}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Atendente:</span>
                            <span class="font-medium ml-2">${summary.attendant_name || 'N/A'}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Data:</span>
                            <span class="font-medium ml-2">${this.formatDateTime(summary.start_time)}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Duração:</span>
                            <span class="font-medium ml-2">${this.formatDuration(summary.duration_seconds)}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Mensagens:</span>
                            <span class="font-medium ml-2">${summary.message_count}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Sentimento:</span>
                            <span class="font-medium ml-2">${this.getSentimentIcon(summary.sentiment)} ${this.getSentimentLabel(summary.sentiment)}</span>
                        </div>
                    </div>
                </div>

                <!-- Resumo da IA -->
                <div>
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">
                        <i class="fas fa-robot text-green-600 mr-2"></i>Resumo Automático
                    </h4>
                    
                    ${parsed.motivo ? `
                        <div class="mb-4">
                            <h5 class="font-semibold text-gray-700 mb-2">Motivo do Contato:</h5>
                            <p class="text-gray-600">${parsed.motivo}</p>
                        </div>
                    ` : ''}
                    
                    ${parsed.acoes && parsed.acoes.length > 0 ? `
                        <div class="mb-4">
                            <h5 class="font-semibold text-gray-700 mb-2">Ações Realizadas:</h5>
                            <ul class="list-disc list-inside space-y-1 text-gray-600">
                                ${parsed.acoes.map(acao => `<li>${acao}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                    
                    ${parsed.resultado ? `
                        <div class="mb-4">
                            <h5 class="font-semibold text-gray-700 mb-2">Resultado:</h5>
                            <p class="text-gray-600">${parsed.resultado}</p>
                        </div>
                    ` : ''}
                    
                    ${parsed.justificativa_sentimento ? `
                        <div class="mb-4">
                            <h5 class="font-semibold text-gray-700 mb-2">Análise de Sentimento:</h5>
                            <p class="text-gray-600">${parsed.justificativa_sentimento}</p>
                        </div>
                    ` : ''}
                    
                    ${parsed.pontos_atencao && parsed.pontos_atencao.length > 0 ? `
                        <div class="mb-4">
                            <h5 class="font-semibold text-gray-700 mb-2">Pontos de Atenção:</h5>
                            <ul class="list-disc list-inside space-y-1 text-gray-600">
                                ${parsed.pontos_atencao.map(ponto => `<li>${ponto}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                    
                    ${parsed.palavras_chave && parsed.palavras_chave.length > 0 ? `
                        <div class="mb-4">
                            <h5 class="font-semibold text-gray-700 mb-2">Palavras-chave:</h5>
                            <div class="flex flex-wrap gap-2">
                                ${parsed.palavras_chave.map(kw => `
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">${kw}</span>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                </div>

                <!-- Metadados -->
                <div class="border-t pt-4 text-xs text-gray-500">
                    <p>Gerado em: ${this.formatDateTime(summary.generated_at)}</p>
                    <p>Modelo: ${summary.ai_model}</p>
                    <p>Tempo de processamento: ${summary.processing_time_ms}ms</p>
                </div>

                <!-- Ações -->
                <div class="flex gap-3 pt-4 border-t">
                    <button onclick="ConversationSummariesApp.downloadPDF(${summary.id})" 
                            class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                        <i class="fas fa-download mr-2"></i>Baixar PDF
                    </button>
                    <button onclick="ConversationSummariesApp.copySummary()" 
                            class="flex-1 px-4 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg font-medium">
                        <i class="fas fa-copy mr-2"></i>Copiar
                    </button>
                    <button onclick="ConversationSummariesApp.viewConversation(${summary.conversation_id})" 
                            class="flex-1 px-4 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg font-medium">
                        <i class="fas fa-comments mr-2"></i>Ver Conversa
                    </button>
                </div>
            </div>
        `;
        
        document.getElementById('modal-summary').classList.remove('hidden');
    },
    
    closeModal() {
        document.getElementById('modal-summary').classList.add('hidden');
    },
    
    async downloadPDF(summaryId) {
        window.open(`/api/conversation_summaries.php?action=download&id=${summaryId}`, '_blank');
    },
    
    copySummary() {
        const content = document.getElementById('modal-summary-content').innerText;
        navigator.clipboard.writeText(content);
        this.showSuccess('Resumo copiado para a área de transferência');
    },
    
    viewConversation(conversationId) {
        window.location.href = `/chat.php?conversation_id=${conversationId}`;
    },
    
    applyFilters() {
        this.filters = {
            attendant_id: document.getElementById('filter-attendant').value,
            date_from: document.getElementById('filter-date-from').value,
            date_to: document.getElementById('filter-date-to').value,
            status: document.getElementById('filter-status').value,
            has_summary: document.getElementById('filter-has-summary').value,
            keyword: document.getElementById('filter-keyword').value.trim()
        };
        
        this.loadConversations();
    },
    
    clearFilters() {
        // Limpar todos os campos de filtro
        document.getElementById('filter-attendant').value = '';
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-has-summary').value = '';
        document.getElementById('filter-keyword').value = '';
        
        // Resetar datas para últimos 7 dias
        const today = new Date().toISOString().split('T')[0];
        const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        document.getElementById('filter-date-from').value = weekAgo;
        document.getElementById('filter-date-to').value = today;
        
        // Aplicar filtros limpos
        this.applyFilters();
    },
    
    async loadSummaries() {
        try {
            const response = await fetch('/api/conversation_summaries.php?action=list');
            const data = await response.json();
            
            if (data.success) {
                this.summaries = data.summaries;
                this.renderSummaries();
            }
        } catch (error) {
            console.error('[SUMMARIES] Erro:', error);
        }
    },
    
    renderSummaries() {
        const container = document.getElementById('summaries-list');
        
        if (this.summaries.length === 0) {
            container.innerHTML = `
                <div class="p-12 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p class="text-lg">Nenhum resumo gerado ainda</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = this.summaries.map(summary => `
            <div class="p-4 hover:bg-gray-50 transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h4 class="text-lg font-semibold text-gray-900 mb-1">
                            ${summary.contact_name || summary.phone}
                        </h4>
                        <p class="text-sm text-gray-600 mb-2">
                            Atendente: ${summary.attendant_name || 'N/A'}
                        </p>
                        <div class="flex items-center gap-4 text-sm text-gray-500">
                            <span><i class="far fa-calendar mr-1"></i>${this.formatDate(summary.generated_at)}</span>
                            <span>${this.getSentimentIcon(summary.sentiment)} ${this.getSentimentLabel(summary.sentiment)}</span>
                        </div>
                    </div>
                    <button onclick="ConversationSummariesApp.viewSummary(${summary.id})" 
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium">
                        <i class="fas fa-eye mr-2"></i>Ver Resumo
                    </button>
                </div>
            </div>
        `).join('');
    },
    
    async loadStats() {
        try {
            const response = await fetch('/api/conversation_summaries.php?action=stats');
            const data = await response.json();
            
            if (data.success) {
                this.renderStats(data);
            }
        } catch (error) {
            console.error('[SUMMARIES] Erro:', error);
        }
    },
    
    renderStats(data) {
        const stats = data.stats;
        const total = parseInt(stats.total_summaries) || 0;
        
        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-avg-time').textContent = 
            (stats.avg_processing_time / 1000).toFixed(1) + 's';
        
        const positivePercent = total > 0 ? ((stats.positive_count / total) * 100).toFixed(0) : 0;
        const negativePercent = total > 0 ? ((stats.negative_count / total) * 100).toFixed(0) : 0;
        
        document.getElementById('stat-positive').textContent = positivePercent + '%';
        document.getElementById('stat-negative').textContent = negativePercent + '%';
        
        // Top atendentes
        const attendantsHtml = data.top_attendants.map((att, idx) => `
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center font-bold">
                        ${idx + 1}
                    </span>
                    <span class="text-gray-700">${att.name}</span>
                </div>
                <span class="text-gray-500 font-medium">${att.summary_count}</span>
            </div>
        `).join('');
        document.getElementById('top-attendants').innerHTML = attendantsHtml || '<p class="text-gray-500 text-sm">Sem dados</p>';
        
        // Top keywords
        const keywordsHtml = Object.entries(data.top_keywords).map(([kw, count]) => `
            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">
                ${kw} <span class="font-bold">(${count})</span>
            </span>
        `).join('');
        document.getElementById('top-keywords').innerHTML = keywordsHtml || '<p class="text-gray-500 text-sm">Sem dados</p>';
    },
    
    // Helpers
    getSentimentIcon(sentiment) {
        const icons = {
            positive: '😊',
            neutral: '😐',
            negative: '😞',
            mixed: '😕'
        };
        return icons[sentiment] || '😐';
    },
    
    getSentimentLabel(sentiment) {
        const labels = {
            positive: 'Positivo',
            neutral: 'Neutro',
            negative: 'Negativo',
            mixed: 'Misto'
        };
        return labels[sentiment] || 'Neutro';
    },
    
    getStatusClass(status) {
        const classes = {
            open: 'bg-blue-100 text-blue-700',
            in_progress: 'bg-yellow-100 text-yellow-700',
            closed: 'bg-gray-100 text-gray-700'
        };
        return classes[status] || 'bg-gray-100 text-gray-700';
    },
    
    getStatusLabel(status) {
        const labels = {
            open: 'Aberto',
            in_progress: 'Em andamento',
            closed: 'Finalizado'
        };
        return labels[status] || status;
    },
    
    formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('pt-BR');
    },
    
    formatDateTime(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleString('pt-BR');
    },
    
    formatDuration(seconds) {
        if (!seconds) return '0s';
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return mins > 0 ? `${mins}min ${secs}s` : `${secs}s`;
    },
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    },
    
    showError(message) {
        this.showNotification(message, 'error');
    },
    
    showNotification(message, type) {
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500'
        };
        
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    },
    
    /**
     * Regenerar resumo de uma conversa (forçar nova geração)
     */
    async regenerateSummary(conversationId) {
        if (!confirm('Deseja regenerar o resumo desta conversa? O resumo anterior será substituído.')) {
            return;
        }
        
        try {
            this.showNotification('Regenerando resumo...', 'success');
            
            const response = await fetch('/api/conversation_summaries.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'regenerate',
                    conversation_id: conversationId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Resumo regenerado com sucesso!');
                this.loadConversations();
                this.loadSummaries();
                
                if (data.summary) {
                    this.renderSummaryModal(data.summary);
                }
            } else {
                this.showError('Erro ao regenerar: ' + data.message);
            }
        } catch (error) {
            console.error('[SUMMARIES] Erro:', error);
            this.showError('Erro ao regenerar resumo');
        }
    },
    
    /**
     * Verificar status do sistema
     */
    async checkSystemStatus() {
        try {
            const response = await fetch('/api/conversation_summaries.php?action=check_status');
            const data = await response.json();
            
            if (data.success) {
                const status = data.status;
                let message = '';
                
                if (status.ready) {
                    message = '✅ Sistema pronto!\n';
                    message += `📊 Total de resumos: ${status.summary_counts?.total || 0}\n`;
                    message += `✓ Completos: ${status.summary_counts?.completed || 0}\n`;
                    message += `✗ Falhas: ${status.summary_counts?.failed || 0}`;
                    this.showSuccess('Sistema funcionando corretamente');
                } else {
                    message = '⚠️ Sistema com problemas:\n';
                    if (!status.database) {
                        message += '❌ Tabelas não criadas - Execute a migration SQL\n';
                    }
                    if (!status.google_ai) {
                        message += '❌ Google AI não configurada - Configure GOOGLE_AI_API_KEY no .env\n';
                    }
                    this.showError('Sistema não está pronto');
                }
                
                alert(message);
            }
        } catch (error) {
            console.error('[SUMMARIES] Erro:', error);
            this.showError('Erro ao verificar status');
        }
    }
};

// Funções globais para tabs
function switchTab(tab) {
    // Atualizar botões
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active', 'border-green-500', 'text-green-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.getElementById(`tab-${tab}`).classList.add('active', 'border-green-500', 'text-green-600');
    document.getElementById(`tab-${tab}`).classList.remove('border-transparent', 'text-gray-500');
    
    // Atualizar conteúdo
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.getElementById(`content-${tab}`).classList.remove('hidden');
    
    // Carregar dados da tab
    if (tab === 'summaries') {
        ConversationSummariesApp.loadSummaries();
    } else if (tab === 'stats') {
        ConversationSummariesApp.loadStats();
    }
}

function applyFilters() {
    ConversationSummariesApp.applyFilters();
}

function clearFilters() {
    ConversationSummariesApp.clearFilters();
}

function selectAllConversations() {
    ConversationSummariesApp.selectAllConversations();
}

function generateBatchSummaries() {
    ConversationSummariesApp.generateBatchSummaries();
}

function closeModal() {
    ConversationSummariesApp.closeModal();
}

function closeBatchModal() {
    ConversationSummariesApp.closeBatchModal();
}

function regenerateSummary(conversationId) {
    ConversationSummariesApp.regenerateSummary(conversationId);
}

function checkSystemStatus() {
    ConversationSummariesApp.checkSystemStatus();
}

// Inicializar ao carregar a página
document.addEventListener('DOMContentLoaded', () => {
    ConversationSummariesApp.init();
});
