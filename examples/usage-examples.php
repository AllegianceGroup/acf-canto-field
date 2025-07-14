<?php
/**
 * ACF Canto Field - Usage Examples
 * 
 * This file contains practical examples of how to use the ACF Canto Field
 * in your WordPress themes and plugins.
 * 
 * ASSET OBJECT STRUCTURE:
 * When using return format 'object' (default), the field returns an array with:
 * 
 * $asset = array(
 *     'id'           => 'canto_asset_id',     // Canto asset ID (string)
 *     'scheme'       => 'image',              // Asset type: 'image', 'video', or 'document'
 *     'name'         => 'Asset Name',         // Asset name/title from Canto
 *     'url'          => 'preview_url',        // Preview URL (may require authentication)
 *     'thumbnail'    => 'thumbnail_url',      // Thumbnail URL (direct access or proxy)
 *     'download_url' => 'download_url',       // Download URL for original file
 *     'dimensions'   => '1920x1080',          // Image/video dimensions (if available)
 *     'mime_type'    => 'image/jpeg',         // MIME type (if available)
 *     'size'         => '2.5 MB',            // Formatted file size (if available)
 *     'uploaded'     => 'timestamp',          // Upload timestamp (if available)
 *     'metadata'     => array()               // Additional metadata from Canto
 * );
 * 
 * RETURN FORMATS:
 * - 'object' (default): Returns the full asset array above
 * - 'id': Returns just the asset ID as a string
 * - 'url': Returns just the preview URL as a string
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example 1: Basic field value retrieval
 */
function example_basic_usage() {
    // Get the field value (returns object by default)
    $canto_asset = get_field('hero_image');
    
    if ($canto_asset) {
        echo '<div class="hero-image">';
        echo '<img src="' . esc_url($canto_asset['thumbnail']) . '" alt="' . esc_attr($canto_asset['name']) . '">';
        echo '<div class="image-caption">' . esc_html($canto_asset['name']) . '</div>';
        echo '</div>';
    }
}

/**
 * Example 2: Using different return formats
 */
function example_return_formats() {
    // Field configured to return 'id'
    $asset_id = get_field('background_image'); // Returns just the ID string
    
    // Field configured to return 'url' 
    $asset_url = get_field('logo_image'); // Returns just the preview URL string
    
    // Field configured to return 'object' (default)
    $asset_object = get_field('featured_image'); // Returns full asset object
    
    if ($asset_url) {
        echo '<img src="' . esc_url($asset_url) . '" class="logo">';
    }
    
    if ($asset_object) {
        echo '<img src="' . esc_url($asset_object['thumbnail']) . '" alt="' . esc_attr($asset_object['name']) . '">';
    }
}

/**
 * Example 3: Complete asset information display
 */
