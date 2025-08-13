# ACF Canto Field

A custom Advanced Custom Fields (ACF) field that integrates with the Canto plugin to allow users to select assets directly from their Canto library.

## Description

This plugin extends ACF by adding a new field type called "Canto Asset" that enables users to browse and select digital assets from their Canto library without leaving the WordPress admin interface.

## Requirements

- WordPress 5.0 or higher
- Advanced Custom Fields (ACF) plugin
- Canto plugin (configured with valid API credentials)
- PHP 7.4 or higher

## Installation

1. Upload the `acf-canto-field` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Make sure you have ACF and the Canto plugin installed and configured

## Usage

### Adding a Canto Asset Field

1. Go to Custom Fields > Field Groups in your WordPress admin
2. Create a new field group or edit an existing one
3. Add a new field and select "Canto Asset" as the field type
4. Configure the field settings:
   - **Field Label**: Display name for the field
   - **Field Name**: Used in code to retrieve the field value
   - **Return Format**: Choose how you want the field value returned:
     - `Object`: Returns the complete asset data with all metadata (default)
     - `ID`: Returns only the Canto asset ID as a string
     - `URL`: Returns the asset preview URL as a string
   - **Required**: Whether the field is required
   - **Default Value**: Not applicable for this field type
   - **Instructions**: Help text shown to users

### Field Interface

The field provides a modal interface with two main tabs:

- **Search Tab**: Search your Canto library by keywords
- **Browse Tab**: Navigate through albums and folders using a tree structure

Both views show asset thumbnails, names, and basic metadata. Users can select an asset by clicking on it, then confirm their selection.

### Frontend Usage

#### Important Notes

- **Thumbnail vs Preview URLs**: Use `thumbnail` for display images as they're optimized for direct access. The `url` field may require authentication.
- **Asset Types**: Check the `scheme` field to handle different asset types (image, video, document) appropriately.
- **Fallback Handling**: The plugin provides fallback thumbnails when Canto assets are unavailable.

#### Getting the field value

**Note:** The field now stores the asset download URL as a string. When you call `get_field()`, the plugin automatically extracts the asset ID from the download URL and loads the full asset data from Canto.

```php
// Get the complete asset object (default)
$canto_asset = get_field('your_field_name');

if ($canto_asset) {
    echo '<img src="' . $canto_asset['thumbnail'] . '" alt="' . $canto_asset['name'] . '">';
    echo '<p>Dimensions: ' . $canto_asset['dimensions'] . '</p>';
    echo '<p>File Size: ' . $canto_asset['size'] . '</p>';
    echo '<p>Asset Type: ' . $canto_asset['scheme'] . '</p>';
    echo '<p>Filename: ' . $canto_asset['filename'] . '</p>';
    echo '<a href="' . $canto_asset['download_url'] . '">Download Original</a>';
}

// When return format is 'ID', you get the Canto asset ID (extracted from download URL)
$asset_id = get_field('your_field_name'); // if return format is 'ID'

// When return format is 'URL', you get the preview URL (dynamically retrieved)  
$asset_url = get_field('your_field_name'); // if return format is 'URL'
```

#### Finding assets by filename (Legacy Support)

For backward compatibility and migration scenarios, you can still search for assets by their filename:

```php
// Find an asset by filename (for migration or legacy compatibility)
$asset = acf_canto_find_asset_by_filename('company-logo.png');

if ($asset) {
    echo '<img src="' . $asset['thumbnail'] . '" alt="' . $asset['name'] . '">';
    echo '<p>Found asset: ' . $asset['name'] . '</p>';
    echo '<p>Download URL: ' . $asset['download_url'] . '</p>';
}

// Flexible retrieval - works with download URLs, asset IDs, and filenames
$asset = acf_canto_get_asset('product-brochure.pdf'); // or use asset ID or download URL
if ($asset) {
    echo '<p>Asset found: ' . $asset['filename'] . '</p>';
}
```

#### Available asset data

When using the 'Object' return format, the following data is available:

