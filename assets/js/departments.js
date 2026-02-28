/**
 * JavaScript para Gerenciamento de Setores
 */

let departments = [];
let editingDepartmentId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadDepartments();
    
    document.getElementById('search-input').addEventListener('input', debounce(loadDepartments, 500));
    document.getElementById('status-filter').addEventListener('change', loadDepartments);
    document.getElementById('department-form').addEventListener('submit', handleFormSubmit);
    
    // Aplicar cores aos quadrados via JavaScript
    applyColorPickerColors();
    
    // Seleção de cor
    document.querySelectorAll('.color-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            // Remover seleção de todas as cores
            document.querySelectorAll('.color-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Adicionar seleção na cor escolhida
            if (this.checked) {
                const colorOption = this.nextElementSibling;
                colorOption.classList.add('selected');
            }
        });
        
        // Adicionar clique no div para selecionar o radio
        radio.nextElementSibling.addEventListener('click', function() {
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDepartmentModal();
    });
});

// Aplicar cores aos quadrados de seleção
function applyColorPickerColors() {
    document.querySelectorAll('.color-option').forEach(option => {
        const color = option.getAttribute('data-color');
        if (color) {
            option.style.backgroundColor = color;
        }
    });
}

async function loadDepartments() {
    const search = document.getElementById('search-input').value;
    const status = document.getElementById('status-filter').value;
    
    document.getElementById('loading-state').classList.remove('hidden');
    document.getElementById('empty-state').classList.add('hidden');
    document.getElementById('departments-grid').innerHTML = '';
    
    try {
        const params = new URLSearchParams({
            action: 'list',
            search: search,
            status: status
        });
        
        const response = await fetch(`api/departments_manager.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            departments = data.departments;
            renderDepartments(departments);
            updateStats(departments);
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao carregar setores:', error);
        showError('Erro ao carregar setores');
    } finally {
        document.getElementById('loading-state').classList.add('hidden');
    }
}

function renderDepartments(departments) {
    const grid = document.getElementById('departments-grid');
    grid.innerHTML = '';
    
    if (departments.length === 0) {
        document.getElementById('empty-state').classList.remove('hidden');
        return;
    }
    
    departments.forEach(dept => {
        const card = document.createElement('div');
        card.className = 'bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-xl transition-all overflow-hidden';
        
        const statusBadge = dept.is_active == 1 ? 
            '<span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300"><i class="fas fa-check-circle mr-1"></i>Ativo</span>' :
            '<span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"><i class="fas fa-pause-circle mr-1"></i>Inativo</span>';
        
        card.innerHTML = `
            <div class="h-3" style="background-color: ${dept.color}"></div>
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background-color: ${dept.color}20">
                            <i class="fas fa-building text-2xl" style="color: ${dept.color}"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">${escapeHtml(dept.name)}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">${dept.user_count} atendente(s)</p>
                        </div>
                    </div>
                    ${statusBadge}
                </div>
                
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 line-clamp-2">
                    ${dept.description || '<em>Sem descrição</em>'}
                </p>
                
                <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-4 text-sm">
                        <span class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                            <i class="fas fa-comments"></i>
                            ${dept.active_conversations || 0}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="editDepartment(${dept.id})" class="text-blue-600 hover:text-blue-900 p-2 hover:bg-blue-50 dark:hover:bg-blue-900 rounded transition-colors" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="toggleDepartmentStatus(${dept.id})" class="text-${dept.is_active == 1 ? 'orange' : 'green'}-600 hover:text-${dept.is_active == 1 ? 'orange' : 'green'}-900 p-2 hover:bg-${dept.is_active == 1 ? 'orange' : 'green'}-50 rounded transition-colors" title="${dept.is_active == 1 ? 'Desativar' : 'Ativar'}">
                            <i class="fas fa-${dept.is_active == 1 ? 'pause' : 'play'}-circle"></i>
                        </button>
                        <button onclick="deleteDepartment(${dept.id})" class="text-red-600 hover:text-red-900 p-2 hover:bg-red-50 dark:hover:bg-red-900 rounded transition-colors" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        grid.appendChild(card);
    });
}