function example_detailed_display() {
    $asset = get_field('product_image');
    
    if ($asset) {
        ?>
        <div class="asset-display">
            <div class="asset-image">
                <img src="<?php echo esc_url($asset['thumbnail']); ?>" alt="<?php echo esc_attr($asset['name']); ?>">
            </div>
            <div class="asset-details">
                <h3><?php echo esc_html($asset['name']); ?></h3>
                
                <?php if (!empty($asset['dimensions'])): ?>
                    <p><strong>Dimensions:</strong> <?php echo esc_html($asset['dimensions']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($asset['size'])): ?>
                    <p><strong>File Size:</strong> <?php echo esc_html($asset['size']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($asset['mime_type'])): ?>
                    <p><strong>Type:</strong> <?php echo esc_html($asset['mime_type']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($asset['scheme'])): ?>
                    <p><strong>Asset Type:</strong> <?php echo esc_html(ucfirst($asset['scheme'])); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($asset['download_url'])): ?>
                    <a href="<?php echo esc_url($asset['download_url']); ?>" class="download-link" target="_blank">
                        Download Original
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

/**
 * Example 4: Gallery of multiple Canto assets
 */
function example_asset_gallery() {
    // Assuming you have a repeater field with Canto asset subfields
    if (have_rows('gallery_images')) {
        echo '<div class="canto-gallery">';
        
        while (have_rows('gallery_images')) {
            the_row();
            $asset = get_sub_field('image');
            
            if ($asset) {
                ?>
                <div class="gallery-item">
                    <img src="<?php echo esc_url($asset['thumbnail']); ?>" 
                         alt="<?php echo esc_attr($asset['name']); ?>"
                         data-full-size="<?php echo esc_url($asset['url']); ?>"
                         data-download="<?php echo esc_url($asset['download_url']); ?>">
                    <div class="gallery-caption">
                        <?php echo esc_html($asset['name']); ?>
                    </div>
                </div>
                <?php
            }
        }
        
        echo '</div>';
    }
}

/**
 * Example 5: Conditional display based on asset type
 */
function example_asset_type_handling() {
    $asset = get_field('media_asset');
    
    if ($asset) {
        switch ($asset['scheme']) {
            case 'image':
                echo '<img src="' . esc_url($asset['thumbnail']) . '" alt="' . esc_attr($asset['name']) . '">';
                break;
                
            case 'video':
                // For videos, use the preview URL if available, otherwise show thumbnail
                if (!empty($asset['url'])) {
                    echo '<video controls poster="' . esc_url($asset['thumbnail']) . '">';
                    echo '<source src="' . esc_url($asset['url']) . '" type="' . esc_attr($asset['mime_type']) . '">';
                    echo 'Your browser does not support the video tag.';
                    echo '</video>';
                } else {
                    echo '<img src="' . esc_url($asset['thumbnail']) . '" alt="' . esc_attr($asset['name']) . '">';
                }
                break;
                
            case 'document':
                echo '<a href="' . esc_url($asset['download_url']) . '" class="document-link" target="_blank">';
                echo '<span class="document-icon">📄</span>';
                echo esc_html($asset['name']);
                if (!empty($asset['size'])) {
                    echo ' (' . esc_html($asset['size']) . ')';
                }
                echo '</a>';
                break;
                
            default:
                echo '<a href="' . esc_url($asset['download_url']) . '" target="_blank">' . esc_html($asset['name']) . '</a>';
        }
    }
}

/**
 * Example 6: Using in Timber/Twig templates
 */
function example_timber_context() {
    // In your functions.php or theme file
    add_filter('timber/context', function($context) {
        // Add Canto asset to global context
        $context['site_logo'] = get_field('site_logo', 'option');
        return $context;
    });
}

/**
 * Example 7: Custom function to get responsive image sizes
 */
function get_canto_responsive_image($field_name, $post_id = null) {
    $asset = get_field($field_name, $post_id);
    
    if (!$asset) {
        return '';
    }
    
    // Build responsive image HTML
    $html = '<img src="' . esc_url($asset['thumbnail']) . '" ';
    $html .= 'alt="' . esc_attr($asset['name']) . '" ';
    $html .= 'loading="lazy" ';
    
    // Add data attributes for higher resolution if needed
    if (!empty($asset['url'])) {
        $html .= 'data-src="' . esc_url($asset['url']) . '" ';
    }
    if (!empty($asset['download_url'])) {
        $html .= 'data-download="' . esc_url($asset['download_url']) . '" ';
    }
    
    // Add dimensions if available
    if (!empty($asset['dimensions'])) {
        $dimensions = explode('x', $asset['dimensions']);
        if (count($dimensions) === 2) {
            $html .= 'width="' . trim($dimensions[0]) . '" ';
            $html .= 'height="' . trim($dimensions[1]) . '" ';
        }
    }
    
    $html .= '>';
    
    return $html;
}

/**
 * Example 8: AJAX endpoint for dynamic asset loading
 */
function example_ajax_asset_loader() {
    add_action('wp_ajax_load_canto_asset', 'load_canto_asset_callback');
    add_action('wp_ajax_nopriv_load_canto_asset', 'load_canto_asset_callback');
}

function load_canto_asset_callback() {
    $post_id = intval($_POST['post_id']);
    $field_name = sanitize_text_field($_POST['field_name']);
    
    $asset = get_field($field_name, $post_id);
    
    if ($asset) {
        wp_send_json_success($asset);
    } else {
        wp_send_json_error('Asset not found');
    }
}

/**
 * Example 9: Shortcode for displaying Canto assets
 */
function canto_asset_shortcode($atts) {
    $atts = shortcode_atts(array(
        'field' => '',
        'post_id' => get_the_ID(),
        'size' => 'full',
        'class' => 'canto-asset'
    ), $atts);
    
    if (empty($atts['field'])) {
        return '';
    }
    
    $asset = get_field($atts['field'], $atts['post_id']);
    
    if (!$asset) {
        return '';
    }
    
    return '<img src="' . esc_url($asset['thumbnail']) . '" ' .
           'alt="' . esc_attr($asset['name']) . '" ' .
           'class="' . esc_attr($atts['class']) . '" ' .
           'data-asset-id="' . esc_attr($asset['id']) . '">';
}
add_shortcode('canto_asset', 'canto_asset_shortcode');

/**
 * Example 10: Working with asset metadata
 */
function example_asset_metadata() {
    $asset = get_field('hero_image');
    
    if ($asset && !empty($asset['metadata'])) {
        echo '<div class="asset-metadata">';
        echo '<h4>Asset Information</h4>';
        
        // Common metadata fields that might be available
        $metadata_fields = array(
            'Copyright' => 'Copyright',
            'Keywords' => 'Keywords', 
            'Description' => 'Description',
            'Creator' => 'Creator',
            'Title' => 'Title',
            'Subject' => 'Subject'
        );
        
        foreach ($metadata_fields as $key => $label) {
            if (!empty($asset['metadata'][$key])) {
                echo '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($asset['metadata'][$key]) . '</p>';
            }
        }
        
        echo '</div>';
    }
}

/**
 * Example 11: Custom meta query for posts with Canto assets
 */
function example_meta_query() {
    $posts = new WP_Query(array(
        'post_type' => 'product',
        'meta_query' => array(
            array(
                'key' => 'product_image',
                'value' => '',
                'compare' => '!='
            )
        )
    ));
    
    if ($posts->have_posts()) {
        while ($posts->have_posts()) {
            $posts->the_post();
            $asset = get_field('product_image');
            
            if ($asset) {
                echo '<div class="product-with-image">';
                echo '<img src="' . esc_url($asset['thumbnail']) . '" alt="' . esc_attr($asset['name']) . '">';
                echo '<h3>' . get_the_title() . '</h3>';
                echo '</div>';
            }
        }
        wp_reset_postdata();
    }
}

/**
 * Example Twig templates (save in your theme's views directory)
 */

/*
// views/components/canto-image.twig
{% if asset %}
    <div class="canto-image {{ class|default('') }}">
        <img src="{{ asset.thumbnail }}" 
             alt="{{ asset.name }}" 
             {% if asset.dimensions %}
                 {% set dims = asset.dimensions|split('x') %}
                 width="{{ dims[0]|trim }}" 
                 height="{{ dims[1]|trim }}"
             {% endif %}
             {% if asset.url %}data-full-src="{{ asset.url }}"{% endif %}
             {% if asset.download_url %}data-download="{{ asset.download_url }}"{% endif %}
             loading="lazy">
        {% if show_caption and asset.name %}
            <div class="image-caption">{{ asset.name }}</div>
        {% endif %}
        {% if show_details %}
            <div class="image-details">
                {% if asset.size %}<span class="file-size">{{ asset.size }}</span>{% endif %}
                {% if asset.dimensions %}<span class="dimensions">{{ asset.dimensions }}</span>{% endif %}
            </div>
        {% endif %}
    </div>
{% endif %}

// views/components/canto-gallery.twig
{% if gallery %}
    <div class="canto-gallery">
        {% for item in gallery %}
            {% if item.image %}
                <div class="gallery-item" data-asset-id="{{ item.image.id }}">
                    <img src="{{ item.image.thumbnail }}" 
                         alt="{{ item.image.name }}"
                         data-full="{{ item.image.url }}"
                         data-download="{{ item.image.download_url }}">
                    <div class="gallery-overlay">
                        <h4>{{ item.image.name }}</h4>
                        {% if item.image.scheme %}
                            <span class="asset-type">{{ item.image.scheme|title }}</span>
                        {% endif %}
                    </div>
                </div>
            {% endif %}
        {% endfor %}
    </div>
{% endif %}

// views/components/canto-video.twig
{% if asset and asset.scheme == 'video' %}
    <div class="canto-video">
        {% if asset.url %}
            <video controls poster="{{ asset.thumbnail }}">
                <source src="{{ asset.url }}" type="{{ asset.mime_type|default('video/mp4') }}">
                Your browser does not support the video tag.
            </video>
        {% else %}
            <div class="video-placeholder">
                <img src="{{ asset.thumbnail }}" alt="{{ asset.name }}">
                <div class="video-info">
                    <h4>{{ asset.name }}</h4>
                    {% if asset.download_url %}
                        <a href="{{ asset.download_url }}" class="download-btn" target="_blank">Download Video</a>
                    {% endif %}
                </div>
            </div>
        {% endif %}
    </div>
{% endif %}

// views/components/canto-document.twig
{% if asset and asset.scheme == 'document' %}
    <div class="canto-document">
        <div class="document-icon">
            <img src="{{ asset.thumbnail }}" alt="Document icon">
        </div>
        <div class="document-info">
            <h4>{{ asset.name }}</h4>
            {% if asset.size %}<p class="file-size">{{ asset.size }}</p>{% endif %}
            {% if asset.mime_type %}<p class="file-type">{{ asset.mime_type }}</p>{% endif %}
            {% if asset.download_url %}
                <a href="{{ asset.download_url }}" class="download-btn" target="_blank">Download Document</a>
            {% endif %}
        </div>
    </div>
{% endif %}
*/
