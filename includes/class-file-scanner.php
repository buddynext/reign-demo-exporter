<?php
/**
 * File Scanner Class
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_File_Scanner {
    
    private $total_size = 0;
    private $file_count = 0;
    private $scanned_directories = array();
    
    public function scan_files() {
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'];
        
        $files_data = array(
            'uploads_size' => $this->get_directory_size($upload_path),
            'total_size' => 0,
            'file_count' => $this->file_count,
            'directories' => $this->analyze_upload_structure($upload_path),
            'special_folders' => $this->scan_special_folders(),
            'theme_files' => $this->scan_theme_files(),
            'custom_files' => $this->scan_custom_files()
        );
        
        // Calculate total size
        $files_data['total_size'] = $files_data['uploads_size'] + 
                                   $this->get_theme_files_size() + 
                                   $this->get_database_export_size();
        
        return $files_data;
    }
    
    private function get_directory_size($dir) {
        $size = 0;
        
        if (!is_dir($dir)) {
            return $size;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                $this->file_count++;
            }
        }
        
        return $size;
    }
    
    private function analyze_upload_structure($upload_path) {
        $structure = array();
        
        // Scan year directories
        $year_pattern = $upload_path . '/20*';
        $year_dirs = glob($year_pattern, GLOB_ONLYDIR);
        
        foreach ($year_dirs as $year_dir) {
            $year = basename($year_dir);
            $year_size = 0;
            $year_files = 0;
            
            // Scan month directories within year
            $month_dirs = glob($year_dir . '/*', GLOB_ONLYDIR);
            
            foreach ($month_dirs as $month_dir) {
                $size = $this->get_directory_size($month_dir);
                $year_size += $size;
                
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($month_dir, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                
                foreach ($files as $file) {
                    if ($file->isFile()) {
                        $year_files++;
                    }
                }
            }
            
            $structure[] = array(
                'path' => $year . '/',
                'size' => $year_size,
                'file_count' => $year_files,
                'type' => 'uploads'
            );
        }
        
        return $structure;
    }
    
    private function scan_special_folders() {
        $upload_dir = wp_upload_dir();
        $special_folders = array();
        
        // Comprehensive list of plugin folders that store data outside of standard attachments
        $folders_to_check = array(
            // BuddyPress/BuddyBoss
            'buddypress' => array(
                'description' => 'BuddyPress user data',
                'subfolders' => array('avatars', 'group-avatars', 'cover-images')
            ),
            'buddyboss' => array(
                'description' => 'BuddyBoss Platform data',
                'subfolders' => array('avatars', 'cover-images', 'bb_documents', 'bb_videos', 'bb_medias', 'bb_photos')
            ),
            'bb-platform-pro' => array(
                'description' => 'BuddyBoss Platform Pro data',
                'subfolders' => array()
            ),
            
            // PeepSo
            'peepso' => array(
                'description' => 'PeepSo social data',
                'subfolders' => array('users', 'covers', 'photos', 'videos', 'files')
            ),
            
            // WooCommerce
            'woocommerce_uploads' => array(
                'description' => 'WooCommerce protected files',
                'subfolders' => array('wc-logs')
            ),
            'wc-logs' => array(
                'description' => 'WooCommerce logs',
                'subfolders' => array()
            ),
            
            // Page Builders
            'elementor' => array(
                'description' => 'Elementor generated files',
                'subfolders' => array('css', 'uploads', 'kits')
            ),
            'bb-plugin' => array(
                'description' => 'Beaver Builder cache',
                'subfolders' => array('cache')
            ),
            'bb-theme' => array(
                'description' => 'Beaver Builder theme cache',
                'subfolders' => array('cache')
            ),
            
            // LMS Plugins
            'learndash' => array(
                'description' => 'LearnDash course files',
                'subfolders' => array('certificates', 'assignments', 'essays')
            ),
            'lifterlms' => array(
                'description' => 'LifterLMS files',
                'subfolders' => array('certificates', 'exports', 'logs')
            ),
            'tutor' => array(
                'description' => 'Tutor LMS files',
                'subfolders' => array('certificates', 'assignments')
            ),
            'sensei' => array(
                'description' => 'Sensei LMS files',
                'subfolders' => array()
            ),
            
            // Marketplace/Vendors
            'dokan' => array(
                'description' => 'Dokan vendor files',
                'subfolders' => array('verification')
            ),
            'wcfm' => array(
                'description' => 'WCFM vendor files',
                'subfolders' => array()
            ),
            'wcvendors' => array(
                'description' => 'WC Vendors files',
                'subfolders' => array()
            ),
            
            // Directory Plugins
            'geodirectory' => array(
                'description' => 'GeoDirectory files',
                'subfolders' => array('temp', 'gd_temp')
            ),
            
            // Job Board
            'job-manager-uploads' => array(
                'description' => 'WP Job Manager files',
                'subfolders' => array()
            ),
            
            // Digital Downloads
            'edd' => array(
                'description' => 'Easy Digital Downloads files',
                'subfolders' => array('file_downloads')
            ),
            
            // Forms
            'gravity_forms' => array(
                'description' => 'Gravity Forms uploads',
                'subfolders' => array()
            ),
            'wpforms' => array(
                'description' => 'WPForms uploads',
                'subfolders' => array()
            ),
            
            // Membership
            'pmpro' => array(
                'description' => 'Paid Memberships Pro',
                'subfolders' => array()
            ),
            'restrict-content-pro' => array(
                'description' => 'Restrict Content Pro',
                'subfolders' => array()
            ),
            
            // Other Common Plugin Folders
            'cache' => array(
                'description' => 'Cache files',
                'subfolders' => array()
            ),
            'et-cache' => array(
                'description' => 'Divi cache',
                'subfolders' => array()
            ),
            'wpallimport' => array(
                'description' => 'WP All Import files',
                'subfolders' => array('uploads', 'temp')
            ),
            'backups-dup-lite' => array(
                'description' => 'Duplicator backups',
                'subfolders' => array()
            )
        );
        
        foreach ($folders_to_check as $folder => $info) {
            $folder_path = $upload_dir['basedir'] . '/' . $folder;
            
            if (is_dir($folder_path)) {
                $folder_data = array(
                    'name' => $folder,
                    'path' => $folder . '/',
                    'description' => $info['description'],
                    'size' => $this->get_directory_size($folder_path),
                    'exists' => true,
                    'subfolders' => array()
                );
                
                // Check subfolders
                foreach ($info['subfolders'] as $subfolder) {
                    $subfolder_path = $folder_path . '/' . $subfolder;
                    if (is_dir($subfolder_path)) {
                        $folder_data['subfolders'][] = array(
                            'name' => $subfolder,
                            'size' => $this->get_directory_size($subfolder_path)
                        );
                    }
                }
                
                $special_folders[] = $folder_data;
            }
        }
        
        // Also scan for any other directories in uploads that aren't year folders
        $upload_contents = scandir($upload_dir['basedir']);
        foreach ($upload_contents as $item) {
            if ($item === '.' || $item === '..' || is_file($upload_dir['basedir'] . '/' . $item)) {
                continue;
            }
            
            // Skip year folders (YYYY format)
            if (preg_match('/^\d{4}$/', $item)) {
                continue;
            }
            
            // Check if we already scanned this folder
            $already_scanned = false;
            foreach ($special_folders as $scanned) {
                if ($scanned['name'] === $item) {
                    $already_scanned = true;
                    break;
                }
            }
            
            if (!$already_scanned) {
                $item_path = $upload_dir['basedir'] . '/' . $item;
                $special_folders[] = array(
                    'name' => $item,
                    'path' => $item . '/',
                    'description' => 'Additional folder',
                    'size' => $this->get_directory_size($item_path),
                    'exists' => true,
                    'subfolders' => array()
                );
            }
        }
        
        return $special_folders;
    }
    
    private function scan_theme_files() {
        $theme_files = array(
            'custom_css' => false,
            'child_theme' => is_child_theme(),
            'additional_files' => array(),
            'theme_size' => 0
        );
        
        // Check for custom CSS file
        $custom_css_file = get_stylesheet_directory() . '/custom.css';
        if (file_exists($custom_css_file)) {
            $theme_files['custom_css'] = true;
            $theme_files['theme_size'] += filesize($custom_css_file);
        }
        
        // Check for additional theme directories
        $theme_dir = get_stylesheet_directory();
        $additional_dirs = array('fonts', 'custom-templates', 'images', 'js', 'css', 'sass', 'assets');
        
        foreach ($additional_dirs as $dir) {
            $dir_path = $theme_dir . '/' . $dir;
            if (is_dir($dir_path)) {
                $dir_size = $this->get_directory_size($dir_path);
                $theme_files['additional_files'][] = array(
                    'name' => $dir,
                    'path' => $dir . '/',
                    'size' => $dir_size
                );
                $theme_files['theme_size'] += $dir_size;
            }
        }
        
        return $theme_files;
    }
    
    private function scan_custom_files() {
        $custom_files = array();
        
        // Check for custom plugin files
        $custom_plugin_dir = WP_PLUGIN_DIR . '/reign-custom';
        if (is_dir($custom_plugin_dir)) {
            $custom_files['custom_plugins'] = array(
                'path' => 'plugins/reign-custom/',
                'size' => $this->get_directory_size($custom_plugin_dir)
            );
        }
        
        // Check for mu-plugins
        $mu_plugins_dir = WPMU_PLUGIN_DIR;
        if (is_dir($mu_plugins_dir)) {
            $files = glob($mu_plugins_dir . '/*.php');
            if (!empty($files)) {
                $custom_files['mu_plugins'] = array(
                    'path' => 'mu-plugins/',
                    'count' => count($files),
                    'size' => array_sum(array_map('filesize', $files))
                );
            }
        }
        
        return $custom_files;
    }
    
    private function get_theme_files_size() {
        $size = 0;
        $theme_dir = get_stylesheet_directory();
        
        // Only count custom files, not the entire theme
        $custom_files = array(
            'custom.css',
            'functions.php',
            'style.css'
        );
        
        foreach ($custom_files as $file) {
            $file_path = $theme_dir . '/' . $file;
            if (file_exists($file_path)) {
                $size += filesize($file_path);
            }
        }
        
        return $size;
    }
    
    private function get_database_export_size() {
        global $wpdb;
        
        // Estimate database size
        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        $total_size = 0;
        
        foreach ($tables as $table) {
            if (strpos($table['Name'], $wpdb->prefix) === 0) {
                $total_size += $table['Data_length'] + $table['Index_length'];
            }
        }
        
        // Add 20% overhead for SQL formatting
        return $total_size * 1.2;
    }
    
    public function get_file_types_summary() {
        $upload_dir = wp_upload_dir();
        $file_types = array();
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($upload_dir['basedir'], RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                
                if (!isset($file_types[$extension])) {
                    $file_types[$extension] = array(
                        'count' => 0,
                        'size' => 0
                    );
                }
                
                $file_types[$extension]['count']++;
                $file_types[$extension]['size'] += $file->getSize();
            }
        }
        
        // Sort by count
        uasort($file_types, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return $file_types;
    }
}