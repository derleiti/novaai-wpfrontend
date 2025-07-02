<?php
/**
 * Plugin Name: Nova AI Integration Pro
 * Plugin URI: https://example.com/nova-ai
 * Description: Erweiterte KI-Integration mit Ollama, LLaVA und Stable Diffusion - Chat, Bildgenerierung und Vision-Analyse
 * Version: 2.0.0
 * Author: Nova AI Team
 * License: GPL v2 or later
 * Text Domain: nova-ai
 */

// Sicherheit
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten
define('NOVA_AI_VERSION', '2.0.0');
define('NOVA_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NOVA_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NOVA_AI_UPLOAD_DIR', wp_upload_dir()['basedir'] . '/nova-ai-temp/');
define('NOVA_AI_UPLOAD_URL', wp_upload_dir()['baseurl'] . '/nova-ai-temp/');

/**
 * Autoloader für Plugin-Klassen
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'Nova_AI_') === 0) {
        $file = NOVA_AI_PLUGIN_DIR . 'includes/class-' . str_replace('_', '-', strtolower($class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Hauptklasse für Plugin-Initialisierung
 */
class Nova_AI_Plugin {
    
    private static $instance = null;
    private $admin;
    private $frontend;
    private $ajax;
    private $settings;
    
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
        // Plugin-Komponenten initialisieren
        add_action('plugins_loaded', array($this, 'load_components'));
        
        // Grundlegende Hooks
        add_action('init', array($this, 'init_plugin'));
        
        // Aktivierung/Deaktivierung
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function load_components() {
        // Einstellungen laden
        $this->settings = new Nova_AI_Settings();
        
        // Admin-Bereich
        if (is_admin()) {
            $this->admin = new Nova_AI_Admin($this->settings);
        }
        
        // Frontend
        $this->frontend = new Nova_AI_Frontend($this->settings);
        
        // AJAX Handler
        $this->ajax = new Nova_AI_Ajax($this->settings);
    }
    
