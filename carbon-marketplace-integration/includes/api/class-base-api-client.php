<?php
/**
 * Base API Client for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace\Api
 */

namespace CarbonMarketplace\Api;

use WP_Error;

/**
 * Abstract base class for all API clients
 */
abstract class BaseApiClient {
    
    /**
     * API base URL
     *
     * @var string
     */
    protected $base_url;
    
    /**
     * API credentials
     *
     * @var array
     */
    protected $credentials;
    
    /**
     * Rate limiting configuration
     *
     * @var array
     */
    protected $rate_limits;
    
    /**
     * Request timeout in seconds
     *
     * @var int
     */
    protected $timeout = 30;
    
    /**
     * Maximum retry attempts
     *
     * @var int
     */
    protected $max_retries = 3;
    
    /**
     * Request cache for duplicate prevention
     *
     * @var array
     */
    private $request_cache = array();
    
    /**
     * Rate limiting tracker
     *
     * @var array
     */
    private static $rate_tracker = array();
    
    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct($config = array()) {
        $this->base_url = $config['base_url'] ?? '';
        $this->credentials = $config['credentials'] ?? array();
        $this->rate_limits = $config['rate_limits'] ?? array(
            'requests_per_second' => 10,
            'burst_limit' => 50,
        );
        
        if (isset($config['timeout'])) {
            $this->timeout = (int) $config['timeout'];
        }
        
        if (isset($config['max_retries'])) {
            $this->max_retries = (int) $config['max_retries'];
        }
    }
    
