<?php
/**
 * WP-CLI Command for Reign Demo Exporter
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_CLI')) {
    return;
}

/**
 * Manage Reign demo exports via WP-CLI
 */
class Reign_Demo_CLI_Command {
    
    /**
     * Export the current site as a Reign demo.
     * 
     * ## OPTIONS
     * 
     * [--skip-requirements-check]
     * : Skip the system requirements check.
     * 
     * [--force]
     * : Overwrite existing export files without confirmation.
     * 
     * [--quiet]
     * : Minimize output messages.
     * 
     * [--format=<format>]
     * : Output format. Default: table
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     * 
     * ## EXAMPLES
     * 
     *     # Run a standard export
     *     $ wp reign-demo export
     * 
     *     # Force export without confirmation
     *     $ wp reign-demo export --force
     * 
     *     # Skip requirements check
     *     $ wp reign-demo export --skip-requirements-check
     * 
     *     # Export with JSON output
     *     $ wp reign-demo export --format=json
     * 
     * @subcommand export
     */
    public function export($args, $assoc_args) {
        $skip_requirements = WP_CLI\Utils\get_flag_value($assoc_args, 'skip-requirements-check', false);
        $force = WP_CLI\Utils\get_flag_value($assoc_args, 'force', false);
        $quiet = WP_CLI\Utils\get_flag_value($assoc_args, 'quiet', false);
        $format = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
        
        if (!$quiet) {
            WP_CLI::log('Starting Reign Demo Export...');
        }
        
        // Check if export files already exist
        if (!$force && $this->check_existing_exports()) {
            WP_CLI::confirm('Export files already exist. Do you want to overwrite them?');
        }
        
        // Check requirements unless skipped
        if (!$skip_requirements) {
            if (!$quiet) {
                WP_CLI::log("\nChecking system requirements...");
            }
            
            if (!$this->check_requirements_internal($quiet)) {
                WP_CLI::error('System requirements not met. Use --skip-requirements-check to bypass.');
            }
        }
        
        // Run the export
        if (!$quiet) {
            WP_CLI::log("\nStarting export process...");
        }
        
        $start_time = microtime(true);
        $results = $this->run_export($quiet);
        $duration = round(microtime(true) - $start_time, 2);
        
        if ($results['success']) {
            if (!$quiet) {
                WP_CLI::success("Export completed in {$duration} seconds!");
            }
            
            // Display results based on format
            $this->display_results($results, $format, $quiet);
        } else {
            WP_CLI::error($results['message']);
        }
    }
    
    /**
     * Check system requirements for export.
     * 
     * ## OPTIONS
     * 
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     * 
     * ## EXAMPLES
     * 
     *     $ wp reign-demo check-requirements
     *     $ wp reign-demo check-requirements --format=json
     * 
     * @subcommand check-requirements
     */
    public function check_requirements($args, $assoc_args) {
        $format = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
        
        WP_CLI::log('Checking Reign Demo Export requirements...');
        
        // Set current user as admin for CLI
        wp_set_current_user(1);
        
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $_POST['nonce'] = wp_create_nonce('reign_demo_export_nonce');
        
        ob_start();
        $ajax_handler->check_system_requirements();
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        if ($response['success']) {
            $requirements = $response['data'];
            $all_passed = true;
            
            $table_data = array();
            foreach ($requirements as $key => $req) {
                $table_data[] = array(
                    'Requirement' => $req['label'],
                    'Required' => $req['required'],
                    'Current' => $req['current'],
                    'Status' => $req['status'] ? '✓' : '✗'
                );
                
                if (!$req['status'] && $key !== 'reign_theme') {
                    $all_passed = false;
                }
            }
            
            WP_CLI\Utils\format_items($format, $table_data, array('Requirement', 'Required', 'Current', 'Status'));
            
            if ($all_passed) {
                WP_CLI::success('All requirements met!');
            } else {
                WP_CLI::warning('Some requirements not met. Export may still work but could encounter issues.');
            }
        } else {
            WP_CLI::error('Failed to check requirements.');
        }
    }
    
