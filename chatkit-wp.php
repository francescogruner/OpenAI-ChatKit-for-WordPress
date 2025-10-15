<?php
/**
 * Plugin Name: OpenAI ChatKit for WordPress
 * Plugin URI: https://github.com/francescogruner/openai-chatkit-wordpress
 * Description: Integrate OpenAI's ChatKit into your WordPress site with guided setup. Supports customizable text in any language.
 * Version: 1.0.3
 * Author: Francesco Grüner
 * Author URI: https://francescogruner.it
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: chatkit-wp
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('CHATKIT_WP_VERSION', '1.0.3');
define('CHATKIT_WP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHATKIT_WP_PLUGIN_URL', plugin_dir_url(__FILE__));

class ChatKit_WordPress {
    private static $instance = null;
    private $options_cache = null;
    private $widget_loaded = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_shortcode('openai_chatkit', [$this, 'render_chatkit_shortcode']);
        add_shortcode('chatkit', [$this, 'render_chatkit_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_footer', [$this, 'maybe_auto_inject_widget'], 999);
        
        if (get_option('chatkit_show_everywhere', false)) {
            add_action('wp_footer', [$this, 'add_body_attributes_script'], 1);
        } else {
            add_action('wp_footer', [$this, 'conditional_body_attributes'], 1);
        }
    }
    
    public function conditional_body_attributes() {
        global $post;
        if ($post && (has_shortcode($post->post_content, 'openai_chatkit') || has_shortcode($post->post_content, 'chatkit'))) {
            $this->add_body_attributes_script();
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain('chatkit-wp', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function add_admin_menu() {
        add_options_page(
            __('ChatKit Settings', 'chatkit-wp'),
            __('ChatKit', 'chatkit-wp'),
            'manage_options',
            'chatkit-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        $settings = [
            'chatkit_api_key' => ['type' => 'string', 'default' => ''],
            'chatkit_workflow_id' => ['type' => 'string', 'default' => ''],
            'chatkit_accent_color' => ['type' => 'string', 'default' => '#FF4500'],
            'chatkit_accent_level' => ['type' => 'string', 'default' => '2'],
            'chatkit_button_text' => ['type' => 'string', 'default' => __('Chat now', 'chatkit-wp')],
            'chatkit_close_text' => ['type' => 'string', 'default' => '✕'],
            'chatkit_theme_mode' => ['type' => 'string', 'default' => 'dark'],
            'chatkit_enable_attachments' => ['type' => 'boolean', 'default' => false],
            'chatkit_persistent_sessions' => ['type' => 'boolean', 'default' => true],
            'chatkit_show_everywhere' => ['type' => 'boolean', 'default' => false],
            'chatkit_greeting_text' => ['type' => 'string', 'default' => __('How can I help you today?', 'chatkit-wp')],
            'chatkit_placeholder_text' => ['type' => 'string', 'default' => __('Send a message...', 'chatkit-wp')],
            'chatkit_button_size' => ['type' => 'string', 'default' => 'medium'],
            'chatkit_button_position' => ['type' => 'string', 'default' => 'bottom-right'],
            'chatkit_border_radius' => ['type' => 'string', 'default' => 'round'],
            'chatkit_shadow_style' => ['type' => 'string', 'default' => 'normal'],
            
            'chatkit_default_prompt_1' => ['type' => 'string', 'default' => __('How can I assist you?', 'chatkit-wp')],
            'chatkit_default_prompt_1_text' => ['type' => 'string', 'default' => __('Hi! How can I assist you today?', 'chatkit-wp')],
            'chatkit_default_prompt_2' => ['type' => 'string', 'default' => ''],
            'chatkit_default_prompt_2_text' => ['type' => 'string', 'default' => ''],
            'chatkit_default_prompt_3' => ['type' => 'string', 'default' => ''],
            'chatkit_default_prompt_3_text' => ['type' => 'string', 'default' => ''],
            
            'chatkit_exclude_ids' => ['type' => 'string', 'default' => ''],
            'chatkit_exclude_home' => ['type' => 'boolean', 'default' => false],
            'chatkit_exclude_archive' => ['type' => 'boolean', 'default' => false],
            'chatkit_exclude_search' => ['type' => 'boolean', 'default' => false],
            'chatkit_exclude_404' => ['type' => 'boolean', 'default' => false],
            
            'chatkit_attachment_max_size' => ['type' => 'string', 'default' => '20'],
            'chatkit_attachment_max_count' => ['type' => 'string', 'default' => '3'],
            'chatkit_enable_model_picker' => ['type' => 'boolean', 'default' => false],
            'chatkit_enable_tools' => ['type' => 'boolean', 'default' => false],
            'chatkit_enable_entity_tags' => ['type' => 'boolean', 'default' => false],
            'chatkit_density' => ['type' => 'string', 'default' => 'normal'],
            'chatkit_locale' => ['type' => 'string', 'default' => ''],
            
            'chatkit_enable_custom_font' => ['type' => 'boolean', 'default' => false],
            'chatkit_font_family' => ['type' => 'string', 'default' => ''],
            'chatkit_font_size' => ['type' => 'string', 'default' => '16'],
            
            'chatkit_show_header' => ['type' => 'boolean', 'default' => true],
            'chatkit_show_history' => ['type' => 'boolean', 'default' => true],
            'chatkit_header_title_text' => ['type' => 'string', 'default' => ''],
            'chatkit_header_left_icon' => ['type' => 'string', 'default' => ''],
            'chatkit_header_left_url' => ['type' => 'string', 'default' => ''],
            'chatkit_header_right_icon' => ['type' => 'string', 'default' => ''],
            'chatkit_header_right_url' => ['type' => 'string', 'default' => ''],
            
            'chatkit_default_prompt_1_icon' => ['type' => 'string', 'default' => 'circle-question'],
            'chatkit_default_prompt_2_icon' => ['type' => 'string', 'default' => 'circle-question'],
            'chatkit_default_prompt_3_icon' => ['type' => 'string', 'default' => 'circle-question'],
            'chatkit_default_prompt_4' => ['type' => 'string', 'default' => ''],
            'chatkit_default_prompt_4_text' => ['type' => 'string', 'default' => ''],
            'chatkit_default_prompt_4_icon' => ['type' => 'string', 'default' => 'circle-question'],
            'chatkit_default_prompt_5' => ['type' => 'string', 'default' => ''],
            'chatkit_default_prompt_5_text' => ['type' => 'string', 'default' => ''],
            'chatkit_default_prompt_5_icon' => ['type' => 'string', 'default' => 'circle-question'],
            
            'chatkit_disclaimer_text' => ['type' => 'string', 'default' => ''],
            'chatkit_disclaimer_high_contrast' => ['type' => 'boolean', 'default' => false],
            'chatkit_initial_thread_id' => ['type' => 'string', 'default' => ''],
        ];

        foreach ($settings as $option => $args) {
            register_setting('chatkit_wp_settings', $option, [
                'type' => $args['type'],
                'sanitize_callback' => $this->get_sanitize_callback($args['type']),
                'default' => $args['default']
            ]);
        }
    }

    private function get_sanitize_callback($type) {
        switch ($type) {
            case 'boolean':
                return null;
            case 'string':
                return 'sanitize_text_field';
            default:
                return 'sanitize_text_field';
        }
    }

    private function get_all_options() {
        if (null === $this->options_cache) {
            $this->options_cache = [
                'api_key' => $this->get_api_key(),
                'workflow_id' => $this->get_workflow_id(),
                'accent_color' => get_option('chatkit_accent_color', '#FF4500'),
                'accent_level' => get_option('chatkit_accent_level', '2'),
                'button_text' => get_option('chatkit_button_text', __('Chat now', 'chatkit-wp')),
                'close_text' => get_option('chatkit_close_text', '✕'),
                'theme_mode' => get_option('chatkit_theme_mode', 'dark'),
                'enable_attachments' => get_option('chatkit_enable_attachments', false),
                'persistent_sessions' => get_option('chatkit_persistent_sessions', true),
                'show_everywhere' => get_option('chatkit_show_everywhere', false),
                'greeting_text' => get_option('chatkit_greeting_text', __('How can I help you today?', 'chatkit-wp')),
                'placeholder_text' => get_option('chatkit_placeholder_text', __('Send a message...', 'chatkit-wp')),
                'button_size' => get_option('chatkit_button_size', 'medium'),
                'button_position' => get_option('chatkit_button_position', 'bottom-right'),
                'border_radius' => get_option('chatkit_border_radius', 'round'),
                'shadow_style' => get_option('chatkit_shadow_style', 'normal'),
                'density' => get_option('chatkit_density', 'normal'),
                'locale' => get_option('chatkit_locale', ''),
                
                'default_prompt_1' => get_option('chatkit_default_prompt_1', __('How can I assist you?', 'chatkit-wp')),
                'default_prompt_1_text' => get_option('chatkit_default_prompt_1_text', __('Hi! How can I assist you today?', 'chatkit-wp')),
                'default_prompt_1_icon' => get_option('chatkit_default_prompt_1_icon', 'circle-question'),
                'default_prompt_2' => get_option('chatkit_default_prompt_2', ''),
                'default_prompt_2_text' => get_option('chatkit_default_prompt_2_text', ''),
                'default_prompt_2_icon' => get_option('chatkit_default_prompt_2_icon', 'circle-question'),
                'default_prompt_3' => get_option('chatkit_default_prompt_3', ''),
                'default_prompt_3_text' => get_option('chatkit_default_prompt_3_text', ''),
                'default_prompt_3_icon' => get_option('chatkit_default_prompt_3_icon', 'circle-question'),
                'default_prompt_4' => get_option('chatkit_default_prompt_4', ''),
                'default_prompt_4_text' => get_option('chatkit_default_prompt_4_text', ''),
                'default_prompt_4_icon' => get_option('chatkit_default_prompt_4_icon', 'circle-question'),
                'default_prompt_5' => get_option('chatkit_default_prompt_5', ''),
                'default_prompt_5_text' => get_option('chatkit_default_prompt_5_text', ''),
                'default_prompt_5_icon' => get_option('chatkit_default_prompt_5_icon', 'circle-question'),
                
                'attachment_max_size' => get_option('chatkit_attachment_max_size', '20'),
                'attachment_max_count' => get_option('chatkit_attachment_max_count', '3'),
                'enable_model_picker' => get_option('chatkit_enable_model_picker', false),
                'enable_tools' => get_option('chatkit_enable_tools', false),
                'enable_entity_tags' => get_option('chatkit_enable_entity_tags', false),
                'enable_custom_font' => get_option('chatkit_enable_custom_font', false),
                'font_family' => get_option('chatkit_font_family', ''),
                'font_size' => get_option('chatkit_font_size', '16'),
                'show_header' => get_option('chatkit_show_header', true),
                'show_history' => get_option('chatkit_show_history', true),
                'header_title_text' => get_option('chatkit_header_title_text', ''),
                'header_left_icon' => get_option('chatkit_header_left_icon', ''),
                'header_left_url' => get_option('chatkit_header_left_url', ''),
                'header_right_icon' => get_option('chatkit_header_right_icon', ''),
                'header_right_url' => get_option('chatkit_header_right_url', ''),
                'disclaimer_text' => get_option('chatkit_disclaimer_text', ''),
                'disclaimer_high_contrast' => get_option('chatkit_disclaimer_high_contrast', false),
                'initial_thread_id' => get_option('chatkit_initial_thread_id', ''),
            ];
        }
        return $this->options_cache;
    }

    private function should_show_widget() {
        if (!get_option('chatkit_show_everywhere', false)) {
            return false;
        }

        if (is_front_page() && get_option('chatkit_exclude_home', false)) {
            return false;
        }
        if (is_archive() && get_option('chatkit_exclude_archive', false)) {
            return false;
        }
        if (is_search() && get_option('chatkit_exclude_search', false)) {
            return false;
        }
        if (is_404() && get_option('chatkit_exclude_404', false)) {
            return false;
        }

        $exclude_ids = get_option('chatkit_exclude_ids', '');
        if (!empty($exclude_ids)) {
            $excluded_array = array_map('trim', explode(',', $exclude_ids));
            $current_id = get_queried_object_id();
            if (in_array($current_id, $excluded_array)) {
                return false;
            }
        }

        return true;
    }

    public function maybe_auto_inject_widget() {
        if ($this->widget_loaded) {
            return;
        }

        if ($this->should_show_widget()) {
            echo $this->render_chatkit_shortcode([]);
        }
    }

    public function add_body_attributes_script() {
        $options = $this->get_all_options();
        ?>
        <script>
        (function() {
            function applyAttributes() {
                const body = document.body;
                if (!body) return;
                
                body.setAttribute('data-chatkit-button-size', '<?php echo esc_js($options['button_size']); ?>');
                body.setAttribute('data-chatkit-position', '<?php echo esc_js($options['button_position']); ?>');
                body.setAttribute('data-chatkit-border-radius', '<?php echo esc_js($options['border_radius']); ?>');
                body.setAttribute('data-chatkit-shadow', '<?php echo esc_js($options['shadow_style']); ?>');
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', applyAttributes);
            } else {
                applyAttributes();
            }
        })();
        </script>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['chatkit_save_settings'])) {
            check_admin_referer('chatkit_settings_save');

            $text_fields = [
                'chatkit_api_key',
                'chatkit_workflow_id',
                'chatkit_button_text',
                'chatkit_close_text',
                'chatkit_theme_mode',
                'chatkit_greeting_text',
                'chatkit_placeholder_text',
                'chatkit_button_size',
                'chatkit_button_position',
                'chatkit_border_radius',
                'chatkit_shadow_style',
                'chatkit_density',
                'chatkit_locale',
                'chatkit_exclude_ids',
                'chatkit_accent_level',
                'chatkit_attachment_max_size',
                'chatkit_attachment_max_count',
                'chatkit_font_family',
                'chatkit_font_size',
                'chatkit_header_title_text',
                'chatkit_header_left_icon',
                'chatkit_header_left_url',
                'chatkit_header_right_icon',
                'chatkit_header_right_url',
                'chatkit_initial_thread_id',
                'chatkit_default_prompt_1',
                'chatkit_default_prompt_1_text',
                'chatkit_default_prompt_1_icon',
                'chatkit_default_prompt_2',
                'chatkit_default_prompt_2_text',
                'chatkit_default_prompt_2_icon',
                'chatkit_default_prompt_3',
                'chatkit_default_prompt_3_text',
                'chatkit_default_prompt_3_icon',
                'chatkit_default_prompt_4',
                'chatkit_default_prompt_4_text',
                'chatkit_default_prompt_4_icon',
                'chatkit_default_prompt_5',
                'chatkit_default_prompt_5_text',
                'chatkit_default_prompt_5_icon'
            ];

            foreach ($text_fields as $field) {
                if (isset($_POST[$field])) {
                    update_option($field, sanitize_text_field($_POST[$field]));
                }
            }

            if (isset($_POST['chatkit_disclaimer_text'])) {
                update_option('chatkit_disclaimer_text', sanitize_textarea_field($_POST['chatkit_disclaimer_text']));
            }

            if (isset($_POST['chatkit_accent_color'])) {
                update_option('chatkit_accent_color', sanitize_hex_color($_POST['chatkit_accent_color']));
            }

            $boolean_fields = [
                'chatkit_enable_attachments',
                'chatkit_persistent_sessions',
                'chatkit_show_everywhere',
                'chatkit_exclude_home',
                'chatkit_exclude_archive',
                'chatkit_exclude_search',
                'chatkit_exclude_404',
                'chatkit_enable_model_picker',
                'chatkit_enable_tools',
                'chatkit_enable_entity_tags',
                'chatkit_enable_custom_font',
                'chatkit_show_header',
                'chatkit_show_history',
                'chatkit_disclaimer_high_contrast'
            ];

            foreach ($boolean_fields as $field) {
                update_option($field, isset($_POST[$field]));
            }

            $this->options_cache = null;

            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'chatkit-wp') . '</p></div>';
        }

        $options = $this->get_all_options();
        extract($options);

        require_once CHATKIT_WP_PLUGIN_DIR . 'admin/settings-page.php';
    }

    public function register_rest_routes() {
        register_rest_route('chatkit/v1', '/session', [
            'methods' => 'POST',
            'callback' => [$this, 'create_session'],
            'permission_callback' => function(\WP_REST_Request $request) {
                $referer = wp_get_referer();
                $home_url = home_url();
                
                if ($referer && strpos($referer, $home_url) === 0) {
                    return true;
                }
                
                if (empty($referer) && !empty($_SERVER['HTTP_HOST'])) {
                    $current_host = parse_url($home_url, PHP_URL_HOST);
                    $request_host = sanitize_text_field($_SERVER['HTTP_HOST']);
                    if ($current_host === $request_host) {
                        return true;
                    }
                }
                
                if (current_user_can('manage_options')) {
                    return true;
                }
                
                return true;
            }
        ]);

        register_rest_route('chatkit/v1', '/test', [
            'methods' => 'POST',
            'callback' => [$this, 'test_connection'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function create_session(\WP_REST_Request $request) {
        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $fingerprint = md5($ip . $user_agent);
        
        $transient_key = 'chatkit_ratelimit_' . $fingerprint;
        $requests = get_transient($transient_key) ?: 0;

        $limit = current_user_can('manage_options') ? 100 : 10;

        if ($requests >= $limit) {
            error_log(sprintf('ChatKit rate limit exceeded for IP: %s', $ip));
            return new \WP_Error(
                'rate_limit_exceeded',
                __('Too many requests. Please try again in a minute.', 'chatkit-wp'),
                ['status' => 429]
            );
        }

        set_transient($transient_key, $requests + 1, 60);

        $api_key = $this->get_api_key();
        $workflow_id = $this->get_workflow_id();

        if (empty($api_key) || empty($workflow_id)) {
            return new \WP_Error(
                'missing_config',
                __('Plugin not configured. Contact administrator.', 'chatkit-wp'),
                ['status' => 500]
            );
        }

        if (!preg_match('/^wf_[a-zA-Z0-9_-]+$/', $workflow_id)) {
            error_log('ChatKit: Invalid workflow_id format');
            return new \WP_Error(
                'invalid_config',
                __('Invalid configuration.', 'chatkit-wp'),
                ['status' => 500]
            );
        }

        $user_id = $this->get_or_create_user_id();

        // Build session body with ChatKit configuration
        $session_body = [
            'workflow' => ['id' => $workflow_id],
            'user' => $user_id
        ];

        // Add file upload configuration if enabled
        $enable_attachments = get_option('chatkit_enable_attachments', false);
        if ($enable_attachments) {
            $max_size = (int) get_option('chatkit_attachment_max_size', 20);
            $max_count = (int) get_option('chatkit_attachment_max_count', 3);
            
            $session_body['chatkit_configuration'] = [
                'file_upload' => [
                    'enabled' => true,
                    'max_file_size' => $max_size,
                    'max_files' => $max_count
                ]
            ];
            
            error_log(sprintf('ChatKit: File upload enabled - max_size: %dMB, max_files: %d', $max_size, $max_count));
        } else {
            error_log('ChatKit: File upload disabled in settings');
        }

        $response = wp_remote_post('https://api.openai.com/v1/chatkit/sessions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'chatkit_beta=v1'
            ],
            'body' => wp_json_encode($session_body),
            'timeout' => 30,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            error_log('ChatKit API Error: ' . $response->get_error_message());
            return new \WP_Error(
                'api_error',
                $response->get_error_message(),
                ['status' => 502]
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200 || empty($body['client_secret'])) {
            error_log('ChatKit Session Error (Status ' . $status_code . '): ' . wp_remote_retrieve_body($response));
            return new \WP_Error(
                'invalid_response',
                __('Error creating session', 'chatkit-wp'),
                ['status' => $status_code]
            );
        }

        // Log session configuration for debugging
        if (!empty($body['chatkit_configuration']['file_upload']['enabled'])) {
            error_log('ChatKit: Session created with file upload enabled ✅');
        } else {
            error_log('ChatKit: Session created WITHOUT file upload ❌');
        }

        return rest_ensure_response([
            'client_secret' => $body['client_secret']
        ]);
    }

    public function test_connection() {
        $api_key = $this->get_api_key();
        $workflow_id = $this->get_workflow_id();

        if (empty($api_key)) {
            return new \WP_Error('missing_api_key', __('API Key not configured', 'chatkit-wp'), ['status' => 400]);
        }

        if (empty($workflow_id)) {
            return new \WP_Error('missing_workflow_id', __('Workflow ID not configured', 'chatkit-wp'), ['status' => 400]);
        }

        $response = wp_remote_post('https://api.openai.com/v1/chatkit/sessions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'chatkit_beta=v1'
            ],
            'body' => wp_json_encode([
                'workflow' => ['id' => $workflow_id],
                'user' => 'test_' . time()
            ]),
            'timeout' => 10,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('connection_failed', $response->get_error_message(), ['status' => 502]);
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200) {
            return rest_ensure_response(['message' => __('Connection successful! Plugin is correctly configured.', 'chatkit-wp')]);
        }

        $body = wp_remote_retrieve_body($response);
        return new \WP_Error('api_error', sprintf(__('API Error (status %d): %s', 'chatkit-wp'), $status_code, $body), ['status' => $status_code]);
    }

    public function enqueue_frontend_assets() {
        $show_everywhere = get_option('chatkit_show_everywhere', false);
        global $post;
        
        $should_load = false;
        
        if ($show_everywhere && $this->should_show_widget()) {
            $should_load = true;
        } elseif ($post && (has_shortcode($post->post_content, 'openai_chatkit') || has_shortcode($post->post_content, 'chatkit'))) {
            $should_load = true;
        }
        
        if (!$should_load) {
            return;
        }

        wp_enqueue_script(
            'chatkit-embed',
            CHATKIT_WP_PLUGIN_URL . 'assets/chatkit-embed.js',
            [],
            CHATKIT_WP_VERSION,
            true
        );

        wp_enqueue_style(
            'chatkit-embed',
            CHATKIT_WP_PLUGIN_URL . 'assets/chatkit-embed.css',
            [],
            CHATKIT_WP_VERSION
        );

        $options = $this->get_all_options();
        
        $prompts_config = [];
        for ($i = 1; $i <= 5; $i++) {
            $label = $options["default_prompt_{$i}"];
            $text = $options["default_prompt_{$i}_text"];
            $icon = $options["default_prompt_{$i}_icon"];
            
            if (!empty($label) && !empty($text)) {
                $prompts_config[] = [
                    'label' => $label,
                    'text' => $text,
                    'icon' => $icon
                ];
            }
        }

        wp_localize_script('chatkit-embed', 'chatkitConfig', [
            'restUrl' => rest_url('chatkit/v1/session'),
            'accentColor' => $options['accent_color'],
            'accentLevel' => (int) $options['accent_level'],
            'themeMode' => $options['theme_mode'],
            'enableAttachments' => $options['enable_attachments'] ? true : false,
            'attachmentMaxSize' => (int) $options['attachment_max_size'],
            'attachmentMaxCount' => (int) $options['attachment_max_count'],
            'buttonText' => $options['button_text'],
            'closeText' => $options['close_text'],
            'greetingText' => $options['greeting_text'],
            'placeholderText' => $options['placeholder_text'],
            'density' => $options['density'],
            'borderRadius' => $options['border_radius'],
            'locale' => $options['locale'],
            'prompts' => $prompts_config,
            'customFont' => $options['enable_custom_font'] && !empty($options['font_family']) ? [
                'fontFamily' => $options['font_family'],
                'baseSize' => (int) $options['font_size']
            ] : null,
            'showHeader' => $options['show_header'] ? true : false,
            'headerTitleText' => $options['header_title_text'],
            'headerLeftIcon' => $options['header_left_icon'],
            'headerLeftUrl' => $options['header_left_url'],
            'headerRightIcon' => $options['header_right_icon'],
            'headerRightUrl' => $options['header_right_url'],
            'historyEnabled' => $options['show_history'] ? true : false,
            'disclaimerText' => $options['disclaimer_text'],
            'disclaimerHighContrast' => $options['disclaimer_high_contrast'] ? true : false,
            'initialThreadId' => $options['initial_thread_id'],
            'i18n' => [
                'unableToStart' => __('Unable to start chat. Please try again later.', 'chatkit-wp'),
                'configError' => __('Chat configuration error. Please contact support.', 'chatkit-wp'),
                'loadFailed' => __('Chat widget failed to load. Please refresh the page.', 'chatkit-wp'),
            ]
        ]);
    }

    public function render_chatkit_shortcode($atts) {
        $this->widget_loaded = true;
        
        $atts = shortcode_atts([
            'button_text' => get_option('chatkit_button_text', __('Chat now', 'chatkit-wp')),
            'accent_color' => get_option('chatkit_accent_color', '#FF4500'),
        ], $atts, 'openai_chatkit');

        $atts['button_text'] = sanitize_text_field($atts['button_text']);
        $atts['accent_color'] = sanitize_hex_color($atts['accent_color']) ?: '#FF4500';

        ob_start();
        ?>
        <button id="chatToggleBtn" 
                type="button" 
                aria-label="<?php echo esc_attr__('Toggle chat window', 'chatkit-wp'); ?>"
                aria-expanded="false"
                style="background-color: <?php echo esc_attr($atts['accent_color']); ?>">
            <?php echo esc_html($atts['button_text']); ?>
        </button>
        <openai-chatkit id="myChatkit" 
                        role="dialog" 
                        aria-modal="false"
                        aria-label="<?php echo esc_attr__('Chat assistant', 'chatkit-wp'); ?>"></openai-chatkit>
        <?php
        return ob_get_clean();
    }

    private function get_api_key() {
        if (defined('CHATKIT_OPENAI_API_KEY') && !empty(CHATKIT_OPENAI_API_KEY)) {
            return CHATKIT_OPENAI_API_KEY;
        }
        return get_option('chatkit_api_key', '');
    }

    private function get_workflow_id() {
        if (defined('CHATKIT_WORKFLOW_ID') && !empty(CHATKIT_WORKFLOW_ID)) {
            return CHATKIT_WORKFLOW_ID;
        }
        return get_option('chatkit_workflow_id', '');
    }

    private function get_or_create_user_id() {
        $persistent = get_option('chatkit_persistent_sessions', true);

        if (!$persistent) {
            return 'guest_' . wp_generate_password(12, false);
        }

        $cookie_name = 'chatkit_user_id';

        if (!empty($_COOKIE[$cookie_name])) {
            $user_id = sanitize_text_field($_COOKIE[$cookie_name]);
            if (preg_match('/^user_[a-f0-9]{32}$/', $user_id)) {
                return $user_id;
            }
        }

        $user_id = 'user_' . md5(uniqid('chatkit_', true) . wp_rand());
        
        if (!headers_sent()) {
            $this->set_user_cookie($cookie_name, $user_id);
        }

        return $user_id;
    }

    private function set_user_cookie($name, $value) {
        $expire = time() + (DAY_IN_SECONDS * 30);
        
        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        } else {
            setcookie($name, $value, $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
    }
}

ChatKit_WordPress::get_instance();