    public function init_plugin() {
        // Textdomain laden
        load_plugin_textdomain('nova-ai', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Upload-Verzeichnis erstellen
        $this->create_upload_directory();
        
        // Cleanup Cron
        $this->schedule_cleanup();
    }
    
    private function create_upload_directory() {
        if (!file_exists(NOVA_AI_UPLOAD_DIR)) {
            wp_mkdir_p(NOVA_AI_UPLOAD_DIR);
            
            // .htaccess für Sicherheit
            $htaccess = NOVA_AI_UPLOAD_DIR . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 
                    "Options -Indexes\n" .
                    "<FilesMatch '\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$'>\n" .
                    "    deny from all\n" .
                    "</FilesMatch>"
                );
            }
        }
    }
    
    private function schedule_cleanup() {
        if (!wp_next_scheduled('nova_ai_cleanup_temp_files')) {
            wp_schedule_event(time(), 'hourly', 'nova_ai_cleanup_temp_files');
        }
        add_action('nova_ai_cleanup_temp_files', array($this, 'cleanup_temp_files'));
    }
    
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
    
    public function activate() {
        // Standardwerte setzen
        $defaults = array(
            'nova_ai_backend_url' => 'http://localhost:8000',
            'nova_ai_chat_model' => 'mixtral:8x7b',
            'nova_ai_vision_model' => 'llava:latest',
            'nova_ai_sd_steps' => 20,
            'nova_ai_sd_size' => '512x512',
            'nova_ai_enable_chat' => true,
            'nova_ai_enable_vision' => true,
            'nova_ai_enable_image_gen' => true,
            'nova_ai_max_context' => 10,
            'nova_ai_timeout' => 120
        );
        
        foreach ($defaults as $key => $value) {
            add_option($key, $value);
        }
        
        // Capabilities hinzufügen
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_nova_ai');
        }
        
        // Database-Tabellen erstellen (falls nötig)
        $this->create_database_tables();
    }
    
    public function deactivate() {
        // Cleanup scheduled events
        wp_clear_scheduled_hook('nova_ai_cleanup_temp_files');
        
        // Temporäre Dateien löschen
        $this->cleanup_temp_files();
    }
    
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nova_ai_conversations';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            conversation_id varchar(255) NOT NULL,
            message_type varchar(20) NOT NULL,
            content longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY conversation_id (conversation_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

/**
 * Einstellungen-Klasse
 */
class Nova_AI_Settings {
    
    private $options = array();
    
    public function __construct() {
        $this->load_options();
    }
    
    private function load_options() {
        $this->options = array(
            'backend_url' => get_option('nova_ai_backend_url', 'http://localhost:8000'),
            'chat_model' => get_option('nova_ai_chat_model', 'mixtral:8x7b'),
            'vision_model' => get_option('nova_ai_vision_model', 'llava:latest'),
            'sd_steps' => get_option('nova_ai_sd_steps', 20),
            'sd_size' => get_option('nova_ai_sd_size', '512x512'),
            'enable_chat' => get_option('nova_ai_enable_chat', true),
            'enable_vision' => get_option('nova_ai_enable_vision', true),
            'enable_image_gen' => get_option('nova_ai_enable_image_gen', true),
            'max_context' => get_option('nova_ai_max_context', 10),
            'timeout' => get_option('nova_ai_timeout', 120)
        );
    }
    
    public function get($key, $default = null) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    public function set($key, $value) {
        $this->options[$key] = $value;
        update_option('nova_ai_' . $key, $value);
    }
    
    public function get_api_url($endpoint = '') {
        $base_url = rtrim($this->get('backend_url'), '/');
        return $endpoint ? $base_url . '/' . ltrim($endpoint, '/') : $base_url;
    }
}

/**
 * Frontend-Klasse
 */
class Nova_AI_Frontend {
    
    private $settings;
    
    public function __construct($settings) {
        $this->settings = $settings;
        $this->init();
    }
    
    private function init() {
        // Shortcode registrieren
        add_shortcode('nova_ai_chat', array($this, 'render_chat_interface'));
        add_shortcode('nova_ai_chat_button', array($this, 'render_chat_button'));
        
        // Assets einbinden
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // REST API Endpoints (alternative zu AJAX)
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    public function enqueue_assets() {
        // Nur laden wenn Shortcode auf der Seite ist
        if (!$this->has_shortcode()) {
            return;
        }
        
        wp_enqueue_style(
            'nova-ai-frontend',
            NOVA_AI_PLUGIN_URL . 'assets/css/nova-frontend.css',
            array(),
            NOVA_AI_VERSION
        );
        
        wp_enqueue_script(
            'nova-ai-frontend',
            NOVA_AI_PLUGIN_URL . 'assets/js/nova-frontend.js',
            array('jquery'),
            NOVA_AI_VERSION,
            true
        );
        
        // Konfiguration an JavaScript übergeben
        wp_localize_script('nova-ai-frontend', 'novaAI', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nova_ai_nonce'),
            'restUrl' => rest_url('nova-ai/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'settings' => array(
                'enableChat' => $this->settings->get('enable_chat'),
                'enableVision' => $this->settings->get('enable_vision'),
                'enableImageGen' => $this->settings->get('enable_image_gen'),
                'maxContext' => $this->settings->get('max_context'),
                'timeout' => $this->settings->get('timeout')
            ),
            'i18n' => array(
                'placeholder' => __('Schreibe eine Nachricht...', 'nova-ai'),
                'placeholderImage' => __('Beschreibe das gewünschte Bild...', 'nova-ai'),
                'placeholderVision' => __('Was möchtest du über das Bild wissen?', 'nova-ai'),
                'generating' => __('Generiere Bild...', 'nova-ai'),
                'analyzing' => __('Analysiere Bild...', 'nova-ai'),
                'error' => __('Ein Fehler ist aufgetreten', 'nova-ai'),
                'connectionError' => __('Verbindungsfehler', 'nova-ai'),
                'selectImage' => __('Bild auswählen', 'nova-ai'),
                'download' => __('Download', 'nova-ai')
            )
        ));
    }
    
    private function has_shortcode() {
        global $post;
        return is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'nova_ai_chat') ||
            has_shortcode($post->post_content, 'nova_ai_chat_button')
        );
    }
    
    public function register_rest_routes() {
        register_rest_route('nova-ai/v1', '/chat', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_chat'),
            'permission_callback' => array($this, 'rest_permission_check')
        ));
        
        register_rest_route('nova-ai/v1', '/image', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_image'),
            'permission_callback' => array($this, 'rest_permission_check')
        ));
        
        register_rest_route('nova-ai/v1', '/vision', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_vision'),
            'permission_callback' => array($this, 'rest_permission_check')
        ));
    }
    
    public function rest_permission_check() {
        return current_user_can('read'); // Anpassen je nach Anforderung
    }
    
    public function render_chat_interface($atts) {
        $atts = shortcode_atts(array(
            'height' => '600px',
            'title' => __('Nova AI Assistant', 'nova-ai'),
            'theme' => 'dark',
            'show_modes' => 'all', // all, chat, image, vision
            'enable_history' => true
        ), $atts);
        
        $modes = $this->get_enabled_modes($atts['show_modes']);
        
        ob_start();
        include NOVA_AI_PLUGIN_DIR . 'templates/chat-interface.php';
        return ob_get_clean();
    }
    
    public function render_chat_button($atts) {
        $atts = shortcode_atts(array(
            'text' => __('AI Chat öffnen', 'nova-ai'),
            'position' => 'bottom-right', // bottom-right, bottom-left, top-right, top-left
            'color' => 'primary'
        ), $atts);
        
        ob_start();
        include NOVA_AI_PLUGIN_DIR . 'templates/chat-button.php';
        return ob_get_clean();
    }
    
    private function get_enabled_modes($show_modes) {
        $all_modes = array(
            'chat' => array(
                'enabled' => $this->settings->get('enable_chat'),
                'icon' => '💬',
                'label' => __('Chat', 'nova-ai')
            ),
            'image' => array(
                'enabled' => $this->settings->get('enable_image_gen'),
                'icon' => '🎨',
                'label' => __('Bild erstellen', 'nova-ai')
            ),
            'vision' => array(
                'enabled' => $this->settings->get('enable_vision'),
                'icon' => '🔍',
                'label' => __('Bild analysieren', 'nova-ai')
            )
        );
        
        if ($show_modes === 'all') {
            return array_filter($all_modes, function($mode) {
                return $mode['enabled'];
            });
        }
        
        $selected_modes = explode(',', $show_modes);
        $enabled_modes = array();
        
        foreach ($selected_modes as $mode) {
            $mode = trim($mode);
            if (isset($all_modes[$mode]) && $all_modes[$mode]['enabled']) {
                $enabled_modes[$mode] = $all_modes[$mode];
            }
        }
        
        return $enabled_modes;
    }
}

