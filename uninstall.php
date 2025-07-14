<?php
/**
 * ACF Canto Field - Uninstall Script
 *
 * This file is executed when the plugin is uninstalled (deleted).
 * It cleans up any data created by the plugin.
 */

// Exit if accessed directly or not during uninstall
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data
 */
function acf_canto_field_uninstall_cleanup() {
    global $wpdb;
    
    // Remove transients created by the plugin
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_canto_asset_%' 
         OR option_name LIKE '_transient_timeout_canto_asset_%'"
    );
    
    // Remove plugin options
    delete_option('acf_canto_field_activated');
    delete_option('acf_canto_field_version');
    
    // Clean up any remaining cache
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

// Run cleanup
acf_canto_field_uninstall_cleanup();