    /**
     * List existing export files.
     * 
     * ## OPTIONS
     * 
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     *   - csv
     * ---
     * 
     * ## EXAMPLES
     * 
     *     $ wp reign-demo list
     *     $ wp reign-demo list --format=json
     * 
     * @subcommand list
     */
    public function list_exports($args, $assoc_args) {
        $format = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
        
        $export_dir = REIGN_DEMO_EXPORT_DIR;
        $files = array('manifest.json', 'plugins-manifest.json', 'files-manifest.json', 'content-package.zip');
        $exports = array();
        
        foreach ($files as $file) {
            $filepath = $export_dir . $file;
            if (file_exists($filepath)) {
                $exports[] = array(
                    'File' => $file,
                    'Size' => $this->format_file_size(filesize($filepath)),
                    'Modified' => date('Y-m-d H:i:s', filemtime($filepath)),
                    'URL' => REIGN_DEMO_EXPORT_URL . $file
                );
            }
        }
        
        if (empty($exports)) {
            WP_CLI::log('No export files found.');
        } else {
            WP_CLI\Utils\format_items($format, $exports, array('File', 'Size', 'Modified', 'URL'));
        }
    }
    
    /**
     * Delete existing export files.
     * 
     * ## OPTIONS
     * 
     * [--force]
     * : Delete without confirmation.
     * 
     * ## EXAMPLES
     * 
     *     $ wp reign-demo clean
     *     $ wp reign-demo clean --force
     * 
     * @subcommand clean
     */
    public function clean($args, $assoc_args) {
        $force = WP_CLI\Utils\get_flag_value($assoc_args, 'force', false);
        
        $export_dir = REIGN_DEMO_EXPORT_DIR;
        $files = array('manifest.json', 'plugins-manifest.json', 'files-manifest.json', 'content-package.zip');
        $found_files = array();
        
        foreach ($files as $file) {
            $filepath = $export_dir . $file;
            if (file_exists($filepath)) {
                $found_files[] = $file;
            }
        }
        
        if (empty($found_files)) {
            WP_CLI::log('No export files found to clean.');
            return;
        }
        
        WP_CLI::log('Found files to delete:');
        foreach ($found_files as $file) {
            WP_CLI::log('  - ' . $file);
        }
        
        if (!$force) {
            WP_CLI::confirm('Are you sure you want to delete these files?');
        }
        
        $deleted = 0;
        foreach ($found_files as $file) {
            $filepath = $export_dir . $file;
            if (unlink($filepath)) {
                $deleted++;
            }
        }
        
        WP_CLI::success("Deleted $deleted export files.");
    }
    
    /**
     * Get information about the last export.
     * 
     * ## OPTIONS
     * 
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     * 
     * ## EXAMPLES
     * 
     *     $ wp reign-demo info
     *     $ wp reign-demo info --format=json
     * 
     * @subcommand info
     */
    public function info($args, $assoc_args) {
        $format = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
        
        $last_export = get_option('reign_demo_last_export');
        
        if (empty($last_export)) {
            WP_CLI::log('No export information found.');
            return;
        }
        
        $info = array(
            array('Field' => 'Export ID', 'Value' => $last_export['export_id']),
            array('Field' => 'Export Date', 'Value' => $last_export['export_date']),
            array('Field' => 'Duration', 'Value' => $last_export['export_duration'] . ' seconds'),
            array('Field' => 'Posts', 'Value' => $last_export['statistics']['posts']),
            array('Field' => 'Pages', 'Value' => $last_export['statistics']['pages']),
            array('Field' => 'Users', 'Value' => $last_export['statistics']['users']),
            array('Field' => 'Active Plugins', 'Value' => $last_export['statistics']['active_plugins']),
            array('Field' => 'Package Size', 'Value' => $this->format_file_size($last_export['statistics']['package_size']))
        );
        
        WP_CLI\Utils\format_items($format, $info, array('Field', 'Value'));
    }
    
