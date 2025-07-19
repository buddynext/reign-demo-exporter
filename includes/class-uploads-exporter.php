<?php
/**
 * Uploads Directory Exporter Class
 * 
 * Exports entire uploads directory including BuddyPress/BuddyBoss files
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Uploads_Exporter {
    
    private $excluded_patterns = array();
    private $export_stats = array();
    
    public function __construct() {
        $this->set_excluded_patterns();
    }
    
    private function set_excluded_patterns() {
        // Only exclude backup and cache directories
        $this->excluded_patterns = array(
            // Backup directories
            'backups',
            'updraft',
            'backwpup',
            'duplicator',
            'ai1wm-backups',
            
            // Cache directories
            'cache',
            'wp-cache',
            'w3tc',
            
            // Temporary files
            'tmp',
            'temp',
            
            // System files
            '.DS_Store',
            'Thumbs.db'
        );
    }
    
    /**
     * Export entire uploads directory
     */
    public function export_uploads_directory($destination) {
        $upload_dir = wp_upload_dir();
        $source = $upload_dir['basedir'];
        
        if (!is_dir($source)) {
            throw new Exception('Uploads directory not found');
        }
        
        $this->export_stats = array(
            'total_files' => 0,
            'total_size' => 0,
            'directories' => array(),
            'excluded_items' => 0
        );
        
        // Copy entire uploads directory
        $this->copy_directory_recursive($source, $destination);
        
        // Create export summary
        $this->create_export_summary($destination);
        
        return $this->export_stats;
    }
    
    /**
     * Copy directory recursively with exclusions
     */
    private function copy_directory_recursive($source, $destination, $base_path = '') {
        if (!is_dir($destination)) {
            wp_mkdir_p($destination);
        }
        
        $items = scandir($source);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            // Check if item should be excluded
            if ($this->should_exclude($item)) {
                $this->export_stats['excluded_items']++;
                continue;
            }
            
            $source_path = $source . '/' . $item;
            $dest_path = $destination . '/' . $item;
            $relative_path = $base_path ? $base_path . '/' . $item : $item;
            
            if (is_dir($source_path)) {
                // Track directories
                if (!preg_match('/^\d{4}$/', $item)) { // Skip year directories in stats
                    $this->export_stats['directories'][$item] = array(
                        'path' => $relative_path,
                        'type' => $this->determine_directory_type($item)
                    );
                }
                
                // Recursively copy subdirectory
                $this->copy_directory_recursive($source_path, $dest_path, $relative_path);
            } else {
                // Copy file
                if ($this->copy_file_with_verification($source_path, $dest_path)) {
                    $this->export_stats['total_files']++;
                    $this->export_stats['total_size'] += filesize($source_path);
                }
            }
        }
    }
    
    /**
     * Determine if item should be excluded
     */
    private function should_exclude($item) {
        foreach ($this->excluded_patterns as $pattern) {
            if (stripos($item, $pattern) !== false) {
                return true;
            }
        }
        
        // Exclude large archive files
        if (preg_match('/\.(zip|tar|gz|rar|7z)$/i', $item)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Copy file with verification
     */
    private function copy_file_with_verification($source, $destination) {
        // Create destination directory if needed
        $dest_dir = dirname($destination);
        if (!is_dir($dest_dir)) {
            wp_mkdir_p($dest_dir);
        }
        
        // Skip very large files (over 100MB)
        if (filesize($source) > 104857600) {
            return false;
        }
        
        // Copy the file
        if (copy($source, $destination)) {
            // Verify copy
            if (filesize($destination) === filesize($source)) {
                return true;
            } else {
                // Remove failed copy
                @unlink($destination);
            }
        }
        
        return false;
    }
    
    /**
     * Determine directory type
     */
    private function determine_directory_type($dir_name) {
        $types = array(
            'avatars' => 'BuddyPress Avatars',
            'group-avatars' => 'BuddyPress Group Avatars',
            'cover-images' => 'BuddyPress Cover Images',
            'buddypress' => 'BuddyPress Uploads',
            'bb_medias' => 'BuddyBoss Media',
            'bb_documents' => 'BuddyBoss Documents',
            'bb_videos' => 'BuddyBoss Videos',
            'rtmedia' => 'rtMedia Files',
            'woocommerce_uploads' => 'WooCommerce Files',
            'elementor' => 'Elementor Files',
            'learndash' => 'LearnDash Files',
            'peepso' => 'PeepSo Files'
        );
        
        return isset($types[$dir_name]) ? $types[$dir_name] : 'Plugin Files';
    }
    
    /**
     * Create export summary
     */
    private function create_export_summary($export_dir) {
        $summary_file = $export_dir . '/uploads-export-summary.json';
        
        $summary = array(
            'export_date' => current_time('c'),
            'statistics' => array(
                'total_files' => $this->export_stats['total_files'],
                'total_size' => size_format($this->export_stats['total_size']),
                'total_size_bytes' => $this->export_stats['total_size'],
                'excluded_items' => $this->export_stats['excluded_items']
            ),
            'directories' => $this->export_stats['directories'],
            'notes' => array(
                'Files over 100MB were excluded',
                'Archive files (zip, tar, etc.) were excluded',
                'Backup and cache directories were excluded'
            )
        );
        
        file_put_contents($summary_file, json_encode($summary, JSON_PRETTY_PRINT));
    }
}