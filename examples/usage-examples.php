<?php
/**
 * ACF Canto Field - Usage Examples
 * 
 * This file contains practical examples of how to use the ACF Canto Field
 * in your WordPress themes and plugins.
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
        echo '<img src="' . esc_url($canto_asset['url']) . '" alt="' . esc_attr($canto_asset['name']) . '">';
        echo '<div class="image-caption">' . esc_html($canto_asset['name']) . '</div>';
        echo '</div>';
    }
}

/**
 * Example 2: Using different return formats
 */
function example_return_formats() {
    // Field configured to return 'id'
    $asset_id = get_field('background_image'); // Returns just the ID
    
    // Field configured to return 'url' 
    $asset_url = get_field('logo_image'); // Returns just the URL
    
    // Field configured to return 'object' (default)
    $asset_object = get_field('featured_image'); // Returns full object
    
    if ($asset_url) {
        echo '<img src="' . esc_url($asset_url) . '" class="logo">';
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
                <img src="<?php echo esc_url($asset['url']); ?>" alt="<?php echo esc_attr($asset['name']); ?>">
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
                         data-full-size="<?php echo esc_url($asset['url']); ?>">
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
                echo '<img src="' . esc_url($asset['url']) . '" alt="' . esc_attr($asset['name']) . '">';
                break;
                
            case 'video':
                echo '<video controls>';
                echo '<source src="' . esc_url($asset['url']) . '" type="' . esc_attr($asset['mime_type']) . '">';
                echo 'Your browser does not support the video tag.';
                echo '</video>';
                break;
                
            case 'document':
                echo '<a href="' . esc_url($asset['download_url']) . '" class="document-link">';
                echo '<span class="document-icon">📄</span>';
                echo esc_html($asset['name']);
                echo '</a>';
                break;
                
            default:
                echo '<a href="' . esc_url($asset['url']) . '">' . esc_html($asset['name']) . '</a>';
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
    $html = '<img src="' . esc_url($asset['url']) . '" ';
    $html .= 'alt="' . esc_attr($asset['name']) . '" ';
    $html .= 'loading="lazy" ';
    
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
    
    return '<img src="' . esc_url($asset['url']) . '" ' .
           'alt="' . esc_attr($asset['name']) . '" ' .
           'class="' . esc_attr($atts['class']) . '">';
}
add_shortcode('canto_asset', 'canto_asset_shortcode');

/**
 * Example 10: Custom meta query for posts with Canto assets
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
        <img src="{{ asset.url }}" 
             alt="{{ asset.name }}" 
             {% if asset.dimensions %}
                 {% set dims = asset.dimensions|split('x') %}
                 width="{{ dims[0]|trim }}" 
                 height="{{ dims[1]|trim }}"
             {% endif %}
             loading="lazy">
        {% if show_caption and asset.name %}
            <div class="image-caption">{{ asset.name }}</div>
        {% endif %}
    </div>
{% endif %}

// views/components/canto-gallery.twig
{% if gallery %}
    <div class="canto-gallery">
        {% for item in gallery %}
            {% if item.image %}
                <div class="gallery-item">
                    <img src="{{ item.image.thumbnail }}" 
                         alt="{{ item.image.name }}"
                         data-full="{{ item.image.url }}">
                </div>
            {% endif %}
        {% endfor %}
    </div>
{% endif %}
*/
