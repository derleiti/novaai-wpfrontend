<?php
/**
 * Chat Interface Template
 * 
 * Variables available:
 * $atts - Shortcode attributes
 * $modes - Available modes based on settings
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Generate unique container ID
$container_id = 'nova-chat-' . uniqid();
$theme_class = 'theme-' . esc_attr($atts['theme']);
?>

<div class="nova-ai-chat-container <?php echo $theme_class; ?>" 
     id="<?php echo $container_id; ?>" 
     style="height: <?php echo esc_attr($atts['height']); ?>"
     data-theme="<?php echo esc_attr($atts['theme']); ?>"
     data-enable-history="<?php echo $atts['enable_history'] ? 'true' : 'false'; ?>">
     
    <!-- Header -->
    <div class="nova-ai-header">
        <h3><?php echo esc_html($atts['title']); ?></h3>
        
        <?php if (count($modes) > 1): ?>
        <div class="nova-ai-mode-selector">
            <?php 
            $first_mode = true;
            foreach ($modes as $mode_key => $mode_data): 
            ?>
                <button class="mode-btn <?php echo $first_mode ? 'active' : ''; ?>" 
                        data-mode="<?php echo esc_attr($mode_key); ?>"
                        title="<?php echo esc_attr($mode_data['label']); ?>">
                    <?php echo $mode_data['icon']; ?> <?php echo esc_html($mode_data['label']); ?>
                </button>
            <?php 
                $first_mode = false;
            endforeach; 
            ?>
        </div>
        <?php endif; ?>
        
        <!-- Status Indicator -->
        <div class="status-indicator online" title="<?php _e('Verbindungsstatus', 'nova-ai'); ?>"></div>
    </div>
    
    <!-- Messages Area -->
    <div class="nova-ai-messages" id="nova-messages" role="log" aria-live="polite" aria-label="<?php _e('Chat-Nachrichten', 'nova-ai'); ?>">
        <!-- Messages will be dynamically added here -->
    </div>
    
    <!-- Input Area -->
    <div class="nova-ai-input-area">
        
        <!-- Chat/Image Mode -->
        <?php if (isset($modes['chat']) || isset($modes['image'])): ?>
        <div class="input-mode" id="mode-chat" style="display: flex;">
            <textarea class="nova-ai-input" 
                      id="nova-prompt" 
                      placeholder="<?php echo isset($modes['chat']) ? __('Schreibe eine Nachricht...', 'nova-ai') : __('Beschreibe das gewünschte Bild...', 'nova-ai'); ?>" 
                      rows="1"
                      aria-label="<?php _e('Nachricht eingeben', 'nova-ai'); ?>"
                      maxlength="2000"></textarea>
                      
            <button class="nova-ai-send" 
                    id="nova-send"
                    aria-label="<?php _e('Nachricht senden', 'nova-ai'); ?>"
                    title="<?php _e('Nachricht senden (Enter)', 'nova-ai'); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13"/>
                </svg>
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Vision Mode -->
        <?php if (isset($modes['vision'])): ?>
        <div class="input-mode" id="mode-vision" style="display: none;">
            <div class="file-upload-wrapper">
                <input type="file" 
                       id="nova-vision-file" 
                       accept="image/*" 
                       class="file-input"
                       aria-label="<?php _e('Bild für Analyse auswählen', 'nova-ai'); ?>" />
                <label for="nova-vision-file" class="file-label">
                    📷 <?php _e('Bild auswählen', 'nova-ai'); ?>
                </label>
            </div>
            
            <textarea class="nova-ai-input" 
                      id="nova-vision-prompt" 
                      placeholder="<?php _e('Was möchtest du über das Bild wissen?', 'nova-ai'); ?>" 
                      rows="1"
                      aria-label="<?php _e('Frage zum Bild eingeben', 'nova-ai'); ?>"
                      maxlength="1000"></textarea>
                      
            <button class="nova-ai-send" 
                    id="nova-vision-analyze"
                    aria-label="<?php _e('Bild analysieren', 'nova-ai'); ?>"
                    title="<?php _e('Bild analysieren', 'nova-ai'); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21L16.65 16.65"/>
                </svg>
            </button>
        </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Typing Indicator -->
    <div class="nova-ai-typing" id="nova-typing" style="display: none;" aria-live="polite">
        <span></span><span></span><span></span>
        <span class="sr-only"><?php _e('KI tippt...', 'nova-ai'); ?></span>
    </div>
    
    <!-- Keyboard Shortcuts Help -->
    <div class="keyboard-shortcuts" style="display: none;">
        <div class="shortcuts-content">
            <h4><?php _e('Tastenkürzel', 'nova-ai'); ?></h4>
            <ul>
                <li><kbd>Enter</kbd> - <?php _e('Nachricht senden', 'nova-ai'); ?></li>
                <li><kbd>Shift + Enter</kbd> - <?php _e('Neue Zeile', 'nova-ai'); ?></li>
                <li><kbd>Ctrl + K</kbd> - <?php _e('Chat löschen', 'nova-ai'); ?></li>
                <li><kbd>Esc</kbd> - <?php _e('Benachrichtigungen schließen', 'nova-ai'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Screen Reader Only Text -->
<div class="sr-only" id="nova-sr-announcements" aria-live="polite"></div>

<!-- Custom Styles for this instance (if needed) -->
<style>
#<?php echo $container_id; ?> {
    <?php if ($atts['theme'] === 'light'): ?>
    --nova-bg-primary: #ffffff;
    --nova-bg-secondary: #f8fafc;
    --nova-text-primary: #1e293b;
    --nova-text-secondary: #475569;
    <?php endif; ?>
}

/* Hide mode selector if only one mode */
<?php if (count($modes) <= 1): ?>
#<?php echo $container_id; ?> .nova-ai-mode-selector {
    display: none;
}
<?php endif; ?>

/* Responsive adjustments based on container size */
@container (max-width: 500px) {
    #<?php echo $container_id; ?> .nova-ai-header {
        flex-direction: column;
        gap: 12px;
    }
    
    #<?php echo $container_id; ?> .nova-ai-mode-selector {
        justify-content: center;
    }
}
</style>

<!-- Initialize specific instance -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Instance-specific configuration
    const config = {
        container: '#<?php echo $container_id; ?>',
        enabledModes: <?php echo wp_json_encode(array_keys($modes)); ?>,
        defaultMode: '<?php echo array_key_first($modes); ?>',
        enableHistory: <?php echo $atts['enable_history'] ? 'true' : 'false'; ?>,
        theme: '<?php echo esc_js($atts['theme']); ?>',
        // Add any instance-specific settings here
    };
    
    // Merge with global config
    if (typeof window.novaAI === 'object') {
        Object.assign(config, window.novaAI);
    }
    
    // Initialize this instance
    const chatContainer = document.querySelector('#<?php echo $container_id; ?>');
    if (chatContainer && typeof window.NovaAI === 'function') {
        chatContainer.novaAIInstance = new window.NovaAI(config);
    }
});
</script>

<?php
// Add structured data for better SEO/accessibility
$structured_data = array(
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => $atts['title'],
    'applicationCategory' => 'ChatApplication',
    'operatingSystem' => 'Web Browser',
    'offers' => array(
        '@type' => 'Offer',
        'price' => '0',
        'priceCurrency' => 'EUR'
    )
);
?>
<script type="application/ld+json">
<?php echo wp_json_encode($structured_data); ?>
</script>