```php
array(
    'id' => 'canto_asset_id',           // Canto asset ID
    'scheme' => 'image',                // Asset type: 'image', 'video', or 'document'
    'name' => 'Asset Name',             // Asset name/title from Canto
    'filename' => 'example.jpg',        // Original filename or constructed filename
    'url' => 'preview_url',             // Preview URL (may require authentication)
    'thumbnail' => 'thumbnail_url',     // Thumbnail URL (direct access or proxy)
    'download_url' => 'download_url',   // Download URL for original file
    'dimensions' => '1920x1080',        // Image/video dimensions (if available)
    'mime_type' => 'image/jpeg',        // MIME type (if available)
    'size' => '2.5 MB',                 // Formatted file size (if available)
    'uploaded' => 'timestamp',          // Upload timestamp (if available)
    'metadata' => array()               // Additional metadata from Canto
)
```

#### Using different return formats

```php
// Get just the asset ID
$asset_id = get_field('your_field_name'); // if return format is 'ID'

// Get just the preview URL
$asset_url = get_field('your_field_name'); // if return format is 'URL'

// Example usage with different return formats
if ($asset_id) {
    // When return format is 'ID', you get just the Canto asset ID as a string
    echo 'Asset ID: ' . $asset_id;
}

if ($asset_url) {
    // When return format is 'URL', you get just the preview URL as a string
    echo '<img src="' . $asset_url . '" alt="Canto Asset">';
}
```

### In Twig Templates (Timber)

```twig
{% set canto_asset = post.meta('your_field_name') %}

{% if canto_asset %}
    <div class="canto-asset">
        <img src="{{ canto_asset.thumbnail }}" alt="{{ canto_asset.name }}">
        <div class="asset-info">
            <h4>{{ canto_asset.name }}</h4>
            {% if canto_asset.scheme %}
                <p>Type: {{ canto_asset.scheme|title }}</p>
            {% endif %}
            {% if canto_asset.dimensions %}
                <p>Dimensions: {{ canto_asset.dimensions }}</p>
            {% endif %}
            {% if canto_asset.size %}
                <p>Size: {{ canto_asset.size }}</p>
            {% endif %}
            {% if canto_asset.download_url %}
                <a href="{{ canto_asset.download_url }}" target="_blank">Download Original</a>
            {% endif %}
        </div>
    </div>
{% endif %}
```

## Helper Functions

The plugin provides several helper functions for working with Canto assets:

### `acf_canto_find_asset_by_filename($filename)`

Search for a Canto asset by its filename.

```php
$asset = acf_canto_find_asset_by_filename('company-logo.png');
if ($asset) {
    echo 'Found: ' . $asset['name'];
}
```

### `acf_canto_get_asset($identifier)`

Flexible asset retrieval that works with download URLs, asset IDs, and filenames.

```php
// Works with download URL (primary method)
$asset = acf_canto_get_asset('https://domain.canto.com/api_binary/v1/document/abc123/download');

// Works with asset ID
$asset = acf_canto_get_asset('canto_asset_12345');

// Also works with filename (for backward compatibility)
$asset = acf_canto_get_asset('product-brochure.pdf');

if ($asset) {
    echo '<img src="' . $asset['thumbnail'] . '" alt="' . $asset['name'] . '">';
}
```

**Parameters:**
- `$identifier` (string) - A Canto download URL, asset ID, or filename

**Returns:**
- Array of asset data if found, `false` otherwise

**Note:** The function first attempts to extract the asset ID from download URLs (most efficient), then tries direct asset ID lookup, and finally falls back to filename search for backward compatibility.

## Features

### User Interface & Experience
- **Search Integration**: Search your Canto library directly from the field interface
- **Browse Navigation**: Navigate through Canto albums and folders using tree structure
- **Asset Preview**: View thumbnails and metadata before selecting
- **Multiple Asset Types**: Supports images, videos, and documents with appropriate handling
- **Responsive Interface**: Modal interface that works well on desktop and mobile devices

