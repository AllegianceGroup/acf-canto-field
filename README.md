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

**Note:** The field now stores only the filename as a string. When you call `get_field()`, the plugin automatically looks up the full asset data from Canto using the filename.

```php
// Get the complete asset object (default)
$canto_asset = get_field('your_field_name');

if ($canto_asset) {
    echo '<img src="' . $canto_asset['thumbnail'] . '" alt="' . $canto_asset['name'] . '">';
    echo '<p>Dimensions: ' . $canto_asset['dimensions'] . '</p>';
    echo '<p>File Size: ' . $canto_asset['size'] . '</p>';
    echo '<p>Asset Type: ' . $canto_asset['scheme'] . '</p>';
    echo '<p>Filename: ' . $canto_asset['filename'] . '</p>';
}

// When return format is 'ID', you get the Canto asset ID (dynamically retrieved)
$asset_id = get_field('your_field_name'); // if return format is 'ID'

// When return format is 'URL', you get the preview URL (dynamically retrieved)  
$asset_url = get_field('your_field_name'); // if return format is 'URL'
```

#### Finding assets by filename

You can also search for assets directly by their filename:

```php
// Find an asset by filename
$asset = acf_canto_find_asset_by_filename('company-logo.png');

if ($asset) {
    echo '<img src="' . $asset['thumbnail'] . '" alt="' . $asset['name'] . '">';
    echo '<p>Found asset: ' . $asset['name'] . '</p>';
}

// Flexible retrieval - works with both asset IDs and filenames
$asset = acf_canto_get_asset('product-brochure.pdf'); // or use asset ID
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

Flexible asset retrieval that works with both asset IDs and filenames.

```php
// Works with asset ID
$asset = acf_canto_get_asset('canto_asset_12345');

// Also works with filename
$asset = acf_canto_get_asset('product-brochure.pdf');

if ($asset) {
    echo '<img src="' . $asset['thumbnail'] . '" alt="' . $asset['name'] . '">';
}
```

**Parameters:**
- `$identifier` (string) - Either a Canto asset ID or filename

**Returns:**
- Array of asset data if found, `false` otherwise

**Note:** The function first attempts to retrieve by asset ID (if the identifier looks like an ID), then falls back to filename search if that fails or if the identifier contains a file extension.

## Features

- **Search Integration**: Search your Canto library directly from the field interface
- **Browse Navigation**: Navigate through Canto albums and folders using tree structure
- **Asset Preview**: View thumbnails and metadata before selecting
- **Multiple Asset Types**: Supports images, videos, and documents with appropriate handling
- **Flexible Return Formats**: Choose between full object, ID only, or URL only
- **Filename-Based Retrieval**: Find and retrieve assets using their original filename
- **Migration Support**: Track and manage asset migrations using filename identifiers
- **Thumbnail Proxy**: Handles thumbnail display even when direct URLs require authentication
- **Caching**: Asset data is cached for improved performance
- **Responsive Interface**: Modal interface that works well on desktop and mobile devices
- **Error Handling**: Graceful fallbacks for missing assets or API issues
- **Security**: Proper nonce verification and capability checks
- **Debug Logging**: Comprehensive logging for troubleshooting when WP_DEBUG is enabled

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

Check your WordPress debug log for any error messages.

## Support

For support and bug reports, please create an issue on the plugin's GitHub repository.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### 1.1.0
- **BREAKING CHANGE: Simplified storage format**
  - Fields now store only the filename as a string (no more JSON or asset IDs)
  - Asset ID is dynamically resolved from filename when needed
  - Significantly simplified data structure and retrieval logic
- **NEW: Enhanced filename-based functionality**
  - Added `acf_canto_find_asset_by_filename()` helper function
  - Added `acf_canto_get_asset()` flexible retrieval function
  - Added AJAX endpoint for filename-based searches
  - Implemented smart filename extraction from Canto metadata
- Migration support for transitioning from old storage formats
- Added comprehensive filename usage examples and documentation

### 1.0.0
- Initial release
- Custom ACF field type for Canto asset selection
- AJAX-powered search functionality
- Tree navigation for albums and folders
- Browse view with album/folder structure
- Multiple return format options (Object, ID, URL)
- Thumbnail proxy for authenticated asset access
- Responsive modal interface with tab navigation
- Support for images, videos, and documents
- Comprehensive error handling and fallback mechanisms
- Asset data caching for improved performance
- Debug logging for troubleshooting
- Usage examples and documentation
