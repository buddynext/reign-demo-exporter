<?php
/**
 * Admin Export Page View Template
 * 
 * @package Reign_Demo_Exporter
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get data passed from admin class
$existing_exports = isset($args['existing_exports']) ? $args['existing_exports'] : array();
$last_export = isset($args['last_export']) ? $args['last_export'] : array();
?>

<div class="wrap reign-demo-exporter-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="reign-demo-exporter-container">
        <!-- Main Export Box -->
        <div class="export-box">
            <div class="export-header">
                <h2><?php _e('Export Demo Content for Reign Theme', 'reign-demo-exporter'); ?></h2>
            </div>
            
            <div class="export-content">
                <p><?php _e('This will create the following files in your site root:', 'reign-demo-exporter'); ?></p>
                
                <div class="notice notice-info inline">
                    <p>
                        <?php _e('Before exporting, you may want to', 'reign-demo-exporter'); ?> 
                        <a href="<?php echo admin_url('options-general.php?page=reign-demo-exporter-settings'); ?>">
                            <?php _e('configure the export settings', 'reign-demo-exporter'); ?>
                        </a> 
                        <?php _e('to customize what data is included.', 'reign-demo-exporter'); ?>
                    </p>
                </div>
                
                <ul class="export-files-list">
                    <li><code>manifest.json</code> - <?php _e('Main demo configuration', 'reign-demo-exporter'); ?></li>
                    <li><code>plugins-manifest.json</code> - <?php _e('Plugin requirements and sources', 'reign-demo-exporter'); ?></li>
                    <li><code>files-manifest.json</code> - <?php _e('File structure and media information', 'reign-demo-exporter'); ?></li>
                    <li><code>content-package.zip</code> - <?php _e('Complete content export package', 'reign-demo-exporter'); ?></li>
                </ul>
                
                <?php if (!empty($existing_exports)): ?>
                    <div class="notice notice-warning">
                        <p><?php _e('Export files already exist. Running a new export will overwrite them.', 'reign-demo-exporter'); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="export-actions">
                    <button id="start-export" class="button button-primary button-hero">
                        <?php _e('Start Export', 'reign-demo-exporter'); ?>
                    </button>
                    
                    <button id="check-requirements" class="button button-secondary">
                        <?php _e('Check Requirements', 'reign-demo-exporter'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Progress Section -->
        <div class="export-progress" style="display: none;">
            <h3><?php _e('Export Progress:', 'reign-demo-exporter'); ?></h3>
            <div class="progress-bar-wrapper">
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: 0%;"></div>
                </div>
                <span class="progress-percentage">0%</span>
            </div>
            <p class="progress-message"></p>
        </div>
        
        <!-- Requirements Check Results -->
        <div id="requirements-results" style="display: none;">
            <h3><?php _e('System Requirements Check', 'reign-demo-exporter'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Requirement', 'reign-demo-exporter'); ?></th>
                        <th><?php _e('Required', 'reign-demo-exporter'); ?></th>
                        <th><?php _e('Current', 'reign-demo-exporter'); ?></th>
                        <th><?php _e('Status', 'reign-demo-exporter'); ?></th>
                    </tr>
                </thead>
                <tbody id="requirements-table-body">
                    <!-- Populated via AJAX -->
                </tbody>
            </table>
        </div>
        
        <!-- Export History -->
        <?php if (!empty($last_export)): ?>
        <div class="export-history">
            <h3><?php _e('Last Export', 'reign-demo-exporter'); ?></h3>
            <div class="history-item">
                <p>
                    <strong><?php _e('Date:', 'reign-demo-exporter'); ?></strong> 
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_export['export_date']))); ?>
                </p>
                <?php if (isset($last_export['statistics'])): ?>
                <p>
                    <strong><?php _e('Content:', 'reign-demo-exporter'); ?></strong>
                    <?php 
                    echo sprintf(
                        __('%d posts, %d pages, %d users, %d active plugins', 'reign-demo-exporter'),
                        $last_export['statistics']['posts'],
                        $last_export['statistics']['pages'],
                        $last_export['statistics']['users'],
                        $last_export['statistics']['active_plugins']
                    );
                    ?>
                </p>
                <?php endif; ?>
                <?php if (isset($last_export['export_duration'])): ?>
                <p>
                    <strong><?php _e('Duration:', 'reign-demo-exporter'); ?></strong>
                    <?php 
                    $duration = $last_export['export_duration'];
                    if ($duration < 60) {
                        echo esc_html($duration . ' ' . __('seconds', 'reign-demo-exporter'));
                    } else {
                        $minutes = floor($duration / 60);
                        $seconds = $duration % 60;
                        echo esc_html(sprintf(__('%d minutes %d seconds', 'reign-demo-exporter'), $minutes, $seconds));
                    }
                    ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Existing Export Files -->
        <?php if (!empty($existing_exports)): ?>
        <div class="existing-exports">
            <h3><?php _e('Current Export Files', 'reign-demo-exporter'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('File', 'reign-demo-exporter'); ?></th>
                        <th><?php _e('Size', 'reign-demo-exporter'); ?></th>
                        <th><?php _e('Modified', 'reign-demo-exporter'); ?></th>
                        <th><?php _e('Actions', 'reign-demo-exporter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existing_exports as $file): ?>
                    <tr>
                        <td><code><?php echo esc_html($file['name']); ?></code></td>
                        <td><?php echo esc_html($file['size']); ?></td>
                        <td><?php echo esc_html($file['modified']); ?></td>
                        <td>
                            <a href="<?php echo esc_url($file['url']); ?>" class="button button-small" target="_blank">
                                <?php _e('View', 'reign-demo-exporter'); ?>
                            </a>
                            <a href="<?php echo esc_url($file['url']); ?>" class="button button-small" download>
                                <?php _e('Download', 'reign-demo-exporter'); ?>
                            </a>
                            <button class="button button-small delete-export-file" data-file="<?php echo esc_attr($file['name']); ?>">
                                <?php _e('Delete', 'reign-demo-exporter'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top: 10px;">
                <button class="button button-secondary delete-all-exports">
                    <?php _e('Delete All Export Files', 'reign-demo-exporter'); ?>
                </button>
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Success Message -->
        <div class="export-success" style="display: none;">
            <div class="notice notice-success">
                <h3><?php _e('Export Completed Successfully!', 'reign-demo-exporter'); ?></h3>
                <p><?php _e('Your demo export files have been created and are accessible at:', 'reign-demo-exporter'); ?></p>
                <ul class="export-urls">
                    <!-- Populated via JavaScript -->
                </ul>
            </div>
        </div>
        
        <!-- Error Message -->
        <div class="export-error" style="display: none;">
            <div class="notice notice-error">
                <p class="error-message"></p>
            </div>
        </div>
    </div>
    
    <!-- Help Section -->
    <div class="reign-demo-exporter-help">
        <h3><?php _e('How to Use', 'reign-demo-exporter'); ?></h3>
        <ol>
            <li><?php _e('Click "Check Requirements" to ensure your system meets the export requirements.', 'reign-demo-exporter'); ?></li>
            <li><?php _e('Click "Start Export" to begin the export process.', 'reign-demo-exporter'); ?></li>
            <li><?php _e('Wait for the process to complete. Do not navigate away from this page.', 'reign-demo-exporter'); ?></li>
            <li><?php _e('Once complete, the export files will be available in the /wp-content/reign-demo-export/ directory.', 'reign-demo-exporter'); ?></li>
            <li><?php _e('These files will be automatically accessible to the Reign Demo Importer system.', 'reign-demo-exporter'); ?></li>
        </ol>
        
        <h4><?php _e('Important Notes:', 'reign-demo-exporter'); ?></h4>
        <ul>
            <li><?php _e('The export process may take several minutes depending on your site size.', 'reign-demo-exporter'); ?></li>
            <li><?php _e('The complete uploads directory will be copied, which may result in large file sizes.', 'reign-demo-exporter'); ?></li>
            <li><?php _e('All custom database tables will be exported automatically.', 'reign-demo-exporter'); ?></li>
            <li><?php _e('Ensure you have sufficient disk space (at least 2x your uploads folder size).', 'reign-demo-exporter'); ?></li>
            <li><?php _e('User passwords are exported in hashed form for security.', 'reign-demo-exporter'); ?></li>
            <li><?php _e('Sensitive data like API keys are excluded from the export.', 'reign-demo-exporter'); ?></li>
        </ul>
        
        <h4><?php _e('What Gets Exported:', 'reign-demo-exporter'); ?></h4>
        <ul>
            <li><strong><?php _e('Database:', 'reign-demo-exporter'); ?></strong> <?php _e('All posts, pages, users (ID 100+), and custom tables', 'reign-demo-exporter'); ?></li>
            <li><strong><?php _e('Files:', 'reign-demo-exporter'); ?></strong> <?php _e('Complete uploads directory including all plugin folders', 'reign-demo-exporter'); ?></li>
            <li><strong><?php _e('Settings:', 'reign-demo-exporter'); ?></strong> <?php _e('Theme options, widgets, menus, and plugin settings', 'reign-demo-exporter'); ?></li>
            <li><strong><?php _e('Special Data:', 'reign-demo-exporter'); ?></strong> <?php _e('BuddyPress/BuddyBoss data, WooCommerce products, LMS courses', 'reign-demo-exporter'); ?></li>
        </ul>
    </div>
</div>

<script type="text/javascript">
    var reign_demo_exporter_data = {
        confirm_export: '<?php _e('Are you sure you want to start the export? This may take several minutes and will overwrite any existing export files.', 'reign-demo-exporter'); ?>',
        export_url: '<?php echo esc_url(REIGN_DEMO_EXPORT_URL); ?>'
    };
</script>