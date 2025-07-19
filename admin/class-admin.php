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
        $exports = array();
        $export_dir = REIGN_DEMO_EXPORT_DIR;
        
        $files = array('manifest.json', 'plugins-manifest.json', 'files-manifest.json', 'content-package.zip');
        
        foreach ($files as $file) {
            $file_path = $export_dir . $file;
            if (file_exists($file_path)) {
                $exports[] = array(
                    'name' => $file,
                    'path' => $file_path,
                    'url' => REIGN_DEMO_EXPORT_URL . $file,
                    'size' => $this->format_file_size(filesize($file_path)),
                    'modified' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($file_path))
                );
            }
        }
        
        return $exports;
    }
    
    private function format_file_size($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
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