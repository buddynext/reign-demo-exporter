<?php
/**
 * AJAX Handler Class
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Ajax_Handler {
    
    private $exporter;
    
    public function __construct() {
        $this->exporter = new Reign_Demo_Core_Exporter();
        
        // Add action for getting export results
        add_action('wp_ajax_reign_demo_get_export_results', array($this, 'get_export_results'));
    }
    
    public function process_export_step() {
        // Verify request
        $verification = Reign_Demo_Exporter_Utils::verify_ajax_request();
        if (is_wp_error($verification)) {
            wp_send_json_error(array('message' => $verification->get_error_message()));
        }
        
        $step = isset($_POST['step']) ? sanitize_text_field($_POST['step']) : '';
        
        // Validate step
        $valid_steps = array(
            'preparing', 'scanning_content', 'analyzing_plugins', 
            'scanning_files', 'creating_manifests', 'packaging_content', 
            'finalizing'
        );
        
        if (!in_array($step, $valid_steps)) {
            wp_send_json_error(array('message' => __('Invalid export step', 'reign-demo-exporter')));
        }
        
        try {
            switch ($step) {
                case 'preparing':
                    $result = $this->prepare_export();
                    break;
                    
                case 'scanning_content':
                    $result = $this->scan_content();
                    break;
                    
                case 'analyzing_plugins':
                    $result = $this->analyze_plugins();
                    break;
                    
                case 'scanning_files':
                    $result = $this->scan_files();
                    break;
                    
                case 'creating_manifests':
                    $result = $this->create_manifests();
                    break;
                    
                case 'packaging_content':
                    $result = $this->package_content();
                    break;
                    
                case 'finalizing':
                    $result = $this->finalize_export();
                    break;
                    
                default:
                    wp_send_json_error(array('message' => __('Invalid export step', 'reign-demo-exporter')));
                    return;
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    private function prepare_export() {
        // Set up export environment
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        
        // Clear any previous export data
        delete_transient('reign_demo_export_data');
        delete_transient('reign_demo_export_progress');
        
        // Create export directory if it doesn't exist
        if (!file_exists(REIGN_DEMO_EXPORT_DIR)) {
            wp_mkdir_p(REIGN_DEMO_EXPORT_DIR);
        }
        
        // Initialize export data
        set_transient('reign_demo_export_data', array(
            'start_time' => time(),
            'status' => 'preparing'
        ), HOUR_IN_SECONDS);
        
        return array(
            'message' => __('Export preparation complete', 'reign-demo-exporter'),
            'progress' => 5
        );
    }
    
    private function scan_content() {
        $content_scanner = new Reign_Demo_Content_Scanner();
        $content = $content_scanner->scan_all_content();
        
        // Store in transient
        $export_data = get_transient('reign_demo_export_data');
        $export_data['content'] = $content;
        $export_data['content_summary'] = $content_scanner->get_content_summary();
        set_transient('reign_demo_export_data', $export_data, HOUR_IN_SECONDS);
        
        return array(
            'message' => __('Content scan complete', 'reign-demo-exporter'),
            'progress' => 25,
            'summary' => $export_data['content_summary']
        );
    }
    
    private function analyze_plugins() {
        $plugin_scanner = new Reign_Demo_Plugin_Scanner();
        $plugins = $plugin_scanner->scan_plugins();
        
        // Store in transient
        $export_data = get_transient('reign_demo_export_data');
        $export_data['plugins'] = $plugins;
        set_transient('reign_demo_export_data', $export_data, HOUR_IN_SECONDS);
        
        return array(
            'message' => __('Plugin analysis complete', 'reign-demo-exporter'),
            'progress' => 35,
            'plugin_count' => count($plugins['required']) + count($plugins['optional'])
        );
    }
    
    private function scan_files() {
        $file_scanner = new Reign_Demo_File_Scanner();
        $files = $file_scanner->scan_files();
        
        // Store in transient
        $export_data = get_transient('reign_demo_export_data');
        $export_data['files'] = $files;
        set_transient('reign_demo_export_data', $export_data, HOUR_IN_SECONDS);
        
        return array(
            'message' => __('File scan complete', 'reign-demo-exporter'),
            'progress' => 50,
            'total_size' => $this->format_bytes($files['total_size'])
        );
    }
    
    private function create_manifests() {
        $export_data = get_transient('reign_demo_export_data');
        
        if (!$export_data) {
            throw new Exception(__('Export data not found', 'reign-demo-exporter'));
        }
        
        // Get demo info
        $exporter = new Reign_Demo_Core_Exporter();
        $reflection = new ReflectionClass($exporter);
        $method = $reflection->getMethod('set_demo_info');
        $method->setAccessible(true);
        $method->invoke($exporter);
        
        $property = $reflection->getProperty('demo_info');
        $property->setAccessible(true);
        $demo_info = $property->getValue($exporter);
        
        // Generate manifests
        $manifest_generator = new Reign_Demo_Manifest_Generator();
        $manifests = $manifest_generator->generate_all_manifests($demo_info, $export_data);
        
        // Save manifests
        foreach ($manifests as $filename => $content) {
            $file_path = REIGN_DEMO_EXPORT_DIR . $filename;
            if (false === file_put_contents($file_path, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                throw new Exception(sprintf(__('Failed to save %s', 'reign-demo-exporter'), $filename));
            }
        }
        
        // Store manifest info
        $export_data['manifests'] = array_keys($manifests);
        set_transient('reign_demo_export_data', $export_data, HOUR_IN_SECONDS);
        
        return array(
            'message' => __('Manifest files created', 'reign-demo-exporter'),
            'progress' => 70,
            'manifests' => $export_data['manifests']
        );
    }
    
    private function package_content() {
        $export_data = get_transient('reign_demo_export_data');
        
        if (!$export_data) {
            throw new Exception(__('Export data not found', 'reign-demo-exporter'));
        }
        
        $package_creator = new Reign_Demo_Package_Creator();
        $package_path = $package_creator->create_package($export_data);
        
        // Move to export directory
        $destination = REIGN_DEMO_EXPORT_DIR . 'content-package.zip';
        if (file_exists($destination)) {
            unlink($destination);
        }
        
        if (!rename($package_path, $destination)) {
            throw new Exception(__('Failed to move content package', 'reign-demo-exporter'));
        }
        
        $export_data['package_path'] = $destination;
        $export_data['package_size'] = filesize($destination);
        set_transient('reign_demo_export_data', $export_data, HOUR_IN_SECONDS);
        
        return array(
            'message' => __('Content package created', 'reign-demo-exporter'),
            'progress' => 95,
            'package_size' => $this->format_bytes($export_data['package_size'])
        );
    }
    
    private function finalize_export() {
        $export_data = get_transient('reign_demo_export_data');
        
        if (!$export_data) {
            throw new Exception(__('Export data not found', 'reign-demo-exporter'));
        }
        
        // Create export log
        $log_data = array(
            'export_id' => uniqid('reign_export_'),
            'export_date' => current_time('c'),
            'export_duration' => time() - $export_data['start_time'],
            'statistics' => array(
                'posts' => isset($export_data['content_summary']['posts']) ? $export_data['content_summary']['posts'] : 0,
                'pages' => isset($export_data['content_summary']['pages']) ? $export_data['content_summary']['pages'] : 0,
                'users' => isset($export_data['content_summary']['users']) ? $export_data['content_summary']['users'] : 0,
                'plugins' => count($export_data['plugins']['required']) + count($export_data['plugins']['optional']),
                'active_plugins' => count($export_data['plugins']['required']),
                'package_size' => $export_data['package_size']
            ),
            'files' => array(
                'manifest.json' => REIGN_DEMO_EXPORT_URL . 'manifest.json',
                'plugins-manifest.json' => REIGN_DEMO_EXPORT_URL . 'plugins-manifest.json',
                'files-manifest.json' => REIGN_DEMO_EXPORT_URL . 'files-manifest.json',
                'content-package.zip' => REIGN_DEMO_EXPORT_URL . 'content-package.zip'
            )
        );
        
        update_option('reign_demo_last_export', $log_data);
        
        // Clean up transients
        delete_transient('reign_demo_export_data');
        delete_transient('reign_demo_export_progress');
        
        return array(
            'message' => __('Export completed successfully!', 'reign-demo-exporter'),
            'progress' => 100,
            'export_url' => REIGN_DEMO_EXPORT_URL,
            'files' => $log_data['files']
        );
    }
    
    public function get_export_results() {
        // Verify request
        $verification = Reign_Demo_Exporter_Utils::verify_ajax_request();
        if (is_wp_error($verification)) {
            wp_send_json_error(array('message' => $verification->get_error_message()));
        }
        
        $last_export = get_option('reign_demo_last_export');
        
        if ($last_export && isset($last_export['files'])) {
            wp_send_json_success(array(
                'message' => __('Export completed successfully!', 'reign-demo-exporter'),
                'files' => $last_export['files'],
                'statistics' => $last_export['statistics']
            ));
        } else {
            wp_send_json_error(array('message' => __('No export data found', 'reign-demo-exporter')));
        }
    }
    
    public function check_system_requirements() {
        // Verify request
        $verification = Reign_Demo_Exporter_Utils::verify_ajax_request();
        if (is_wp_error($verification)) {
            wp_send_json_error(array('message' => $verification->get_error_message()));
        }
        
        $requirements = array(
            'php_version' => array(
                'label' => __('PHP Version', 'reign-demo-exporter'),
                'required' => '7.4+',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4', '>=')
            ),
            'wp_version' => array(
                'label' => __('WordPress Version', 'reign-demo-exporter'),
                'required' => '6.0+',
                'current' => get_bloginfo('version'),
                'status' => version_compare(get_bloginfo('version'), '6.0', '>=')
            ),
            'memory_limit' => array(
                'label' => __('Memory Limit', 'reign-demo-exporter'),
                'required' => '256M',
                'current' => ini_get('memory_limit'),
                'status' => $this->check_memory_limit('256M')
            ),
            'max_execution_time' => array(
                'label' => __('Max Execution Time', 'reign-demo-exporter'),
                'required' => '300 seconds',
                'current' => ini_get('max_execution_time') . ' seconds',
                'status' => ini_get('max_execution_time') == 0 || ini_get('max_execution_time') >= 300
            ),
            'reign_theme' => array(
                'label' => __('Reign Theme', 'reign-demo-exporter'),
                'required' => __('Active', 'reign-demo-exporter'),
                'current' => $this->is_reign_theme_active() ? __('Active', 'reign-demo-exporter') : __('Not Active', 'reign-demo-exporter'),
                'status' => $this->is_reign_theme_active()
            ),
            'disk_space' => array(
                'label' => __('Available Disk Space', 'reign-demo-exporter'),
                'required' => '500MB+',
                'current' => $this->get_available_disk_space(),
                'status' => $this->check_disk_space()
            ),
            'export_directory' => array(
                'label' => __('Export Directory', 'reign-demo-exporter'),
                'required' => __('Writable', 'reign-demo-exporter'),
                'current' => is_writable(WP_CONTENT_DIR) ? __('Writable', 'reign-demo-exporter') : __('Not Writable', 'reign-demo-exporter'),
                'status' => is_writable(WP_CONTENT_DIR)
            )
        );
        
        wp_send_json_success($requirements);
    }
    
    private function check_memory_limit($required) {
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit == -1) {
            return true;
        }
        
        $memory_bytes = $this->convert_to_bytes($memory_limit);
        $required_bytes = $this->convert_to_bytes($required);
        
        return $memory_bytes >= $required_bytes;
    }
    
    private function convert_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    private function is_reign_theme_active() {
        $theme = wp_get_theme();
        $parent_theme = $theme->parent();
        
        return ($theme->get('Name') === 'Reign' || ($parent_theme && $parent_theme->get('Name') === 'Reign'));
    }
    
    private function get_available_disk_space() {
        $bytes = disk_free_space(WP_CONTENT_DIR);
        return $this->format_bytes($bytes);
    }
    
    private function check_disk_space() {
        $bytes = disk_free_space(WP_CONTENT_DIR);
        return $bytes > (500 * 1024 * 1024); // 500MB
    }
    
    private function format_bytes($bytes, $precision = 2) {
        return Reign_Demo_Exporter_Utils::format_bytes($bytes, $precision);
    }
}