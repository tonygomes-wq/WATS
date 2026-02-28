/**
 * Modal Animation and Interaction System
 * Handles modal entry/exit animations, focus trap, and keyboard navigation
 * Based on: .kiro/specs/modal-redesign/design.md
 */

(function() {
    'use strict';

    /**
     * Modal Manager Class
     * Handles all modal-related functionality including animations, focus trap, and keyboard navigation
     */
    class ModalManager {
        constructor() {
            this.activeModal = null;
            this.previousFocus = null;
            this.focusableElements = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
            this.isAnimating = false;
            
            // Bind methods
            this.handleKeyDown = this.handleKeyDown.bind(this);
            this.handleBackdropClick = this.handleBackdropClick.bind(this);
        }

        /**
         * Open a modal with animation
         * @param {string} modalId - The ID of the modal to open
         * @param {Object} options - Configuration options
         */
        openModal(modalId, options = {}) {
            console.log('[Modal Manager] openModal called for:', modalId);
            
            // Removido: if (this.isAnimating) return;
            // Permitir abertura mesmo durante animações
            
            const modal = document.getElementById(modalId);
            if (!modal) {
                console.error(`[Modal Manager] Modal with ID "${modalId}" not found`);
                return;
            }

            console.log('[Modal Manager] Modal found:', modal);
            console.log('[Modal Manager] Modal aria-hidden before:', modal.getAttribute('aria-hidden'));
            console.log('[Modal Manager] Modal classes before:', modal.className);

            // Store previous focus
            this.previousFocus = document.activeElement;
            
            // Set active modal
            this.activeModal = modal;
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
            
            // Show modal
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            
            console.log('[Modal Manager] Modal aria-hidden after:', modal.getAttribute('aria-hidden'));
            console.log('[Modal Manager] Modal classes after:', modal.className);
            console.log('[Modal Manager] Modal display:', window.getComputedStyle(modal).display);
            
            // Get backdrop and container
            const backdrop = modal.querySelector('.modal-backdrop') || modal;
            const container = modal.querySelector('.modal-container');
            
            console.log('[Modal Manager] Backdrop:', backdrop);
            console.log('[Modal Manager] Container:', container);
            
            // Apply entry animations
            this.isAnimating = true;
            
            if (backdrop) {
                backdrop.classList.add('backdrop-enter');
                console.log('[Modal Manager] Added backdrop-enter class');
            }
            
            if (container) {
                container.classList.add('modal-enter');
                container.classList.remove('modal-container--hidden');
                console.log('[Modal Manager] Added modal-enter class, removed modal-container--hidden');
            }
            
            // Setup focus trap
            setTimeout(() => {
                this.setupFocusTrap(modal);
                this.focusFirstElement(modal);
                this.isAnimating = false;
                console.log('[Modal Manager] Focus trap setup complete');
            }, 50);
            
            // Add event listeners
            document.addEventListener('keydown', this.handleKeyDown);
            
            console.log('[Modal Manager] openModal complete');
            
            // Callback
            if (options.onOpen && typeof options.onOpen === 'function') {
                options.onOpen(modal);
            }
        }

        /**
         * Close a modal with animation
         * @param {string} modalId - The ID of the modal to close
         * @param {Object} options - Configuration options
         */
        closeModal(modalId, options = {}) {
            // Removido: if (this.isAnimating) return;
            // Permitir fechamento mesmo durante animações
            
            const modal = document.getElementById(modalId);
            if (!modal) return;
            
            const backdrop = modal.querySelector('.modal-backdrop') || modal;
            const container = modal.querySelector('.modal-container');
            
            // Apply exit animations
            this.isAnimating = true;
            
            if (backdrop) {
                backdrop.classList.remove('backdrop-enter');
                backdrop.classList.add('backdrop-exit');
            }
            
            if (container) {
                container.classList.remove('modal-enter');
                container.classList.add('modal-exit');
            }
            
            // Wait for animation to complete
            setTimeout(() => {
                // Hide modal
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                
                // Remove animation classes
                if (backdrop) {
                    backdrop.classList.remove('backdrop-exit');
                }
                
                if (container) {
                    container.classList.remove('modal-exit');
                }
                
                // Restore body scroll
                document.body.style.overflow = '';
                
                // Restore focus
                if (this.previousFocus) {
                    this.previousFocus.focus();
                    this.previousFocus = null;
                }
                
                // Clear active modal
                this.activeModal = null;
                
                // Remove event listeners
                document.removeEventListener('keydown', this.handleKeyDown);
                
                this.isAnimating = false;
                
                // Callback
                if (options.onClose && typeof options.onClose === 'function') {
                    options.onClose(modal);
                }
            }, 200); // Match modal exit animation duration
        }

        /**
         * Setup focus trap within modal
         * @param {HTMLElement} modal - The modal element
         */
        setupFocusTrap(modal) {
            const focusableContent = modal.querySelectorAll(this.focusableElements);
            const firstFocusable = focusableContent[0];
            const lastFocusable = focusableContent[focusableContent.length - 1];
            
            // Store for tab cycling
            modal._firstFocusable = firstFocusable;
            modal._lastFocusable = lastFocusable;
        }

        /**
         * Focus first focusable element in modal
         * @param {HTMLElement} modal - The modal element
         */
        focusFirstElement(modal) {
            const focusableContent = modal.querySelectorAll(this.focusableElements);
            if (focusableContent.length > 0) {
                focusableContent[0].focus();
            }
        }

        /**
         * Handle keyboard events
         * @param {KeyboardEvent} event - The keyboard event
         */
        handleKeyDown(event) {
            if (!this.activeModal) return;
            
            // Escape key - close modal
            if (event.key === 'Escape' || event.keyCode === 27) {
                event.preventDefault();
                const modalId = this.activeModal.id;
                this.closeModal(modalId);
                return;
            }
            
            // Tab key - cycle focus
            if (event.key === 'Tab' || event.keyCode === 9) {
                this.handleTabKey(event);
            }
        }

        /**
         * Handle Tab key for focus cycling
         * @param {KeyboardEvent} event - The keyboard event
         */
        handleTabKey(event) {
            const modal = this.activeModal;
            if (!modal) return;
            
            const focusableContent = modal.querySelectorAll(this.focusableElements);
            const firstFocusable = focusableContent[0];
            const lastFocusable = focusableContent[focusableContent.length - 1];
            
            // Shift + Tab
            if (event.shiftKey) {
                if (document.activeElement === firstFocusable) {
                    event.preventDefault();
                    lastFocusable.focus();
                }
            }
            // Tab
            else {
                if (document.activeElement === lastFocusable) {
                    event.preventDefault();
                    firstFocusable.focus();
                }
            }
        }

        /**
         * Handle backdrop click
         * @param {Event} event - The click event
         */
        handleBackdropClick(event) {
            if (event.target.classList.contains('modal-backdrop')) {
                const modal = event.target.closest('[role="dialog"]');
                if (modal) {
                    this.closeModal(modal.id);
                }
            }
        }

        /**
         * Transition between modal views (e.g., login to 2FA)
         * @param {HTMLElement} fromElement - Element to hide
         * @param {HTMLElement} toElement - Element to show
         * @param {string} direction - 'left' or 'right'
         */
        transitionView(fromElement, toElement, direction = 'left') {
            if (!fromElement || !toElement) return;
            
            // Hide from element with animation
            fromElement.classList.add(direction === 'left' ? 'slide-left' : 'slide-up');
            
            setTimeout(() => {
                fromElement.classList.add('hidden');
                fromElement.classList.remove('slide-left', 'slide-up');
                
                // Show to element with animation
                toElement.classList.remove('hidden');
                toElement.classList.add(direction === 'left' ? 'slide-right' : 'slide-down');
                
                // Focus first element in new view
                const firstInput = toElement.querySelector('input, button');
                if (firstInput) {
                    firstInput.focus();
                }
                
                // Remove animation class after completion
                setTimeout(() => {
                    toElement.classList.remove('slide-right', 'slide-down');
                }, 250);
            }, 200);
        }

        /**
         * Show error message with animation
         * @param {HTMLElement} errorElement - The error message element
         * @param {string} message - The error message
         */
        showError(errorElement, message) {
            if (!errorElement) return;
            
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
            errorElement.classList.add('error-appear');
            
            // Announce to screen readers
            errorElement.setAttribute('role', 'alert');
            errorElement.setAttribute('aria-live', 'polite');
            
            // Remove animation class after completion
            setTimeout(() => {
                errorElement.classList.remove('error-appear');
            }, 200);
        }

        /**
         * Hide error message
         * @param {HTMLElement} errorElement - The error message element
         */
        hideError(errorElement) {
            if (!errorElement) return;
            
            errorElement.classList.add('hidden');
            errorElement.textContent = '';
        }

        /**
         * Initialize all modals on the page
         */
        initializeModals() {
            console.log('[Modal Manager] Initializing modals...');
            
            // Find all modal elements
            const modals = document.querySelectorAll('[role="dialog"]');
            
            console.log('[Modal Manager] Found', modals.length, 'modals');
            
            modals.forEach(modal => {
                console.log('[Modal Manager] Initializing modal:', modal.id);
                
                // Setup backdrop click handler
                const backdrop = modal.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.addEventListener('click', this.handleBackdropClick);
                }
                
                // Setup close button
                const closeButton = modal.querySelector('.modal-container__close, [data-modal-close]');
                if (closeButton) {
                    closeButton.addEventListener('click', () => {
                        this.closeModal(modal.id);
                    });
                }
                
                // Set initial ARIA attributes
                modal.setAttribute('aria-hidden', 'true');
                modal.setAttribute('aria-modal', 'true');
                
                console.log('[Modal Manager] Modal', modal.id, 'initialized with aria-hidden=true');
            });
            
            console.log('[Modal Manager] Initialization complete');
        }
    }

    // Create global instance
    window.modalManager = new ModalManager();

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.modalManager.initializeModals();
        });
    } else {
        window.modalManager.initializeModals();
    }

    // Expose helper functions for backward compatibility
    window.openModalWithAnimation = function(modalId, options) {
        window.modalManager.openModal(modalId, options);
    };

    window.closeModalWithAnimation = function(modalId, options) {
        window.modalManager.closeModal(modalId, options);
    };

    window.transitionModalView = function(fromElement, toElement, direction) {
        window.modalManager.transitionView(fromElement, toElement, direction);
    };

})();

