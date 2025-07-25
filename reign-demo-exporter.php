<?php
/**
 * Plugin Name: Reign Demo Exporter
 * Plugin URI: https://wbcomdesigns.com/
 * Description: One-click export tool to generate JSON manifests and content packages for Reign Theme demos
 * Version: 1.0.0
 * Author: WB Com Designs
 * Author URI: https://wbcomdesigns.com/
 * License: GPL v2 or later
 * Text Domain: reign-demo-exporter
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('REIGN_DEMO_EXPORTER_VERSION', '1.0.0');
define('REIGN_DEMO_EXPORTER_PATH', plugin_dir_path(__FILE__));
define('REIGN_DEMO_EXPORTER_URL', plugin_dir_url(__FILE__));
define('REIGN_DEMO_EXPORT_DIR', WP_CONTENT_DIR . '/reign-demo-export/');
define('REIGN_DEMO_EXPORT_URL', content_url('reign-demo-export/'));

// Main plugin class
class Reign_Demo_Exporter {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize settings
        new Reign_Demo_Exporter_Settings();
        
        // Initialize hooks
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_reign_demo_export_step', array($this, 'handle_export_step'));
        add_action('wp_ajax_reign_demo_check_requirements', array($this, 'check_requirements'));
        add_action('wp_ajax_reign_demo_delete_export', array($this, 'handle_delete_export'));
        add_action('wp_ajax_reign_demo_delete_all_exports', array($this, 'handle_delete_all_exports'));
        
        // Create export directory on activation
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
    }
    
    private function load_dependencies() {
        require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-utils.php';
        require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-exporter.php';
        require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-content-scanner.php';
        require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-settings.php';
        require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-plugin-scanner.php';
        require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-file-scanner.php';
        require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-manifest-generator.php';
        require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-uploads-exporter.php';
        require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-package-creator.php';
        require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-ajax-handler.php';
        
        if (is_admin()) {
            require_once REIGN_DEMO_EXPORTER_PATH . 'admin/class-admin.php';
        }
        
        // Load WP-CLI command if available
        if (defined('WP_CLI') && WP_CLI) {
            require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-cli-command.php';
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('reign-demo-exporter', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function add_admin_menu() {
        // Add export page under Tools
        add_submenu_page(
            'tools.php',
            __('Reign Demo Export', 'reign-demo-exporter'),
            __('Reign Demo Export', 'reign-demo-exporter'),
            'manage_options',
            'reign-demo-export',
            array($this, 'render_admin_page')
        );
        
        // Add settings page under Settings
        add_options_page(
            __('Reign Demo Exporter Settings', 'reign-demo-exporter'),
            __('Reign Demo Exporter', 'reign-demo-exporter'),
            'manage_options',
            'reign-demo-exporter-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function render_admin_page() {
        $admin = new Reign_Demo_Exporter_Admin();
        $admin->render();
    }
    
    public function render_settings_page() {
        $settings = new Reign_Demo_Exporter_Settings();
        $settings->render_settings_page();
    }
    
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_reign-demo-export') {
            return;
        }
        
        wp_enqueue_style(
            'reign-demo-exporter-admin',
            REIGN_DEMO_EXPORTER_URL . 'admin/css/exporter-admin.css',
            array(),
            REIGN_DEMO_EXPORTER_VERSION
        );
        
        wp_enqueue_script(
            'reign-demo-exporter-admin',
            REIGN_DEMO_EXPORTER_URL . 'admin/js/exporter-admin.js',
            array('jquery'),
            REIGN_DEMO_EXPORTER_VERSION,
            true
        );
        
        wp_localize_script('reign-demo-exporter-admin', 'reign_demo_exporter', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reign_demo_export_nonce'),
            'export_url' => REIGN_DEMO_EXPORT_URL,
            'messages' => array(
                'confirm_export' => __('Are you sure you want to start the export? This may take several minutes and will overwrite any existing export files.', 'reign-demo-exporter'),
                'export_in_progress' => __('Export in progress...', 'reign-demo-exporter'),
                'export_complete' => __('Export completed successfully!', 'reign-demo-exporter'),
                'export_failed' => __('Export failed. Please check the error log.', 'reign-demo-exporter'),
            )
        ));
    }
    
    public function handle_export_step() {
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $ajax_handler->process_export_step();
    }
    
    public function check_requirements() {
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $ajax_handler->check_system_requirements();
    }
    
    public function handle_delete_export() {
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $ajax_handler->delete_export_file();
    }
    
    public function handle_delete_all_exports() {
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $ajax_handler->delete_all_export_files();
    }
    
    public function activate() {
        // Create export directory
        if (!file_exists(REIGN_DEMO_EXPORT_DIR)) {
            wp_mkdir_p(REIGN_DEMO_EXPORT_DIR);
        }
        
        // Create .htaccess for public access
        $htaccess_content = '
# Allow access to JSON and ZIP files
<FilesMatch "\.(json|zip)$">
    Order allow,deny
    Allow from all
</FilesMatch>

# Prevent directory listing
Options -Indexes
        ';
        
        @file_put_contents(REIGN_DEMO_EXPORT_DIR . '.htaccess', trim($htaccess_content));
        
        // Create index.php for security
        @file_put_contents(REIGN_DEMO_EXPORT_DIR . 'index.php', '<?php // Silence is golden');
    }
    
    /**
     * Add plugin action links
     * 
     * @param array $links Existing plugin action links
     * @return array Modified plugin action links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=reign-demo-exporter-settings') . '">' . __('Settings', 'reign-demo-exporter') . '</a>';
        
        // Add settings link after deactivate link
        array_splice($links, 1, 0, array($settings_link));
        
        return $links;
    }
}

// Initialize plugin
Reign_Demo_Exporter::get_instance();