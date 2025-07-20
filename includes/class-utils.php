<?php
/**
 * Utility Functions Class
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Exporter_Utils {
    
    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes Number of bytes
     * @param int $precision Decimal precision
     * @return string Formatted size
     */
    public static function format_bytes($bytes, $precision = 2) {
        if ($bytes < 1) {
            return '0 B';
        }
        
        // Use WordPress built-in function for consistency
        return size_format($bytes, $precision);
    }
    
    /**
     * Get directory size recursively
     * 
     * @param string $directory Directory path
     * @return int Size in bytes
     */
    public static function get_directory_size($directory) {
        if (!is_dir($directory)) {
            return 0;
        }
        
        $size = 0;
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Exception $e) {
            // Log error but don't break the process
            error_log('Error calculating directory size: ' . $e->getMessage());
        }
        
        return $size;
    }
    
    /**
     * Check if export files exist
     * 
     * @return array Existing export files with details
     */
    public static function check_existing_exports() {
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
                    'size' => self::format_bytes(filesize($file_path)),
                    'size_bytes' => filesize($file_path),
                    'modified' => filemtime($file_path),
                    'modified_formatted' => date_i18n(
                        get_option('date_format') . ' ' . get_option('time_format'), 
                        filemtime($file_path)
                    ),
                    'exists' => true
                );
            }
        }
        
        return $exports;
    }
    
    /**
     * Verify AJAX nonce and permissions
     * 
     * @param string $nonce_action Nonce action name
     * @param string $capability Required capability
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function verify_ajax_request($nonce_action = 'reign_demo_export_nonce', $capability = 'manage_options') {
        // Check if nonce exists
        if (!isset($_POST['nonce'])) {
            return new WP_Error('missing_nonce', __('Security nonce is missing', 'reign-demo-exporter'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], $nonce_action)) {
            return new WP_Error('invalid_nonce', __('Security check failed', 'reign-demo-exporter'));
        }
        
        // Check permissions
        if (!current_user_can($capability)) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to perform this action', 'reign-demo-exporter'));
        }
        
        return true;
    }
    
    /**
     * Send AJAX JSON response
     * 
     * @param bool $success Success status
     * @param mixed $data Response data
     * @param string $message Optional message
     */
    public static function send_json_response($success, $data = null, $message = '') {
        $response = array(
            'success' => $success,
            'data' => $data
        );
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if ($success) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error($response);
        }
    }
    
    /**
     * Create directory recursively with proper permissions
     * 
     * @param string $directory Directory path
     * @return bool Success status
     */
    public static function create_directory($directory) {
        if (!is_dir($directory)) {
            return wp_mkdir_p($directory);
        }
        return true;
    }
    
    /**
     * Copy directory recursively
     * 
     * @param string $source Source directory
     * @param string $destination Destination directory
     * @param array $exclude Patterns to exclude
     * @return array Statistics about the copy operation
     */
    public static function copy_directory($source, $destination, $exclude = array()) {
        $stats = array(
            'files_copied' => 0,
            'directories_created' => 0,
            'total_size' => 0,
            'errors' => 0
        );
        
        // Validate paths
        $source = realpath($source);
        if (!$source || !is_dir($source)) {
            return $stats;
        }
        
        // Ensure source is within allowed directories
        $allowed_dirs = array(
            realpath(WP_CONTENT_DIR),
            realpath(ABSPATH)
        );
        
        $is_allowed = false;
        foreach ($allowed_dirs as $allowed_dir) {
            if (strpos($source, $allowed_dir) === 0) {
                $is_allowed = true;
                break;
            }
        }
        
        if (!$is_allowed) {
            error_log('Reign Demo Exporter: Attempted to copy from unauthorized directory: ' . $source);
            return $stats;
        }
        
        self::create_directory($destination);
        $stats['directories_created']++;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $source_file = $file->getRealPath();
            $relative_path = str_replace($source, '', $source_file);
            $dest_file = $destination . $relative_path;
            
            // Check exclusions
            $skip = false;
            foreach ($exclude as $pattern) {
                if (fnmatch($pattern, basename($source_file))) {
                    $skip = true;
                    break;
                }
            }
            
            if ($skip) {
                continue;
            }
            
            if ($file->isDir()) {
                self::create_directory($dest_file);
                $stats['directories_created']++;
            } else {
                $dest_dir = dirname($dest_file);
                if (!is_dir($dest_dir)) {
                    self::create_directory($dest_dir);
                    $stats['directories_created']++;
                }
                
                if (copy($source_file, $dest_file)) {
                    $stats['files_copied']++;
                    $stats['total_size'] += filesize($source_file);
                } else {
                    $stats['errors']++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Clean up directory recursively
     * 
     * @param string $directory Directory to clean
     * @return bool Success status
     */
    public static function cleanup_directory($directory) {
        if (!is_dir($directory)) {
            return true;
        }
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            
            rmdir($directory);
            return true;
        } catch (Exception $e) {
            error_log('Error cleaning directory: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get file extension
     * 
     * @param string $filename File name
     * @return string File extension in lowercase
     */
    public static function get_file_extension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * Sanitize filename for export
     * 
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    public static function sanitize_filename($filename) {
        // Remove any directory traversal attempts
        $filename = str_replace(array('../', '..\\', '..', '/', '\\'), '', $filename);
        
        // Get only the filename
        $filename = basename($filename);
        
        // Replace spaces with hyphens
        $filename = str_replace(' ', '-', $filename);
        
        // Remove special characters, but keep alphanumeric, hyphens, underscores, and dots
        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '', $filename);
        
        // Remove multiple consecutive hyphens or underscores
        $filename = preg_replace('/[-_]+/', '-', $filename);
        
        // Ensure no double dots
        $filename = str_replace('..', '.', $filename);
        
        // Trim dots from start and end
        $filename = trim($filename, '.');
        
        // If filename is empty after sanitization, generate a safe one
        if (empty($filename)) {
            $filename = 'export-' . wp_generate_password(8, false);
        }
        
        return $filename;
    }
    
    /**
     * Get WordPress memory limit in bytes
     * 
     * @return int Memory limit in bytes
     */
    public static function get_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            if ($matches[2] == 'M') {
                return $matches[1] * 1024 * 1024; // MB to bytes
            } else if ($matches[2] == 'K') {
                return $matches[1] * 1024; // KB to bytes
            } else if ($matches[2] == 'G') {
                return $matches[1] * 1024 * 1024 * 1024; // GB to bytes
            }
        }
        
        return $memory_limit;
    }
    
    /**
     * Check if we're running low on memory
     * 
     * @param float $threshold Percentage threshold (e.g., 0.8 for 80%)
     * @return bool True if memory usage is above threshold
     */
    public static function is_memory_low($threshold = 0.8) {
        $memory_limit = self::get_memory_limit();
        $memory_usage = memory_get_usage(true);
        
        return ($memory_usage / $memory_limit) > $threshold;
    }
}