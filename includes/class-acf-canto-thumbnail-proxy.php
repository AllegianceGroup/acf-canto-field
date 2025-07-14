<?php
/**
 * ACF Canto Thumbnail Proxy
 *
 * Handles authenticated thumbnail requests from Canto
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ACF_Canto_Thumbnail_Proxy
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Add rewrite rule for thumbnail proxy
        add_action('init', array($this, 'add_rewrite_rule'));
        add_action('template_redirect', array($this, 'handle_thumbnail_request'));
        add_filter('query_vars', array($this, 'add_query_vars'));
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'canto_thumbnail';
        $vars[] = 'asset_type';
        $vars[] = 'asset_id';
        return $vars;
    }
    
    /**
     * Add rewrite rule for thumbnail proxy
     */
    public function add_rewrite_rule()
    {
        add_rewrite_rule(
            '^canto-thumbnail/([^/]+)/([^/]+)/?$',
            'index.php?canto_thumbnail=1&asset_type=$matches[1]&asset_id=$matches[2]',
            'top'
        );
    }
    
    /**
     * Handle thumbnail requests
     */
    public function handle_thumbnail_request()
    {
        if (get_query_var('canto_thumbnail')) {
            $asset_type = get_query_var('asset_type');
            $asset_id = get_query_var('asset_id');
            
            if (empty($asset_type) || empty($asset_id)) {
                status_header(400);
                exit('Bad Request');
            }
            
            $this->serve_thumbnail($asset_type, $asset_id);
        }
    }
    
    /**
     * Serve thumbnail with authentication
     */
    private function serve_thumbnail($asset_type, $asset_id)
    {
        // Check if Canto is configured
        $token = get_option('fbc_app_token');
        $domain = get_option('fbc_flight_domain');
        $app_api = get_option('fbc_app_api') ?: 'canto.com';
        
        if (!$token || !$domain) {
            status_header(404);
            exit('Not Found');
        }
        
        // Build thumbnail URL
        $thumbnail_url = 'https://' . $domain . '.' . $app_api . '/api_binary/v1/' . $asset_type . '/' . $asset_id . '/preview';
        
        // Set up headers for the API request
        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'WordPress Plugin'
        );
        
        // Make request to Canto API
        $response = wp_remote_get($thumbnail_url, array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            status_header(404);
            exit('Not Found');
        }
        
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code !== 200 || empty($body)) {
            status_header(404);
            exit('Not Found');
        }
        
        // Get content type from response
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (empty($content_type)) {
            $content_type = 'image/jpeg'; // fallback
        }
        
        // Set appropriate headers and serve the image
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($body));
        header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
        
        echo $body;
        exit;
    }
}

// Initialize the thumbnail proxy
new ACF_Canto_Thumbnail_Proxy();