function updateStats(departments) {
    const total = departments.length;
    const active = departments.filter(d => d.is_active == 1).length;
    const totalUsers = departments.reduce((sum, d) => sum + parseInt(d.user_count || 0), 0);
    const totalConversations = departments.reduce((sum, d) => sum + parseInt(d.active_conversations || 0), 0);
    
    document.getElementById('total-departments').textContent = total;
    document.getElementById('active-departments').textContent = active;
    document.getElementById('total-users').textContent = totalUsers;
    document.getElementById('total-conversations').textContent = totalConversations;
}

function openCreateModal() {
    editingDepartmentId = null;
    document.getElementById('modal-title').innerHTML = '<i class="fas fa-building text-green-600"></i> Novo Setor';
    document.getElementById('department-form').reset();
    document.getElementById('department-id').value = '';
    
    // Aplicar cores novamente (caso tenham sido perdidas)
    applyColorPickerColors();
    
    // Limpar seleção de cores
    document.querySelectorAll('.color-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    
    // Selecionar primeira cor por padrão
    const firstColor = document.querySelector('.color-radio');
    if (firstColor) {
        firstColor.checked = true;
        const colorOption = firstColor.nextElementSibling;
        colorOption.classList.add('selected');
    }
    
    document.getElementById('department-modal').classList.remove('hidden');
    document.getElementById('department-modal').classList.add('flex');
}

async function editDepartment(deptId) {
    editingDepartmentId = deptId;
    
    try {
        const response = await fetch(`api/departments_manager.php?action=get&department_id=${deptId}`);
        const data = await response.json();
        
        if (data.success) {
            const dept = data.department;
            
            document.getElementById('modal-title').innerHTML = '<i class="fas fa-edit text-blue-600"></i> Editar Setor';
            document.getElementById('department-id').value = dept.id;
            document.getElementById('department-name').value = dept.name;
            document.getElementById('department-description').value = dept.description || '';
            
            // Limpar seleção de cores
            document.querySelectorAll('.color-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Selecionar cor
            const colorRadio = document.querySelector(`input[name="color"][value="${dept.color}"]`);
            if (colorRadio) {
                colorRadio.checked = true;
                const colorOption = colorRadio.nextElementSibling;
                colorOption.classList.add('selected');
            }
            
            document.getElementById('department-modal').classList.remove('hidden');
            document.getElementById('department-modal').classList.add('flex');
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao carregar setor:', error);
        showError('Erro ao carregar dados do setor');
    }
}

function closeDepartmentModal() {
    document.getElementById('department-modal').classList.add('hidden');
    document.getElementById('department-modal').classList.remove('flex');
    document.getElementById('department-form').reset();
    
    // Limpar seleção de cores
    document.querySelectorAll('.color-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    
    editingDepartmentId = null;
}

async function handleFormSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('action', editingDepartmentId ? 'update' : 'create');
    
    try {
        const response = await fetch('api/departments_manager.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            closeDepartmentModal();
            loadDepartments();
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao salvar setor:', error);
        showError('Erro ao salvar setor');
    }
}

async function toggleDepartmentStatus(deptId) {
    try {
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('department_id', deptId);
        
        const response = await fetch('api/departments_manager.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            loadDepartments();
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao alterar status:', error);
        showError('Erro ao alterar status do setor');
    }
}

async function deleteDepartment(deptId) {
    if (!confirm('Deseja realmente excluir este setor? Esta ação não pode ser desfeita.')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('department_id', deptId);
        
        const response = await fetch('api/departments_manager.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            loadDepartments();
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao excluir setor:', error);
        showError('Erro ao excluir setor');
    }
}

async function createDefaultDepartments() {
    if (!confirm('Deseja criar os 8 setores padrão do sistema?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'create_defaults');
        
        const response = await fetch('api/departments_manager.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            loadDepartments();
        } else {
            showError(data.error);
        }
    } catch (error) {
        console.error('Erro ao criar setores padrão:', error);
        showError('Erro ao criar setores padrão');
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function showSuccess(message) {
    alert('✅ ' + message);
}

function showError(message) {
    alert('❌ ' + message);
}
