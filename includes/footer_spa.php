            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script>
        // Fechar menu ao clicar fora
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const userButton = event.target.closest('button[onclick="toggleUserMenu()"]');
            
            if (userMenu && !userButton && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });
        
        // Marcar item ativo na sidebar e iniciar toggle de tema
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            
            // Primeiro, marcar links diretos (não dentro de submenus)
            const directLinks = document.querySelectorAll('a.sidebar-item');
            directLinks.forEach(link => {
                if (link.getAttribute('href') === currentPath) {
                    link.classList.add('active');
                }
            });
            
            // Depois, marcar links dentro de submenus e abrir o submenu pai
            const submenuLinks = document.querySelectorAll('.sidebar-submenu a');
            submenuLinks.forEach(link => {
                if (link.getAttribute('href') === currentPath) {
                    // Adicionar classe active ao link
                    link.classList.add('text-green-600', 'font-semibold');
                    
                    // Encontrar e abrir o submenu pai
                    const submenu = link.closest('.sidebar-submenu');
                    if (submenu) {
                        submenu.classList.add('open');
                        
                        // Rotacionar a seta
                        const menuId = submenu.id.replace('-menu', '');
                        const arrow = document.getElementById(menuId + '-arrow');
                        if (arrow) {
                            arrow.style.transform = 'rotate(180deg)';
                        }
                    }
                }
            });
            
            const themeToggles = document.querySelectorAll('[data-theme-toggle]');
            const root = document.documentElement;
            
            const updateToggleLabel = (theme) => {
                themeToggles.forEach((toggle) => {
                    const icon = toggle.querySelector('i');
                    const label = toggle.querySelector('span');
                    if (theme === 'dark') {
                        if (icon) icon.className = 'fas fa-sun';
                        if (label) label.textContent = 'Modo Claro';
                    } else {
                        if (icon) icon.className = 'fas fa-moon';
                        if (label) label.textContent = 'Modo Escuro';
                    }
                });
            };
            
            if (themeToggles.length) {
                const currentTheme = root.getAttribute('data-theme') || 'light';
                updateToggleLabel(currentTheme);
                
                themeToggles.forEach((toggle) => {
                    toggle.addEventListener('click', () => {
                        const newTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                        root.setAttribute('data-theme', newTheme);
                        localStorage.setItem('watsTheme', newTheme);
                        
                        // Atualizar classe dark para compatibilidade com Tailwind
                        if (newTheme === 'dark') {
                            root.classList.add('dark');
                        } else {
                            root.classList.remove('dark');
                        }
                        
                        updateToggleLabel(newTheme);
                    });
                });
            }
        });
    </script>
    
    <?php
    // Mostrar mensagens de sucesso/erro
    $success = getSuccess();
    $error = getError();
    
    if ($success || $error):
    ?>
    <script>
        <?php if ($success): ?>
        // Mostrar mensagem de sucesso
        const successDiv = document.createElement('div');
        successDiv.className = 'fixed top-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-lg z-50';
        successDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <p><?php echo addslashes($success); ?></p>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-green-700 hover:text-green-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.body.appendChild(successDiv);
        setTimeout(() => successDiv.remove(), 5000);
        <?php endif; ?>
        
        <?php if ($error): ?>
        // Mostrar mensagem de erro
        const errorDiv = document.createElement('div');
        errorDiv.className = 'fixed top-4 right-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-lg z-50';
        errorDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <p><?php echo addslashes($error); ?></p>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-red-700 hover:text-red-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.body.appendChild(errorDiv);
        setTimeout(() => errorDiv.remove(), 5000);
        <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>
