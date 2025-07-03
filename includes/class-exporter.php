<?php
/**
 * Core Exporter Class
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Core_Exporter {
    
    private $export_data = array();
    private $export_id;
    private $demo_info = array();
    
    public function __construct() {
        $this->export_id = 'reign-' . sanitize_title(get_bloginfo('name')) . '-' . date('YmdHis');
        $this->set_demo_info();
    }
    
    private function set_demo_info() {
        $theme = wp_get_theme();
        
        // Extract demo ID from site URL or title
        $site_url = get_site_url();
        $demo_slug = $this->extract_demo_slug($site_url);
        
        $this->demo_info = array(
            'demo_id' => $demo_slug,
            'demo_name' => get_bloginfo('name'),
            'demo_slug' => sanitize_title($demo_slug),
            'theme' => $theme->get('Name'),
            'theme_version' => $theme->get('Version'),
            'export_version' => REIGN_DEMO_EXPORTER_VERSION,
            'export_date' => current_time('c'),
            'wordpress_version' => get_bloginfo('version'),
            'site_url' => $site_url,
            'demo_url' => $site_url,
            'admin_url' => admin_url(),
            'preview_image' => $this->get_preview_image_url($demo_slug),
            'description' => get_bloginfo('description'),
            'category' => $this->determine_demo_category(),
            'version' => '1.0.0'
        );
    }
    
    private function extract_demo_slug($url) {
        // Extract demo name from URL
        $parsed = parse_url($url);
        $host = $parsed['host'];
        
        // Remove common suffixes and prefixes
        $slug = str_replace(array('installer.wbcomdesigns.com/', 'https://', 'http://', '/'), '', $url);
        $slug = trim($slug, '/');
        
        // If it's a subdirectory install, get the directory name
        if (!empty($parsed['path']) && $parsed['path'] !== '/') {
            $slug = trim($parsed['path'], '/');
        }
        
        return 'reign-' . $slug;
    }
    
    private function get_preview_image_url($demo_slug) {
        // Construct preview image URL for central hub
        $clean_slug = str_replace('reign-', '', $demo_slug);
        return 'https://installer.wbcomdesigns.com/reign-demos/demo-assets/previews/' . $clean_slug . '.jpg';
    }
    
    private function determine_demo_category() {
        // Determine category based on active plugins and content
        $categories = array(
            'community' => array('buddypress', 'bbpress', 'peepso'),
            'education' => array('learndash', 'lifterlms', 'tutor', 'sensei'),
            'marketplace' => array('dokan', 'wcfm', 'wc-vendors'),
            'ecommerce' => array('woocommerce'),
            'job-board' => array('wp-job-manager'),
            'directory' => array('geodirectory'),
            'nonprofit' => array('give', 'charitable'),
            'dating' => array('bp-better-messages', 'sweet-date')
        );
        
        $active_plugins = get_option('active_plugins', array());
        
        foreach ($categories as $category => $plugins) {
            foreach ($plugins as $plugin) {
                foreach ($active_plugins as $active_plugin) {
                    if (strpos($active_plugin, $plugin) !== false) {
                        return $category;
                    }
                }
            }
        }
        
        return 'general';
    }
    
    public function run_export() {
        try {
            // Set maximum execution time
            @set_time_limit(0);
            @ini_set('memory_limit', '512M');
            
            // Initialize scanners
            $content_scanner = new Reign_Demo_Content_Scanner();
            $plugin_scanner = new Reign_Demo_Plugin_Scanner();
            $file_scanner = new Reign_Demo_File_Scanner();
            $manifest_generator = new Reign_Demo_Manifest_Generator();
            
            // Step 1: Scan content
            $this->export_data['content'] = $content_scanner->scan_all_content();
            
            // Step 2: Scan plugins
            $this->export_data['plugins'] = $plugin_scanner->scan_plugins();
            
            // Step 3: Scan files
            $this->export_data['files'] = $file_scanner->scan_files();
            
            // Step 4: Generate manifests
            $manifests = $manifest_generator->generate_all_manifests(
                $this->demo_info,
                $this->export_data
            );
            
            // Step 5: Create content package
            $package_creator = new Reign_Demo_Package_Creator();
            $package_path = $package_creator->create_package($this->export_data);
            
            // Step 6: Save manifests to export directory
            $this->save_manifests($manifests);
            
            // Step 7: Move package to export directory
            $this->move_package($package_path);
            
            // Step 8: Create success log
            $this->create_export_log();
            
            return array(
                'success' => true,
                'message' => __('Export completed successfully!', 'reign-demo-exporter'),
                'export_url' => REIGN_DEMO_EXPORT_URL,
                'files' => array(
                    'manifest' => REIGN_DEMO_EXPORT_URL . 'manifest.json',
                    'plugins' => REIGN_DEMO_EXPORT_URL . 'plugins-manifest.json',
                    'files' => REIGN_DEMO_EXPORT_URL . 'files-manifest.json',
                    'package' => REIGN_DEMO_EXPORT_URL . 'content-package.zip'
                )
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    private function save_manifests($manifests) {
        foreach ($manifests as $filename => $content) {
            $file_path = REIGN_DEMO_EXPORT_DIR . $filename;
            
            if (false === file_put_contents($file_path, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                throw new Exception(sprintf(__('Failed to save %s', 'reign-demo-exporter'), $filename));
            }
        }
    }
    
    private function move_package($source_path) {
        $destination = REIGN_DEMO_EXPORT_DIR . 'content-package.zip';
        
        if (!rename($source_path, $destination)) {
            throw new Exception(__('Failed to move content package to export directory', 'reign-demo-exporter'));
        }
    }
    
    private function create_export_log() {
        $log_data = array(
            'export_id' => $this->export_id,
            'export_date' => current_time('c'),
            'demo_info' => $this->demo_info,
            'statistics' => array(
                'posts' => wp_count_posts()->publish,
                'pages' => wp_count_posts('page')->publish,
                'users' => count_users()['total_users'],
                'plugins' => count(get_plugins()),
                'active_plugins' => count(get_option('active_plugins', array()))
            )
        );
        
        update_option('reign_demo_last_export', $log_data);
    }
    
    public function get_export_progress() {
        return get_transient('reign_demo_export_progress');
    }
    
    public function update_export_progress($step, $progress, $message) {
        set_transient('reign_demo_export_progress', array(
            'step' => $step,
            'progress' => $progress,
            'message' => $message
        ), 300); // 5 minutes
    }
}