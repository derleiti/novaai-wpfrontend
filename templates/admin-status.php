<?php
/**
 * Admin Dashboard Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get plugin statistics
global $wpdb;
$stats = array(
    'total_conversations' => 0,
    'total_messages' => 0,
    'active_users' => 0,
    'last_activity' => null
);

// Check if conversation table exists
$table_name = $wpdb->prefix . 'nova_ai_conversations';
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
    $stats['total_conversations'] = $wpdb->get_var("SELECT COUNT(DISTINCT conversation_id) FROM $table_name");
    $stats['total_messages'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $stats['active_users'] = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_name WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['last_activity'] = $wpdb->get_var("SELECT MAX(created_at) FROM $table_name");
}

// Get system status
$system_status = array(
    'php_version' => PHP_VERSION,
    'wp_version' => get_bloginfo('version'),
    'plugin_version' => NOVA_AI_VERSION,
    'upload_dir_writable' => is_writable(NOVA_AI_UPLOAD_DIR),
    'curl_available' => function_exists('curl_init'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time')
);

// Get enabled features
$enabled_features = array(
    'chat' => get_option('nova_ai_enable_chat', true),
    'vision' => get_option('nova_ai_enable_vision', true),
    'image_gen' => get_option('nova_ai_enable_image_gen', true)
);

// Recent activity (if table exists)
$recent_activity = array();
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
    $recent_activity = $wpdb->get_results(
        "SELECT u.display_name, c.message_type, c.created_at 
         FROM $table_name c 
         LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
         ORDER BY c.created_at DESC 
         LIMIT 10"
    );
}
?>

<div class="wrap nova-ai-dashboard">
    <h1><?php _e('Nova AI Dashboard', 'nova-ai'); ?></h1>
    <p class="description"><?php _e('Übersicht über die Nova AI Integration und Systemstatus', 'nova-ai'); ?></p>
    
    <!-- Quick Stats -->
    <div class="nova-stats-grid">
        <div class="stat-card">
            <div class="stat-icon">💬</div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['total_conversations']); ?></h3>
                <p><?php _e('Unterhaltungen', 'nova-ai'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['total_messages']); ?></h3>
                <p><?php _e('Nachrichten', 'nova-ai'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['active_users']); ?></h3>
                <p><?php _e('Aktive Nutzer (30 Tage)', 'nova-ai'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">⏰</div>
            <div class="stat-content">
                <h3><?php echo $stats['last_activity'] ? human_time_diff(strtotime($stats['last_activity'])) : __('Nie', 'nova-ai'); ?></h3>
                <p><?php _e('Letzte Aktivität', 'nova-ai'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Main Content Grid -->
    <div class="nova-dashboard-grid">
        
        <!-- System Status -->
        <div class="dashboard-card">
            <h2><?php _e('System-Status', 'nova-ai'); ?></h2>
            
            <div class="status-list">
                <div class="status-item <?php echo version_compare($system_status['php_version'], '7.4', '>=') ? 'status-good' : 'status-warning'; ?>">
                    <span class="status-label"><?php _e('PHP Version:', 'nova-ai'); ?></span>
                    <span class="status-value"><?php echo $system_status['php_version']; ?></span>
                    <?php if (version_compare($system_status['php_version'], '7.4', '>=')): ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning"></span>
                    <?php endif; ?>
                </div>
                
                <div class="status-item status-good">
                    <span class="status-label"><?php _e('WordPress Version:', 'nova-ai'); ?></span>
                    <span class="status-value"><?php echo $system_status['wp_version']; ?></span>
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                
                <div class="status-item <?php echo $system_status['upload_dir_writable'] ? 'status-good' : 'status-error'; ?>">
                    <span class="status-label"><?php _e('Upload-Verzeichnis:', 'nova-ai'); ?></span>
                    <span class="status-value"><?php echo $system_status['upload_dir_writable'] ? __('Beschreibbar', 'nova-ai') : __('Nicht beschreibbar', 'nova-ai'); ?></span>
                    <?php if ($system_status['upload_dir_writable']): ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss"></span>
                    <?php endif; ?>
                </div>
                
                <div class="status-item <?php echo $system_status['curl_available'] ? 'status-good' : 'status-error'; ?>">
                    <span class="status-label"><?php _e('cURL:', 'nova-ai'); ?></span>
                    <span class="status-value"><?php echo $system_status['curl_available'] ? __('Verfügbar', 'nova-ai') : __('Nicht verfügbar', 'nova-ai'); ?></span>
                    <?php if ($system_status['curl_available']): ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss"></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-actions">
                <a href="<?php echo admin_url('admin.php?page=nova-ai-status'); ?>" class="button button-secondary">
                    <?php _e('Detaillierter Status', 'nova-ai'); ?>
                </a>
            </div>
        </div>
        
        <!-- Active Features -->
        <div class="dashboard-card">
            <h2><?php _e('Aktive Features', 'nova-ai'); ?></h2>
            
            <div class="feature-list">
                <div class="feature-item <?php echo $enabled_features['chat'] ? 'feature-enabled' : 'feature-disabled'; ?>">
                    <span class="feature-icon">💬</span>
                    <span class="feature-name"><?php _e('Text-Chat', 'nova-ai'); ?></span>
                    <span class="feature-status">
                        <?php if ($enabled_features['chat']): ?>
                            <span class="status-badge status-enabled"><?php _e('Aktiv', 'nova-ai'); ?></span>
                        <?php else: ?>
                            <span class="status-badge status-disabled"><?php _e('Deaktiviert', 'nova-ai'); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="feature-item <?php echo $enabled_features['vision'] ? 'feature-enabled' : 'feature-disabled'; ?>">
                    <span class="feature-icon">🔍</span>
                    <span class="feature-name"><?php _e('Bildanalyse', 'nova-ai'); ?></span>
                    <span class="feature-status">
                        <?php if ($enabled_features['vision']): ?>
                            <span class="status-badge status-enabled"><?php _e('Aktiv', 'nova-ai'); ?></span>
                        <?php else: ?>
                            <span class="status-badge status-disabled"><?php _e('Deaktiviert', 'nova-ai'); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="feature-item <?php echo $enabled_features['image_gen'] ? 'feature-enabled' : 'feature-disabled'; ?>">
                    <span class="feature-icon">🎨</span>
                    <span class="feature-name"><?php _e('Bildgenerierung', 'nova-ai'); ?></span>
                    <span class="feature-status">
                        <?php if ($enabled_features['image_gen']): ?>
                            <span class="status-badge status-enabled"><?php _e('Aktiv', 'nova-ai'); ?></span>
                        <?php else: ?>
                            <span class="status-badge status-disabled"><?php _e('Deaktiviert', 'nova-ai'); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <div class="card-actions">
                <a href="<?php echo admin_url('admin.php?page=nova-ai-settings#features'); ?>" class="button button-primary">
                    <?php _e('Features verwalten', 'nova-ai'); ?>
                </a>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="dashboard-card">
            <h2><?php _e('Schnellaktionen', 'nova-ai'); ?></h2>
            
            <div class="quick-actions">
                <a href="#" id="test-backend-connection" class="action-button">
                    <span class="action-icon">🔗</span>
                    <span class="action-text"><?php _e('Backend testen', 'nova-ai'); ?></span>
                </a>
                
                <a href="#" id="clear-temp-files" class="action-button">
                    <span class="action-icon">🗑️</span>
                    <span class="action-text"><?php _e('Temp-Dateien löschen', 'nova-ai'); ?></span>
                </a>
                
                <a href="#" id="export-settings" class="action-button">
                    <span class="action-icon">📦</span>
                    <span class="action-text"><?php _e('Einstellungen exportieren', 'nova-ai'); ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=nova-ai-settings'); ?>" class="action-button">
                    <span class="action-icon">⚙️</span>
                    <span class="action-text"><?php _e('Einstellungen', 'nova-ai'); ?></span>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="dashboard-card">
            <h2><?php _e('Kürzliche Aktivitäten', 'nova-ai'); ?></h2>
            
            <?php if (!empty($recent_activity)): ?>
            <div class="activity-list">
                <?php foreach ($recent_activity as $activity): ?>
                <div class="activity-item">
                    <span class="activity-icon">
                        <?php 
                        switch ($activity->message_type) {
                            case 'user': echo '👤'; break;
                            case 'assistant': echo '🤖'; break;
                            case 'image': echo '🎨'; break;
                            case 'vision': echo '🔍'; break;
                            default: echo '💬'; break;
                        }
                        ?>
                    </span>
                    <div class="activity-content">
                        <span class="activity-user">
                            <?php echo esc_html($activity->display_name ?: __('Gast', 'nova-ai')); ?>
                        </span>
                        <span class="activity-action">
                            <?php 
                            switch ($activity->message_type) {
                                case 'user':
                                    _e('hat eine Nachricht gesendet', 'nova-ai');
                                    break;
                                case 'assistant':
                                    _e('hat eine Antwort erhalten', 'nova-ai');
                                    break;
                                case 'image':
                                    _e('hat ein Bild generiert', 'nova-ai');
                                    break;
                                case 'vision':
                                    _e('hat ein Bild analysiert', 'nova-ai');
                                    break;
                                default:
                                    _e('hatte eine Interaktion', 'nova-ai');
                                    break;
                            }
                            ?>
                        </span>
                        <span class="activity-time">
                            <?php echo human_time_diff(strtotime($activity->created_at)) . ' ' . __('her', 'nova-ai'); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-activity">
                <p><?php _e('Noch keine Aktivitäten vorhanden.', 'nova-ai'); ?></p>
                <p class="description"><?php _e('Sobald Benutzer mit der KI interagieren, werden hier die Aktivitäten angezeigt.', 'nova-ai'); ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Getting Started -->
        <div class="dashboard-card">
            <h2><?php _e('Erste Schritte', 'nova-ai'); ?></h2>
            
            <div class="getting-started">
                <div class="step">
                    <span class="step-number">1</span>
                    <div class="step-content">
                        <h4><?php _e('Backend konfigurieren', 'nova-ai'); ?></h4>
                        <p><?php _e('Stelle sicher, dass das Nova AI Backend läuft und erreichbar ist.', 'nova-ai'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=nova-ai-settings'); ?>" class="step-link">
                            <?php _e('Zu den Einstellungen', 'nova-ai'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="step">
                    <span class="step-number">2</span>
                    <div class="step-content">
                        <h4><?php _e('Chat einbinden', 'nova-ai'); ?></h4>
                        <p><?php _e('Verwende den Shortcode, um das Chat-Interface auf deiner Website einzubinden.', 'nova-ai'); ?></p>
                        <code>[nova_ai_chat]</code>
                    </div>
                </div>
                
                <div class="step">
                    <span class="step-number">3</span>
                    <div class="step-content">
                        <h4><?php _e('Testen', 'nova-ai'); ?></h4>
                        <p><?php _e('Teste alle Features, um sicherzustellen, dass alles korrekt funktioniert.', 'nova-ai'); ?></p>
                        <button id="open-test-chat" class="step-link button">
                            <?php _e('Test-Chat öffnen', 'nova-ai'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Test Chat Modal -->
<div id="test-chat-modal" class="nova-test-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3><?php _e('Test-Chat', 'nova-ai'); ?></h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-content">
            <?php echo do_shortcode('[nova_ai_chat height="400px" title="Test-Chat"]'); ?>
        </div>
    </div>
</div>

<style>
/* Dashboard Grid */
.nova-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.nova-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