/**
 * AJAX Handler Klasse
 */
class Nova_AI_Ajax {
    
    private $settings;
    
    public function __construct($settings) {
        $this->settings = $settings;
        $this->init();
    }
    
    private function init() {
        // AJAX Hooks
        add_action('wp_ajax_nova_ai_process', array($this, 'handle_request'));
        add_action('wp_ajax_nopriv_nova_ai_process', array($this, 'handle_request'));
        
        // Health Check
        add_action('wp_ajax_nova_ai_health_check', array($this, 'health_check'));
        add_action('wp_ajax_nova_ai_get_models', array($this, 'get_models'));
    }
    
    public function handle_request() {
        // Nonce-Überprüfung
        if (!wp_verify_nonce($_POST['nonce'], 'nova_ai_nonce')) {
            wp_send_json_error(__('Ungültige Sicherheitstoken', 'nova-ai'));
        }
        
        $type = sanitize_text_field($_POST['type']);
        
        // Rate Limiting (einfach)
        $this->check_rate_limit();
        
        try {
            switch ($type) {
                case 'chat':
                    $this->handle_chat();
                    break;
                case 'image':
                    $this->handle_image_generation();
                    break;
                case 'vision':
                    $this->handle_vision();
                    break;
                default:
                    wp_send_json_error(__('Unbekannter Request-Typ', 'nova-ai'));
            }
        } catch (Exception $e) {
            error_log('Nova AI Error: ' . $e->getMessage());
            wp_send_json_error(__('Ein unerwarteter Fehler ist aufgetreten', 'nova-ai'));
        }
    }
    
