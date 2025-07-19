<?php
/**
 * Package Creator Class
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Package_Creator {
    
    private $temp_dir;
    private $zip_file;
    
    public function __construct() {
        $this->temp_dir = WP_CONTENT_DIR . '/reign-demo-temp-' . uniqid() . '/';
        wp_mkdir_p($this->temp_dir);
    }
    
    public function create_package($export_data) {
        try {
            // Create SQL database export
            require_once REIGN_DEMO_EXPORTER_PATH . 'includes/class-sql-exporter.php';
            $sql_exporter = new Reign_Demo_SQL_Exporter();
            $db_stats = $sql_exporter->export_database($this->temp_dir);
            
            // Export entire uploads directory (includes all media and plugin files)
            $uploads_exporter = new Reign_Demo_Uploads_Exporter();
            $uploads_stats = $uploads_exporter->export_uploads_directory($this->temp_dir . 'uploads/');
            
            // Export theme customizations
            $this->export_theme_customizations();
            
            // Create info file with uploads stats and db stats
            $export_data['uploads_stats'] = $uploads_stats;
            $export_data['database_stats'] = $db_stats;
            $this->create_info_file($export_data);
            
            // Create ZIP archive
            $zip_path = $this->create_zip_archive();
            
            // Clean up temp directory
            $this->cleanup_temp_directory();
            
            return $zip_path;
            
        } catch (Exception $e) {
            $this->cleanup_temp_directory();
            throw $e;
        }
    }
    
    private function create_zip_archive() {
        $zip_filename = 'content-package.zip';
        $zip_path = WP_CONTENT_DIR . '/' . $zip_filename;
        
        // Remove existing zip if exists
        if (file_exists($zip_path)) {
            unlink($zip_path);
        }
        
        $zip = new ZipArchive();
        
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception(__('Cannot create ZIP file', 'reign-demo-exporter'));
        }
        
        // Add all files from temp directory to zip
        $this->add_directory_to_zip($zip, $this->temp_dir, '');
        
        $zip->close();
        
        if (!file_exists($zip_path)) {
            throw new Exception(__('Failed to create ZIP archive', 'reign-demo-exporter'));
        }
        
        return $zip_path;
    }
    
    private function add_directory_to_zip($zip, $source_dir, $zip_path) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $file_path = $file->getRealPath();
            $relative_path = str_replace($source_dir, '', $file_path);
            $relative_path = ltrim($relative_path, '/\\');
            
            if ($zip_path) {
                $relative_path = $zip_path . '/' . $relative_path;
            }
            
            if ($file->isDir()) {
                $zip->addEmptyDir($relative_path);
            } else {
                $zip->addFile($file_path, $relative_path);
            }
        }
    }
    
    private function copy_directory($source, $destination) {
        if (!is_dir($destination)) {
            wp_mkdir_p($destination);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $source_file = $file->getRealPath();
            $dest_file = $destination . '/' . $iterator->getSubPathName();
            
            if ($file->isDir()) {
                wp_mkdir_p($dest_file);
            } else {
                $dest_dir = dirname($dest_file);
                if (!is_dir($dest_dir)) {
                    wp_mkdir_p($dest_dir);
                }
                copy($source_file, $dest_file);
            }
        }
    }
    
    private function cleanup_temp_directory() {
        if (!is_dir($this->temp_dir)) {
            return;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->temp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($this->temp_dir);
    }
    
    public function __destruct() {
        // Ensure cleanup on destruction
        if (is_dir($this->temp_dir)) {
            $this->cleanup_temp_directory();
        }
    }
    
    private function copy_media_files($media_items) {
        $upload_dir = wp_upload_dir();
        $media_dir = $this->temp_dir . 'media/';
        wp_mkdir_p($media_dir);
        
        foreach ($media_items as $media) {
            if (empty($media['file_url'])) {
                continue;
            }
            
            // Get file path from URL
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $media['file_url']);
            
            if (!file_exists($file_path)) {
                continue;
            }
            
            // Determine relative path
            $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file_path);
            $dest_path = $media_dir . $relative_path;
            
            // Create directory structure
            $dest_dir = dirname($dest_path);
            if (!is_dir($dest_dir)) {
                wp_mkdir_p($dest_dir);
            }
            
            // Copy file
            if (!copy($file_path, $dest_path)) {
                // Log error but continue
                error_log('Failed to copy media file: ' . $file_path);
            }
            
            // Copy additional image sizes
            if (isset($media['metadata']['sizes']) && is_array($media['metadata']['sizes'])) {
                $file_dir = dirname($file_path);
                
                foreach ($media['metadata']['sizes'] as $size => $size_data) {
                    $size_file = $file_dir . '/' . $size_data['file'];
                    
                    if (file_exists($size_file)) {
                        $size_relative = str_replace($upload_dir['basedir'] . '/', '', $size_file);
                        $size_dest = $media_dir . $size_relative;
                        
                        $size_dest_dir = dirname($size_dest);
                        if (!is_dir($size_dest_dir)) {
                            wp_mkdir_p($size_dest_dir);
                        }
                        
                        copy($size_file, $size_dest);
                    }
                }
            }
        }
    }
    
    private function export_theme_customizations() {
        $theme_dir = $this->temp_dir . 'theme/';
        wp_mkdir_p($theme_dir);
        
        // Export custom CSS if exists
        $custom_css_file = get_stylesheet_directory() . '/custom.css';
        if (file_exists($custom_css_file)) {
            copy($custom_css_file, $theme_dir . 'custom.css');
        }
        
        // Export child theme files if applicable
        if (is_child_theme()) {
            $child_theme_files = array('style.css', 'functions.php', 'screenshot.png');
            
            foreach ($child_theme_files as $file) {
                $file_path = get_stylesheet_directory() . '/' . $file;
                if (file_exists($file_path)) {
                    copy($file_path, $theme_dir . $file);
                }
            }
        }
        
        // Export custom fonts
        $fonts_dir = get_stylesheet_directory() . '/fonts';
        if (is_dir($fonts_dir)) {
            $this->copy_directory($fonts_dir, $theme_dir . 'fonts');
        }
        
        // Export custom templates
        $templates_dir = get_stylesheet_directory() . '/custom-templates';
        if (is_dir($templates_dir)) {
            $this->copy_directory($templates_dir, $theme_dir . 'custom-templates');
        }
    }
    
    private function create_info_file($export_data) {
        $info_file = $this->temp_dir . 'export-info.json';
        
        $info = array(
            'export_version' => REIGN_DEMO_EXPORTER_VERSION,
            'export_date' => current_time('c'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'export_type' => 'sql',
            'theme' => array(
                'name' => wp_get_theme()->get('Name'),
                'version' => wp_get_theme()->get('Version'),
                'parent' => is_child_theme() ? wp_get_theme()->parent()->get('Name') : null
            ),
            'plugins_count' => count($export_data['plugins']['required']) + count($export_data['plugins']['optional']),
            'content_summary' => isset($export_data['content_summary']) ? $export_data['content_summary'] : array(),
            'database_stats' => isset($export_data['database_stats']) ? $export_data['database_stats'] : array(),
            'uploads_stats' => isset($export_data['uploads_stats']) ? $export_data['uploads_stats'] : array(),
            'site_info' => array(
                'name' => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'url' => get_site_url(),
                'admin_email' => get_option('admin_email')
            )
        );
        
        file_put_contents($info_file, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}