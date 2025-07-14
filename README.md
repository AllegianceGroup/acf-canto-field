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
   - **Return Format**: Choose how you want the field value returned:
     - `Object`: Returns the complete asset data (default)
     - `ID`: Returns only the Canto asset ID
     - `URL`: Returns the asset preview URL

### Frontend Usage

#### Getting the field value

```php
// Get the complete asset object (default)
$canto_asset = get_field('your_field_name');

if ($canto_asset) {
    echo '<img src="' . $canto_asset['url'] . '" alt="' . $canto_asset['name'] . '">';
    echo '<p>Dimensions: ' . $canto_asset['dimensions'] . '</p>';
    echo '<p>File Size: ' . $canto_asset['size'] . '</p>';
}
```

#### Available asset data

When using the 'Object' return format, the following data is available:

```php
array(
    'id' => 'asset_id',
    'name' => 'Asset Name',
    'url' => 'preview_url',
    'thumbnail' => 'thumbnail_url',
    'download_url' => 'download_url',
    'dimensions' => 'width x height',
    'mime_type' => 'image/jpeg',
    'size' => 'formatted_file_size',
    'uploaded' => 'upload_date',
    'metadata' => array() // Additional Canto metadata
)
```

#### Using different return formats

```php
// Get just the asset ID
$asset_id = get_field('your_field_name'); // if return format is 'ID'

// Get just the asset URL
$asset_url = get_field('your_field_name'); // if return format is 'URL'
```

### In Twig Templates (Timber)

```twig
{% set canto_asset = post.meta('your_field_name') %}

{% if canto_asset %}
    <img src="{{ canto_asset.url }}" alt="{{ canto_asset.name }}">
    <div class="asset-info">
        <h4>{{ canto_asset.name }}</h4>
        {% if canto_asset.dimensions %}
            <p>Dimensions: {{ canto_asset.dimensions }}</p>
        {% endif %}
        {% if canto_asset.size %}
            <p>Size: {{ canto_asset.size }}</p>
        {% endif %}
    </div>
{% endif %}
```

## Features

- **Search Integration**: Search your Canto library directly from the field interface
- **Asset Preview**: View thumbnails and metadata before selecting
- **Multiple Asset Types**: Supports images, videos, and documents
- **Caching**: Asset data is cached for improved performance
- **Responsive Interface**: Works well on desktop and mobile devices
- **Security**: Proper nonce verification and capability checks

## Troubleshooting

### Common Issues

1. **"Canto plugin not available" error**
   - Make sure the Canto plugin is installed and activated
   - Verify that your Canto API credentials are properly configured

2. **Assets not loading**
   - Check that your Canto domain is properly set in the plugin settings
   - Verify your API token is valid and not expired

3. **Field not appearing**
   - Make sure ACF is installed and activated
   - Check that you're using a compatible version of ACF

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

### 1.0.0
- Initial release
- Basic Canto asset selection functionality
- Search and browse capabilities
- Multiple return format options
- Responsive modal interface