/**
 * Loading Indicator Helper Functions
 * Utilities for showing and hiding loading indicators
 */

/**
 * Show inline loading spinner
 * @param {HTMLElement} element - The element to show spinner in (e.g., button)
 * @param {string} text - Optional loading text
 */
function showInlineSpinner(element, text = '') {
    if (!element) return;
    
    // Check if spinner already exists
    let spinner = element.querySelector('.loading-spinner--inline');
    
    if (!spinner) {
        // Create spinner
        spinner = document.createElement('span');
        spinner.className = 'loading-spinner loading-spinner--inline';
        spinner.setAttribute('role', 'status');
        
        const icon = document.createElement('span');
        icon.className = 'loading-spinner__icon';
        icon.setAttribute('aria-label', 'Loading');
        spinner.appendChild(icon);
        
        if (text) {
            const textSpan = document.createElement('span');
            textSpan.className = 'loading-spinner__text';
            textSpan.textContent = text;
            spinner.appendChild(textSpan);
        }
        
        // Insert at beginning of element
        element.insertBefore(spinner, element.firstChild);
    }
    
    // Set aria-busy
    element.setAttribute('aria-busy', 'true');
    
    // Disable if it's a button
    if (element.tagName === 'BUTTON') {
        element.disabled = true;
        element.classList.add('btn--loading');
    }
}

