<?php
/**
 * Admin Interface Class
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Exporter_Admin {
    
    public function render() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'reign-demo-exporter'));
        }
        
        // Check if export files already exist
        $existing_exports = $this->check_existing_exports();
        $last_export = get_option('reign_demo_last_export', array());
        
        // Pass data to view
        $args = array(
            'existing_exports' => $existing_exports,
            'last_export' => $last_export
        );
        
        // Load the view template
        $this->load_view('export-page', $args);
    }
    
    private function load_view($view, $args = array()) {
        // Make args available to the view
        extract($args);
        
        // Load the view file
        $view_file = REIGN_DEMO_EXPORTER_PATH . 'admin/views/' . $view . '.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="error"><p>' . sprintf(__('View file %s not found.', 'reign-demo-exporter'), $view) . '</p></div>';
        }
    }
    
    private function check_existing_exports() {
        return Reign_Demo_Exporter_Utils::check_existing_exports();
    }
    
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . ' ' . __('seconds', 'reign-demo-exporter');
        }
        
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        
        if ($minutes < 60) {
            return sprintf(__('%d minutes %d seconds', 'reign-demo-exporter'), $minutes, $seconds);
        }
        
        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;
        
        return sprintf(__('%d hours %d minutes', 'reign-demo-exporter'), $hours, $minutes);
    }
}