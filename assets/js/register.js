// =====================================================
// SISTEMA DE REGISTRO - WATS MACIP
// =====================================================

// Variáveis globais
let currentUserId = null;
let availablePlans = [];

// Abrir modal de registro
window.openRegisterModal = function () {
    console.log('openRegisterModal chamado');
    const modal = document.getElementById('registerModal');
    if (!modal) {
        console.error('Modal de registro não encontrado!');
        alert('Erro: Modal de registro não encontrado. Por favor, recarregue a página.');
        return;
    }

    // Close login modal if open
    if (typeof closeLoginModal === 'function') closeLoginModal();

    // Resetar para etapa 1
    showRegisterStep(1);
    
    // Use Modal Manager if available
    if (window.modalManager) {
        console.log('Usando Modal Manager para abrir modal de registro');
        window.modalManager.openModal('registerModal');
    } else {
        console.log('Modal Manager não disponível, usando fallback');
        // Fallback: manual open
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    // Focar no primeiro campo
    setTimeout(() => {
        const nameInput = document.getElementById('reg_name');
        if (nameInput) nameInput.focus();
    }, 100);

    // Inicializar Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    console.log('Modal de registro aberto com sucesso');
}

// Fechar modal de registro
window.closeRegisterModal = function (event) {
    const modal = document.getElementById('registerModal');
    if (!modal) return;

    // Use Modal Manager if available
    if (window.modalManager) {
        console.log('Usando Modal Manager para fechar modal de registro');
        window.modalManager.closeModal('registerModal');
    } else {
        console.log('Modal Manager não disponível, usando fallback');
        // Fallback: manual close
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        modal.style.display = '';
        document.body.style.overflow = '';
    }
    
    resetRegisterModal();
}

// Resetar modal
function resetRegisterModal() {
    const form = document.getElementById('registerForm');
    if (form) form.reset();

    const alert = document.getElementById('registerAlert');
    if (alert) alert.classList.add('hidden');

    const planAlert = document.getElementById('planAlert');
    if (planAlert) planAlert.classList.add('hidden');

    showRegisterStep(1);
}

// Mostrar etapa específica
function showRegisterStep(step) {
    document.getElementById('registerStep1').classList.toggle('hidden', step !== 1);
    document.getElementById('registerStep2').classList.toggle('hidden', step !== 2);
}

// Alternar para login
window.switchToLogin = function () {
    closeRegisterModal();
    setTimeout(() => {
        if (typeof openLoginModal === 'function') {
            openLoginModal();
        }
    }, 300);
}

// Atualizar máscara de documento
window.updateDocumentMask = function () {
    const documentType = document.querySelector('input[name="document_type"]:checked').value;
    const documentInput = document.getElementById('reg_document');
    const documentLabel = document.getElementById('documentLabel');

    if (documentType === 'cpf') {
        documentLabel.textContent = 'CPF';
        documentInput.placeholder = '000.000.000-00';
        documentInput.maxLength = 14;
    } else {
        documentLabel.textContent = 'CNPJ';
        documentInput.placeholder = '00.000.000/0000-00';
        documentInput.maxLength = 18;
    }

    documentInput.value = '';
}

// Aplicar máscara de CPF/CNPJ
function applyDocumentMask(value, type) {
    value = value.replace(/\D/g, '');

    if (type === 'cpf') {
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    } else {
        value = value.replace(/^(\d{2})(\d)/, '$1.$2');
        value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
    }

    return value;
}

// Aplicar máscara de telefone
function applyPhoneMask(value) {
    value = value.replace(/\D/g, '');
    value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
    value = value.replace(/(\d)(\d{4})$/, '$1-$2');
    return value;
}

// Mostrar alerta
function showRegisterAlert(message, type = 'error') {
    const alertDiv = document.getElementById('registerAlert');
    if (!alertDiv) return;

    alertDiv.classList.remove('hidden', 'bg-red-100', 'border-red-400', 'text-red-700',
        'bg-green-100', 'border-green-400', 'text-green-700',
        'bg-blue-100', 'border-blue-400', 'text-blue-700');

    let icon = 'alert-circle';
    if (type === 'error') {
        alertDiv.classList.add('bg-red-100', 'border', 'border-red-400', 'text-red-700');
        icon = 'alert-circle';
    } else if (type === 'success') {
        alertDiv.classList.add('bg-green-100', 'border', 'border-green-400', 'text-green-700');
        icon = 'check-circle';
    } else if (type === 'info') {
        alertDiv.classList.add('bg-blue-100', 'border', 'border-blue-400', 'text-blue-700');
        icon = 'info';
    }

    alertDiv.innerHTML = `<i data-lucide="${icon}" class="inline w-5 h-5 mr-2"></i>${message}`;

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Mostrar alerta de plano
function showPlanAlert(message, type = 'error') {
    const alertDiv = document.getElementById('planAlert');
    if (!alertDiv) return;

    alertDiv.classList.remove('hidden', 'bg-red-100', 'border-red-400', 'text-red-700',
        'bg-green-100', 'border-green-400', 'text-green-700');

    let icon = 'alert-circle';
    if (type === 'error') {
        alertDiv.classList.add('bg-red-100', 'border', 'border-red-400', 'text-red-700');
    } else if (type === 'success') {
        alertDiv.classList.add('bg-green-100', 'border', 'border-green-400', 'text-green-700');
        icon = 'check-circle';
    }

    alertDiv.innerHTML = `<i data-lucide="${icon}" class="inline w-5 h-5 mr-2"></i>${message}`;

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Carregar planos disponíveis
async function loadAvailablePlans() {
    try {
        const response = await fetch('api/get_plans.php');
        const data = await response.json();

        if (data.success) {
            availablePlans = data.plans;
            renderPlans();
        }
    } catch (error) {
        console.error('Erro ao carregar planos:', error);
        showPlanAlert('Erro ao carregar planos. Tente novamente.', 'error');
    }
}

// Renderizar planos
function renderPlans() {
    const grid = document.getElementById('plansGrid');
    if (!grid) return;

    grid.innerHTML = '';

    availablePlans.forEach(plan => {
        const isFree = plan.price == 0;
        const features = JSON.parse(plan.features || '[]');

        const planCard = document.createElement('div');
        planCard.className = `border-2 rounded-xl p-6 cursor-pointer transition-all hover:scale-105 ${plan.is_popular ? 'border-green-500 bg-green-50' : 'border-gray-300 hover:border-green-400'
            }`;
        planCard.onclick = () => selectPlan(plan.id);

        planCard.innerHTML = `
            ${plan.is_popular ? '<div class="bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full inline-block mb-2">POPULAR</div>' : ''}
            <h3 class="text-xl font-bold text-gray-800 mb-2">${plan.name}</h3>
            <div class="mb-4">
                <span class="text-3xl font-black text-green-600">R$ ${parseFloat(plan.price).toFixed(2).replace('.', ',')}</span>
                <span class="text-gray-600">/mês</span>
            </div>
            ${isFree ? '<div class="bg-yellow-100 text-yellow-800 text-sm px-3 py-1 rounded-full inline-block mb-4"><i data-lucide="clock" class="inline w-4 h-4 mr-1"></i>15 dias grátis</div>' : ''}
            <ul class="space-y-2 mb-6 text-sm">
                ${features.map(f => `
                    <li class="flex items-start">
                        <i data-lucide="check" class="w-4 h-4 text-green-600 mr-2 flex-shrink-0 mt-0.5"></i>
                        <span class="text-gray-700">${f}</span>
                    </li>
                `).join('')}
            </ul>
            <button class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition">
                ${isFree ? 'Começar Grátis' : 'Escolher Plano'}
            </button>
        `;

        grid.appendChild(planCard);
    });

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Selecionar plano
async function selectPlan(planId) {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" class="inline w-4 h-4 mr-2 animate-spin"></i>Processando...';

    try {
        const response = await fetch('api/register_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'select_plan',
                plan_id: planId
            })
        });

        const data = await response.json();

        if (data.success) {
            if (data.plan_type === 'trial') {
                // Plano grátis - redirecionar para dashboard
                showPlanAlert(data.message, 'success');
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            } else {
                // Plano pago - redirecionar para pagamento
                showPlanAlert('Redirecionando para pagamento...', 'success');
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            }
        } else {
            showPlanAlert(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Erro:', error);
        showPlanAlert('Erro ao processar. Tente novamente.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Inicialização
document.addEventListener('DOMContentLoaded', function () {
    // Aplicar máscaras
    const phoneInput = document.getElementById('reg_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            e.target.value = applyPhoneMask(e.target.value);
        });
    }

    const documentInput = document.getElementById('reg_document');
    if (documentInput) {
        documentInput.addEventListener('input', function (e) {
            const type = document.querySelector('input[name="document_type"]:checked').value;
            e.target.value = applyDocumentMask(e.target.value, type);
        });
    }

    // Processar formulário de registro
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const btn = document.getElementById('registerBtn');
            const formData = new FormData(registerForm);

            // Validar senhas
            const password = formData.get('password');
            const passwordConfirm = formData.get('password_confirm');

            if (password !== passwordConfirm) {
                showRegisterAlert('As senhas não coincidem.', 'error');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="inline w-5 h-5 mr-2 animate-spin"></i>Criando conta...';

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            try {
                const response = await fetch('api/register_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'register',
                        name: formData.get('name'),
                        company_name: formData.get('company_name'),
                        email: formData.get('email'),
                        phone: formData.get('phone'),
                        document: formData.get('document'),
                        document_type: formData.get('document_type'),
                        password: password,
                        password_confirm: passwordConfirm
                    })
                });

                const data = await response.json();

                if (data.success) {
                    currentUserId = data.user_id;
                    showRegisterAlert(data.message, 'success');

                    // Aguardar 1 segundo e ir para seleção de plano
                    setTimeout(() => {
                        showRegisterStep(2);
                        loadAvailablePlans();
                    }, 1000);
                } else {
                    showRegisterAlert(data.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="rocket" class="inline w-5 h-5 mr-2"></i>Criar Conta Grátis';

                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            } catch (error) {
                console.error('Erro:', error);
                showRegisterAlert('Erro ao criar conta. Tente novamente.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="rocket" class="inline w-5 h-5 mr-2"></i>Criar Conta Grátis';

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        });
    }

    // Fechar modal com ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeRegisterModal();
        }
    });

    console.log('✅ Sistema de registro carregado com sucesso!');
    console.log('Função openRegisterModal disponível:', typeof window.openRegisterModal === 'function');
});