/**
 * Hide inline loading spinner
 * @param {HTMLElement} element - The element to hide spinner from
 */
function hideInlineSpinner(element) {
    if (!element) return;
    
    const spinner = element.querySelector('.loading-spinner--inline');
    if (spinner) {
        spinner.remove();
    }
    
    // Remove aria-busy
    element.removeAttribute('aria-busy');
    
    // Re-enable if it's a button
    if (element.tagName === 'BUTTON') {
        element.disabled = false;
        element.classList.remove('btn--loading');
    }
}

/**
 * Show full-screen loading overlay
 * @param {HTMLElement} container - The container to show overlay in (e.g., modal-container)
 * @param {string} text - Loading message text
 * @returns {HTMLElement} The overlay element
 */
function showLoadingOverlay(container, text = 'Processing...') {
    if (!container) return null;
    
    // Check if overlay already exists
    let overlay = container.querySelector('.loading-spinner--overlay');
    
    if (!overlay) {
        // Create overlay
        overlay = document.createElement('div');
        overlay.className = 'loading-spinner loading-spinner--overlay loading-spinner--entering';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        
        const content = document.createElement('div');
        content.className = 'loading-spinner__content';
        
        const icon = document.createElement('span');
        icon.className = 'loading-spinner__icon';
        icon.setAttribute('aria-label', 'Loading');
        content.appendChild(icon);
        
        const textSpan = document.createElement('span');
        textSpan.className = 'loading-spinner__text';
        textSpan.textContent = text;
        content.appendChild(textSpan);
        
        overlay.appendChild(content);
        container.appendChild(overlay);
        
        // Set aria-busy on container
        container.setAttribute('aria-busy', 'true');
        
        // Remove entering class after animation
        setTimeout(() => {
            overlay.classList.remove('loading-spinner--entering');
        }, 200);
    } else {
        // Update text if overlay exists
        const textSpan = overlay.querySelector('.loading-spinner__text');
        if (textSpan) {
            textSpan.textContent = text;
        }
    }
    
    return overlay;
}

