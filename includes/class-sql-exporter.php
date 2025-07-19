<?php
/**
 * SQL Exporter Class
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_SQL_Exporter {
    
    private $chunk_size = 1000;
    private $excluded_options = array(
        '_transient_%',
        '_site_transient_%',
        'cron',
        'rewrite_rules',
        '_edit_lock_%'
    );
    
    /**
     * Export database as SQL files
     */
    public function export_database($export_dir) {
        global $wpdb;
        
        // Create database directory
        $db_dir = $export_dir . 'database/';
        wp_mkdir_p($db_dir);
        
        // Get all tables
        $tables = $this->get_all_tables();
        
        // Create import order file
        $import_order = $this->determine_import_order($tables);
        file_put_contents($db_dir . 'import-order.json', json_encode($import_order, JSON_PRETTY_PRINT));
        
        // Export each table
        foreach ($tables as $table) {
            $this->export_table($table, $db_dir);
        }
        
        // Create combined SQL file for easy import
        $this->create_combined_sql($db_dir, $tables);
        
        return array(
            'tables_exported' => count($tables),
            'total_size' => $this->get_directory_size($db_dir)
        );
    }
    
    /**
     * Export a single table
     */
    private function export_table($table_name, $export_dir) {
        global $wpdb;
        
        $filename = $export_dir . $table_name . '.sql';
        $handle = fopen($filename, 'w');
        
        if (!$handle) {
            throw new Exception("Cannot create file: $filename");
        }
        
        // Write header
        fwrite($handle, "-- Reign Demo Export\n");
        fwrite($handle, "-- Table: $table_name\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
        
        // Drop table if exists
        fwrite($handle, "DROP TABLE IF EXISTS `$table_name`;\n\n");
        
        // Create table structure
        $create_table = $wpdb->get_row("SHOW CREATE TABLE `$table_name`", ARRAY_A);
        fwrite($handle, $create_table['Create Table'] . ";\n\n");
        
        // Get row count
        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
        
        if ($row_count > 0) {
            // Disable keys for faster import
            fwrite($handle, "/*!40000 ALTER TABLE `$table_name` DISABLE KEYS */;\n");
            
            // Export data
            if ($row_count > $this->chunk_size) {
                $this->export_data_chunked($table_name, $handle);
            } else {
                $this->export_data_single($table_name, $handle);
            }
            
            // Re-enable keys
            fwrite($handle, "/*!40000 ALTER TABLE `$table_name` ENABLE KEYS */;\n");
        }
        
        fclose($handle);
        
        // Compress if large
        if (filesize($filename) > 1048576) { // 1MB
            $this->compress_file($filename);
        }
    }
    
    /**
     * Export data in chunks for large tables
     */
    private function export_data_chunked($table_name, $handle) {
        global $wpdb;
        
        $offset = 0;
        $table_name_clean = str_replace($wpdb->prefix, '', $table_name);
        
        // Special handling for options table
        if ($table_name_clean === 'options') {
            $where_clause = $this->get_options_where_clause();
        } else {
            $where_clause = '';
        }
        
        do {
            $rows = $wpdb->get_results(
                "SELECT * FROM `$table_name` $where_clause 
                 LIMIT {$this->chunk_size} OFFSET $offset",
                ARRAY_A
            );
            
            if ($rows) {
                $this->write_insert_statements($table_name, $rows, $handle);
                $offset += $this->chunk_size;
            }
            
        } while ($rows);
    }
    
    /**
     * Export all data at once for small tables
     */
    private function export_data_single($table_name, $handle) {
        global $wpdb;
        
        $table_name_clean = str_replace($wpdb->prefix, '', $table_name);
        
        // Special handling for options table
        if ($table_name_clean === 'options') {
            $where_clause = $this->get_options_where_clause();
        } else {
            $where_clause = '';
        }
        
        $rows = $wpdb->get_results("SELECT * FROM `$table_name` $where_clause", ARRAY_A);
        
        if ($rows) {
            $this->write_insert_statements($table_name, $rows, $handle);
        }
    }
    
    /**
     * Write INSERT statements
     */
    private function write_insert_statements($table_name, $rows, $handle) {
        global $wpdb;
        
        if (empty($rows)) {
            return;
        }
        
        // Get column names
        $columns = array_keys($rows[0]);
        $columns_list = '`' . implode('`, `', $columns) . '`';
        
        // Build INSERT statements
        $values = array();
        foreach ($rows as $row) {
            $row_values = array();
            foreach ($row as $value) {
                if ($value === null) {
                    $row_values[] = 'NULL';
                } else {
                    $row_values[] = "'" . $wpdb->_real_escape($value) . "'";
                }
            }
            $values[] = '(' . implode(', ', $row_values) . ')';
            
            // Write in batches of 100 rows
            if (count($values) >= 100) {
                fwrite($handle, "INSERT INTO `$table_name` ($columns_list) VALUES\n");
                fwrite($handle, implode(",\n", $values) . ";\n\n");
                $values = array();
            }
        }
        
        // Write remaining values
        if (!empty($values)) {
            fwrite($handle, "INSERT INTO `$table_name` ($columns_list) VALUES\n");
            fwrite($handle, implode(",\n", $values) . ";\n\n");
        }
    }
    
    /**
     * Get WHERE clause for options table
     */
    private function get_options_where_clause() {
        $conditions = array();
        
        foreach ($this->excluded_options as $pattern) {
            $conditions[] = "option_name NOT LIKE '$pattern'";
        }
        
        return 'WHERE ' . implode(' AND ', $conditions);
    }
    
    /**
     * Get all tables to export
     */
    private function get_all_tables() {
        global $wpdb;
        
        $tables = array();
        $all_tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        
        foreach ($all_tables as $table) {
            $table_name = $table[0];
            
            // Only export tables with our prefix
            if (strpos($table_name, $wpdb->prefix) === 0) {
                $tables[] = $table_name;
            }
        }
        
        return $tables;
    }
    
    /**
     * Determine correct import order
     */
    private function determine_import_order($tables) {
        global $wpdb;
        
        // Define table dependencies
        $order = array(
            // Core tables first
            $wpdb->prefix . 'users',
            $wpdb->prefix . 'usermeta',
            $wpdb->prefix . 'terms',
            $wpdb->prefix . 'termmeta',
            $wpdb->prefix . 'term_taxonomy',
            $wpdb->prefix . 'posts',
            $wpdb->prefix . 'postmeta',
            $wpdb->prefix . 'term_relationships',
            $wpdb->prefix . 'comments',
            $wpdb->prefix . 'commentmeta',
            $wpdb->prefix . 'links',
            $wpdb->prefix . 'options',
        );
        
        // Add remaining tables
        foreach ($tables as $table) {
            if (!in_array($table, $order)) {
                $order[] = $table;
            }
        }
        
        // Only return tables that exist
        return array_values(array_intersect($order, $tables));
    }
    
    /**
     * Create combined SQL file
     */
    private function create_combined_sql($db_dir, $tables) {
        $combined_file = $db_dir . 'complete-export.sql';
        $handle = fopen($combined_file, 'w');
        
        fwrite($handle, "-- Reign Demo Complete Database Export\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Tables: " . count($tables) . "\n\n");
        
        fwrite($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        fwrite($handle, "SET time_zone = '+00:00';\n");
        fwrite($handle, "SET foreign_key_checks = 0;\n\n");
        
        // Concatenate all SQL files
        foreach ($tables as $table) {
            $file = $db_dir . $table . '.sql';
            if (file_exists($file)) {
                fwrite($handle, "\n-- Table: $table\n");
                fwrite($handle, file_get_contents($file));
                fwrite($handle, "\n");
            }
        }
        
        fwrite($handle, "\nSET foreign_key_checks = 1;\n");
        fclose($handle);
        
        // Compress
        $this->compress_file($combined_file);
    }
    
    /**
     * Compress file using gzip
     */
    private function compress_file($filename) {
        $gz_file = $filename . '.gz';
        
        $fp_in = fopen($filename, 'rb');
        $fp_out = gzopen($gz_file, 'wb9'); // Max compression
        
        while (!feof($fp_in)) {
            gzwrite($fp_out, fread($fp_in, 1048576)); // 1MB chunks
        }
        
        fclose($fp_in);
        gzclose($fp_out);
        
        // Remove original if compression successful
        if (file_exists($gz_file) && filesize($gz_file) > 0) {
            unlink($filename);
        }
    }
    
    /**
     * Get directory size
     */
    private function get_directory_size($dir) {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
}