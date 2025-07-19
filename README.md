# Reign Demo Exporter Plugin

## Overview

The Reign Demo Exporter is a WordPress plugin that creates standardized export files for Reign Theme demo sites. It generates JSON manifests and content packages that can be imported by the Reign Demo Importer system.

## Key Features

### Complete Data Export

- **Table-by-Table Export**: Exports ALL custom tables regardless of plugin configuration
- **Full Upload Directory**: Copies entire uploads folder structure preserving all plugin data
- **Non-Attachment Files**: Captures avatars, covers, documents, and other files that aren't in Media Library
- **Plugin-Specific Folders**: Automatically detects and exports all plugin data directories

## Installation

1. **Upload the Plugin**

   - Upload the `reign-demo-exporter` folder to your `/wp-content/plugins/` directory
   - Or install via WordPress admin: Plugins → Add New → Upload Plugin

2. **Activate the Plugin**

   - Go to Plugins → Installed Plugins
   - Find "Reign Demo Exporter" and click "Activate"

3. **Verify Requirements**
   - PHP 7.4 or higher
   - WordPress 6.0 or higher
   - Reign Theme must be active
   - At least 256MB memory limit

## Usage

### Admin Interface

1. **Navigate to Export Tool**

   - Go to Tools → Reign Demo Export in your WordPress admin

2. **Check Requirements**

   - Click "Check Requirements" to ensure your system meets all requirements
   - Fix any issues before proceeding

3. **Start Export**

   - Click "Start Export" to begin the export process
   - Do not navigate away from the page during export
   - The process may take several minutes depending on site size

4. **Export Complete**
   - Once complete, you'll see download links for all export files
   - Files are automatically saved to `/wp-content/reign-demo-export/`

### WP-CLI Commands

The plugin includes comprehensive WP-CLI support for command-line exports:

#### Export Command
```bash
# Basic export
wp reign-demo export

# Force export (overwrite existing files without confirmation)
wp reign-demo export --force

# Skip requirements check
wp reign-demo export --skip-requirements-check

# Quiet mode (minimal output)
wp reign-demo export --quiet

# Export with JSON output format
wp reign-demo export --format=json
```

#### Check Requirements
```bash
# Check system requirements
wp reign-demo check-requirements

# Output as JSON
wp reign-demo check-requirements --format=json
```

#### List Export Files
```bash
# List existing export files
wp reign-demo list

# Output as JSON
wp reign-demo list --format=json
```

#### Clean Export Files
```bash
# Delete export files (with confirmation)
wp reign-demo clean

# Force delete without confirmation
wp reign-demo clean --force
```

#### Export Information
```bash
# Get information about the last export
wp reign-demo info

# Output as JSON
wp reign-demo info --format=json
```

## Generated Files

The plugin creates the following files in `/wp-content/reign-demo-export/`:

### 1. `manifest.json`

Main demo configuration including:

- Demo metadata (name, ID, category)
- Content summary (posts, pages, users, etc.)
- Theme settings information
- Feature list and requirements

### 2. `plugins-manifest.json`

Plugin requirements including:

- Required and optional plugins
- Plugin sources (WordPress.org, premium, custom)
- Version information
- License requirements

### 3. `files-manifest.json`

File structure information including:

- Complete uploads directory structure
- All plugin-specific folders (BuddyPress, PeepSo, etc.)
- Media file statistics
- Special folders inventory
- Database table listing

### 4. `content-package.zip`

Complete content export including:

- **database/**: SQL dumps for all database tables
  - Individual `.sql` files for each table (gzipped if >1MB)
  - `complete-export.sql.gz`: Combined SQL file for easy import
  - `import-order.json`: Specifies correct table import sequence
- **uploads/**: Complete uploads directory with all files
- **theme/**: Theme customizations
- **export-info.json**: Export metadata and statistics

## What Gets Exported

### Database Content

- All posts, pages, and custom post types
- Users with ID 100+ (demo users)
- All custom tables (bp*\*, wc*\*, etc.)
- Comments, menus, widgets
- Theme settings and options
- Exported as SQL dumps with:
  - Table structure (CREATE TABLE)
  - Data (INSERT statements)
  - Automatic chunking for large tables
  - Excluded transients and cache data

### File System

- **Complete uploads folder** including:
  - Year/month organized media
  - BuddyPress/BuddyBoss avatars and covers
  - PeepSo user files
  - WooCommerce downloadable files
  - LMS certificates and assignments
  - Elementor generated CSS
  - All other plugin folders

### Special Handling

- Non-attachment files (avatars, covers) use real paths
- Plugin-specific directories are preserved
- Cache folders included for page builders
- Protected/private files included

## File Access

All exported files are automatically accessible via:

- `https://yoursite.com/wp-content/reign-demo-export/manifest.json`
- `https://yoursite.com/wp-content/reign-demo-export/plugins-manifest.json`
- `https://yoursite.com/wp-content/reign-demo-export/files-manifest.json`
- `https://yoursite.com/wp-content/reign-demo-export/content-package.zip`

## Important Notes

### Large Sites

- The export includes COMPLETE uploads directory
- Package size can be several GB for media-heavy sites
- Ensure sufficient disk space (2x the uploads folder size)
- Consider server timeout settings

### User Data

- All users with ID 100+ are exported as demo users
- User passwords are exported in hashed form
- Email addresses are preserved for demo purposes
- User metadata is included

### Security

- API keys and sensitive data are excluded from options export
- IP addresses are removed from comments
- Private content is included (ensure this is intended)

### Performance

- Uses shell commands for faster copying when available
- Falls back to PHP copying if shell_exec is disabled
- Progress tracking shows current operation
- May require increased memory limit for large sites

## Supported Plugin Data

The exporter automatically detects and includes data from:

### Social/Community

- BuddyPress (all tables, avatars, covers)
- BuddyBoss Platform (documents, videos, media)
- PeepSo (user files, covers, photos)

### E-Commerce

- WooCommerce (orders, logs, downloadables)
- Dokan (vendor files)
- WCFM (vendor data)
- WC Vendors

### LMS

- LearnDash (certificates, assignments)
- LifterLMS (certificates, exports)
- Tutor LMS
- Sensei LMS

### Others

- Elementor (generated CSS, kits)
- GeoDirectory (temp files)
- WP Job Manager (company logos, resumes)
- Easy Digital Downloads (protected downloads)
- Gravity Forms / WPForms uploads

## Troubleshooting

### Export Fails

1. Check PHP memory limit (increase to 512M or 1GB)
2. Increase max_execution_time or set to 0
3. Ensure write permissions on `/wp-content/`
4. Check available disk space (need 2x uploads size)
5. Review PHP error logs

### Missing Content

1. Check if custom tables exist in database
2. Verify folder permissions in uploads directory
3. Look for PHP warnings about failed copies

### Large Export Size

1. Normal for sites with many images/videos
2. Consider cleaning up unused media first
3. Check for backup files in uploads folder

### Files Not Accessible

1. Check `.htaccess` in export directory
2. Verify file permissions (644 for files)
3. Ensure no security plugins blocking access

## Support

For issues or questions:

- Documentation: https://wbcomdesigns.com/docs/reign-theme/
- Support: support@wbcomdesigns.com

## Import Instructions

### Database Import

1. **Quick Import** (Recommended)
   ```bash
   # Import complete database
   gunzip -c complete-export.sql.gz | mysql -u username -p database_name
   ```

2. **Table-by-Table Import**
   - Follow the order specified in `import-order.json`
   - Import gzipped files:
     ```bash
     gunzip -c table_name.sql.gz | mysql -u username -p database_name
     ```
   - Import regular SQL files:
     ```bash
     mysql -u username -p database_name < table_name.sql
     ```

3. **Files Import**
   - Extract uploads folder to `wp-content/uploads/`
   - Extract theme customizations to appropriate theme directory

### Import Notes

- SQL files automatically handle table creation
- Large tables are chunked but import seamlessly
- Transients and cache data are excluded
- Update site URL after import if needed

## Changelog

### Version 1.0.0

- Initial release
- SQL-based database export (instead of JSON)
- Automatic compression for large SQL files
- Table chunking for memory efficiency
- Import order management
- Complete uploads directory copying
- Support for all plugin-specific folders
- Automatic folder detection
- Progress tracking
- Requirements checker
- WP-CLI support