/* Stat Cards */
.stat-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 32px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-content h3 {
    margin: 0 0 4px 0;
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
    line-height: 1;
}

.stat-content p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
    font-weight: 500;
}

/* Dashboard Cards */
.dashboard-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    height: fit-content;
}

.dashboard-card h2 {
    margin: 0 0 20px 0;
    font-size: 20px;
    color: #1f2937;
    border-bottom: 2px solid #667eea;
    padding-bottom: 10px;
}

/* Status List */
.status-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    background: #f9fafb;
}

.status-item.status-good {
    background: #f0fdf4;
    color: #166534;
}

.status-item.status-warning {
    background: #fffbeb;
    color: #92400e;
}

.status-item.status-error {
    background: #fef2f2;
    color: #991b1b;
}

.status-label {
    font-weight: 500;
    min-width: 120px;
}

.status-value {
    flex: 1;
}

/* Feature List */
.feature-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border-radius: 8px;
    background: #f9fafb;
    transition: background 0.2s ease;
}

.feature-item.feature-enabled {
    background: #f0fdf4;
}

.feature-item.feature-disabled {
    background: #fef2f2;
    opacity: 0.7;
}

.feature-icon {
    font-size: 24px;
}

.feature-name {
    flex: 1;
    font-weight: 500;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.status-enabled {
    background: #dcfce7;
    color: #166534;
}

.status-badge.status-disabled {
    background: #fecaca;
    color: #991b1b;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}

.action-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px 16px;
    border-radius: 8px;
    background: #f9fafb;
    text-decoration: none;
    color: #374151;
    transition: all 0.2s ease;
    border: 1px solid #e5e7eb;
}

.action-button:hover {
    background: #667eea;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    text-decoration: none;
}

.action-icon {
    font-size: 24px;
}

.action-text {
    font-size: 13px;
    font-weight: 500;
    text-align: center;
}

/* Activity List */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 300px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    background: #f9fafb;
}

