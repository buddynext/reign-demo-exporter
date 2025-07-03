<?php
/**
 * Plugin Scanner Class
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Plugin_Scanner {
    
    private $plugin_categories = array(
        'wordpress_org' => array(),
        'premium' => array(),
        'custom' => array()
    );
    
    private $known_premium_plugins = array(
        'elementor-pro' => array(
            'name' => 'Elementor Pro',
            'purchase_url' => 'https://elementor.com/pro/',
            'license_required' => true
        ),
        'buddyboss-platform-pro' => array(
            'name' => 'BuddyBoss Platform Pro',
            'purchase_url' => 'https://www.buddyboss.com/platform/',
            'license_required' => true
        ),
        'learndash' => array(
            'name' => 'LearnDash LMS',
            'purchase_url' => 'https://www.learndash.com/',
            'license_required' => true
        ),
        'lifterlms' => array(
            'name' => 'LifterLMS',
            'purchase_url' => 'https://lifterlms.com/',
            'license_required' => true
        ),
        'tutor-pro' => array(
            'name' => 'Tutor LMS Pro',
            'purchase_url' => 'https://www.themeum.com/product/tutor-lms/',
            'license_required' => true
        ),
        'sensei-pro' => array(
            'name' => 'Sensei Pro',
            'purchase_url' => 'https://senseilms.com/',
            'license_required' => true
        ),
        'dokan-pro' => array(
            'name' => 'Dokan Pro',
            'purchase_url' => 'https://wedevs.com/dokan/',
            'license_required' => true
        ),
        'wcfm-ultimate' => array(
            'name' => 'WCFM Ultimate',
            'purchase_url' => 'https://wclovers.com/product/woocommerce-frontend-manager-ultimate/',
            'license_required' => true
        ),
        'wc-vendors-pro' => array(
            'name' => 'WC Vendors Pro',
            'purchase_url' => 'https://www.wcvendors.com/',
            'license_required' => true
        ),
        'geodirectory' => array(
            'name' => 'GeoDirectory',
            'purchase_url' => 'https://wpgeodirectory.com/',
            'license_required' => true
        ),
        'wp-job-manager' => array(
            'name' => 'WP Job Manager',
            'purchase_url' => 'https://wpjobmanager.com/',
            'license_required' => false
        )
    );
    
    private $reign_plugins = array(
        'reign-theme-addon',
        'wbcom-essential',
        'reign-buddypress-addon',
        'reign-peepso-addon',
        'reign-learndash-addon',
        'reign-lifterlms-addon',
        'reign-tutorlms-addon',
        'reign-sensei-addon',
        'reign-dokan-addon',
        'reign-wcfm-addon',
        'reign-wc-vendors-addon',
        'reign-geodirectory-addon',
        'reign-wp-job-manager-addon',
        'reign-edd-addon'
    );
    
    public function scan_plugins() {
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        
        $plugins_data = array(
            'required' => array(),
            'optional' => array()
        );
        
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins);
            
            if ($is_active) {
                $plugin_info = $this->get_plugin_info($plugin_file, $plugin_data);
                
                // Determine if required or optional
                if ($this->is_required_plugin($plugin_file)) {
                    $plugins_data['required'][] = $plugin_info;
                } else {
                    $plugins_data['optional'][] = $plugin_info;
                }
            }
        }
        
        return $plugins_data;
    }
    
    private function get_plugin_info($plugin_file, $plugin_data) {
        $plugin_slug = $this->get_plugin_slug($plugin_file);
        $plugin_source = $this->determine_plugin_source($plugin_slug, $plugin_file);
        
        $info = array(
            'name' => $plugin_data['Name'],
            'slug' => $plugin_slug,
            'version' => $plugin_data['Version'],
            'source' => $plugin_source['type'],
            'type' => $plugin_source['category'],
            'status' => 'active',
            'required' => $this->is_required_plugin($plugin_file)
        );
        
        // Add source-specific data
        if ($plugin_source['type'] === 'wordpress.org') {
            $info['source_url'] = 'https://wordpress.org/plugins/' . $plugin_slug . '/';
        } elseif ($plugin_source['type'] === 'purchase') {
            if (isset($this->known_premium_plugins[$plugin_slug])) {
                $info['purchase_url'] = $this->known_premium_plugins[$plugin_slug]['purchase_url'];
                $info['license_required'] = $this->known_premium_plugins[$plugin_slug]['license_required'];
            }
        } elseif ($plugin_source['type'] === 'self-hosted') {
            $info['download_url'] = 'https://installer.wbcomdesigns.com/reign-demos/plugins/' . $plugin_slug . '.zip';
        }
        
        return $info;
    }
    
    private function get_plugin_slug($plugin_file) {
        // Extract slug from plugin file path
        if (strpos($plugin_file, '/') !== false) {
            $parts = explode('/', $plugin_file);
            return $parts[0];
        }
        
        // Single file plugin
        return str_replace('.php', '', $plugin_file);
    }
    
    private function determine_plugin_source($slug, $plugin_file) {
        // Check if it's a Reign/WBcom plugin
        if (in_array($slug, $this->reign_plugins)) {
            return array(
                'type' => 'self-hosted',
                'category' => 'custom'
            );
        }
        
        // Check if it's a known premium plugin
        if (isset($this->known_premium_plugins[$slug])) {
            return array(
                'type' => 'purchase',
                'category' => 'premium'
            );
        }
        
        // Check if plugin exists on WordPress.org
        if ($this->check_wordpress_org($slug)) {
            return array(
                'type' => 'wordpress.org',
                'category' => 'free'
            );
        }
        
        // Default to premium/unknown
        return array(
            'type' => 'purchase',
            'category' => 'premium'
        );
    }
    
    private function check_wordpress_org($slug) {
        // Check cached results first
        $cached = get_transient('reign_plugin_wp_org_' . $slug);
        if ($cached !== false) {
            return $cached;
        }
        
        // Check WordPress.org API
        $api_url = 'https://api.wordpress.org/plugins/info/1.0/' . $slug . '.json';
        $response = wp_remote_get($api_url, array('timeout' => 5));
        
        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            $exists = ($code === 200);
            
            // Cache for 1 week
            set_transient('reign_plugin_wp_org_' . $slug, $exists, WEEK_IN_SECONDS);
            
            return $exists;
        }
        
        return false;
    }
    
    private function is_required_plugin($plugin_file) {
        $required_plugins = array(
            'buddypress/bp-loader.php',
            'bbpress/bbpress.php',
            'woocommerce/woocommerce.php',
            'elementor/elementor.php',
            'learndash/learndash.php',
            'lifterlms/lifterlms.php',
            'tutor/tutor.php',
            'sensei-lms/sensei-lms.php',
            'dokan-lite/dokan.php',
            'wc-multivendor-marketplace/wc-multivendor-marketplace.php',
            'wc-vendors/class-wc-vendors.php',
            'geodirectory/geodirectory.php',
            'wp-job-manager/wp-job-manager.php',
            'easy-digital-downloads/easy-digital-downloads.php',
            'peepso-core/peepso.php',
            'buddyboss-platform/bp-loader.php'
        );
        
        // All Reign plugins are required
        $plugin_slug = $this->get_plugin_slug($plugin_file);
        if (in_array($plugin_slug, $this->reign_plugins)) {
            return true;
        }
        
        return in_array($plugin_file, $required_plugins);
    }
    
    public function generate_plugins_manifest($demo_info, $plugins_data) {
        $manifest = array(
            'demo_id' => $demo_info['demo_id'],
            'plugins_count' => count($plugins_data['required']) + count($plugins_data['optional']),
            'plugins' => $plugins_data
        );
        
        return $manifest;
    }
}