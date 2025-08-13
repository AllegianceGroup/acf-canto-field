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
     * Logger instance
     *
     * @var ACF_Canto_Logger
     */
    private $logger;
    
    /**
     * API helper instance
     *
     * @var ACF_Canto_API
     */
    private $api;
    
    /**
     * Asset formatter instance
     *
     * @var ACF_Canto_Asset_Formatter
     */
    private $formatter;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize helper classes
        $this->logger = new ACF_Canto_Logger();
        $this->api = new ACF_Canto_API($this->logger);
        $this->formatter = new ACF_Canto_Asset_Formatter($this->logger, $this->api);
        
        $this->logger->debug('AJAX Handler initialized');
        
        // AJAX actions for logged in users
        add_action('wp_ajax_acf_canto_search', array($this, 'search_assets'));
        add_action('wp_ajax_acf_canto_get_asset', array($this, 'get_asset'));
        add_action('wp_ajax_acf_canto_get_tree', array($this, 'get_tree'));
        add_action('wp_ajax_acf_canto_get_album', array($this, 'get_album_assets'));
        add_action('wp_ajax_acf_canto_find_by_filename', array($this, 'find_by_filename'));
    }
    
    /**
     * Search Canto assets
     */
    public function search_assets()
    {
        $this->logger->debug('search_assets called');

        // Security and permission checks
        if (!$this->verify_ajax_request()) {
            return;
        }
        
        // Check if API is configured
        if (!$this->api->is_configured()) {
            $errors = $this->api->get_config_errors();
            wp_send_json_error(implode(', ', $errors));
            return;
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $selected_id = isset($_POST['selected_id']) ? sanitize_text_field($_POST['selected_id']) : '';
        
        $this->logger->debug('AJAX search request', array(
            'query' => $query,
            'selected_id' => $selected_id
        ));
        
        $search_options = array(
            'limit' => 50,
            'start' => 0
        );
        
        $result = $this->api->search_assets($query, $search_options);
        
        if (is_wp_error($result)) {
            $this->logger->error('Search request failed: ' . $result->get_error_message());
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        $assets = array();
        if (isset($result['results']) && is_array($result['results'])) {
            foreach ($result['results'] as $item) {
                $asset_data = $this->format_asset_data($item);
                if ($asset_data) {
                    $assets[] = $asset_data;
                }
            }
        }
        
        wp_send_json_success($assets);
    }
    
    /**
     * Format asset data for frontend (compatible with original format)
     *
     * @param array $data Raw asset data from API
     * @return array|false Formatted asset data
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
            if (strpos($data['url']['preview'], '/video/') !== false) {
                $scheme = 'video';
            } elseif (strpos($data['url']['preview'], '/document/') !== false) {
                $scheme = 'document';
            }
        }
        
        $asset = array(
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
        
        // Enhanced metadata collection
        if (isset($data['default']) && is_array($data['default'])) {
            $asset['metadata'] = $data['default'];
            
            // Extract and format common metadata fields
            $metadata_mapping = array(
                'Dimensions' => 'dimensions',
                'Content Type' => 'mime_type', 
                'File Size' => 'file_size',
                'Created' => 'created_date',
                'Modified' => 'modified_date',
                'Creator' => 'creator',
                'Copyright' => 'copyright',
                'Keywords' => 'keywords',
                'Description' => 'description',
                'Title' => 'title',
                'Color Space' => 'color_space',
                'Bit Depth' => 'bit_depth',
                'Resolution' => 'resolution',
                'Camera Make' => 'camera_make',
                'Camera Model' => 'camera_model',
                'ISO' => 'iso',
                'Aperture' => 'aperture',
                'Shutter Speed' => 'shutter_speed',
                'Focal Length' => 'focal_length',
                'GPS Latitude' => 'gps_latitude',
                'GPS Longitude' => 'gps_longitude',
                'Duration' => 'duration',
                'Frame Rate' => 'frame_rate',
                'Video Codec' => 'video_codec',
                'Audio Codec' => 'audio_codec',
                'Page Count' => 'page_count',
                'Author' => 'author',
                'Subject' => 'subject'
            );
            
            foreach ($metadata_mapping as $canto_field => $asset_field) {
                if (isset($data['default'][$canto_field]) && !empty($data['default'][$canto_field])) {
                    $asset[$asset_field] = $data['default'][$canto_field];
                }
            }
            
            // Handle dimensions specially if not already set
            if (empty($asset['dimensions'])) {
                $dimension_fields = array('Dimensions', 'Size', 'Image Size', 'Resolution');
                foreach ($dimension_fields as $field) {
                    if (isset($data['default'][$field]) && !empty($data['default'][$field])) {
                        $asset['dimensions'] = $data['default'][$field];
                        break;
                    }
                }
            }
            
            // Extract filename from various possible metadata fields
            $filename_fields = array('Filename', 'File Name', 'Original Filename', 'filename', 'file_name');
            foreach ($filename_fields as $field) {
                if (isset($data['default'][$field]) && !empty($data['default'][$field])) {
                    $asset['filename'] = $data['default'][$field];
                    break;
                }
            }
            
            // Format metadata for display
            $asset['metadata_display'] = $this->format_metadata_for_display($data['default'], $scheme);
        }
        
        // If no filename found in metadata, try to extract from name or construct from ID
        if (empty($asset['filename'])) {
            // If name contains file extension, use it as filename
            if (preg_match('/\.[a-zA-Z0-9]{2,5}$/', $asset['name'])) {
                $asset['filename'] = $asset['name'];
            } else {
                // Construct filename using asset name and scheme-based extension
                $extension_map = array(
                    'image' => 'jpg',
                    'video' => 'mp4', 
                    'document' => 'pdf'
                );
                $extension = isset($extension_map[$scheme]) ? $extension_map[$scheme] : 'bin';
                $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $asset['name']);
                $asset['filename'] = $safe_name . '.' . $extension;
            }
        }
        
        // File size
        if (isset($data['size'])) {
            $asset['size'] = size_format($data['size']);
        }
        
        // Add formatted date (try multiple date fields)
        $asset['formatted_date'] = $this->get_formatted_asset_date($data, $asset['uploaded']);
        
        return $asset;
    }
    
    /**
     * Format metadata for display - simplified to only show required fields
     *
     * @param array $metadata Raw metadata from Canto
     * @param string $scheme Asset type (image, video, document)
     * @return array Organized metadata for display
     */
    private function format_metadata_for_display($metadata, $scheme)
    {
        // This method is no longer used for modal display
        // Metadata is now shown directly under asset titles
        return array('metadata' => array());
    }
    
    /**
     * Get formatted asset date from various possible sources
     *
     * @param array $data Raw asset data
     * @param string $uploaded_date Fallback uploaded date
     * @return string Formatted date or empty string
     */
    private function get_formatted_asset_date($data, $uploaded_date = '')
    {
        // Try to find date from metadata first
        if (isset($data['default']) && is_array($data['default'])) {
            $date_fields = array('Modified', 'Last Modified', 'Date Modified', 'Created', 'Date Created', 'lastModified', 'lastUploaded');
            
            foreach ($date_fields as $field) {
                if (isset($data['default'][$field]) && !empty($data['default'][$field])) {
                    return $this->format_date_to_us($data['default'][$field]);
                }
            }
        }
        
        // Try lastUploaded from main data
        if (isset($data['lastUploaded']) && !empty($data['lastUploaded'])) {
            return $this->format_date_to_us($data['lastUploaded']);
        }
        
        // Fallback to uploaded date parameter
        if (!empty($uploaded_date)) {
            return $this->format_date_to_us($uploaded_date);
        }
        
        return '';
    }
    
    /**
     * Format date to US format (mm/dd/yyyy)
     *
     * @param string $date_string Raw date string from Canto
     * @return string Formatted date or original string if parsing fails
     */
    private function format_date_to_us($date_string)
    {
        // Try to parse the date string
        $timestamp = strtotime($date_string);
        
        if ($timestamp !== false) {
            return date('m/d/Y', $timestamp);
        }
        
        // If parsing fails, return original string
        return $date_string;
    }
    
    /**
     * Verify AJAX request security and permissions
     *
     * @return bool
     */
    private function verify_ajax_request()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'acf_canto_nonce')) {
            $this->logger->warning('AJAX request failed nonce verification');
            wp_die('Security check failed');
        }
        
        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            $this->logger->warning('AJAX request from user without edit_posts capability');
            wp_die('Insufficient permissions');
        }
        
        // Check if Canto function exists (compatibility check)
        if (!function_exists('Canto')) {
            $this->logger->error('Canto plugin function not available');
            wp_send_json_error('Canto plugin not found');
            return false;
        }
        
        return true;
    }
    
    /**
     * Get specific asset by ID
     */
    public function get_asset()
    {
        // Security and permission checks
        if (!$this->verify_ajax_request()) {
            return;
        }
        
        // Check if API is configured
        if (!$this->api->is_configured()) {
            $errors = $this->api->get_config_errors();
            wp_send_json_error(implode(', ', $errors));
            return;
        }
        
        $asset_id = isset($_POST['asset_id']) ? sanitize_text_field($_POST['asset_id']) : '';
        
        if (empty($asset_id)) {
            wp_send_json_error('Asset ID required');
            return;
        }
        
        $this->logger->debug('AJAX get asset request', array('asset_id' => $asset_id));
        
        $result = $this->api->get_asset($asset_id);
        
        if (is_wp_error($result)) {
            $this->logger->error('Get asset request failed: ' . $result->get_error_message(), array('asset_id' => $asset_id));
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        $formatted_asset = $this->format_asset_data($result);
        
        if (!$formatted_asset) {
            wp_send_json_error('Failed to format asset data');
            return;
        }
        
        // Asset data formatted successfully
        
        wp_send_json_success($formatted_asset);
    }
    
    /**
     * Get Canto tree/folder structure
     */
    public function get_tree()
    {
        $this->logger->debug('get_tree called');

        // Security and permission checks
        if (!$this->verify_ajax_request()) {
            return;
        }
        
        $album_id = isset($_POST['album_id']) ? sanitize_text_field($_POST['album_id']) : '';
        
        // Get Canto configuration
        $domain = get_option('fbc_flight_domain');
        $app_api = get_option('fbc_app_api') ?: 'canto.com';
        $token = get_option('fbc_app_token');
        
        if (!$domain || !$token) {
            wp_send_json_error('Canto domain or token not configured');
            return;
        }
        
        // Build tree URL
        if (!empty($album_id)) {
            $tree_url = 'https://' . $domain . '.' . $app_api . '/api/v1/tree/' . $album_id . '?sortBy=name&sortDirection=ascending';
        } else {
            $tree_url = 'https://' . $domain . '.' . $app_api . '/api/v1/tree?sortBy=name&sortDirection=ascending&layer=1';
        }
        
        $this->logger->debug('Tree API URL', array('url' => $tree_url));
        
        // Make API call
        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'WordPress Plugin',
            'Content-Type' => 'application/json;charset=utf-8'
        );
        
        $response = wp_remote_get($tree_url, array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $this->logger->error('Tree API request failed', array('error' => $response->get_error_message()));
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
            $this->logger->info('Tree endpoint not available (404), using fallback');
            
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
            $this->logger->error('Tree API returned error', array('code' => $http_code, 'body' => substr($body, 0, 200)));
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
        $this->logger->debug('get_album_assets called');

        // Security and permission checks
        if (!$this->verify_ajax_request()) {
            return;
        }
        
        $album_id = isset($_POST['album_id']) ? sanitize_text_field($_POST['album_id']) : '';
        
        $this->logger->debug('Album ID requested', array('album_id' => $album_id));
        
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
        $token = get_option('fbc_app_token');
        
        if (!$domain || !$token) {
            wp_send_json_error('Canto domain or token not configured');
            return;
        }
        
        // Build album URL - try multiple endpoints
        $start = 0;
        $limit = 50;
        
        // File types as used in the Canto plugin
        $fileType = 'GIF|JPG|PNG|SVG|WEBP|DOC|KEY|ODT|PDF|PPT|XLS|MPEG|M4A|OGG|WAV|AVI|MP4|MOV|OGG|VTT|WMV|3GP';
        
        // Try different album/folder endpoints
        $endpoints_to_try = array(
            'album' => 'https://' . $domain . '.' . $app_api . '/api/v1/album/' . $album_id . '?limit=' . $limit . '&start=' . $start . '&fileType=' . urlencode($fileType),
            'folder' => 'https://' . $domain . '.' . $app_api . '/api/v1/folder/' . $album_id . '?limit=' . $limit . '&start=' . $start . '&fileType=' . urlencode($fileType),
            'search_in_album' => 'https://' . $domain . '.' . $app_api . '/api/v1/search?albumId=' . urlencode($album_id) . '&fileType=' . urlencode($fileType) . '&limit=' . $limit . '&start=' . $start
        );
        
        // Make API call
        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'WordPress Plugin',
            'Content-Type' => 'application/json;charset=utf-8'
        );
        
        $assets = array();
        
        foreach ($endpoints_to_try as $endpoint_name => $album_url) {
            $this->logger->debug('Trying album endpoint', array('endpoint' => $endpoint_name, 'url' => $album_url));
            
            $response = wp_remote_get($album_url, array(
                'headers' => $headers,
                'timeout' => 30
            ));
            
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
                        
                        $this->logger->debug('Found assets using endpoint', array('endpoint' => $endpoint_name, 'count' => count($assets), 'album_id' => $album_id));
                        
                        break; // Success, stop trying other endpoints
                    }
                }
            }
        }
        
        // If no assets found, it might be a folder with subfolders only
        if (empty($assets)) {
            $this->logger->info('No assets found for album/folder', array('album_id' => $album_id));
            wp_send_json_success(array()); // Return empty array instead of error
            return;
        }

        wp_send_json_success($assets);
    }
    
    /**
     * Find asset by filename via AJAX
     */
    public function find_by_filename()
    {
        $this->logger->debug('find_by_filename called');

        // Security and permission checks
        if (!$this->verify_ajax_request()) {
            return;
        }
        
        $filename = isset($_POST['filename']) ? sanitize_text_field($_POST['filename']) : '';
        
        if (empty($filename)) {
            wp_send_json_error(__('Filename required', 'acf-canto-field'));
            return;
        }
        
        $this->logger->debug('AJAX find by filename request', array('filename' => $filename));
        
        // Use the field class method for consistency
        if (class_exists('ACF_Field_Canto')) {
            $field = new ACF_Field_Canto();
            $asset = $field->find_asset_by_filename($filename);
            
            if ($asset) {
                wp_send_json_success($asset);
            } else {
                wp_send_json_error(__('Asset not found with filename: ', 'acf-canto-field') . $filename);
            }
        } else {
            wp_send_json_error(__('ACF Canto Field class not available', 'acf-canto-field'));
        }
    }
}