    /**
     * Make HTTP request with retry logic and rate limiting
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array|WP_Error Response data or error
     */
    protected function make_request($method, $endpoint, $data = array(), $headers = array()) {
        // Check rate limits
        if (!$this->check_rate_limit()) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                array('status' => 429)
            );
        }
        
        // Generate cache key for duplicate request prevention
        $cache_key = $this->generate_cache_key($method, $endpoint, $data);
        
        // Check for duplicate request
        if (isset($this->request_cache[$cache_key])) {
            return $this->request_cache[$cache_key];
        }
        
        $url = $this->build_url($endpoint);
        $request_args = $this->build_request_args($method, $data, $headers);
        
        $attempt = 0;
        $last_error = null;
        
        while ($attempt < $this->max_retries) {
            $attempt++;
            
            // Log request attempt
            $this->log_request($method, $url, $request_args, $attempt);
            
            // Make the request
            $response = wp_remote_request($url, $request_args);
            
            // Handle response
            if (is_wp_error($response)) {
                $last_error = $response;
                $this->log_error('HTTP request failed', array(
                    'url' => $url,
                    'error' => $response->get_error_message(),
                    'attempt' => $attempt,
                ));
                
                // Wait before retry (exponential backoff)
                if ($attempt < $this->max_retries) {
                    sleep(pow(2, $attempt - 1));
                }
                continue;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            // Handle rate limiting
            if ($status_code === 429) {
                $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                $wait_time = $retry_after ? (int) $retry_after : pow(2, $attempt);
                
                $this->log_error('Rate limited by API', array(
                    'url' => $url,
                    'retry_after' => $retry_after,
                    'wait_time' => $wait_time,
                    'attempt' => $attempt,
                ));
                
                if ($attempt < $this->max_retries) {
                    sleep($wait_time);
                }
                continue;
            }
            
            // Handle server errors (5xx)
            if ($status_code >= 500) {
                $last_error = new WP_Error(
                    'server_error',
                    'Server error: ' . $status_code,
                    array('status' => $status_code, 'body' => $body)
                );
                
                $this->log_error('Server error', array(
                    'url' => $url,
                    'status_code' => $status_code,
                    'body' => $body,
                    'attempt' => $attempt,
                ));
                
                if ($attempt < $this->max_retries) {
                    sleep(pow(2, $attempt - 1));
                }
                continue;
            }
            
            // Parse response
            $parsed_response = $this->parse_response($body, $status_code);
            
            // Handle client errors (4xx)
            if ($status_code >= 400 && $status_code < 500) {
                $error_message = $this->extract_error_message($parsed_response, $status_code);
                $error = new WP_Error(
                    'client_error',
                    $error_message,
                    array('status' => $status_code, 'response' => $parsed_response)
                );
                
                $this->log_error('Client error', array(
                    'url' => $url,
                    'status_code' => $status_code,
                    'error_message' => $error_message,
                ));
                
                // Cache and return error for 4xx errors (don't retry)
                $this->request_cache[$cache_key] = $error;
                return $error;
            }
            
            // Success - cache and return response
            $this->log_success($method, $url, $status_code);
            $this->request_cache[$cache_key] = $parsed_response;
            $this->update_rate_limit_tracker();
            
            return $parsed_response;
        }
        
        // All retries exhausted
        if ($last_error) {
            return $last_error;
        }
        
        return new WP_Error(
            'max_retries_exceeded',
            'Maximum retry attempts exceeded',
            array('attempts' => $attempt)
        );
    }
    
    /**
     * Build full URL from endpoint
     *
     * @param string $endpoint API endpoint
     * @return string Full URL
     */
    protected function build_url($endpoint) {
        return rtrim($this->base_url, '/') . '/' . ltrim($endpoint, '/');
    }
    
    /**
     * Build request arguments for wp_remote_request
     *
     * @param string $method HTTP method
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array Request arguments
     */
    protected function build_request_args($method, $data, $headers) {
        $args = array(
            'method' => strtoupper($method),
            'timeout' => $this->timeout,
            'headers' => array_merge($this->get_default_headers(), $headers),
            'user-agent' => $this->get_user_agent(),
        );
        
        // Add authentication headers
        $auth_headers = $this->get_auth_headers();
        if (!empty($auth_headers)) {
            $args['headers'] = array_merge($args['headers'], $auth_headers);
        }
        
        // Add body data for POST/PUT requests
        if (in_array($method, array('POST', 'PUT', 'PATCH')) && !empty($data)) {
            if (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json') {
                $args['body'] = wp_json_encode($data);
            } else {
                $args['body'] = $data;
            }
        } elseif ($method === 'GET' && !empty($data)) {
            // Add query parameters for GET requests
            $args['body'] = $data;
        }
        
        return $args;
    }
    
    /**
     * Get default headers
     *
     * @return array Default headers
     */
    protected function get_default_headers() {
        return array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        );
    }
    
    /**
     * Get authentication headers (to be implemented by child classes)
     *
     * @return array Authentication headers
     */
    abstract protected function get_auth_headers();
    
    /**
     * Get user agent string
     *
     * @return string User agent
     */
    protected function get_user_agent() {
        return 'Carbon-Marketplace-WordPress-Plugin/' . CARBON_MARKETPLACE_VERSION;
    }
    
    /**
     * Parse API response
     *
     * @param string $body Response body
     * @param int $status_code HTTP status code
     * @return array|WP_Error Parsed response or error
     */
    protected function parse_response($body, $status_code) {
        if (empty($body)) {
            return array();
        }
        
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'json_decode_error',
                'Failed to decode JSON response: ' . json_last_error_msg(),
                array('body' => $body, 'status' => $status_code)
            );
        }
        
        return $decoded;
    }
    
    /**
     * Extract error message from response
     *
     * @param array|WP_Error $response Parsed response
     * @param int $status_code HTTP status code
     * @return string Error message
     */
    protected function extract_error_message($response, $status_code) {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }
        
        // Common error message fields
        $error_fields = array('error', 'message', 'error_description', 'detail');
        
        foreach ($error_fields as $field) {
            if (isset($response[$field])) {
                return is_string($response[$field]) ? $response[$field] : wp_json_encode($response[$field]);
            }
        }
        
        return 'HTTP Error ' . $status_code;
    }
    
    /**
     * Check rate limits
     *
     * @return bool True if request is allowed
     */
    protected function check_rate_limit() {
        $client_class = get_class($this);
        $current_time = time();
        
        if (!isset(self::$rate_tracker[$client_class])) {
            self::$rate_tracker[$client_class] = array(
                'requests' => array(),
                'last_reset' => $current_time,
            );
        }
        
        $tracker = &self::$rate_tracker[$client_class];
        
        // Remove old requests (older than 1 second)
        $tracker['requests'] = array_filter($tracker['requests'], function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 1;
        });
        
        // Check if we're within limits
        $requests_per_second = $this->rate_limits['requests_per_second'] ?? 10;
        
        if (count($tracker['requests']) >= $requests_per_second) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Update rate limit tracker
     */
    protected function update_rate_limit_tracker() {
        $client_class = get_class($this);
        $current_time = time();
        
        if (!isset(self::$rate_tracker[$client_class])) {
            self::$rate_tracker[$client_class] = array(
                'requests' => array(),
                'last_reset' => $current_time,
            );
        }
        
        self::$rate_tracker[$client_class]['requests'][] = $current_time;
    }
    
    /**
     * Generate cache key for request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return string Cache key
     */
    protected function generate_cache_key($method, $endpoint, $data) {
        return md5($method . $endpoint . serialize($data));
    }
    
    /**
     * Validate API credentials
     *
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    abstract public function validate_credentials();
    
    /**
     * Log successful request
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param int $status_code Response status code
     */
    protected function log_success($method, $url, $status_code) {
        if (!$this->is_logging_enabled()) {
            return;
        }
        
        error_log(sprintf(
            '[Carbon Marketplace] %s %s - Success (%d)',
            $method,
            $url,
            $status_code
        ));
    }
    
    /**
     * Log request attempt
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array $args Request arguments
     * @param int $attempt Attempt number
     */
    protected function log_request($method, $url, $args, $attempt) {
        if (!$this->is_logging_enabled()) {
            return;
        }
        
        error_log(sprintf(
            '[Carbon Marketplace] %s %s - Attempt %d',
            $method,
            $url,
            $attempt
        ));
    }
    
    /**
     * Log error
     *
     * @param string $message Error message
     * @param array $context Error context
     */
    protected function log_error($message, $context = array()) {
        if (!$this->is_logging_enabled()) {
            return;
        }
        
        $log_message = '[Carbon Marketplace] ' . $message;
        if (!empty($context)) {
            $log_message .= ' - ' . wp_json_encode($context);
        }
        
        error_log($log_message);
    }
    
    /**
     * Check if logging is enabled
     *
     * @return bool True if logging is enabled
     */
    protected function is_logging_enabled() {
        return get_option('carbon_marketplace_enable_logging', true);
    }
    
    /**
     * Clear request cache
     */
    public function clear_cache() {
        $this->request_cache = array();
    }
    
    /**
     * Get rate limit status
     *
     * @return array Rate limit information
     */
    public function get_rate_limit_status() {
        $client_class = get_class($this);
        $current_time = time();
        
        if (!isset(self::$rate_tracker[$client_class])) {
            return array(
                'requests_made' => 0,
                'requests_remaining' => $this->rate_limits['requests_per_second'] ?? 10,
                'reset_time' => $current_time + 1,
            );
        }
        
        $tracker = self::$rate_tracker[$client_class];
        
        // Count recent requests
        $recent_requests = array_filter($tracker['requests'], function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 1;
        });
        
        $requests_per_second = $this->rate_limits['requests_per_second'] ?? 10;
        
        return array(
            'requests_made' => count($recent_requests),
            'requests_remaining' => max(0, $requests_per_second - count($recent_requests)),
            'reset_time' => $current_time + 1,
        );
    }
}