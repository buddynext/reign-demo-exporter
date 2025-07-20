<?php
/**
 * Settings Class for Reign Demo Exporter
 * 
 * @package Reign_Demo_Exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Exporter_Settings {
    
    private $option_name = 'reign_demo_exporter_settings';
    private $settings_group = 'reign_demo_exporter_settings_group';
    
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            $this->settings_group,
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->get_default_settings()
            )
        );
        
        // Export Settings Section
        add_settings_section(
            'reign_demo_export_settings',
            __('Export Settings', 'reign-demo-exporter'),
            array($this, 'render_export_section'),
            'reign_demo_exporter_settings'
        );
        
        // Demo Information Section
        add_settings_section(
            'reign_demo_info_settings',
            __('Demo Information', 'reign-demo-exporter'),
            array($this, 'render_info_section'),
            'reign_demo_exporter_settings'
        );
        
        // Add fields
        $this->add_settings_fields();
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // Export Settings Fields
        add_settings_field(
            'export_users',
            __('Export Users', 'reign-demo-exporter'),
            array($this, 'render_export_users_field'),
            'reign_demo_exporter_settings',
            'reign_demo_export_settings'
        );
        
        add_settings_field(
            'min_user_id',
            __('Minimum User ID', 'reign-demo-exporter'),
            array($this, 'render_min_user_id_field'),
            'reign_demo_exporter_settings',
            'reign_demo_export_settings'
        );
        
        add_settings_field(
            'export_media',
            __('Export Media Files', 'reign-demo-exporter'),
            array($this, 'render_export_media_field'),
            'reign_demo_exporter_settings',
            'reign_demo_export_settings'
        );
        
        add_settings_field(
            'exclude_tables',
            __('Exclude Tables', 'reign-demo-exporter'),
            array($this, 'render_exclude_tables_field'),
            'reign_demo_exporter_settings',
            'reign_demo_export_settings'
        );
        
        add_settings_field(
            'chunk_size',
            __('Database Chunk Size', 'reign-demo-exporter'),
            array($this, 'render_chunk_size_field'),
            'reign_demo_exporter_settings',
            'reign_demo_export_settings'
        );
        
        add_settings_field(
            'compress_threshold',
            __('Compression Threshold', 'reign-demo-exporter'),
            array($this, 'render_compress_threshold_field'),
            'reign_demo_exporter_settings',
            'reign_demo_export_settings'
        );
        
        add_settings_field(
            'exclude_upload_patterns',
            __('Exclude Upload Patterns', 'reign-demo-exporter'),
            array($this, 'render_exclude_upload_patterns_field'),
            'reign_demo_exporter_settings',
            'reign_demo_export_settings'
        );
        
        // Demo Information Fields
        add_settings_field(
            'demo_id',
            __('Demo ID', 'reign-demo-exporter'),
            array($this, 'render_demo_id_field'),
            'reign_demo_exporter_settings',
            'reign_demo_info_settings'
        );
        
        add_settings_field(
            'demo_name',
            __('Demo Name', 'reign-demo-exporter'),
            array($this, 'render_demo_name_field'),
            'reign_demo_exporter_settings',
            'reign_demo_info_settings'
        );
        
        add_settings_field(
            'demo_category',
            __('Demo Category', 'reign-demo-exporter'),
            array($this, 'render_demo_category_field'),
            'reign_demo_exporter_settings',
            'reign_demo_info_settings'
        );
        
        add_settings_field(
            'demo_tags',
            __('Demo Tags', 'reign-demo-exporter'),
            array($this, 'render_demo_tags_field'),
            'reign_demo_exporter_settings',
            'reign_demo_info_settings'
        );
        
        add_settings_field(
            'demo_description',
            __('Demo Description', 'reign-demo-exporter'),
            array($this, 'render_demo_description_field'),
            'reign_demo_exporter_settings',
            'reign_demo_info_settings'
        );
    }
    
    /**
     * Render export section description
     */
    public function render_export_section() {
        echo '<p>' . __('Configure what data to include in the export.', 'reign-demo-exporter') . '</p>';
    }
    
    /**
     * Render info section description
     */
    public function render_info_section() {
        echo '<p>' . __('Provide information about this demo for the importer.', 'reign-demo-exporter') . '</p>';
    }
    
    /**
     * Render fields
     */
    public function render_export_users_field() {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[export_users]" 
                   value="1" <?php checked($settings['export_users'], 1); ?> />
            <?php _e('Include demo users in the export', 'reign-demo-exporter'); ?>
        </label>
        <p class="description"><?php _e('Export users with ID greater than or equal to the minimum user ID.', 'reign-demo-exporter'); ?></p>
        <?php
    }
    
    public function render_min_user_id_field() {
        $settings = $this->get_settings();
        ?>
        <input type="number" name="<?php echo esc_attr($this->option_name); ?>[min_user_id]" 
               value="<?php echo esc_attr($settings['min_user_id']); ?>" 
               min="1" class="small-text" />
        <p class="description"><?php _e('Only export users with ID >= this value (default: 100).', 'reign-demo-exporter'); ?></p>
        <?php
    }
    
    public function render_export_media_field() {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[export_media]" 
                   value="1" <?php checked($settings['export_media'], 1); ?> />
            <?php _e('Include all media files and uploads', 'reign-demo-exporter'); ?>
        </label>
        <p class="description"><?php _e('Export the entire uploads directory including all media files.', 'reign-demo-exporter'); ?></p>
        <?php
    }
    
    public function render_exclude_tables_field() {
        $settings = $this->get_settings();
        ?>
        <textarea name="<?php echo esc_attr($this->option_name); ?>[exclude_tables]" 
                  rows="5" cols="50" class="large-text"><?php echo esc_textarea($settings['exclude_tables']); ?></textarea>
        <p class="description">
            <?php _e('Enter table names to exclude (one per line). You can use wildcards like "*_logs" or "wp_statistics*".', 'reign-demo-exporter'); ?><br>
            <?php _e('Default exclusions: cache tables, log tables, statistics tables.', 'reign-demo-exporter'); ?>
        </p>
        <?php
    }
    
    public function render_chunk_size_field() {
        $settings = $this->get_settings();
        ?>
        <input type="number" name="<?php echo esc_attr($this->option_name); ?>[chunk_size]" 
               value="<?php echo esc_attr($settings['chunk_size']); ?>" 
               min="100" max="10000" step="100" class="small-text" />
        <p class="description"><?php _e('Number of rows to process at once for large tables (default: 1000).', 'reign-demo-exporter'); ?></p>
        <?php
    }
    
    public function render_compress_threshold_field() {
        $settings = $this->get_settings();
        ?>
        <input type="number" name="<?php echo esc_attr($this->option_name); ?>[compress_threshold]" 
               value="<?php echo esc_attr($settings['compress_threshold']); ?>" 
               min="0.5" max="100" step="0.5" class="small-text" /> MB
        <p class="description"><?php _e('Compress SQL files larger than this size (default: 1MB).', 'reign-demo-exporter'); ?></p>
        <?php
    }
    
    public function render_exclude_upload_patterns_field() {
        $settings = $this->get_settings();
        ?>
        <textarea name="<?php echo esc_attr($this->option_name); ?>[exclude_upload_patterns]" 
                  rows="8" cols="50" class="large-text"><?php echo esc_textarea($settings['exclude_upload_patterns']); ?></textarea>
        <p class="description">
            <?php _e('Enter patterns to exclude from uploads export (one per line). Use wildcards like "*.log" or "backup*".', 'reign-demo-exporter'); ?><br>
            <?php _e('Common exclusions are already built-in: cache folders, backup files, logs, temporary files, archives.', 'reign-demo-exporter'); ?><br>
            <?php _e('Add custom patterns here to exclude additional files or folders.', 'reign-demo-exporter'); ?>
        </p>
        <?php
    }
    
    public function render_demo_id_field() {
        $settings = $this->get_settings();
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[demo_id]" 
               value="<?php echo esc_attr($settings['demo_id']); ?>" 
               class="regular-text" />
        <p class="description"><?php _e('Unique identifier for this demo (e.g., "reign-buddyx").', 'reign-demo-exporter'); ?></p>
        <?php
    }
    
    public function render_demo_name_field() {
        $settings = $this->get_settings();
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[demo_name]" 
               value="<?php echo esc_attr($settings['demo_name']); ?>" 
               class="regular-text" />
        <p class="description"><?php _e('Display name for this demo.', 'reign-demo-exporter'); ?></p>
        <?php
    }
    
    public function render_demo_category_field() {
        $settings = $this->get_settings();
        $categories = array(
            'community' => __('Community', 'reign-demo-exporter'),
            'business' => __('Business', 'reign-demo-exporter'),
            'education' => __('Education', 'reign-demo-exporter'),
            'marketplace' => __('Marketplace', 'reign-demo-exporter'),
            'social' => __('Social Network', 'reign-demo-exporter'),
            'other' => __('Other', 'reign-demo-exporter')
        );
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[demo_category]">
            <?php foreach ($categories as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['demo_category'], $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    public function render_demo_tags_field() {
        $settings = $this->get_settings();
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[demo_tags]" 
               value="<?php echo esc_attr($settings['demo_tags']); ?>" 
               class="large-text" />
        <p class="description"><?php _e('Comma-separated tags (e.g., "buddypress, social, networking").', 'reign-demo-exporter'); ?></p>
        <?php
    }
    
    public function render_demo_description_field() {
        $settings = $this->get_settings();
        ?>
        <textarea name="<?php echo esc_attr($this->option_name); ?>[demo_description]" 
                  rows="3" cols="50" class="large-text"><?php echo esc_textarea($settings['demo_description']); ?></textarea>
        <p class="description"><?php _e('Brief description of this demo site.', 'reign-demo-exporter'); ?></p>
        <?php
    }
    
    /**
     * Get plugin settings
     */
    public function get_settings() {
        $defaults = $this->get_default_settings();
        $settings = get_option($this->option_name, $defaults);
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Get default settings
     */
    private function get_default_settings() {
        $site_name = get_bloginfo('name');
        $site_url = parse_url(get_site_url(), PHP_URL_HOST);
        
        return array(
            // Export settings
            'export_users' => 1,
            'min_user_id' => 100,
            'export_media' => 1,
            'exclude_tables' => "*_logs\n*_log\n*_statistics\n*_stats\n*_cache\n*_sessions",
            'chunk_size' => 1000,
            'compress_threshold' => 1, // 1MB
            'exclude_upload_patterns' => '', // User can add custom patterns
            
            // Demo information
            'demo_id' => 'reign-' . sanitize_title($site_name),
            'demo_name' => $site_name,
            'demo_category' => 'community',
            'demo_tags' => 'community, buddypress, social, networking',
            'demo_description' => sprintf(__('Demo site for %s', 'reign-demo-exporter'), $site_name)
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Checkboxes
        $sanitized['export_users'] = isset($input['export_users']) ? 1 : 0;
        $sanitized['export_media'] = isset($input['export_media']) ? 1 : 0;
        
        // Numbers
        $sanitized['min_user_id'] = absint($input['min_user_id']) ?: 100;
        $sanitized['chunk_size'] = absint($input['chunk_size']) ?: 1000;
        $sanitized['compress_threshold'] = floatval($input['compress_threshold']) ?: 1;
        
        // Text fields
        $sanitized['demo_id'] = sanitize_key($input['demo_id']);
        $sanitized['demo_name'] = sanitize_text_field($input['demo_name']);
        $sanitized['demo_category'] = sanitize_key($input['demo_category']);
        $sanitized['demo_tags'] = sanitize_text_field($input['demo_tags']);
        
        // Textareas
        $sanitized['exclude_tables'] = sanitize_textarea_field($input['exclude_tables']);
        $sanitized['exclude_upload_patterns'] = sanitize_textarea_field($input['exclude_upload_patterns']);
        $sanitized['demo_description'] = sanitize_textarea_field($input['demo_description']);
        
        return $sanitized;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if settings were saved
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            add_settings_error(
                'reign_demo_exporter_messages',
                'reign_demo_exporter_message',
                __('Settings saved successfully.', 'reign-demo-exporter'),
                'updated'
            );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('reign_demo_exporter_messages'); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->settings_group);
                do_settings_sections('reign_demo_exporter_settings');
                submit_button(__('Save Settings', 'reign-demo-exporter'));
                ?>
            </form>
            
            <div class="reign-demo-actions">
                <h2><?php _e('Quick Actions', 'reign-demo-exporter'); ?></h2>
                <p>
                    <a href="<?php echo admin_url('tools.php?page=reign-demo-export'); ?>" class="button button-primary">
                        <?php _e('Go to Export Page', 'reign-demo-exporter'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}