.activity-icon {
    font-size: 20px;
}

.activity-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.activity-user {
    font-weight: 600;
    color: #1f2937;
}

.activity-action {
    font-size: 13px;
    color: #6b7280;
}

.activity-time {
    font-size: 12px;
    color: #9ca3af;
}

.no-activity {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}

/* Getting Started */
.getting-started {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.step {
    display: flex;
    gap: 16px;
    align-items: flex-start;
}

.step-number {
    background: #667eea;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}

.step-content h4 {
    margin: 0 0 8px 0;
    color: #1f2937;
}

.step-content p {
    margin: 0 0 8px 0;
    color: #6b7280;
    font-size: 14px;
}

.step-link {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    font-size: 13px;
}

.step-link:hover {
    text-decoration: underline;
}

/* Card Actions */
.card-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

/* Test Modal */
.nova-test-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
}

.modal-container {
    position: relative;
    background: white;
    border-radius: 12px;
    max-width: 800px;
    max-height: 90vh;
    width: 90%;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.modal-close:hover {
    background: #f3f4f6;
}

.modal-content {
    height: 500px;
    overflow: hidden;
}

/* Responsive */
@media (max-width: 768px) {
    .nova-stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .nova-dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 16px;
        flex-direction: column;
        text-align: center;
    }
    
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .step {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Test Backend Connection
    $('#test-backend-connection').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const originalText = $button.find('.action-text').text();
        
        $button.find('.action-text').text('<?php _e('Teste...', 'nova-ai'); ?>');
        $button.addClass('loading');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'nova_ai_health_check',
                nonce: '<?php echo wp_create_nonce('nova_ai_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.find('.action-text').text('<?php _e('✓ Erfolgreich', 'nova-ai'); ?>');
                    setTimeout(() => {
                        $button.find('.action-text').text(originalText);
                    }, 2000);
                } else {
                    $button.find('.action-text').text('<?php _e('✗ Fehlgeschlagen', 'nova-ai'); ?>');
                    setTimeout(() => {
                        $button.find('.action-text').text(originalText);
                    }, 3000);
                }
            },
            error: function() {
                $button.find('.action-text').text('<?php _e('✗ Fehler', 'nova-ai'); ?>');
                setTimeout(() => {
                    $button.find('.action-text').text(originalText);
                }, 3000);
            },
            complete: function() {
                $button.removeClass('loading');
            }
        });
    });
    
    // Clear Temp Files
    $('#clear-temp-files').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('Temporäre Dateien wirklich löschen?', 'nova-ai'); ?>')) {
            return;
        }
        
        const $button = $(this);
        const originalText = $button.find('.action-text').text();
        
        $button.find('.action-text').text('<?php _e('Lösche...', 'nova-ai'); ?>');
        
        // Simulate cleanup (replace with actual AJAX call)
        setTimeout(() => {
            $button.find('.action-text').text('<?php _e('✓ Gelöscht', 'nova-ai'); ?>');
            setTimeout(() => {
                $button.find('.action-text').text(originalText);
            }, 2000);
        }, 1000);
    });
    
    // Export Settings
    $('#export-settings').on('click', function(e) {
        e.preventDefault();
        
        const settings = {
            backend_url: '<?php echo esc_js(get_option('nova_ai_backend_url')); ?>',
            chat_model: '<?php echo esc_js(get_option('nova_ai_chat_model')); ?>',
            vision_model: '<?php echo esc_js(get_option('nova_ai_vision_model')); ?>',
            // Add more settings as needed
        };
        
        const dataStr = JSON.stringify(settings, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        const url = URL.createObjectURL(dataBlob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = 'nova-ai-settings.json';
        link.click();
        
        URL.revokeObjectURL(url);
    });
    
    // Test Chat Modal
    $('#open-test-chat').on('click', function() {
        $('#test-chat-modal').show();
    });
    
    $('.modal-close, .modal-overlay').on('click', function() {
        $('#test-chat-modal').hide();
    });
    
    // Auto-refresh stats every 30 seconds
    setInterval(() => {
        location.reload();
    }, 30000);
});
</script>
