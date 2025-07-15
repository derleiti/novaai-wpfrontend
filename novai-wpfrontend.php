<?php
/**
 * Plugin Name: Nova AI Integration
 * Plugin URI: https://example.com/
 * Description: Intelligente KI-Integration mit Ollama, LLaVA und Stable Diffusion
 * Version: 1.0.0
 * Author: Nova AI
 * License: GPL v2 or later
 */

// Sicherheit
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten
define('NOVA_AI_VERSION', '1.0.0');
define('NOVA_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NOVA_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NOVA_AI_UPLOAD_DIR', wp_upload_dir()['basedir'] . '/nova-ai-temp/');
define('NOVA_AI_UPLOAD_URL', wp_upload_dir()['baseurl'] . '/nova-ai-temp/');

/**
 * Hauptklasse
 */
class Nova_AI_Integration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Hooks
        add_action('init', array($this, 'create_upload_directory'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX Handlers
        add_action('wp_ajax_nova_ai_process', array($this, 'handle_ai_request'));
        add_action('wp_ajax_nopriv_nova_ai_process', array($this, 'handle_ai_request'));
        
        // Shortcode
        add_shortcode('nova_ai_chat', array($this, 'render_chat_interface'));
        
        // Assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Cleanup Cron
        add_action('nova_ai_cleanup_temp_files', array($this, 'cleanup_temp_files'));
        if (!wp_next_scheduled('nova_ai_cleanup_temp_files')) {
            wp_schedule_event(time(), 'hourly', 'nova_ai_cleanup_temp_files');
        }
    }
    
    /**
     * Erstelle temporäres Upload-Verzeichnis
     */
    public function create_upload_directory() {
        if (!file_exists(NOVA_AI_UPLOAD_DIR)) {
            wp_mkdir_p(NOVA_AI_UPLOAD_DIR);
            
            // .htaccess für Sicherheit
            $htaccess = NOVA_AI_UPLOAD_DIR . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Options -Indexes\n<FilesMatch '\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$'>\n    deny from all\n</FilesMatch>");
            }
        }
    }
    
    /**
     * Admin Menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Nova AI',
            'Nova AI',
            'manage_options',
            'nova-ai',
            array($this, 'render_admin_page'),
            'dashicons-superhero-alt',
            100
        );
        
        add_submenu_page(
            'nova-ai',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'nova-ai-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Registriere Einstellungen
     */
    public function register_settings() {
        register_setting('nova_ai_settings', 'nova_ai_port');
        register_setting('nova_ai_settings', 'nova_ai_chat_model');
        register_setting('nova_ai_settings', 'nova_ai_vision_model');
        register_setting('nova_ai_settings', 'nova_ai_sd_steps');
        register_setting('nova_ai_settings', 'nova_ai_sd_size');
    }
    
    /**
     * Settings Page
     */
    public function render_settings_page() {
        if (isset($_POST['submit'])) {
            update_option('nova_ai_port', sanitize_text_field($_POST['nova_ai_port']));
            update_option('nova_ai_chat_model', sanitize_text_field($_POST['nova_ai_chat_model']));
            update_option('nova_ai_vision_model', sanitize_text_field($_POST['nova_ai_vision_model']));
            update_option('nova_ai_sd_steps', intval($_POST['nova_ai_sd_steps']));
            update_option('nova_ai_sd_size', sanitize_text_field($_POST['nova_ai_sd_size']));
            
            echo '<div class="notice notice-success"><p>Einstellungen gespeichert!</p></div>';
        }
        
        $port = get_option('nova_ai_port', '8000');
        $chat_model = get_option('nova_ai_chat_model', 'mixtral:8x7b');
        $vision_model = get_option('nova_ai_vision_model', 'llava:latest');
        $sd_steps = get_option('nova_ai_sd_steps', 20);
        $sd_size = get_option('nova_ai_sd_size', '512x512');
        ?>
        <div class="wrap">
            <h1>Nova AI Einstellungen</h1>
            
            <form method="post" action="">
                <?php settings_fields('nova_ai_settings'); ?>
                
                <h2>Backend Verbindung</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Backend Port</th>
                        <td>
                            <input type="text" name="nova_ai_port" 
                                   value="<?php echo esc_attr($port); ?>" 
                                   class="small-text" />
                            <p class="description">Standard Port: 8000</p>
                            <p class="description">API URL wird sein: <code>http://172.17.0.1:<?php echo esc_html($port); ?></code></p>
                        </td>
                    </tr>
                </table>
                
                <h2>Modell-Einstellungen</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Chat Modell</th>
                        <td>
                            <input type="text" name="nova_ai_chat_model" 
                                   value="<?php echo esc_attr($chat_model); ?>" 
                                   class="regular-text" />
                            <p class="description">z.B. mixtral:8x7b</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Vision Modell</th>
                        <td>
                            <input type="text" name="nova_ai_vision_model" 
                                   value="<?php echo esc_attr($vision_model); ?>" 
                                   class="regular-text" />
                            <p class="description">z.B. llava:latest</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Stable Diffusion Steps</th>
                        <td>
                            <input type="number" name="nova_ai_sd_steps" 
                                   value="<?php echo esc_attr($sd_steps); ?>" 
                                   min="1" max="150" class="small-text" />
                            <p class="description">Anzahl der Generierungsschritte (1-150)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Bildgröße</th>
                        <td>
                            <select name="nova_ai_sd_size">
                                <option value="256x256" <?php selected($sd_size, '256x256'); ?>>256x256</option>
                                <option value="512x512" <?php selected($sd_size, '512x512'); ?>>512x512</option>
                                <option value="768x768" <?php selected($sd_size, '768x768'); ?>>768x768</option>
                                <option value="1024x1024" <?php selected($sd_size, '1024x1024'); ?>>1024x1024</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2>Verwendung</h2>
            <div class="card">
                <p>Verwende den folgenden Shortcode um das AI-Chat Interface einzubinden:</p>
                <code>[nova_ai_chat]</code>
            </div>
        </div>
        <?php
    }
    
    /**
     * Admin Dashboard
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Nova AI Dashboard</h1>
            <p>Willkommen bei Nova AI! Nutze den Shortcode <code>[nova_ai_chat]</code> um die KI einzubinden.</p>
        </div>
        <?php
    }
    
    /**
     * AJAX Handler für alle AI Requests
     */
    public function handle_ai_request() {
        check_ajax_referer('nova_ai_nonce', 'nonce');
        
        $type = sanitize_text_field($_POST['type']);
        $api_url = $this->get_api_url();
        
        switch ($type) {
            case 'chat':
                $this->handle_chat_request($api_url);
                break;
                
            case 'image_generate':
                $this->handle_image_generation($api_url);
                break;
                
            case 'vision':
                $this->handle_vision_request($api_url . '/vision/upload');
                break;
                
            default:
                wp_send_json_error('Unbekannter Request-Typ');
        }
    }
    
    /**
     * Chat Request Handler
     */
    private function handle_chat_request($api_url) {
        $prompt = sanitize_text_field($_POST['prompt']);
        error_log("Prompt: $prompt");
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();
        
        $messages = array();
        
        // Context hinzufügen
        if (is_array($context)) {
            foreach ($context as $msg) {
                $messages[] = array(
                    'role' => $msg['role'],
                    'content' => $msg['content']
                );
            }
        }
        
        // Aktuelle Nachricht
        $messages[] = array(
            'role' => 'user',
            'content' => $prompt
        );
        
        $body = json_encode(array(
            'prompt' => $prompt,
            'session_id' => sanitize_text_field($_POST['session_id'])
        // Debug
        , 'debug_session_id' => $_POST['session_id'],
            'model' => get_option('nova_ai_chat_model', 'mixtral:8x7b')
        ));

        $body = json_encode(array(
            'prompt' => $prompt,
            'session_id' => sanitize_text_field($_POST['session_id']),
            'model' => get_option('nova_ai_chat_model', 'mixtral:8x7b')
        ));

        error_log("NovaAI Plugin → POST to /chat: " . $body);

        $response = wp_remote_post($api_url . '/chat', array(
            'method'    => 'POST',
            'headers'   => array('Content-Type' => 'application/json'),
            'body'      => $body,
            'timeout'   => 30
        ));

        if (is_wp_error($response)) {
            error_log("NovaAI Plugin → WP Error: " . $response->get_error_message());
        } else {
            error_log("NovaAI Plugin → Backend Response: " . wp_remote_retrieve_body($response));
        }

        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['message']['content'])) {
            wp_send_json_success(array(
                'content' => $body['message']['content'],
                'role' => 'assistant'
            ));
        } else {
            wp_send_json_error('Ungültige API-Antwort');
        }
    }
    
    /**
     * Image Generation Handler
     */
    private function handle_image_generation($api_url) {
        $prompt = sanitize_text_field($_POST['prompt']);
        error_log("Prompt: $prompt");
        $size = get_option('nova_ai_sd_size', '512x512');
        list($width, $height) = explode('x', $size);
        
        $response = wp_remote_post($api_url . '/image/generate', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'prompt' => $prompt,
                'negative_prompt' => 'ugly, blurry, low quality, distorted',
                'steps' => intval(get_option('nova_ai_sd_steps', 20)),
                'width' => intval($width),
                'height' => intval($height)
            )),
            'timeout' => 120,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['images'][0])) {
            wp_send_json_success(array(
                'image' => 'data:image/png;base64,' . $body['images'][0],
                'seed' => isset($body['info']['seed']) ? $body['info']['seed'] : null
            ));
        } else {
            wp_send_json_error('Bildgenerierung fehlgeschlagen');
        }
    }
    
    /**
     * Vision Request Handler
     */
    private function handle_vision_request($api_url) {
        $prompt = sanitize_text_field($_POST['prompt']);
        error_log("Prompt: $prompt");
        $image_data = $_POST['image'];
        
        $response = wp_remote_post($api_url . '/vision', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'prompt' => $prompt,
                'image' => $image_data,
                'model' => get_option('nova_ai_vision_model', 'llava:latest')
            )),
            'timeout' => 60,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['response'])) {
            wp_send_json_success(array(
                'content' => $body['response'],
                'role' => 'assistant'
            ));
        } else {
            wp_send_json_error('Vision-Analyse fehlgeschlagen');
        }
    }
    
    /**
     * Get API URL - Immer 172.17.0.1 verwenden
     */
    private function get_api_url() {
        $port = get_option('nova_ai_port', '8000');
        return 'http://172.17.0.1:' . $port;
    }
    
    /**
     * Cleanup alte temporäre Dateien
     */
    public function cleanup_temp_files() {
        $files = glob(NOVA_AI_UPLOAD_DIR . 'temp_*');
        if (!$files) return;
        
        $now = time();
        
        foreach ($files as $file) {
            if (filemtime($file) < ($now - 300)) { // 5 Minuten
                unlink($file);
            }
        }
    }
    
    /**
     * Render Chat Interface Shortcode
     */
    public function render_chat_interface($atts) {
        $atts = shortcode_atts(array(
            'height' => '600px',
            'title' => 'Nova AI Assistant'
        ), $atts);
        
        ob_start();
        ?>
        <div class="nova-ai-chat-container" style="height: <?php echo esc_attr($atts['height']); ?>">
            <div class="nova-ai-header">
                <h3><?php echo esc_html($atts['title']); ?></h3>
                <div class="nova-ai-mode-selector">
                    <button class="mode-btn active" data-mode="chat">💬 Chat</button>
                    <button class="mode-btn" data-mode="image">🎨 Bild erstellen</button>
                    <button class="mode-btn" data-mode="vision">🔍 Bild analysieren</button>
                </div>
            </div>
            
            <div class="nova-ai-messages" id="nova-messages"></div>
            
            <div class="nova-ai-input-area">
                <!-- Chat/Image Mode -->
                <div class="input-mode" id="mode-chat" style="display: block;">
                    <textarea class="nova-ai-input" id="nova-prompt" 
                              placeholder="Schreibe eine Nachricht... (Shift+Enter für neue Zeile)" 
                              rows="1"></textarea>
                    <button class="nova-ai-send" id="nova-send">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Vision Mode -->
                <div class="input-mode" id="mode-vision" style="display: none;">
                    <div class="file-upload-wrapper">
                        <input type="file" id="nova-vision-file" accept="image/*" class="file-input" />
                        <label for="nova-vision-file" class="file-label">📷 Bild auswählen</label>
                    </div>
                    <textarea class="nova-ai-input" id="nova-vision-prompt" 
                              placeholder="Was möchtest du über das Bild wissen?" 
                              rows="1"></textarea>
                    <button class="nova-ai-send" id="nova-vision-analyze">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21L16.65 16.65"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="nova-ai-typing" id="nova-typing" style="display: none;">
                <span></span><span></span><span></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Frontend Assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'nova-ai-frontend',
            NOVA_AI_PLUGIN_URL . 'assets/nova-frontend.css',
            array(),
            NOVA_AI_VERSION
        );
        
        wp_enqueue_script(
            'nova-ai-frontend',
            NOVA_AI_PLUGIN_URL . 'assets/nova-frontend.js',
            array('jquery'),
            NOVA_AI_VERSION,
            true
        );
        
        wp_localize_script('nova-ai-frontend', 'nova_ai', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nova_ai_nonce')
        ));
    }
    
    /**
     * Admin Assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'nova-ai') === false) {
            return;
        }
        
        wp_enqueue_style(
            'nova-ai-admin',
            NOVA_AI_PLUGIN_URL . 'assets/nova-admin.css',
            array(),
            NOVA_AI_VERSION
        );
    }
}

// Plugin initialisieren
add_action('plugins_loaded', function() {
    Nova_AI_Integration::get_instance();
});

// Aktivierung
register_activation_hook(__FILE__, function() {
    // Standardwerte setzen
    add_option('nova_ai_port', '8000');
    add_option('nova_ai_chat_model', 'mixtral:8x7b');
    add_option('nova_ai_vision_model', 'llava:latest');
    add_option('nova_ai_sd_steps', 20);
    add_option('nova_ai_sd_size', '512x512');
});

// Deaktivierung
register_deactivation_hook(__FILE__, function() {
    // Cleanup scheduled events
    wp_clear_scheduled_hook('nova_ai_cleanup_temp_files');
});
