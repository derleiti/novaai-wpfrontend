<?php
/**
 * Chat Button Template
 * 
 * Variables available:
 * $atts - Shortcode attributes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Generate unique button ID
$button_id = 'nova-chat-btn-' . uniqid();
$modal_id = 'nova-chat-modal-' . uniqid();
?>

<!-- Floating Chat Button -->
<button class="nova-chat-button <?php echo esc_attr($atts['position']); ?> <?php echo esc_attr($atts['color']); ?>" 
        id="<?php echo $button_id; ?>"
        aria-label="<?php _e('AI Chat öffnen', 'nova-ai'); ?>"
        title="<?php echo esc_attr($atts['text']); ?>">
    <span class="button-icon">🤖</span>
    <span class="button-text"><?php echo esc_html($atts['text']); ?></span>
</button>

<!-- Chat Modal -->
<div class="nova-chat-modal" id="<?php echo $modal_id; ?>" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="<?php echo $modal_id; ?>-title">
    <div class="modal-backdrop" aria-hidden="true"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="<?php echo $modal_id; ?>-title"><?php _e('Nova AI Assistant', 'nova-ai'); ?></h3>
            <button class="modal-close" aria-label="<?php _e('Chat schließen', 'nova-ai'); ?>" title="<?php _e('Schließen (ESC)', 'nova-ai'); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        
        <div class="modal-body">
            <?php
            // Embed chat interface
            $chat_atts = array(
                'height' => '500px',
                'title' => __('Nova AI Assistant', 'nova-ai'),
                'theme' => 'dark',
                'show_modes' => 'all',
                'enable_history' => true
            );
            
            // Get enabled modes
            $modes = array();
            if (get_option('nova_ai_enable_chat', true)) {
                $modes['chat'] = array(
                    'enabled' => true,
                    'icon' => '💬',
                    'label' => __('Chat', 'nova-ai')
                );
            }
            if (get_option('nova_ai_enable_image_gen', true)) {
                $modes['image'] = array(
                    'enabled' => true,
                    'icon' => '🎨',
                    'label' => __('Bild erstellen', 'nova-ai')
                );
            }
            if (get_option('nova_ai_enable_vision', true)) {
                $modes['vision'] = array(
                    'enabled' => true,
                    'icon' => '🔍',
                    'label' => __('Bild analysieren', 'nova-ai')
                );
            }
            
            include NOVA_AI_PLUGIN_DIR . 'templates/chat-interface.php';
            ?>
        </div>
    </div>
</div>

<style>
/* Chat Button Styles */
.nova-chat-button {
    position: fixed;
    z-index: 9998;
    background: linear-gradient(135deg, var(--nova-primary, #6366f1), var(--nova-secondary, #8b5cf6));
    color: white;
    border: none;
    padding: 16px 20px;
    border-radius: 50px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 60px;
    max-width: 200px;
    overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.nova-chat-button:hover {
    transform: translateY(-4px) scale(1.05);
    box-shadow: 0 12px 40px rgba(99, 102, 241, 0.4);
}

.nova-chat-button:active {
    transform: translateY(-2px) scale(1.02);
}

.nova-chat-button.bottom-right {
    bottom: 24px;
    right: 24px;
}

.nova-chat-button.bottom-left {
    bottom: 24px;
    left: 24px;
}

.nova-chat-button.top-right {
    top: 24px;
    right: 24px;
}

.nova-chat-button.top-left {
    top: 24px;
    left: 24px;
}

.nova-chat-button .button-icon {
    font-size: 24px;
    line-height: 1;
    flex-shrink: 0;
}

.nova-chat-button .button-text {
    white-space: nowrap;
    opacity: 0;
    max-width: 0;
    transition: all 0.3s ease;
    overflow: hidden;
}

.nova-chat-button:hover .button-text {
    opacity: 1;
    max-width: 120px;
    margin-left: 4px;
}

/* Color Variants */
.nova-chat-button.primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.nova-chat-button.success {
    background: linear-gradient(135deg, #10b981, #059669);
}

.nova-chat-button.warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.nova-chat-button.danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

/* Chat Modal Styles */
.nova-chat-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.nova-chat-modal .modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    animation: fadeIn 0.3s ease;
}

.nova-chat-modal .modal-content {
    position: relative;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 800px;
    max-height: 90vh;
    width: 100%;
    overflow: hidden;
    animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.nova-chat-modal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.nova-chat-modal .modal-header h3 {
    margin: 0;
    font-size: 20px;
    color: #1f2937;
}

.nova-chat-modal .modal-close {
    background: none;
    border: none;
    padding: 8px;
    border-radius: 8px;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.2s ease;
}

.nova-chat-modal .modal-close:hover {
    background: #e5e7eb;
    color: #374151;
}

.nova-chat-modal .modal-body {
    padding: 0;
    height: 600px;
    overflow: hidden;
}

.nova-chat-modal .nova-ai-chat-container {
    height: 100%;
    border-radius: 0;
    box-shadow: none;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(60px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .nova-chat-modal {
        padding: 0;
    }
    
    .nova-chat-modal .modal-content {
        max-width: 100%;
        max-height: 100%;
        border-radius: 0;
        height: 100vh;
    }
    
    .nova-chat-modal .modal-body {
        height: calc(100vh - 80px);
    }
    
    .nova-chat-button {
        padding: 12px 16px;
        font-size: 14px;
    }
    
    .nova-chat-button .button-icon {
        font-size: 20px;
    }
    
    .nova-chat-button.bottom-right,
    .nova-chat-button.bottom-left {
        bottom: 16px;
    }
    
    .nova-chat-button.bottom-right {
        right: 16px;
    }
    
    .nova-chat-button.bottom-left {
        left: 16px;
    }
}

/* Accessibility */
@media (prefers-reduced-motion: reduce) {
    .nova-chat-button,
    .nova-chat-modal .modal-backdrop,
    .nova-chat-modal .modal-content {
        animation: none;
        transition: none;
    }
}

/* Focus styles */
.nova-chat-button:focus,
.nova-chat-modal .modal-close:focus {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const button = document.getElementById('<?php echo $button_id; ?>');
    const modal = document.getElementById('<?php echo $modal_id; ?>');
    const backdrop = modal.querySelector('.modal-backdrop');
    const closeBtn = modal.querySelector('.modal-close');
    
    // Open modal
    button.addEventListener('click', function() {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Focus management
        const firstInput = modal.querySelector('.nova-ai-input');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 300);
        }
        
        // Announce to screen readers
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.textContent = '<?php _e('Chat geöffnet', 'nova-ai'); ?>';
        document.body.appendChild(announcement);
        setTimeout(() => document.body.removeChild(announcement), 1000);
    });
    
    // Close modal
    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        button.focus(); // Return focus to button
    }
    
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    
    // Keyboard handling
    document.addEventListener('keydown', function(e) {
        if (modal.style.display === 'flex') {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeModal();
            }
            
            // Trap focus within modal
            if (e.key === 'Tab') {
                const focusableElements = modal.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];
                
                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        lastElement.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        firstElement.focus();
                        e.preventDefault();
                    }
                }
            }
        }
    });
    
    // Pulse animation on page load
    setTimeout(() => {
        button.style.animation = 'pulse 2s infinite';
    }, 2000);
    
    // Remove pulse on first interaction
    button.addEventListener('click', function() {
        button.style.animation = '';
    }, { once: true });
});

// Add pulse animation
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { box-shadow: 0 8px 32px rgba(99, 102, 241, 0.3); }
        50% { box-shadow: 0 8px 32px rgba(99, 102, 241, 0.6), 0 0 0 8px rgba(99, 102, 241, 0.1); }
    }
`;
document.head.appendChild(style);
</script>