    private function check_rate_limit() {
        $user_id = get_current_user_id();
        $transient_key = 'nova_ai_rate_limit_' . ($user_id ?: $_SERVER['REMOTE_ADDR']);
        
        $count = get_transient($transient_key);
        if ($count && $count > 30) { // 30 Requests pro Minute
            wp_send_json_error(__('Rate Limit erreicht. Bitte warten Sie eine Minute.', 'nova-ai'));
        }
        
        set_transient($transient_key, ($count ?: 0) + 1, 60);
    }
    
    private function handle_chat() {
        $prompt = sanitize_textarea_field($_POST['prompt']);
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();
        
        if (empty($prompt)) {
            wp_send_json_error(__('Prompt darf nicht leer sein', 'nova-ai'));
        }
        
        // Kontext-Limit beachten
        $max_context = $this->settings->get('max_context');
        if (count($context) > $max_context * 2) { // *2 wegen user+assistant Paaren
            $context = array_slice($context, -$max_context * 2);
        }
        
        $messages = $context;
        $messages[] = array('role' => 'user', 'content' => $prompt);
        
        $response = $this->make_api_request('/chat', array(
            'messages' => $messages,
            'model' => $this->settings->get('chat_model')
        ));
        
        if ($response && isset($response['message']['content'])) {
            wp_send_json_success(array(
                'content' => $response['message']['content'],
                'role' => 'assistant'
            ));
        } else {
            wp_send_json_error(__('Ungültige API-Antwort', 'nova-ai'));
        }
    }
    
    private function handle_image_generation() {
        $prompt = sanitize_textarea_field($_POST['prompt']);
        
        if (empty($prompt)) {
            wp_send_json_error(__('Prompt darf nicht leer sein', 'nova-ai'));
        }
        
        $size = $this->settings->get('sd_size');
        list($width, $height) = explode('x', $size);
        
        $response = $this->make_api_request('/image/generate', array(
            'prompt' => $prompt,
            'negative_prompt' => 'ugly, blurry, low quality, distorted',
            'steps' => intval($this->settings->get('sd_steps')),
            'width' => intval($width),
            'height' => intval($height)
        ));
        
        if ($response && isset($response['images'][0])) {
            wp_send_json_success(array(
                'image' => 'data:image/png;base64,' . $response['images'][0],
                'seed' => isset($response['info']['seed']) ? $response['info']['seed'] : null
            ));
        } else {
            wp_send_json_error(__('Bildgenerierung fehlgeschlagen', 'nova-ai'));
        }
    }
    
    private function handle_vision() {
        $prompt = sanitize_textarea_field($_POST['prompt']);
        $image_data = $_POST['image'];
        
        if (empty($prompt)) {
            wp_send_json_error(__('Prompt darf nicht leer sein', 'nova-ai'));
        }
        
        if (empty($image_data)) {
            wp_send_json_error(__('Bild darf nicht leer sein', 'nova-ai'));
        }
        
        $response = $this->make_api_request('/vision', array(
            'prompt' => $prompt,
            'image' => $image_data,
            'model' => $this->settings->get('vision_model')
        ));
        
        if ($response && isset($response['response'])) {
            wp_send_json_success(array(
                'content' => $response['response'],
                'role' => 'assistant'
            ));
        } else {
            wp_send_json_error(__('Vision-Analyse fehlgeschlagen', 'nova-ai'));
        }
    }
    