    /**
     * Run the export process
     */
    private function run_export($quiet = false) {
        // Set current user as admin for CLI
        wp_set_current_user(1);
        
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $_POST['nonce'] = wp_create_nonce('reign_demo_export_nonce');
        
        $steps = array(
            'preparing' => 'Preparing export',
            'scanning_content' => 'Scanning content',
            'analyzing_plugins' => 'Analyzing plugins',
            'scanning_files' => 'Scanning files',
            'creating_manifests' => 'Creating manifests',
            'packaging_content' => 'Packaging content',
            'finalizing' => 'Finalizing export'
        );
        
        $progress = null;
        if (!$quiet && class_exists('\WP_CLI\Utils\make_progress_bar')) {
            $progress = \WP_CLI\Utils\make_progress_bar('Exporting', count($steps));
        }
        
        $results = array('success' => true, 'files' => array());
        
        foreach ($steps as $step => $label) {
            if (!$quiet && !$progress) {
                WP_CLI::log("  → $label...");
            }
            
            $_POST['step'] = $step;
            
            ob_start();
            $ajax_handler->process_export_step();
            $output = ob_get_clean();
            $response = json_decode($output, true);
            
            if (!$response['success']) {
                $results['success'] = false;
                $results['message'] = $response['data']['message'] ?? 'Export failed at step: ' . $step;
                break;
            }
            
            // Store final file URLs
            if ($step === 'finalizing' && isset($response['data']['files'])) {
                $results['files'] = $response['data']['files'];
            }
            
            if ($progress) {
                $progress->tick();
            }
        }
        
        if ($progress) {
            $progress->finish();
        }
        
        // Get export statistics
        if ($results['success']) {
            $last_export = get_option('reign_demo_last_export');
            $results['statistics'] = $last_export['statistics'] ?? array();
        }
        
        return $results;
    }
    
    /**
     * Check if export files already exist
     */
    private function check_existing_exports() {
        $exports = Reign_Demo_Exporter_Utils::check_existing_exports();
        return !empty($exports);
    }
    
    /**
     * Check system requirements (internal method)
     */
    private function check_requirements_internal($quiet = false) {
        // Set current user as admin for CLI
        wp_set_current_user(1);
        
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $_POST['nonce'] = wp_create_nonce('reign_demo_export_nonce');
        
        ob_start();
        $ajax_handler->check_system_requirements();
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        if ($response['success']) {
            $all_passed = true;
            $requirements = $response['data'];
            
            foreach ($requirements as $key => $req) {
                if (!$req['status'] && $key !== 'reign_theme') {
                    $all_passed = false;
                    if (!$quiet) {
                        WP_CLI::warning("  ✗ {$req['label']}: {$req['current']} (Required: {$req['required']})");
                    }
                } elseif (!$quiet) {
                    WP_CLI::log("  ✓ {$req['label']}: {$req['current']}");
                }
            }
            
            return $all_passed;
        }
        
        return false;
    }
    
    /**
     * Display export results
     */
    private function display_results($results, $format, $quiet) {
        if ($quiet) {
            return;
        }
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($results, JSON_PRETTY_PRINT));
        } elseif ($format === 'yaml') {
            WP_CLI::log(Spyc::YAMLDump($results));
        } else {
            // Table format
            WP_CLI::log("\nExport Files Created:");
            
            $table_data = array();
            foreach ($results['files'] as $filename => $url) {
                $filepath = REIGN_DEMO_EXPORT_DIR . $filename . '.json';
                if ($filename === 'package') {
                    $filepath = REIGN_DEMO_EXPORT_DIR . 'content-package.zip';
                }
                
                $size = file_exists($filepath) ? $this->format_file_size(filesize($filepath)) : 'N/A';
                
                $table_data[] = array(
                    'File' => basename($filepath),
                    'Size' => $size,
                    'URL' => $url
                );
            }
            
            WP_CLI\Utils\format_items('table', $table_data, array('File', 'Size', 'URL'));
            
            if (!empty($results['statistics'])) {
                WP_CLI::log("\nExport Statistics:");
                WP_CLI::log("  Posts: " . $results['statistics']['posts']);
                WP_CLI::log("  Pages: " . $results['statistics']['pages']);
                WP_CLI::log("  Users: " . $results['statistics']['users']);
                WP_CLI::log("  Active Plugins: " . $results['statistics']['active_plugins']);
            }
        }
    }
    
    /**
     * Format file size
     */
    private function format_file_size($bytes) {
        return Reign_Demo_Exporter_Utils::format_bytes($bytes);
    }
}

// Register the command
WP_CLI::add_command('reign-demo', 'Reign_Demo_CLI_Command');