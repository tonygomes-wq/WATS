/**
 * VoIP Settings - JavaScript
 * Configurações gerais do sistema VoIP
 */

class VoIPSettings {
    constructor() {
        this.currentTab = 'general';
        this.audioDevices = [];
        this.videoDevices = [];
        this.init();
    }
    
    async init() {
        console.log('[VoIP Settings] Inicializando...');
        
        // Carregar dispositivos
        await this.loadDevices();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Carregar configurações salvas
        this.loadSettings();
    }
    
    setupEventListeners() {
        // Tabs
        document.querySelectorAll('.voip-tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tab = e.currentTarget.textContent.trim().toLowerCase();
                const tabMap = {
                    'geral': 'general',
                    'áudio': 'audio',
                    'vídeo': 'video',
                    'rede': 'network',
                    'avançado': 'advanced'
                };
                this.switchTab(tabMap[tab] || 'general');
            });
        });
        
        // Form submit
        const form = document.getElementById('voip-settings-form');
        if (form) {
            form.addEventListener('submit', (e) => this.saveSettings(e));
        }
        
        // Conditional fields
        this.setupConditionalFields();
        
        // Codec buttons
        this.setupCodecButtons();
    }
    
    setupConditionalFields() {
        // Video enabled
        const videoEnabled = document.querySelector('[name="video_enabled"]');
        if (videoEnabled) {
            videoEnabled.addEventListener('change', (e) => {
                const videoSettings = document.getElementById('video-settings');
                if (videoSettings) {
                    videoSettings.style.display = e.target.checked ? 'block' : 'none';
                }
            });
        }
        
        // Call recording
        const callRecording = document.querySelector('[name="call_recording"]');
        if (callRecording) {
            callRecording.addEventListener('change', (e) => {
                const recordingSettings = document.getElementById('recording-settings');
                if (recordingSettings) {
                    recordingSettings.style.display = e.target.checked ? 'block' : 'none';
                }
            });
        }
        
        // Auto answer
        const autoAnswer = document.querySelector('[name="auto_answer"]');
        if (autoAnswer) {
            autoAnswer.addEventListener('change', (e) => {
                const delayGroup = document.getElementById('auto-answer-delay-group');
                if (delayGroup) {
                    delayGroup.style.display = e.target.checked ? 'block' : 'none';
                }
            });
        }
        
        // Call forwarding
        const callForwarding = document.getElementById('call_forwarding');
        if (callForwarding) {
            callForwarding.addEventListener('change', (e) => {
                const numberGroup = document.getElementById('forwarding-number-group');
                if (numberGroup) {
                    numberGroup.style.display = e.target.value !== 'disabled' ? 'block' : 'none';
                }
            });
        }
    }
    
    setupCodecButtons() {
        // Botões de ordenação de codecs
        const moveUpBtn = document.querySelector('.voip-codec-buttons button:nth-child(1)');
        const moveDownBtn = document.querySelector('.voip-codec-buttons button:nth-child(2)');
        const addBtn = document.querySelector('.voip-codec-buttons button:nth-child(3)');
        const removeBtn = document.querySelector('.voip-codec-buttons button:nth-child(4)');
        
        if (moveUpBtn) moveUpBtn.addEventListener('click', () => this.moveCodecUp());
        if (moveDownBtn) moveDownBtn.addEventListener('click', () => this.moveCodecDown());
        if (addBtn) addBtn.addEventListener('click', () => this.addCodec());
        if (removeBtn) removeBtn.addEventListener('click', () => this.removeCodec());
    }
    
    switchTab(tabName) {
        // Desativar todas as tabs
        document.querySelectorAll('.voip-tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelectorAll('.voip-tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Ativar tab selecionada
        const tabIndex = ['general', 'audio', 'video', 'network', 'advanced'].indexOf(tabName);
        if (tabIndex >= 0) {
            document.querySelectorAll('.voip-tab-btn')[tabIndex]?.classList.add('active');
        }
        
        const tabContent = document.getElementById('tab-' + tabName);
        if (tabContent) {
            tabContent.classList.add('active');
        }
        
        this.currentTab = tabName;
    }
    
    async loadDevices() {
        try {
            // Solicitar permissões
            await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
            
            // Enumerar dispositivos
            const devices = await navigator.mediaDevices.enumerateDevices();
            
            this.audioDevices = {
                input: devices.filter(d => d.kind === 'audioinput'),
                output: devices.filter(d => d.kind === 'audiooutput')
            };
            
            this.videoDevices = devices.filter(d => d.kind === 'videoinput');
            
            // Preencher selects
            this.populateDeviceSelects();
            
        } catch (error) {
            console.error('[VoIP Settings] Erro ao carregar dispositivos:', error);
            this.showNotification('Erro ao carregar dispositivos de áudio/vídeo', 'warning');
        }
    }
    
    populateDeviceSelects() {
        // Microfone
        this.populateSelect('microphone_device', this.audioDevices.input);
        
        // Alto-falante
        this.populateSelect('speaker_device', this.audioDevices.output);
        this.populateSelect('ring_device', this.audioDevices.output);
        
        // Câmera
        this.populateSelect('camera_device', this.videoDevices);
    }
    
    populateSelect(selectId, devices) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        // Limpar opções existentes (exceto default)
        while (select.options.length > 1) {
            select.remove(1);
        }
        
        // Adicionar dispositivos
        devices.forEach(device => {
            const option = document.createElement('option');
            option.value = device.deviceId;
            option.textContent = device.label || `Device ${device.deviceId.substring(0, 8)}`;
            select.appendChild(option);
        });
    }
    
    // Gerenciamento de codecs
    moveCodecUp() {
        const select = document.getElementById('enabled-codecs');
        if (!select) return;
        
        const selectedIndex = select.selectedIndex;
        
        if (selectedIndex > 0) {
            const option = select.options[selectedIndex];
            select.insertBefore(option, select.options[selectedIndex - 1]);
            select.selectedIndex = selectedIndex - 1;
        }
    }
    
    moveCodecDown() {
        const select = document.getElementById('enabled-codecs');
        if (!select) return;
        
        const selectedIndex = select.selectedIndex;
        
        if (selectedIndex >= 0 && selectedIndex < select.options.length - 1) {
            const option = select.options[selectedIndex];
            select.insertBefore(option, select.options[selectedIndex + 2]);
            select.selectedIndex = selectedIndex + 1;
        }
    }
    
    addCodec() {
        const available = document.getElementById('available-codecs');
        const enabled = document.getElementById('enabled-codecs');
        
        if (!available || !enabled) return;
        
        const selectedOption = available.options[available.selectedIndex];
        
        if (selectedOption) {
            const newOption = selectedOption.cloneNode(true);
            enabled.appendChild(newOption);
            available.removeChild(selectedOption);
        }
    }
    
    removeCodec() {
        const available = document.getElementById('available-codecs');
        const enabled = document.getElementById('enabled-codecs');
        
        if (!available || !enabled) return;
        
        const selectedOption = enabled.options[enabled.selectedIndex];
        
        if (selectedOption) {
            const newOption = selectedOption.cloneNode(true);
            available.appendChild(newOption);
            enabled.removeChild(selectedOption);
        }
    }
    
    loadSettings() {
        // Configurações já vêm do PHP
        console.log('[VoIP Settings] Configurações carregadas');
    }
    
    async saveSettings(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        // Serializar codecs habilitados
        const enabledCodecs = Array.from(
            document.querySelectorAll('#enabled-codecs option')
        ).map(opt => opt.value);
        
        formData.set('enabled_codecs', enabledCodecs.join(','));
        
        // Mostrar loading
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Salvando...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('/api/voip/save_settings.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Configurações salvas com sucesso!', 'success');
            } else {
                this.showNotification('Erro: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('[VoIP Settings] Erro ao salvar:', error);
            this.showNotification('Erro ao salvar configurações', 'error');
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    }
    
    showNotification(message, type = 'info') {
        console.log(`[VoIP Settings] ${type.toUpperCase()}: ${message}`);
        
        // Criar toast notification
        const toast = document.createElement('div');
        toast.className = `voip-toast voip-toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// Funções globais
function switchTab(tabName) {
    window.voipSettings?.switchTab(tabName);
}

function moveCodecUp() {
    window.voipSettings?.moveCodecUp();
}

function moveCodecDown() {
    window.voipSettings?.moveCodecDown();
}

function addCodec() {
    window.voipSettings?.addCodec();
}

function removeCodec() {
    window.voipSettings?.removeCodec();
}

function browseFolder() {
    alert('Seletor de pasta a ser implementado');
}

function closeDialog() {
    if (confirm('Descartar alterações?')) {
        window.location.href = '/voip_dialer.php';
    }
}

// Inicializar
let voipSettings;
document.addEventListener('DOMContentLoaded', () => {
    voipSettings = new VoIPSettings();
    window.voipSettings = voipSettings;
});

// Adicionar animações CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
