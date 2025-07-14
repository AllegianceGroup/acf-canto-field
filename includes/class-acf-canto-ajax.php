<?php
/**
 * ACF Canto AJAX Handler
 *
 * Handles AJAX requests for the ACF Canto field
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ACF_Canto_AJAX_Handler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: AJAX Handler initialized');
        }
        
        // AJAX actions for logged in users
        add_action('wp_ajax_acf_canto_search', array($this, 'search_assets'));
        add_action('wp_ajax_acf_canto_get_asset', array($this, 'get_asset'));
        add_action('wp_ajax_acf_canto_get_tree', array($this, 'get_tree'));
        add_action('wp_ajax_acf_canto_get_album', array($this, 'get_album_assets'));
    }
    
    /**
     * Search Canto assets
     */
    public function search_assets()
    {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: search_assets called');
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'acf_canto_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        // Check if Canto is available
        if (!function_exists('Canto')) {
            wp_send_json_error('Canto plugin not found');
            return;
        }
        
        if (!get_option('fbc_app_token')) {
            wp_send_json_error('Canto API token not configured');
            return;
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $selected_id = isset($_POST['selected_id']) ? sanitize_text_field($_POST['selected_id']) : '';
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: Search query: ' . $query);
            error_log('ACF Canto: Selected ID: ' . $selected_id);
        }
        
        // Get Canto configuration
        $domain = get_option('fbc_flight_domain');
        $app_api = get_option('fbc_app_api') ?: 'canto.com';
        
        if (!$domain) {
            wp_send_json_error('Canto domain not configured');
            return;
        }
        
        // Build search URL using same structure as Canto plugin
        $start = 0;
        $limit = 50;
        
        // File types as used in the Canto plugin
        $fileType4Images = "GIF|JPG|PNG|SVG|WEBP|";
        $fileType4Documents = "DOC|KEY|ODT|PDF|PPT|XLS|";
        $fileType4Audio = "MPEG|M4A|OGG|WAV|";
        $fileType4Video = "AVI|MP4|MOV|OGG|VTT|WMV|3GP";
        $fileType = $fileType4Images . $fileType4Documents . $fileType4Audio . $fileType4Video;
        
        if (!empty($query)) {
            $search_url = 'https://' . $domain . '.' . $app_api . '/api/v1/search?keyword=' . urlencode($query) . '&fileType=' . urlencode($fileType) . '&operator=and&limit=' . $limit . '&start=' . $start;
        } else {
            $search_url = 'https://' . $domain . '.' . $app_api . '/api/v1/search?keyword=&fileType=' . urlencode($fileType) . '&limit=' . $limit . '&start=' . $start;
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: API URL: ' . $search_url);
        }
        
        // Make API call using wp_remote_get like the Canto plugin
        $headers = array(
            'Authorization' => 'Bearer ' . get_option('fbc_app_token'),
            'User-Agent' => 'WordPress Plugin',
            'Content-Type' => 'application/json;charset=utf-8'
        );
        
        $response = wp_remote_get($search_url, array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        // Debug logging for API response
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: API Response Code: ' . wp_remote_retrieve_response_code($response));
            if (is_wp_error($response)) {
                error_log('ACF Canto: API Error: ' . $response->get_error_message());
            }
        }
        
        if (is_wp_error($response)) {
            wp_send_json_error('API request failed: ' . $response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        
        // Debug: Log raw API response
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: Raw API Response: ' . substr($body, 0, 500));
        }
        
        if (empty($body)) {
            wp_send_json_error('No response from Canto API');
            return;
        }
        
        if ($http_code !== 200) {
            wp_send_json_error('API request failed with HTTP code: ' . $http_code . '. Response: ' . substr($body, 0, 200));
            return;
        }
        
        $data = json_decode($body, true);
        
        // Debug: Log data structure
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: Decoded data type: ' . gettype($data));
            if (is_array($data)) {
                error_log('ACF Canto: Data keys: ' . implode(', ', array_keys($data)));
                if (isset($data['results'])) {
                    error_log('ACF Canto: Results count: ' . count($data['results']));
                }
            } else {
                error_log('ACF Canto: Raw data: ' . print_r($data, true));
            }
        }
        
        if (!$data) {
            wp_send_json_error('Invalid JSON response from Canto API');
            return;
        }
        
        if (isset($data['error'])) {
            wp_send_json_error('Error from Canto API: ' . $data['error']);
            return;
        }
        
        // Debug: Log first result structure
        if (defined('WP_DEBUG') && WP_DEBUG && isset($data['results']) && count($data['results']) > 0) {
            error_log('ACF Canto: First result structure: ' . print_r($data['results'][0], true));
        }
        
        $assets = array();
        
        // Process results
        if (isset($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $item) {
                $asset = $this->format_asset_data($item);
                if ($asset) {
                    $assets[] = $asset;
                }
            }
        }
        
        // If we have a selected_id and it's not in results, try to fetch it separately
        if (!empty($selected_id) && !$this->asset_in_results($selected_id, $assets)) {
            $selected_asset = $this->get_single_asset($selected_id);
            if ($selected_asset) {
                array_unshift($assets, $selected_asset);
            }
        }
          // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: Found ' . count($assets) . ' assets');
            if (count($assets) > 0) {
                error_log('ACF Canto: First asset thumbnail URL: ' . $assets[0]['thumbnail']);
            }
        }

        wp_send_json_success($assets);
    }
    
    /**
     * Get single Canto asset
     */
    public function get_asset()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'acf_canto_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $asset_id = sanitize_text_field($_POST['asset_id']);
        
        if (empty($asset_id)) {
            wp_send_json_error('Asset ID required');
            return;
        }
        
        $asset = $this->get_single_asset($asset_id);
        
        if ($asset) {
            wp_send_json_success($asset);
        } else {
            wp_send_json_error('Asset not found');
        }
    }
    
    /**
     * Get single asset data from Canto
     *
     * @param string $asset_id The asset ID
     * @return array|false
     */
    private function get_single_asset($asset_id)
    {
        if (!get_option('fbc_app_token') || !get_option('fbc_flight_domain')) {
            return false;
        }
        
        // Try cache first
        $cache_key = 'canto_asset_' . $asset_id;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $domain = get_option('fbc_flight_domain');
        $app_api = get_option('fbc_app_api') ?: 'canto.com';
        
        // Try different API endpoints that might exist
        $base_url = 'https://' . $domain . '.' . $app_api . '/api/v1/';
        $endpoints = array('image', 'video', 'document');
        
        $headers = array(
            'Authorization' => 'Bearer ' . get_option('fbc_app_token'),
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
                    $data = json_decode($body, true);
                    if ($data && !isset($data['error'])) {
                        $asset = $this->format_asset_data($data);
                        if ($asset) {
                            // Cache for 1 hour
                            set_transient($cache_key, $asset, HOUR_IN_SECONDS);
                            return $asset;
                        }
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Format asset data for consistent output
     *
     * @param array $data Raw asset data from Canto API
     * @return array|false
     */
    private function format_asset_data($data)
    {
        if (!is_array($data) || !isset($data['id'])) {
            return false;
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
        
        // Build asset object
        $asset = array(
            'id' => $data['id'],
            'scheme' => $scheme,
            'name' => isset($data['name']) ? $data['name'] : 'Untitled',
            'url' => '',
            'thumbnail' => '',
            'download_url' => '',
            'dimensions' => '',
            'mime_type' => '',
            'size' => '',
            'uploaded' => '',
            'metadata' => array(),
        );
        
        // Get Canto configuration for proper URL construction
        $domain = get_option('fbc_flight_domain');
        $app_api = get_option('fbc_app_api') ?: 'canto.com';
        
        // URLs from API response (if available)
        if (isset($data['url'])) {
            if (isset($data['url']['preview'])) {
                $asset['url'] = $data['url']['preview'];
            }
            if (isset($data['url']['download'])) {
                $asset['download_url'] = $data['url']['download'];
            }
            // Check for directUrlPreview which doesn't need authentication
            if (isset($data['url']['directUrlPreview'])) {
                $asset['thumbnail'] = $data['url']['directUrlPreview'];
            }
        }
        
        // Fallback: Build proper thumbnail URL using our proxy endpoint if no direct URL
        if (empty($asset['thumbnail']) && $domain) {
            $asset['thumbnail'] = home_url('canto-thumbnail/' . $scheme . '/' . $data['id']);
        }
        
        // Final fallback: Use default thumbnail based on asset type
        if (empty($asset['thumbnail'])) {
            $plugin_url = plugin_dir_url(dirname(__FILE__));
            switch ($scheme) {
                case 'video':
                    $asset['thumbnail'] = $plugin_url . 'assets/images/default-video.svg';
                    break;
                case 'document':
                    $asset['thumbnail'] = $plugin_url . 'assets/images/default-document.svg';
                    break;
                case 'image':
                default:
                    $asset['thumbnail'] = $plugin_url . 'assets/images/default-image.svg';
                    break;
            }
        }
        
        // If no download URL from API, construct one using binary API
        if (empty($asset['download_url']) && $domain) {
            if ($scheme === 'image') {
                $asset['download_url'] = 'https://' . $domain . '.' . $app_api . '/api_binary/v1/advance/image/' . $data['id'] . '/download/directuri?type=jpg&dpi=72';
            } elseif ($scheme === 'video') {
                $asset['download_url'] = 'https://' . $domain . '.' . $app_api . '/api_binary/v1/video/' . $data['id'] . '/download';
            } elseif ($scheme === 'document') {
                $asset['download_url'] = 'https://' . $domain . '.' . $app_api . '/api_binary/v1/document/' . $data['id'] . '/download';
            }
        }
        
        // Metadata
        if (isset($data['default']) && is_array($data['default'])) {
            $asset['metadata'] = $data['default'];
            
            // Extract common metadata
            if (isset($data['default']['Dimensions'])) {
                $asset['dimensions'] = $data['default']['Dimensions'];
            }
            if (isset($data['default']['Content Type'])) {
                $asset['mime_type'] = $data['default']['Content Type'];
            }
        }
        
        // File size
        if (isset($data['size'])) {
            $asset['size'] = size_format($data['size']);
        }
        
        // Upload date
        if (isset($data['lastUploaded'])) {
            $asset['uploaded'] = $data['lastUploaded'];
        }
        
        return $asset;
    }
    
    /**
     * Check if asset ID is already in results array
     *
     * @param string $asset_id The asset ID to search for
     * @param array $assets Array of assets to search in
     * @return bool
     */
    private function asset_in_results($asset_id, $assets)
    {
        foreach ($assets as $asset) {
            if (isset($asset['id']) && $asset['id'] === $asset_id) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get Canto tree/folder structure
     */
    public function get_tree()
    {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: get_tree called');
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'acf_canto_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        // Check if Canto is available
        if (!get_option('fbc_app_token')) {
            wp_send_json_error('Canto API token not configured');
            return;
        }
        
        $album_id = isset($_POST['album_id']) ? sanitize_text_field($_POST['album_id']) : '';
        
        // Get Canto configuration
        $domain = get_option('fbc_flight_domain');
        $app_api = get_option('fbc_app_api') ?: 'canto.com';
        
        if (!$domain) {
            wp_send_json_error('Canto domain not configured');
            return;
        }
        
        // Build tree URL
        if (!empty($album_id)) {
            $tree_url = 'https://' . $domain . '.' . $app_api . '/api/v1/tree/' . $album_id . '?sortBy=name&sortDirection=ascending';
        } else {
            $tree_url = 'https://' . $domain . '.' . $app_api . '/api/v1/tree?sortBy=name&sortDirection=ascending&layer=1';
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: Tree URL: ' . $tree_url);
        }
        
        // Make API call
        $headers = array(
            'Authorization' => 'Bearer ' . get_option('fbc_app_token'),
            'User-Agent' => 'WordPress Plugin',
            'Content-Type' => 'application/json;charset=utf-8'
        );
        
        $response = wp_remote_get($tree_url, array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('API request failed: ' . $response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if (empty($body)) {
            wp_send_json_error('No response from Canto API');
            return;
        }
        
        if ($http_code === 404) {
            // Tree endpoint not available, return a fallback structure
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ACF Canto: Tree endpoint not available (404), using fallback');
            }
            
            $fallback_data = array(
                'results' => array(
                    array(
                        'id' => 'all',
                        'name' => 'All Assets',
                        'type' => 'folder',
                        'children' => array()
                    )
                ),
                'found' => 1,
                'limit' => 1,
                'start' => 0
            );
            
            wp_send_json_success($fallback_data);
            return;
        }
        
        if ($http_code !== 200) {
            wp_send_json_error('API request failed with HTTP code: ' . $http_code);
            return;
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            wp_send_json_error('Invalid JSON response from Canto API');
            return;
        }
        
        if (isset($data['error'])) {
            wp_send_json_error('Error from Canto API: ' . $data['error']);
            return;
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Get assets from a specific album
     */
    public function get_album_assets()
    {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: get_album_assets called');
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'acf_canto_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        // Check if Canto is available
        if (!get_option('fbc_app_token')) {
            wp_send_json_error('Canto API token not configured');
            return;
        }
        
        $album_id = isset($_POST['album_id']) ? sanitize_text_field($_POST['album_id']) : '';
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ACF Canto: Album ID: ' . $album_id);
        }
        
        if (empty($album_id)) {
            wp_send_json_error('Album ID required');
            return;
        }
        
        // Handle special "all" case for fallback
        if ($album_id === 'all') {
            // Return a general search instead
            $this->search_assets();
            return;
        }
        
        // Get Canto configuration
        $domain = get_option('fbc_flight_domain');
        $app_api = get_option('fbc_app_api') ?: 'canto.com';
        
        if (!$domain) {
            wp_send_json_error('Canto domain not configured');
            return;
        }
        
        // Build album URL
        $start = 0;
        $limit = 50;
        
        // File types as used in the Canto plugin
        $fileType4Images = "GIF|JPG|PNG|SVG|WEBP|";
        $fileType4Documents = "DOC|KEY|ODT|PDF|PPT|XLS|";
        $fileType4Audio = "MPEG|M4A|OGG|WAV|";
        $fileType4Video = "AVI|MP4|MOV|OGG|VTT|WMV|3GP";
        $fileType = $fileType4Images . $fileType4Documents . $fileType4Audio . $fileType4Video;
        
        // Try different album/folder endpoints
        $endpoints_to_try = array(
            'album' => 'https://' . $domain . '.' . $app_api . '/api/v1/album/' . $album_id . '?limit=' . $limit . '&start=' . $start . '&fileType=' . urlencode($fileType),
            'folder' => 'https://' . $domain . '.' . $app_api . '/api/v1/folder/' . $album_id . '?limit=' . $limit . '&start=' . $start . '&fileType=' . urlencode($fileType),
            'search_in_album' => 'https://' . $domain . '.' . $app_api . '/api/v1/search?albumId=' . urlencode($album_id) . '&fileType=' . urlencode($fileType) . '&limit=' . $limit . '&start=' . $start
        );
        
        // Make API call
        $headers = array(
            'Authorization' => 'Bearer ' . get_option('fbc_app_token'),
            'User-Agent' => 'WordPress Plugin',
            'Content-Type' => 'application/json;charset=utf-8'
        );
        
        $assets = array();
        
        foreach ($endpoints_to_try as $endpoint_name => $album_url) {
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ACF Canto: Trying ' . $endpoint_name . ' URL: ' . $album_url);
            }
            
            $response = wp_remote_get($album_url, array(
                'headers' => $headers,
                'timeout' => 30
            ));
            
            // Debug logging for API response
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ACF Canto: ' . $endpoint_name . ' API Response Code: ' . wp_remote_retrieve_response_code($response));
            }
            
            if (is_wp_error($response)) {
                continue; // Try next endpoint
            }
            
            $body = wp_remote_retrieve_body($response);
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code === 200 && !empty($body)) {
                $data = json_decode($body, true);
                
                if ($data && !isset($data['error'])) {
                    // Process results
                    if (isset($data['results']) && is_array($data['results'])) {
                        foreach ($data['results'] as $item) {
                            $asset = $this->format_asset_data($item);
                            if ($asset) {
                                $assets[] = $asset;
                            }
                        }
                        
                        // Debug logging
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('ACF Canto: Found ' . count($assets) . ' assets using ' . $endpoint_name . ' for ID ' . $album_id);
                        }
                        
                        break; // Success, stop trying other endpoints
                    }
                }
            }
        }
        
        // If no assets found, it might be a folder with subfolders only
        if (empty($assets)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ACF Canto: No assets found for album/folder ' . $album_id . ', it might be a folder with only subfolders');
            }
            wp_send_json_success(array()); // Return empty array instead of error
            return;
        }

        wp_send_json_success($assets);
    }
}
