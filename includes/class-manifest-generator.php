<?php
/**
 * Manifest Generator Class
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Manifest_Generator {
    
    public function generate_all_manifests($demo_info, $export_data) {
        $manifests = array();
        
        // Generate main manifest
        $manifests['manifest.json'] = $this->generate_main_manifest($demo_info, $export_data);
        
        // Generate plugins manifest
        $manifests['plugins-manifest.json'] = $this->generate_plugins_manifest($demo_info, $export_data['plugins']);
        
        // Generate files manifest
        $manifests['files-manifest.json'] = $this->generate_files_manifest($demo_info, $export_data['files']);
        
        return $manifests;
    }
    
    private function generate_main_manifest($demo_info, $export_data) {
        $content_scanner = new Reign_Demo_Content_Scanner();
        $content_summary = $content_scanner->get_content_summary();
        
        // Get settings
        $settings_obj = new Reign_Demo_Exporter_Settings();
        $settings = $settings_obj->get_settings();
        
        // Get feature list based on active plugins
        $features = $this->determine_features();
        
        // Get demo requirements
        $requirements = $this->determine_requirements();
        
        // Use tags from settings or generate default
        $tags = !empty($settings['demo_tags']) ? 
            array_map('trim', explode(',', $settings['demo_tags'])) : 
            $this->generate_tags($demo_info['category'], $features);
        
        $manifest = array(
            'demo_id' => !empty($settings['demo_id']) ? $settings['demo_id'] : $demo_info['demo_id'],
            'demo_name' => !empty($settings['demo_name']) ? $settings['demo_name'] : $demo_info['demo_name'],
            'demo_category' => !empty($settings['demo_category']) ? $settings['demo_category'] : 'community',
            'demo_description' => !empty($settings['demo_description']) ? $settings['demo_description'] : '',
            'theme' => $demo_info['theme'],
            'theme_version' => $demo_info['theme_version'],
            'export_version' => $demo_info['export_version'],
            'export_date' => $demo_info['export_date'],
            'wordpress_version' => $demo_info['wordpress_version'],
            'site_url' => $demo_info['site_url'],
            'content_summary' => $content_summary,
            'theme_settings' => array(
                'customizer_settings' => !empty($export_data['content']['theme_mods']),
                'theme_options' => isset($export_data['content']['options']['reign_theme_options']),
                'reign_settings' => true
            ),
            'features' => $features,
            'requirements' => $requirements,
            'tags' => $tags
        );
        
        return $manifest;
    }
    
    private function generate_plugins_manifest($demo_info, $plugins_data) {
        $plugin_scanner = new Reign_Demo_Plugin_Scanner();
        return $plugin_scanner->generate_plugins_manifest($demo_info, $plugins_data);
    }
    
    private function generate_files_manifest($demo_info, $files_data) {
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'];
        
        $manifest = array(
            'demo_id' => $demo_info['demo_id'],
            'export_size' => $this->format_bytes($files_data['total_size']),
            'files' => array(
                'uploads' => array(
                    'total_size' => $this->format_bytes($files_data['uploads_size']),
                    'directories' => $this->analyze_upload_directories($upload_path),
                    'special_folders' => $this->get_special_folders()
                ),
                'theme_files' => array(
                    'custom_css' => file_exists(get_stylesheet_directory() . '/custom.css'),
                    'child_theme' => is_child_theme(),
                    'additional_files' => $this->get_additional_theme_files()
                ),
                'database_tables' => array(
                    'custom_tables' => $this->get_custom_tables()
                )
            )
        );
        
        return $manifest;
    }
    
    private function determine_features() {
        $features = array();
        
        // Check for major plugins and features
        $feature_checks = array(
            'BuddyPress' => 'buddypress/bp-loader.php',
            'BuddyBoss Platform' => 'buddyboss-platform/bp-loader.php',
            'WooCommerce' => 'woocommerce/woocommerce.php',
            'bbPress' => 'bbpress/bbpress.php',
            'LearnDash' => 'learndash/learndash.php',
            'LifterLMS' => 'lifterlms/lifterlms.php',
            'Tutor LMS' => 'tutor/tutor.php',
            'Sensei LMS' => 'sensei-lms/sensei-lms.php',
            'Elementor' => 'elementor/elementor.php',
            'Dokan' => 'dokan-lite/dokan.php',
            'WCFM' => 'wc-multivendor-marketplace/wc-multivendor-marketplace.php',
            'WC Vendors' => 'wc-vendors/class-wc-vendors.php',
            'PeepSo' => 'peepso-core/peepso.php',
            'GeoDirectory' => 'geodirectory/geodirectory.php',
            'WP Job Manager' => 'wp-job-manager/wp-job-manager.php',
            'Easy Digital Downloads' => 'easy-digital-downloads/easy-digital-downloads.php'
        );
        
        foreach ($feature_checks as $feature => $plugin) {
            if (is_plugin_active($plugin)) {
                $features[] = $feature;
            }
        }
        
        // Add specific features based on functionality
        if (is_plugin_active('buddypress/bp-loader.php')) {
            if (bp_is_active('groups')) {
                $features[] = 'Groups';
            }
            if (bp_is_active('activity')) {
                $features[] = 'Activity Feeds';
            }
            if (bp_is_active('members')) {
                $features[] = 'Members Directory';
            }
            if (bp_is_active('forums') || is_plugin_active('bbpress/bbpress.php')) {
                $features[] = 'Forums';
            }
        }
        
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            $features[] = 'Shopping Cart';
            $features[] = 'Product Catalog';
            
            if (is_plugin_active('dokan-lite/dokan.php') || 
                is_plugin_active('wc-multivendor-marketplace/wc-multivendor-marketplace.php') ||
                is_plugin_active('wc-vendors/class-wc-vendors.php')) {
                $features[] = 'Multi-vendor';
                $features[] = 'Vendor Dashboard';
            }
        }
        
        return $features;
    }
    
    private function determine_requirements() {
        return array(
            'wp_version' => '6.0+',
            'php_version' => '7.4+',
            'memory_limit' => '256M',
            'max_execution_time' => '300',
            'upload_max_filesize' => '64M'
        );
    }
    
    private function generate_tags($category, $features) {
        $tags = array($category);
        
        // Add feature-based tags
        $feature_tags = array(
            'BuddyPress' => 'buddypress',
            'WooCommerce' => 'woocommerce',
            'LearnDash' => 'learndash',
            'LifterLMS' => 'lifterlms',
            'Multi-vendor' => 'multivendor',
            'Forums' => 'forums',
            'Social' => 'social',
            'LMS' => 'lms'
        );
        
        foreach ($features as $feature) {
            if (isset($feature_tags[$feature])) {
                $tags[] = $feature_tags[$feature];
            }
        }
        
        // Add category-specific tags
        switch ($category) {
            case 'education':
                $tags[] = 'courses';
                $tags[] = 'learning';
                break;
            case 'marketplace':
                $tags[] = 'vendors';
                $tags[] = 'ecommerce';
                break;
            case 'community':
                $tags[] = 'social';
                $tags[] = 'networking';
                break;
            case 'job-board':
                $tags[] = 'jobs';
                $tags[] = 'recruitment';
                break;
        }
        
        return array_unique($tags);
    }
    
    private function analyze_upload_directories($upload_path) {
        $directories = array();
        $year_dirs = glob($upload_path . '/20*', GLOB_ONLYDIR);
        
        foreach ($year_dirs as $dir) {
            $dir_name = basename($dir);
            $size = $this->get_directory_size($dir);
            $file_count = $this->count_files_in_directory($dir);
            
            $directories[] = array(
                'path' => $dir_name . '/',
                'size' => $this->format_bytes($size),
                'file_count' => $file_count
            );
        }
        
        return $directories;
    }
    
    private function get_special_folders() {
        $upload_dir = wp_upload_dir();
        $special_folders = array();
        
        $folders_to_check = array(
            'buddypress',
            'buddyboss',
            'woocommerce_uploads',
            'elementor',
            'learndash',
            'geodirectory',
            'peepso'
        );
        
        foreach ($folders_to_check as $folder) {
            if (is_dir($upload_dir['basedir'] . '/' . $folder)) {
                $special_folders[] = $folder . '/';
            }
        }
        
        return $special_folders;
    }
    
    private function get_additional_theme_files() {
        $additional_files = array();
        $theme_dir = get_stylesheet_directory();
        
        $check_dirs = array('fonts', 'custom-templates', 'images', 'js', 'css');
        
        foreach ($check_dirs as $dir) {
            if (is_dir($theme_dir . '/' . $dir)) {
                $additional_files[] = $dir . '/';
            }
        }
        
        return $additional_files;
    }
    
    private function get_custom_tables() {
        global $wpdb;
        $custom_tables = array();
        
        // Get all tables
        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $wp_prefix = $wpdb->prefix;
        
        // Known custom table patterns
        $custom_patterns = array(
            'reign_',
            'bp_',
            'wc_',
            'learndash_',
            'lifterlms_',
            'geodir_',
            'peepso_'
        );
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // Skip default WordPress tables
            if (strpos($table_name, $wp_prefix) === 0) {
                $table_suffix = str_replace($wp_prefix, '', $table_name);
                
                // Check if it's a custom table
                foreach ($custom_patterns as $pattern) {
                    if (strpos($table_suffix, $pattern) === 0) {
                        $custom_tables[] = $table_suffix;
                        break;
                    }
                }
            }
        }
        
        return $custom_tables;
    }
    
    private function get_directory_size($dir) {
        return Reign_Demo_Exporter_Utils::get_directory_size($dir);
    }
    
    private function count_files_in_directory($dir) {
        $count = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
        
        return $count;
    }
    
    private function format_bytes($bytes, $precision = 2) {
        return Reign_Demo_Exporter_Utils::format_bytes($bytes, $precision);
    }
}