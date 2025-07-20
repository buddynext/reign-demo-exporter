=== Reign Demo Exporter ===
Contributors: wbcomdesigns
Donate link: https://wbcomdesigns.com/donate/
Tags: demo, export, import, content, reign-theme
Requires at least: 6.0
Tested up to: 6.8.2
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

One-click export tool to generate standardized demo content packages for Reign Theme demo sites. Creates JSON manifests and SQL exports for easy demo replication.

== Description ==

Reign Demo Exporter is a powerful WordPress plugin designed specifically for Reign Theme users who need to create exportable demo content packages. It generates standardized export files that can be imported to replicate demo sites perfectly.

= Key Features =

* **One-Click Export**: Simple interface to export your entire demo site
* **Smart Content Detection**: Automatically identifies and exports all custom content
* **SQL-Based Export**: Reliable database export with proper data integrity
* **Intelligent Exclusions**: Automatically excludes cache, logs, and backup files
* **WP-CLI Support**: Full command-line interface for automated exports
* **Progress Tracking**: Real-time export progress with detailed status updates
* **Settings Customization**: Configure what content to include in exports

= What Gets Exported =

**Database Content:**
* All posts, pages, and custom post types
* Users (configurable minimum ID)
* Menus, widgets, and theme settings
* All custom tables (BuddyPress, WooCommerce, etc.)
* Comments and metadata

**Files:**
* Complete uploads directory structure
* Theme customizations
* Plugin-specific uploads (BuddyPress avatars, covers, etc.)

**Configuration:**
* Active plugins list with version info
* Theme settings and customizer data
* Site configuration and requirements

= Export Files Generated =

1. `manifest.json` - Main demo configuration
2. `plugins-manifest.json` - Plugin requirements
3. `files-manifest.json` - File structure information
4. `content-package.zip` - Complete content package

= Perfect For =

* Theme developers creating demo sites
* Agencies replicating site templates
* Developers sharing demo content
* Creating backup snapshots of demo sites

= Requirements =

* WordPress 6.0 or higher
* PHP 7.4 or higher
* Reign Theme (active)
* 256MB memory limit (recommended)
* Write permissions on wp-content directory

== Installation ==

= From WordPress Admin =

1. Navigate to Plugins > Add New
2. Search for "Reign Demo Exporter"
3. Click "Install Now" and then "Activate"
4. Go to Tools > Reign Demo Export to start

= Manual Installation =

1. Download the plugin zip file
2. Extract to `/wp-content/plugins/reign-demo-exporter/`
3. Activate through the 'Plugins' menu in WordPress
4. Navigate to Tools > Reign Demo Export

= After Activation =

1. Go to Settings > Reign Demo Exporter to configure options
2. Navigate to Tools > Reign Demo Export
3. Click "Check Requirements" to verify your system
4. Click "Start Export" to begin the export process

== Frequently Asked Questions ==

= Do I need Reign Theme to use this plugin? =

Yes, this plugin is specifically designed for Reign Theme and requires it to be active.

= What PHP version is required? =

PHP 7.4 or higher is required. PHP 8.0+ is recommended for better performance.

= Where are the export files stored? =

Export files are stored in `/wp-content/reign-demo-export/` and are accessible via HTTP.

= Can I exclude specific content from export? =

Yes, you can configure exclusions in Settings > Reign Demo Exporter. You can exclude specific database tables, file patterns, and set minimum user ID for export.

= Is the exported data compressed? =

Yes, large SQL files (>1MB) are automatically compressed using gzip. The entire export is packaged in a ZIP file.

= Can I use WP-CLI to export? =

Yes, full WP-CLI support is included. Use `wp reign-demo export` to start an export from the command line.

= What happens to user passwords? =

User passwords are exported in their hashed form. They remain secure and cannot be reversed to plain text.

= Are transients and cache data exported? =

No, transients, cache data, and temporary files are automatically excluded to keep exports clean.

= Can I export only specific plugins' data? =

The plugin exports all active plugins' data. Use the settings page to exclude specific database tables if needed.

= How large can the export be? =

Export size depends on your site content. The plugin handles large sites well, but ensure you have sufficient disk space (2x your uploads folder size).

== Screenshots ==

1. Main export interface in WordPress admin
2. Export progress tracking with real-time updates
3. Settings page with configuration options
4. Successful export with download links
5. WP-CLI commands in action
6. Generated export files overview

== Changelog ==

= 1.0.0 - 2025-07-20 =
* Initial release
* One-click export functionality
* SQL-based database export
* Smart file exclusions
* WP-CLI command support
* Settings page for customization
* Progress tracking interface
* Support for all custom tables
* Automatic compression for large files
* Security hardening and validation

== Upgrade Notice ==

= 1.0.0 =
Initial release of Reign Demo Exporter. Install to start creating exportable demo packages for your Reign Theme sites.

== Additional Information ==

= WP-CLI Commands =

* `wp reign-demo export` - Run export
* `wp reign-demo export --force` - Force export (overwrite existing)
* `wp reign-demo check-requirements` - Check system requirements
* `wp reign-demo list` - List existing exports
* `wp reign-demo clean` - Remove export files
* `wp reign-demo info` - Show last export information

= Export File Structure =

The plugin generates a standardized structure that includes:

* SQL dumps for all database tables
* Complete uploads directory
* Theme customization files
* Export metadata and statistics

= Security =

* All AJAX requests are nonce-verified
* Capability checks ensure only administrators can export
* Path validation prevents directory traversal
* Sensitive data can be excluded via settings

= Support =

For support, feature requests, or bug reports, please visit:
* [Plugin Support Forum](https://wordpress.org/support/plugin/reign-demo-exporter/)
* [Documentation](https://wbcomdesigns.com/docs/reign-theme/)
* [GitHub Repository](https://github.com/wbcomdesigns/reign-demo-exporter/)

= Credits =

Developed by [WB Com Designs](https://wbcomdesigns.com/)

= Privacy Policy =

This plugin does not collect any personal data. All export operations are performed locally on your server. Exported files may contain user data from your site - handle with appropriate care.

== Development ==

= Contributing =

We welcome contributions! Please see our [GitHub repository](https://github.com/wbcomdesigns/reign-demo-exporter/) for development guidelines.

= Hooks and Filters =

The plugin provides several hooks for developers:

* `reign_demo_export_before_export` - Run before export starts
* `reign_demo_export_after_export` - Run after export completes
* `reign_demo_export_excluded_tables` - Filter excluded database tables
* `reign_demo_export_excluded_files` - Filter excluded file patterns

= Custom Exclusions =

Add custom exclusions via filters:

`add_filter('reign_demo_export_excluded_tables', function($tables) {
    $tables[] = 'my_custom_table';
    return $tables;
});`