    public function health_check() {
        if (!wp_verify_nonce($_POST['nonce'], 'nova_ai_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $response = wp_remote_get($this->settings->get_api_url(), array(
            'timeout' => 5,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success(array(
                'status' => 'healthy',
                'response_code' => wp_remote_retrieve_response_code($response)
            ));
        }
    }
    
    public function get_models() {
        if (!wp_verify_nonce($_POST['nonce'], 'nova_ai_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $chat_models = $this->make_api_request('/chat/models');
        $sd_models = $this->make_api_request('/image/models');
        
        wp_send_json_success(array(
            'chat_models' => $chat_models,
            'sd_models' => $sd_models
        ));
    }
    
    private function make_api_request($endpoint, $data = null) {
        $url = $this->settings->get_api_url($endpoint);
        
        $args = array(
            'timeout' => $this->settings->get('timeout'),
            'sslverify' => false,
            'headers' => array('Content-Type' => 'application/json')
        );
        
        if ($data) {
            $args['method'] = 'POST';
            $args['body'] = wp_json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            throw new Exception('HTTP ' . $code . ': ' . $body);
        }
        
        return json_decode($body, true);
    }
}

/**
 * Admin-Klasse
 */
class Nova_AI_Admin {
    
    private $settings;
    
    public function __construct($settings) {
        $this->settings = $settings;
        $this->init();
    }
    
    private function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Nova AI', 'nova-ai'),
            __('Nova AI', 'nova-ai'),
            'manage_nova_ai',
            'nova-ai',
            array($this, 'render_dashboard'),
            'dashicons-superhero-alt',
            100
        );
        
        add_submenu_page(
            'nova-ai',
            __('Einstellungen', 'nova-ai'),
            __('Einstellungen', 'nova-ai'),
            'manage_nova_ai',
            'nova-ai-settings',
            array($this, 'render_settings')
        );
        
        add_submenu_page(
            'nova-ai',
            __('Status', 'nova-ai'),
            __('Status', 'nova-ai'),
            'manage_nova_ai',
            'nova-ai-status',
            array($this, 'render_status')
        );
    }
    
    public function register_settings() {
        register_setting('nova_ai_settings', 'nova_ai_backend_url');
        register_setting('nova_ai_settings', 'nova_ai_chat_model');
        register_setting('nova_ai_settings', 'nova_ai_vision_model');
        register_setting('nova_ai_settings', 'nova_ai_sd_steps');
        register_setting('nova_ai_settings', 'nova_ai_sd_size');
        register_setting('nova_ai_settings', 'nova_ai_enable_chat');
        register_setting('nova_ai_settings', 'nova_ai_enable_vision');
        register_setting('nova_ai_settings', 'nova_ai_enable_image_gen');
        register_setting('nova_ai_settings', 'nova_ai_max_context');
        register_setting('nova_ai_settings', 'nova_ai_timeout');
    }
    
    public function render_dashboard() {
        include NOVA_AI_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }
    
    public function render_settings() {
        include NOVA_AI_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    public function render_status() {
        include NOVA_AI_PLUGIN_DIR . 'templates/admin-status.php';
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'nova-ai') === false) {
            return;
        }
        
        wp_enqueue_style(
            'nova-ai-admin',
            NOVA_AI_PLUGIN_URL . 'assets/css/nova-admin.css',
            array(),
            NOVA_AI_VERSION
        );
        
        wp_enqueue_script(
            'nova-ai-admin',
            NOVA_AI_PLUGIN_URL . 'assets/js/nova-admin.js',
            array('jquery'),
            NOVA_AI_VERSION,
            true
        );
        
        wp_localize_script('nova-ai-admin', 'novaAIAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nova_ai_nonce')
        ));
    }
}

// Plugin initialisieren
Nova_AI_Plugin::get_instance();
