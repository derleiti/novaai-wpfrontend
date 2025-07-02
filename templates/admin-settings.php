<?php
/**
 * Admin Settings Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'nova_ai_settings')) {
    // Update settings
    $settings_to_update = array(
        'nova_ai_backend_url' => sanitize_url($_POST['nova_ai_backend_url']),
        'nova_ai_chat_model' => sanitize_text_field($_POST['nova_ai_chat_model']),
        'nova_ai_vision_model' => sanitize_text_field($_POST['nova_ai_vision_model']),
        'nova_ai_sd_steps' => absint($_POST['nova_ai_sd_steps']),
        'nova_ai_sd_size' => sanitize_text_field($_POST['nova_ai_sd_size']),
        'nova_ai_enable_chat' => isset($_POST['nova_ai_enable_chat']),
        'nova_ai_enable_vision' => isset($_POST['nova_ai_enable_vision']),
        'nova_ai_enable_image_gen' => isset($_POST['nova_ai_enable_image_gen']),
        'nova_ai_max_context' => absint($_POST['nova_ai_max_context']),
        'nova_ai_timeout' => absint($_POST['nova_ai_timeout'])
    );
    
    foreach ($settings_to_update as $key => $value) {
        update_option($key, $value);
    }
    
    echo '<div class="notice notice-success"><p>' . __('Einstellungen erfolgreich gespeichert!', 'nova-ai') . '</p></div>';
}

// Get current settings
$backend_url = get_option('nova_ai_backend_url', 'http://localhost:8000');
$chat_model = get_option('nova_ai_chat_model', 'mixtral:8x7b');
$vision_model = get_option('nova_ai_vision_model', 'llava:latest');
$sd_steps = get_option('nova_ai_sd_steps', 20);
$sd_size = get_option('nova_ai_sd_size', '512x512');
$enable_chat = get_option('nova_ai_enable_chat', true);
$enable_vision = get_option('nova_ai_enable_vision', true);
$enable_image_gen = get_option('nova_ai_enable_image_gen', true);
$max_context = get_option('nova_ai_max_context', 10);
$timeout = get_option('nova_ai_timeout', 120);
?>

<div class="wrap nova-ai-settings">
    <h1><?php _e('Nova AI Einstellungen', 'nova-ai'); ?></h1>
    
    <div class="nova-settings-container">
        <!-- Settings Navigation -->
        <nav class="nav-tab-wrapper">
            <a href="#connection" class="nav-tab nav-tab-active"><?php _e('Verbindung', 'nova-ai'); ?></a>
            <a href="#models" class="nav-tab"><?php _e('Modelle', 'nova-ai'); ?></a>
            <a href="#features" class="nav-tab"><?php _e('Features', 'nova-ai'); ?></a>
            <a href="#advanced" class="nav-tab"><?php _e('Erweitert', 'nova-ai'); ?></a>
        </nav>
        
        <form method="post" action="" class="nova-settings-form">
            <?php wp_nonce_field('nova_ai_settings'); ?>
            
            <!-- Connection Settings -->
            <div id="connection" class="tab-content active">
                <h2><?php _e('Backend-Verbindung', 'nova-ai'); ?></h2>
                <p class="description"><?php _e('Konfiguriere die Verbindung zum Nova AI Backend.', 'nova-ai'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nova_ai_backend_url"><?php _e('Backend URL', 'nova-ai'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="nova_ai_backend_url"
                                   name="nova_ai_backend_url" 
                                   value="<?php echo esc_attr($backend_url); ?>" 
                                   class="regular-text" 
                                   required />
                            <p class="description">
                                <?php _e('Standard:', 'nova-ai'); ?> <code>http://localhost:8000</code><br>
                                <?php _e('Für Docker:', 'nova-ai'); ?> <code>http://172.17.0.1:8000</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nova_ai_timeout"><?php _e('Timeout (Sekunden)', 'nova-ai'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="nova_ai_timeout"
                                   name="nova_ai_timeout" 
                                   value="<?php echo esc_attr($timeout); ?>" 
                                   min="30" 
                                   max="300" 
                                   class="small-text" />
                            <p class="description"><?php _e('Maximale Wartezeit für API-Anfragen (30-300 Sekunden)', 'nova-ai'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <!-- Connection Test -->
                <div class="connection-test">
                    <h3><?php _e('Verbindungstest', 'nova-ai'); ?></h3>
                    <button type="button" id="test-connection" class="button button-secondary">
                        <?php _e('Verbindung testen', 'nova-ai'); ?>
                    </button>
                    <div id="connection-result" class="test-result"></div>
                </div>
            </div>
            
            <!-- Model Settings -->
            <div id="models" class="tab-content">
                <h2><?php _e('KI-Modelle', 'nova-ai'); ?></h2>
                <p class="description"><?php _e('Wähle die zu verwendenden KI-Modelle aus.', 'nova-ai'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nova_ai_chat_model"><?php _e('Chat-Modell', 'nova-ai'); ?></label>
                        </th>
                        <td>
                            <select id="nova_ai_chat_model" name="nova_ai_chat_model" class="regular-text">
                                <option value="mixtral:8x7b" <?php selected($chat_model, 'mixtral:8x7b'); ?>>Mixtral 8x7B</option>
                                <option value="llama2:70b" <?php selected($chat_model, 'llama2:70b'); ?>>Llama 2 70B</option>
                                <option value="llama2:13b" <?php selected($chat_model, 'llama2:13b'); ?>>Llama 2 13B</option>
                                <option value="codellama:34b" <?php selected($chat_model, 'codellama:34b'); ?>>Code Llama 34B</option>
                                <option value="phi:latest" <?php selected($chat_model, 'phi:latest'); ?>>Phi</option>
                                <option value="openchat:latest" <?php selected($chat_model, 'openchat:latest'); ?>>OpenChat</option>
                            </select>
                            <button type="button" id="refresh-chat-models" class="button button-small">
                                <?php _e('Aktualisieren', 'nova-ai'); ?>
                            </button>
                            <p class="description"><?php _e('Modell für Textgenerierung und Unterhaltungen', 'nova-ai'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="nova_ai_vision_model"><?php _e('Vision-Modell', 'nova-ai'); ?></label>
                        </th>
                        <td>
                            <select id="nova_ai_vision_model" name="nova_ai_vision_model" class="regular-text">
                                <option value="llava:latest" <?php selected($vision_model, 'llava:latest'); ?>>LLaVA Latest</option>
                                <option value="llava:13b" <?php selected($vision_model, 'llava:13b'); ?>>LLaVA 13B</option>
                                <option value="llava:7b" <?php selected($vision_model, 'llava:7b'); ?>>LLaVA 7B</option>
                                <option value="bakllava:latest" <?php selected($vision_model, 'bakllava:latest'); ?>>BakLLaVA</option>
                            </select>
                            <button type="button" id="refresh-vision-models" class="button button-small">
                                <?php _e('Aktualisieren', 'nova-ai'); ?>
                            </button>
                            <p class="description"><?php _e('Modell für Bildanalyse und -erkennung', 'nova-ai'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <!-- Image Generation Settings -->
                <h3><?php _e('Bildgenerierung', 'nova-ai'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nova_ai_sd_steps"><?php _e('Generierungsschritte', 'nova-ai'); ?></label>
                        </th>
                        <td>
                            <input type="range" 
                                   id="nova_ai_sd_steps"
                                   name="nova_ai_sd_steps" 
                                   value="<?php echo esc_attr($sd_steps); ?>" 
                                   min="10" 
                                   max="150" 
                                   class="widefat" 
                                   oninput="this.nextElementSibling.textContent = this.value" />
                            <span class="range-value"><?php echo esc_html($sd_steps); ?></span>
                            <p class="description">
                                <?php _e('Mehr Schritte = bessere Qualität, aber längere Generierungszeit', 'nova-ai'); ?><br>
                                <?php _e('Empfehlung: 20-50 für schnelle Tests, 50-100 für Produktionsqualität', 'nova-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="nova_ai_sd_size"><?php _e('Bildgröße', 'nova-ai'); ?></label>
                        </th>
                        <td>
                            <select id="nova_ai_sd_size" name="nova_ai_sd_size">
                                <option value="256x256" <?php selected($sd_size, '256x256'); ?>>256×256 (Schnell)</option>
                                <option value="512x512" <?php selected($sd_size, '512x512'); ?>>512×512 (Standard)</option>
                                <option value="768x768" <?php selected($sd_size, '768x768'); ?>>768×768 (Hoch)</option>
                                <option value="1024x1024" <?php selected($sd_size, '1024x1024'); ?>>1024×1024 (Ultra)</option>
                                <option value="512x768" <?php selected($sd_size, '512x768'); ?>>512×768 (Portrait)</option>
                                <option value="768x512" <?php selected($sd_size, '768x512'); ?>>768×512 (Landscape)</option>
                            </select>
                            <p class="description"><?php _e('Größere Bilder benötigen mehr Zeit und Ressourcen', 'nova-ai'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Feature Settings -->
            <div id="features" class="tab-content">
                <h2><?php _e('Aktivierte Features', 'nova-ai'); ?></h2>
                <p class="description"><?php _e('Wähle welche KI-Features für Benutzer verfügbar sein sollen.', 'nova-ai'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Chat-Funktion', 'nova-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="nova_ai_enable_chat" 
                                       value="1" 
                                       <?php checked($enable_chat); ?> />
                                <?php _e('Text-Chat mit KI aktivieren', 'nova-ai'); ?>
                            </label>
                            <p class="description"><?php _e('Ermöglicht Unterhaltungen mit dem KI-Modell', 'nova-ai'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Bildanalyse', 'nova-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="nova_ai_enable_vision" 
                                       value="1" 
                                       <?php checked($enable_vision); ?> />
                                <?php _e('Bildanalyse und -erkennung aktivieren', 'nova-ai'); ?>
                            </label>
                            <p class="description"><?php _e('Ermöglicht das Hochladen und Analysieren von Bildern', 'nova-ai'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Bildgenerierung', 'nova-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="nova_ai_enable_image_gen" 
                                       value="1" 
                                       <?php checked($enable_image_gen); ?> />
                                <?php _e('KI-Bildgenerierung aktivieren', 'nova-ai'); ?>
                            </label>
                            <p class="description"><?php _e('Ermöglicht die Generierung von Bildern aus Textbeschreibungen', 'nova-ai'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Advanced Settings -->
            <div id="advanced" class="tab-content">
                <h2><?php _e('Erweiterte Einstellungen', 'nova-ai'); ?></h2>
                <p class="description"><?php _e('Konfiguriere erweiterte Parameter für erfahrene Benutzer.', 'nova-ai'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nova_ai_max_context"><?php _e('Maximaler Kontext', 'nova-ai'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="nova_ai_max_context"
                                   name="nova_ai_max_context" 
                                   value="<?php echo esc_attr($max_context); ?>" 
                                   min="5" 
                                   max="50" 
                                   class="small-text" />
                            <p class="description">
                                <?php _e('Anzahl der vorherigen Nachrichten, die an die KI gesendet werden (5-50)', 'nova-ai'); ?><br>
                                <?php _e('Höhere Werte = besserer Kontext, aber langsamere Antworten', 'nova-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <!-- Debug Information -->
                <h3><?php _e('System-Informationen', 'nova-ai'); ?></h3>
                <div class="debug-info">
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <td><strong><?php _e('Plugin-Version:', 'nova-ai'); ?></strong></td>
                                <td><?php echo NOVA_AI_VERSION; ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('WordPress-Version:', 'nova-ai'); ?></strong></td>
                                <td><?php echo get_bloginfo('version'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('PHP-Version:', 'nova-ai'); ?></strong></td>
                                <td><?php echo PHP_VERSION; ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Upload-Verzeichnis:', 'nova-ai'); ?></strong></td>
                                <td>
                                    <?php echo NOVA_AI_UPLOAD_DIR; ?>
                                    <?php if (is_writable(NOVA_AI_UPLOAD_DIR)): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('cURL verfügbar:', 'nova-ai'); ?></strong></td>
                                <td>
                                    <?php if (function_exists('curl_init')): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php _e('Ja', 'nova-ai'); ?>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-dismiss" style="color: red;"></span> <?php _e('Nein', 'nova-ai'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Reset Settings -->
                <h3><?php _e('Einstellungen zurücksetzen', 'nova-ai'); ?></h3>
                <p class="description"><?php _e('Alle Einstellungen auf Standardwerte zurücksetzen.', 'nova-ai'); ?></p>
                <button type="button" id="reset-settings" class="button button-secondary">
                    <?php _e('Alle Einstellungen zurücksetzen', 'nova-ai'); ?>
                </button>
            </div>
            
            <?php submit_button(__('Einstellungen speichern', 'nova-ai')); ?>
        </form>
    </div>
</div>

<!-- Usage Information -->
<div class="nova-usage-info">
    <h2><?php _e('Verwendung', 'nova-ai'); ?></h2>
    
    <div class="usage-grid">
        <div class="usage-card">
            <h3><?php _e('Shortcode für Chat-Interface', 'nova-ai'); ?></h3>
            <code>[nova_ai_chat]</code>
            <p><?php _e('Bindet das vollständige Chat-Interface ein', 'nova-ai'); ?></p>
            
            <h4><?php _e('Parameter:', 'nova-ai'); ?></h4>
            <ul>
                <li><code>height="600px"</code> - <?php _e('Höhe des Chat-Containers', 'nova-ai'); ?></li>
                <li><code>title="Mein AI Assistant"</code> - <?php _e('Titel im Header', 'nova-ai'); ?></li>
                <li><code>theme="dark|light"</code> - <?php _e('Farbschema', 'nova-ai'); ?></li>
                <li><code>show_modes="chat,image,vision"</code> - <?php _e('Verfügbare Modi', 'nova-ai'); ?></li>
            </ul>
            
            <h4><?php _e('Beispiele:', 'nova-ai'); ?></h4>
            <code>[nova_ai_chat height="500px" title="Support Bot" theme="light"]</code><br>
            <code>[nova_ai_chat show_modes="chat" enable_history="false"]</code>
        </div>
        
        <div class="usage-card">
            <h3><?php _e('Shortcode für Chat-Button', 'nova-ai'); ?></h3>
            <code>[nova_ai_chat_button]</code>
            <p><?php _e('Erstellt einen schwebenden Chat-Button', 'nova-ai'); ?></p>
            
            <h4><?php _e('Parameter:', 'nova-ai'); ?></h4>
            <ul>
                <li><code>text="AI Chat öffnen"</code> - <?php _e('Button-Text', 'nova-ai'); ?></li>
                <li><code>position="bottom-right"</code> - <?php _e('Position auf der Seite', 'nova-ai'); ?></li>
                <li><code>color="primary"</code> - <?php _e('Farbe des Buttons', 'nova-ai'); ?></li>
            </ul>
        </div>
        
        <div class="usage-card">
            <h3><?php _e('PHP-Integration', 'nova-ai'); ?></h3>
            <p><?php _e('Für Theme-Entwickler:', 'nova-ai'); ?></p>
            <code>
                &lt;?php<br>
                if (function_exists('nova_ai_chat')) {<br>
                &nbsp;&nbsp;echo nova_ai_chat(array(<br>
                &nbsp;&nbsp;&nbsp;&nbsp;'height' => '400px',<br>
                &nbsp;&nbsp;&nbsp;&nbsp;'theme' => 'light'<br>
                &nbsp;&nbsp;));<br>
                }<br>
                ?&gt;
            </code>
        </div>
    </div>
</div>

<style>
.nova-settings-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.nav-tab-wrapper {
    border-bottom: 1px solid #ccd0d4;
    margin-bottom: 20px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.connection-test, .debug-info {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    margin-top: 20px;
}

.test-result {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
}

.test-result.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.test-result.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.range-value {
    display: inline-block;
    min-width: 30px;
    text-align: center;
    font-weight: bold;
    margin-left: 10px;
}

.nova-usage-info {
    margin-top: 30px;
}

.usage-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.usage-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.usage-card h3 {
    margin-top: 0;
    color: #667eea;
}

.usage-card code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 90%;
}

.usage-card ul {
    margin-left: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab Navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').removeClass('active');
        $($(this).attr('href')).addClass('active');
    });
    
    // Connection Test
    $('#test-connection').on('click', function() {
        const $button = $(this);
        const $result = $('#connection-result');
        
        $button.prop('disabled', true).text('<?php _e('Teste...', 'nova-ai'); ?>');
        $result.removeClass('success error').text('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'nova_ai_health_check',
                nonce: '<?php echo wp_create_nonce('nova_ai_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('success').text('<?php _e('Verbindung erfolgreich!', 'nova-ai'); ?>');
                } else {
                    $result.addClass('error').text('<?php _e('Verbindung fehlgeschlagen:', 'nova-ai'); ?> ' + response.data);
                }
            },
            error: function() {
                $result.addClass('error').text('<?php _e('Fehler beim Verbindungstest', 'nova-ai'); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e('Verbindung testen', 'nova-ai'); ?>');
            }
        });
    });
    
    // Reset Settings
    $('#reset-settings').on('click', function() {
        if (confirm('<?php _e('Alle Einstellungen wirklich zurücksetzen?', 'nova-ai'); ?>')) {
            // Reset form to defaults
            $('#nova_ai_backend_url').val('http://localhost:8000');
            $('#nova_ai_chat_model').val('mixtral:8x7b');
            $('#nova_ai_vision_model').val('llava:latest');
            $('#nova_ai_sd_steps').val(20);
            $('#nova_ai_sd_size').val('512x512');
            $('input[name="nova_ai_enable_chat"]').prop('checked', true);
            $('input[name="nova_ai_enable_vision"]').prop('checked', true);
            $('input[name="nova_ai_enable_image_gen"]').prop('checked', true);
            $('#nova_ai_max_context').val(10);
            $('#nova_ai_timeout').val(120);
        }
    });
    
    // Refresh Models
    $('#refresh-chat-models, #refresh-vision-models').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Lade...', 'nova-ai'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'nova_ai_get_models',
                nonce: '<?php echo wp_create_nonce('nova_ai_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Update model options (simplified)
                    alert('<?php _e('Modelle aktualisiert! Seite wird neu geladen.', 'nova-ai'); ?>');
                    location.reload();
                }
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e('Aktualisieren', 'nova-ai'); ?>');
            }
        });
    });
});
</script>