/**
 * Hide full-screen loading overlay
 * @param {HTMLElement} container - The container with the overlay
 * @param {Function} callback - Optional callback after overlay is hidden
 */
function hideLoadingOverlay(container, callback) {
    if (!container) return;
    
    const overlay = container.querySelector('.loading-spinner--overlay');
    if (!overlay) return;
    
    // Add exiting animation
    overlay.classList.add('loading-spinner--exiting');
    
    // Remove after animation
    setTimeout(() => {
        overlay.remove();
        container.removeAttribute('aria-busy');
        
        if (callback && typeof callback === 'function') {
            callback();
        }
    }, 200);
}

/**
 * Show loading state on form input
 * @param {HTMLElement} formGroup - The form-group element
 */
function showInputLoading(formGroup) {
    if (!formGroup) return;
    
    formGroup.classList.add('form-group--loading');
    
    // Check if spinner already exists
    let spinner = formGroup.querySelector('.loading-spinner--inline');
    
    if (!spinner) {
        spinner = document.createElement('span');
        spinner.className = 'loading-spinner loading-spinner--inline';
        
        const icon = document.createElement('span');
        icon.className = 'loading-spinner__icon';
        icon.setAttribute('aria-label', 'Validating');
        spinner.appendChild(icon);
        
        formGroup.appendChild(spinner);
    }
    
    // Set aria-busy on input
    const input = formGroup.querySelector('input, select, textarea');
    if (input) {
        input.setAttribute('aria-busy', 'true');
    }
}

/**
 * Hide loading state on form input
 * @param {HTMLElement} formGroup - The form-group element
 */
function hideInputLoading(formGroup) {
    if (!formGroup) return;
    
    formGroup.classList.remove('form-group--loading');
    
    const spinner = formGroup.querySelector('.loading-spinner--inline');
    if (spinner) {
        spinner.remove();
    }
    
    // Remove aria-busy from input
    const input = formGroup.querySelector('input, select, textarea');
    if (input) {
        input.removeAttribute('aria-busy');
    }
}

/**
 * Update loading overlay text
 * @param {HTMLElement} container - The container with the overlay
 * @param {string} text - New loading message
 */
function updateLoadingText(container, text) {
    if (!container) return;
    
    const overlay = container.querySelector('.loading-spinner--overlay');
    if (!overlay) return;
    
    const textSpan = overlay.querySelector('.loading-spinner__text');
    if (textSpan) {
        textSpan.textContent = text;
    }
}

// Expose functions globally
window.showInlineSpinner = showInlineSpinner;
window.hideInlineSpinner = hideInlineSpinner;
window.showLoadingOverlay = showLoadingOverlay;
window.hideLoadingOverlay = hideLoadingOverlay;
window.showInputLoading = showInputLoading;
window.hideInputLoading = hideInputLoading;
window.updateLoadingText = updateLoadingText;

