<?php
/**
 * ACF Canto Logger Class
 *
 * Centralized logging for the plugin with configurable debug levels
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ACF_Canto_Logger
{
    // Log levels
    const LEVEL_ERROR = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_INFO = 3;
    const LEVEL_DEBUG = 4;
    
    /**
     * Current log level
     */
    private $log_level;
    
    /**
     * Log prefix
     */
    private $prefix = 'ACF Canto Field';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->log_level = $this->get_log_level();
    }
    
    /**
     * Get current log level from settings
     *
     * @return int
     */
    private function get_log_level()
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return 0; // No logging
        }
        
        // Allow customization via constant
        if (defined('ACF_CANTO_LOG_LEVEL')) {
            return (int) ACF_CANTO_LOG_LEVEL;
        }
        
        // Default to INFO level in debug mode
        return self::LEVEL_INFO;
    }
    
    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     */
    public function error($message, $context = array())
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log warning message
     *
     * @param string $message
     * @param array $context
     */
    public function warning($message, $context = array())
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     */
    public function info($message, $context = array())
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log debug message
     *
     * @param string $message
     * @param array $context
     */
    public function debug($message, $context = array())
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log message at specified level
     *
     * @param int $level
     * @param string $message
     * @param array $context
     */
    private function log($level, $message, $context = array())
    {
        if ($level > $this->log_level) {
            return;
        }
        
        $level_names = array(
            self::LEVEL_ERROR => 'ERROR',
            self::LEVEL_WARNING => 'WARNING',
            self::LEVEL_INFO => 'INFO',
            self::LEVEL_DEBUG => 'DEBUG'
        );
        
        $level_name = isset($level_names[$level]) ? $level_names[$level] : 'UNKNOWN';
        $formatted_message = sprintf('[%s] %s: %s', $this->prefix, $level_name, $message);
        
        if (!empty($context)) {
            $formatted_message .= ' | Context: ' . json_encode($context);
        }
        
        error_log($formatted_message);
    }
    
    /**
     * Log exception with full details
     *
     * @param Exception $exception
     * @param string $message
     */
    public function exception($exception, $message = '')
    {
        $context = array(
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        );
        
        $log_message = $message ?: 'Exception occurred';
        $this->error($log_message, $context);
    }
    
    /**
     * Log API request/response for debugging
     *
     * @param string $url
     * @param array $args
     * @param mixed $response
     */
    public function api_request($url, $args = array(), $response = null)
    {
        if ($this->log_level < self::LEVEL_DEBUG) {
            return;
        }
        
        $context = array(
            'url' => $url,
            'method' => isset($args['method']) ? $args['method'] : 'GET',
            'timeout' => isset($args['timeout']) ? $args['timeout'] : 'default'
        );
        
        if ($response) {
            if (is_wp_error($response)) {
                $context['error'] = $response->get_error_message();
            } else {
                $context['response_code'] = wp_remote_retrieve_response_code($response);
                $context['response_size'] = strlen(wp_remote_retrieve_body($response));
            }
        }
        
        $this->debug('API Request', $context);
    }
    
    /**
     * Check if logging is enabled for a specific level
     *
     * @param int $level
     * @return bool
     */
    public function is_enabled($level)
    {
        return $level <= $this->log_level;
    }
    
    /**
     * Get current log level
     *
     * @return int
     */
    public function get_current_level()
    {
        return $this->log_level;
    }
}