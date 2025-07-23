<?php
/**
 * ACF Canto Field Class
 *
 * A custom ACF field that integrates with the Canto plugin to allow
 * users to select assets directly from their Canto library.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if class already exists
if (!class_exists('ACF_Field_Canto')) :

class ACF_Field_Canto extends acf_field
{
    /**
     * Controls field type visibility in REST requests.
     *
     * @var bool
     */
    public $show_in_rest = true;

    /**
     * Environment values relating to the plugin.
     *
     * @var array $env Plugin context such as 'url' and 'version'.
     */
    private $env;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Field type name (single word, no spaces, underscores allowed)
        $this->name = 'canto';
        
        // Field type label (multiple words, can include spaces, visible when selecting a field type)
        $this->label = __('Canto Asset', 'acf-canto-field');
        
        // Field type category (basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME)
        $this->category = 'content';
        
        // Field type description
        $this->description = __('Select assets from your Canto library.', 'acf-canto-field');
        
        // Field defaults
        $this->defaults = array(
            'return_format' => 'object',
        );
        
        // JavaScript strings
        $this->l10n = array(
            'error' => __('Error! Please select a Canto asset.', 'acf-canto-field'),
            'select' => __('Select Canto Asset', 'acf-canto-field'),
            'edit' => __('Edit Asset', 'acf-canto-field'),
            'remove' => __('Remove Asset', 'acf-canto-field'),
            'loading' => __('Loading...', 'acf-canto-field'),
            'no_assets' => __('No assets found.', 'acf-canto-field'),
            'search_placeholder' => __('Search assets...', 'acf-canto-field'),
            'select_asset_button' => __('Select Asset', 'acf-canto-field'),
            'cancel' => __('Cancel', 'acf-canto-field'),
        );
        
        // Environment settings
        $this->env = array(
            'url'     => ACF_CANTO_FIELD_PLUGIN_URL,
            'version' => ACF_CANTO_FIELD_VERSION,
        );
        
        // Call parent constructor
        parent::__construct();
    }
    
    /**
     * Create extra settings for the field
     *
     * @param array $field The field being edited
     */
    public function render_field_settings($field)
    {
        acf_render_field_setting($field, array(
            'label'        => __('Return Format', 'acf-canto-field'),
            'instructions' => __('Specify the returned value on front end', 'acf-canto-field'),
            'type'         => 'select',
            'name'         => 'return_format',
            'choices'      => array(
                'object'    => __('Canto Asset Object', 'acf-canto-field'),
                'id'        => __('Canto Asset ID', 'acf-canto-field'),
                'url'       => __('Asset URL', 'acf-canto-field'),
            )
        ));
    }
    
    /**
     * Create the HTML interface for the field
     *
     * @param array $field The field being rendered
     */
    public function render_field($field)
    {
        // Check if Canto is available
        if (!function_exists('Canto') || !get_option('fbc_app_token')) {
            echo '<div class="acf-canto-error">';
            echo '<p>' . __('Canto plugin is not configured or not available.', 'acf-canto-field') . '</p>';
            if (!function_exists('Canto')) {
                echo '<p><em>Canto plugin not found.</em></p>';
            }
            if (!get_option('fbc_app_token')) {
                echo '<p><em>Canto API token not configured.</em></p>';
            }
            echo '</div>';
            return;
        }
        
        $value = $field['value'];
        $canto_data = null;
        
        // Get Canto data if we have a value
        $canto_data = null;
        
        if ($value) {
            // Value is now just the filename
            $filename = (string) $value;
            $canto_data = $this->find_asset_by_filename($filename);
        }
        
        ?>
        <div class="acf-canto-field" data-field-name="<?php echo esc_attr($field['name']); ?>">
            <input type="hidden" name="<?php echo esc_attr($field['name']); ?>" value="<?php echo esc_attr($value); ?>" />
            
            <div class="acf-canto-container">
                <?php if ($canto_data): ?>
                    <div class="acf-canto-preview">
                        <div class="acf-canto-preview-image">
                            <?php if (isset($canto_data['thumbnail'])): ?>
                                <img src="<?php echo esc_url($canto_data['thumbnail']); ?>" alt="<?php echo esc_attr($canto_data['name']); ?>" />
                            <?php endif; ?>
                        </div>
                        <div class="acf-canto-preview-details">
                            <h4><?php echo esc_html($canto_data['name']); ?></h4>
                            <?php if (isset($canto_data['dimensions']) && $canto_data['dimensions']): ?>
                                <p><?php echo esc_html($canto_data['dimensions']); ?></p>
                            <?php endif; ?>
                            <?php if (isset($canto_data['size']) && $canto_data['size']): ?>
                                <p><?php echo esc_html($canto_data['size']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="acf-canto-actions">
                            <button type="button" class="button acf-canto-edit"><?php echo esc_html($this->l10n['edit']); ?></button>
                            <button type="button" class="button acf-canto-remove"><?php echo esc_html($this->l10n['remove']); ?></button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="acf-canto-placeholder">
                        <button type="button" class="button button-primary acf-canto-select">
                            <?php echo esc_html($this->l10n['select']); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Modal for asset selection -->
            <div class="acf-canto-modal" style="display: none;">
                <div class="acf-canto-modal-content">
                    <div class="acf-canto-modal-header">
                        <h3><?php echo esc_html($this->l10n['select']); ?></h3>
                        <button type="button" class="acf-canto-modal-close">&times;</button>
                    </div>
                    <div class="acf-canto-modal-body">
                        <div class="acf-canto-navigation">
                            <div class="acf-canto-nav-tabs">
                                <button type="button" class="acf-canto-nav-tab active" data-view="search"><?php _e('Search', 'acf-canto-field'); ?></button>
                                <button type="button" class="acf-canto-nav-tab" data-view="browse"><?php _e('Browse', 'acf-canto-field'); ?></button>
                            </div>
                        </div>
                        
                        <div class="acf-canto-content">
                            <!-- Search View -->
                            <div class="acf-canto-view acf-canto-search-view active">
                                <div class="acf-canto-search">
                                    <input type="text" class="acf-canto-search-input" placeholder="<?php echo esc_attr($this->l10n['search_placeholder']); ?>" />
                                    <button type="button" class="button acf-canto-search-btn"><?php _e('Search', 'acf-canto-field'); ?></button>
                                </div>
                                <div class="acf-canto-results">
                                    <div class="acf-canto-loading" style="display: none;">
                                        <?php echo esc_html($this->l10n['loading']); ?>
                                    </div>
                                    <div class="acf-canto-assets-grid"></div>
                                </div>
                            </div>
                            
                            <!-- Browse View -->
                            <div class="acf-canto-view acf-canto-browse-view">
                                <div class="acf-canto-browse-layout">
                                    <div class="acf-canto-tree-sidebar">
                                        <div class="acf-canto-tree-header">
                                            <h4><?php _e('Albums & Folders', 'acf-canto-field'); ?></h4>
                                            <button type="button" class="button-link acf-canto-tree-refresh" title="<?php _e('Refresh', 'acf-canto-field'); ?>">↻</button>
                                        </div>
                                        <div class="acf-canto-tree-loading" style="display: none;">
                                            <?php echo esc_html($this->l10n['loading']); ?>
                                        </div>
                                        <div class="acf-canto-tree-container">
                                            <ul class="acf-canto-tree-list"></ul>
                                        </div>
                                    </div>
                                    <div class="acf-canto-browse-content">
                                        <div class="acf-canto-browse-header">
                                            <h4 class="acf-canto-current-path"><?php _e('All Assets', 'acf-canto-field'); ?></h4>
                                            <button type="button" class="button-link acf-canto-browse-refresh" title="<?php _e('Refresh', 'acf-canto-field'); ?>">↻</button>
                                        </div>
                                        <div class="acf-canto-browse-loading" style="display: none;">
                                            <?php echo esc_html($this->l10n['loading']); ?>
                                        </div>
                                        <div class="acf-canto-browse-assets"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="acf-canto-modal-footer">
                        <button type="button" class="button button-primary acf-canto-confirm-selection" disabled><?php echo esc_html($this->l10n['select_asset_button']); ?></button>
                        <button type="button" class="button acf-canto-cancel"><?php echo esc_html($this->l10n['cancel']); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue scripts and styles for the field
     */
    public function input_admin_enqueue_scripts()
    {
        $url = trailingslashit($this->env['url']);
        $version = $this->env['version'];
        
        // Register & include JS
        wp_register_script(
            'acf-input-canto',
            "{$url}assets/js/input.js",
            array('acf-input'),
            $version
        );
        wp_enqueue_script('acf-input-canto');
        
        // Register & include CSS
        wp_register_style(
            'acf-input-canto',
            "{$url}assets/css/input.css",
            array('acf-input'),
            $version
        );
        wp_enqueue_style('acf-input-canto');
        
        // Localize script with Canto data
        wp_localize_script('acf-input-canto', 'acf_canto', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('acf_canto_nonce'),
            'plugin_url' => ACF_CANTO_FIELD_PLUGIN_URL,
            'l10n' => $this->l10n,
            'canto_domain' => get_option('fbc_flight_domain'),
            'canto_available' => function_exists('Canto') && get_option('fbc_app_token')
        ));
    }
    
    /**
     * Format the field value for frontend display
     *
     * @param mixed $value The value found in the database
     * @param int $post_id The post ID from which the value was loaded
     * @param array $field The field array holding all the field options
     * @return mixed
     */
    public function format_value($value, $post_id, $field)
    {
        // Return false if no value
        if (empty($value)) {
            return false;
        }
        
        // Value is now just the filename string
        $filename = (string) $value;
        
        // Find the asset by filename
        $asset_data = $this->find_asset_by_filename($filename);
        
        if (!$asset_data) {
            return false;
        }
        
        return $this->prepare_return_value($asset_data, $field);
    }
    
    /**
     * Prepare return value based on field return format
     *
     * @param array|false $asset_data The asset data
     * @param array $field The field configuration
     * @return mixed
     */
    private function prepare_return_value($asset_data, $field)
    {
        if (!$asset_data) {
            return false;
        }
        
        // Check return format
        if ($field['return_format'] == 'id') {
            return $asset_data['id'];
        } elseif ($field['return_format'] == 'url') {
            return isset($asset_data['url']) ? $asset_data['url'] : false;
        } else {
            // Return full object
            return $asset_data;
        }
    }
    
    /**
     * Parse stored value - Legacy support for migration from old formats
     * This can be removed after migration is complete
     *
     * @param mixed $value The stored value
     * @return string The filename
     */
    private function parse_legacy_value($value)
    {
        // Handle old JSON format for migration purposes
        if (is_string($value) && (strpos($value, '{') === 0)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && isset($decoded['filename'])) {
                return $decoded['filename'];
            }
        }
        
        // For now, assume anything else is a filename
        return (string) $value;
    }
    
    /**
     * Search for asset by filename
     *
     * @param string $filename The filename to search for
     * @return array|false Asset data if found, false otherwise
     */
    public function find_asset_by_filename($filename)
    {
        if (empty($filename)) {
            return false;
        }
        
        // Try cache first
        $cache_key = 'canto_filename_' . md5($filename);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ACF Canto Field: Using cached data for filename: ' . $filename);
            }
            return $cached_data;
        }
        
        // Get Canto configuration
        $domain = get_option('fbc_flight_domain');
        $app_api = get_option('fbc_app_api') ?: 'canto.com';
        $token = get_option('fbc_app_token');
        
        if (!$domain || !$token) {
            return false;
        }
        
        // Search for the filename using Canto search API
        $search_url = 'https://' . $domain . '.' . $app_api . '/api/v1/search?keyword=' . urlencode($filename) . '&limit=50';
        
        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'WordPress Plugin',
            'Content-Type' => 'application/json;charset=utf-8'
        );
        
        $response = wp_remote_get($search_url, array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ACF Canto Field: Filename search failed: ' . $response->get_error_message());
            }
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code !== 200 || empty($body)) {
            return false;
        }
        
        $data = json_decode($body, true);
        if (!$data || !isset($data['results'])) {
            return false;
        }
        
        // Look for exact filename match
        foreach ($data['results'] as $item) {
            $asset_data = $this->format_asset_data_from_search($item);
            if ($asset_data && $asset_data['filename'] === $filename) {
                // Cache for 1 hour
                set_transient($cache_key, $asset_data, HOUR_IN_SECONDS);
                return $asset_data;
            }
        }
        
        // If no filename match found, try matching against the name field
        // This handles cases where assets don't have explicit filename metadata
        foreach ($data['results'] as $item) {
            $asset_data = $this->format_asset_data_from_search($item);
            if ($asset_data && $asset_data['name'] === $filename) {
                // Cache for 1 hour
                set_transient($cache_key, $asset_data, HOUR_IN_SECONDS);
                return $asset_data;
            }
        }
        
        return false;
    }
    
    /**
     * Format asset data from search results
     *
     * @param array $data Raw asset data from search API
     * @return array|false Formatted asset data
     */
    private function format_asset_data_from_search($data)
    {
        if (!is_array($data) || !isset($data['id'])) {
            return false;
        }
        
        // Determine asset type
        $scheme = 'image'; // default
        if (isset($data['scheme'])) {
            $scheme = $data['scheme'];
        } elseif (isset($data['url']['preview'])) {
            if (strpos($data['url']['preview'], '/video/') !== false) {
                $scheme = 'video';
            } elseif (strpos($data['url']['preview'], '/document/') !== false) {
                $scheme = 'document';
            }
        }
        
        $formatted_data = array(
            'id' => $data['id'],
            'scheme' => $scheme,
            'name' => isset($data['name']) ? $data['name'] : 'Untitled',
            'filename' => '',
            'url' => '',
            'thumbnail' => '',
            'download_url' => '',
            'dimensions' => '',
            'mime_type' => '',
            'size' => '',
            'uploaded' => isset($data['lastUploaded']) ? $data['lastUploaded'] : '',
            'metadata' => isset($data['default']) ? $data['default'] : array(),
        );
        
        // Extract filename from metadata
        if (isset($data['default']) && is_array($data['default'])) {
            $filename_fields = array('Filename', 'File Name', 'Original Filename', 'filename', 'file_name');
            foreach ($filename_fields as $field) {
                if (isset($data['default'][$field]) && !empty($data['default'][$field])) {
                    $formatted_data['filename'] = $data['default'][$field];
                    break;
                }
            }
        }
        
        // If no filename found, construct from name
        if (empty($formatted_data['filename'])) {
            if (preg_match('/\.[a-zA-Z0-9]{2,5}$/', $formatted_data['name'])) {
                $formatted_data['filename'] = $formatted_data['name'];
            } else {
                $extension_map = array('image' => 'jpg', 'video' => 'mp4', 'document' => 'pdf');
                $extension = isset($extension_map[$scheme]) ? $extension_map[$scheme] : 'bin';
                $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $formatted_data['name']);
                $formatted_data['filename'] = $safe_name . '.' . $extension;
            }
        }
        
        // Get URLs and other data (similar to existing logic)
        $domain = get_option('fbc_flight_domain');
        $app_api = get_option('fbc_app_api') ?: 'canto.com';
        
        if (isset($data['url'])) {
            if (isset($data['url']['preview'])) {
                $formatted_data['url'] = $data['url']['preview'];
            }
            if (isset($data['url']['download'])) {
                $formatted_data['download_url'] = $data['url']['download'];
            }
            if (isset($data['url']['directUrlPreview'])) {
                $formatted_data['thumbnail'] = $data['url']['directUrlPreview'];
            }
        }
        
        // Fallback thumbnail
        if (empty($formatted_data['thumbnail']) && $domain) {
            $formatted_data['thumbnail'] = home_url('canto-thumbnail/' . $scheme . '/' . $data['id']);
        }
        
        return $formatted_data;
    }
    
    /**
     * Validate the field value
     *
     * @param bool $valid Current validation status
     * @param mixed $value The $_POST value
     * @param array $field The field array holding all the field options
     * @param string $input The corresponding input name for $_POST value
     * @return bool|string
     */
    public function validate_value($valid, $value, $field, $input)
    {
        // Basic validation
        if ($field['required'] && empty($value)) {
            $valid = __('This field is required.', 'acf-canto-field');
        }
        
        return $valid;
    }
    
    /**
     * Helper function to get Canto asset data
     *
     * @param string $asset_id The Canto asset ID
     * @return array|false
     */
    private function get_canto_asset_data($asset_id)
    {
        if (empty($asset_id)) {
            return false;
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto Field: Loading asset data for ID: ' . $asset_id);
        }
        
        // Try to get cached data first
        $cache_key = 'canto_asset_' . $asset_id;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ACF Canto Field: Using cached data for: ' . $asset_id);
            }
            return $cached_data;
        }
        
        // Get Canto configuration
        $domain = get_option('fbc_flight_domain');
        $app_api = get_option('fbc_app_api') ?: 'canto.com';
        $token = get_option('fbc_app_token');
        
        if (!$domain || !$token) {
            return false;
        }
        
        // Try different API endpoints that might exist
        $base_url = 'https://' . $domain . '.' . $app_api . '/api/v1/';
        $endpoints = array('image', 'video', 'document');
        $data = false;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'WordPress Plugin',
            'Content-Type' => 'application/json;charset=utf-8'
        );
        
        foreach ($endpoints as $endpoint) {
            $asset_url = $base_url . $endpoint . '/' . $asset_id;
            $response = wp_remote_get($asset_url, array(
                'headers' => $headers,
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $http_code = wp_remote_retrieve_response_code($response);
                
                if ($http_code === 200 && !empty($body)) {
                    $json_data = json_decode($body, true);
                    if ($json_data && !isset($json_data['error'])) {
                        $data = $json_data;
                        break;
                    }
                }
            }
        }
        
        if (!$data) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ACF Canto Field: No asset data found for ID: ' . $asset_id);
            }
            return false;
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto Field: Asset data loaded for: ' . $asset_id . ', thumbnail will be: ' . (isset($data['url']['directUrlPreview']) ? $data['url']['directUrlPreview'] : 'fallback'));
        }
        
        // Determine asset type
        $scheme = 'image'; // default
        if (isset($data['scheme'])) {
            $scheme = $data['scheme'];
        } elseif (isset($data['url']['preview'])) {
            // Try to determine from URL structure
            if (strpos($data['url']['preview'], '/video/') !== false) {
                $scheme = 'video';
            } elseif (strpos($data['url']['preview'], '/document/') !== false) {
                $scheme = 'document';
            }
        }
        
        // Format the data
        $formatted_data = array(
            'id' => $asset_id,
            'scheme' => $scheme,
            'name' => isset($data['name']) ? $data['name'] : 'Untitled',
            'filename' => '', // Will be extracted from metadata or name
            'url' => '',
            'thumbnail' => '',
            'download_url' => '',
            'dimensions' => '',
            'mime_type' => '',
            'size' => '',
            'uploaded' => isset($data['lastUploaded']) ? $data['lastUploaded'] : '',
            'metadata' => isset($data['default']) ? $data['default'] : array(),
        );
        
        // URLs from API response (if available)
        if (isset($data['url'])) {
            if (isset($data['url']['preview'])) {
                $formatted_data['url'] = $data['url']['preview'];
            }
            if (isset($data['url']['download'])) {
                $formatted_data['download_url'] = $data['url']['download'];
            }
            // Check for directUrlPreview which doesn't need authentication
            if (isset($data['url']['directUrlPreview'])) {
                $formatted_data['thumbnail'] = $data['url']['directUrlPreview'];
            }
        }
        
        // Fallback: Build proper thumbnail URL using our proxy endpoint if no direct URL
        if (empty($formatted_data['thumbnail']) && $domain) {
            $formatted_data['thumbnail'] = home_url('canto-thumbnail/' . $scheme . '/' . $asset_id);
        }
        
        // Final fallback: Use default thumbnail based on asset type
        if (empty($formatted_data['thumbnail'])) {
            $plugin_url = plugin_dir_url(dirname(__FILE__));
            switch ($scheme) {
                case 'video':
                    $formatted_data['thumbnail'] = $plugin_url . 'assets/images/default-video.svg';
                    break;
                case 'document':
                    $formatted_data['thumbnail'] = $plugin_url . 'assets/images/default-document.svg';
                    break;
                case 'image':
                default:
                    $formatted_data['thumbnail'] = $plugin_url . 'assets/images/default-image.svg';
                    break;
            }
        }
        
        // If no download URL from API, construct one using binary API
        if (empty($formatted_data['download_url']) && $domain) {
            if ($scheme === 'image') {
                $formatted_data['download_url'] = 'https://' . $domain . '.' . $app_api . '/api_binary/v1/advance/image/' . $asset_id . '/download/directuri?type=jpg&dpi=72';
            } elseif ($scheme === 'video') {
                $formatted_data['download_url'] = 'https://' . $domain . '.' . $app_api . '/api_binary/v1/video/' . $asset_id . '/download';
            } elseif ($scheme === 'document') {
                $formatted_data['download_url'] = 'https://' . $domain . '.' . $app_api . '/api_binary/v1/document/' . $asset_id . '/download';
            }
        }
        
        // Extract additional metadata if available
        if (isset($data['default']) && is_array($data['default'])) {
            $formatted_data['metadata'] = $data['default'];
            
            // Extract common metadata
            if (isset($data['default']['Dimensions'])) {
                $formatted_data['dimensions'] = $data['default']['Dimensions'];
            }
            if (isset($data['default']['Content Type'])) {
                $formatted_data['mime_type'] = $data['default']['Content Type'];
            }
            
            // Extract filename from various possible metadata fields
            $filename_fields = array('Filename', 'File Name', 'Original Filename', 'filename', 'file_name');
            foreach ($filename_fields as $field) {
                if (isset($data['default'][$field]) && !empty($data['default'][$field])) {
                    $formatted_data['filename'] = $data['default'][$field];
                    break;
                }
            }
        }
        
        // If no filename found in metadata, try to extract from name or construct from ID
        if (empty($formatted_data['filename'])) {
            // If name contains file extension, use it as filename
            if (preg_match('/\.[a-zA-Z0-9]{2,5}$/', $formatted_data['name'])) {
                $formatted_data['filename'] = $formatted_data['name'];
            } else {
                // Construct filename using asset name and scheme-based extension
                $extension_map = array(
                    'image' => 'jpg',
                    'video' => 'mp4', 
                    'document' => 'pdf'
                );
                $extension = isset($extension_map[$scheme]) ? $extension_map[$scheme] : 'bin';
                $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $formatted_data['name']);
                $formatted_data['filename'] = $safe_name . '.' . $extension;
            }
        }
        
        // File size
        if (isset($data['size'])) {
            $formatted_data['size'] = size_format($data['size']);
        }
        
        // Cache for 1 hour
        set_transient($cache_key, $formatted_data, HOUR_IN_SECONDS);
        
        return $formatted_data;
    }
}

// End class_exists check
endif;