### Data Management & Storage
- **Download URL-Based Storage**: Fields store asset download URLs as unique identifiers for maximum reliability
- **Asset ID Extraction**: Automatically extracts asset IDs from download URLs for efficient lookups
- **Flexible Return Formats**: Choose between full object, ID only, or URL only
- **Migration Support**: Seamlessly handles migration from filename-based to URL-based identifiers
- **Filename-Based Retrieval**: Find and retrieve assets using their original filename (maintained for backward compatibility)

### Performance & Reliability
- **Advanced Caching System**: Multi-tier caching with optimized cache keys and namespace separation
- **Efficient API Communication**: Centralized HTTP handling with proper timeouts and connection management
- **Smart Asset Loading**: Direct asset loading by ID extraction instead of search-based lookups
- **Optimized Data Processing**: Dedicated formatter classes for consistent and efficient data handling

### Developer Experience
- **Modular Architecture**: Well-organized codebase with separation of concerns using dedicated helper classes
- **Comprehensive Error Handling**: Full try-catch blocks, WP_Error handling, and graceful fallbacks throughout
- **Advanced Logging System**: Configurable debug levels (ERROR, WARNING, INFO, DEBUG) with structured context data
- **Enhanced Documentation**: Improved PHPDoc blocks with proper type hints and detailed method descriptions
- **Modern PHP Standards**: Follows WordPress coding standards with clean, maintainable code structure

### Security & Compatibility
- **Security First**: Proper nonce verification, capability checks, and input sanitization
- **Thumbnail Proxy**: Handles thumbnail display even when direct URLs require authentication  
- **Backward Compatibility**: Maintains support for legacy implementations while providing modern features
- **WordPress Integration**: Seamless integration with WordPress and ACF ecosystem

## Troubleshooting

### Common Issues

1. **"Canto plugin not available" error**
   - Make sure the Canto plugin is installed and activated
   - Verify that your Canto API credentials are properly configured
   - Check that the Canto domain and API token are set in WordPress options

2. **Assets not loading or showing default thumbnails**
   - Check that your Canto domain is properly set (`fbc_flight_domain` option)
   - Verify your API token is valid and not expired (`fbc_app_token` option)
   - Ensure the Canto API endpoints are accessible from your server
   - Check the WordPress debug log for API error messages

3. **Field not appearing in ACF**
   - Make sure ACF is installed and activated
   - Check that you're using a compatible version of ACF (5.0+)
   - Verify the plugin is activated in WordPress admin

4. **Search not working**
   - Ensure your Canto API token has search permissions
   - Check network connectivity to Canto servers
   - Look for JavaScript errors in browser console

5. **Tree navigation not loading**
   - The tree endpoint may not be available on all Canto instances
   - The plugin will fall back to "All Assets" if tree API is unavailable
   - Check debug logs for tree API response status

### Debug Mode

To enable debug mode, add this to your `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Advanced Logging Configuration

The plugin now includes a configurable logging system with multiple debug levels:

```php
// Set logging level (optional - defaults to INFO when WP_DEBUG is enabled)
define('ACF_CANTO_LOG_LEVEL', 4); // 1=ERROR, 2=WARNING, 3=INFO, 4=DEBUG

// Example log levels:
define('ACF_CANTO_LOG_LEVEL', 1); // Only critical errors
define('ACF_CANTO_LOG_LEVEL', 2); // Errors and warnings
define('ACF_CANTO_LOG_LEVEL', 3); // Errors, warnings, and info messages (default)
define('ACF_CANTO_LOG_LEVEL', 4); // All messages including detailed debug info
```

**Log Level Details:**
- **ERROR (1)**: Critical errors that prevent functionality
- **WARNING (2)**: Non-critical issues that should be addressed
- **INFO (3)**: General operational messages and status updates
- **DEBUG (4)**: Detailed debugging information including API requests/responses

Check your WordPress debug log (`/wp-content/debug.log`) for detailed error messages with context data.

## Support

For support and bug reports, please create an issue on the plugin's GitHub repository.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history and release notes.
