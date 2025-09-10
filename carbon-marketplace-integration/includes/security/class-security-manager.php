<?php
/**
 * Security Manager for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Security;

use WP_Error;

/**
 * Manages security features including input validation, sanitization, and nonce verification
 */
class SecurityManager {
    
    private InputValidator $validator;
    
    public function __construct() {
        $this->validator = new InputValidator();
    }
    
    /**
     * Initialize security features
     */
    public function init(): void {
        add_action('init', [$this, 'setup_security_headers']);
        add_action('wp_ajax_carbon_marketplace_validate_input', [$this, 'handle_input_validation']);
        add_action('wp_ajax_nopriv_carbon_marketplace_validate_input', [$this, 'handle_input_validation']);
        
        // Add security filters
        add_filter('carbon_marketplace_sanitize_input', [$this, 'sanitize_input'], 10, 2);
        add_filter('carbon_marketplace_validate_nonce', [$this, 'validate_nonce'], 10, 2);
    }
    
    /**
     * Setup security headers
     */
    public function setup_security_headers(): void {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }
    
    /**
     * Validate and sanitize search parameters
     */
    public function sanitize_search_params(array $params): array {
        $sanitized = [];
        
        // Keyword sanitization
        if (isset($params['keyword'])) {
            $sanitized['keyword'] = $this->validator->sanitize_search_keyword($params['keyword']);
        }
        
        // Location sanitization
        if (isset($params['location'])) {
            $sanitized['location'] = $this->validator->sanitize_location($params['location']);
        }
        
        // Project type sanitization
        if (isset($params['project_type'])) {
            $sanitized['project_type'] = $this->validator->sanitize_project_type($params['project_type']);
        }
        
        // Price range validation
        if (isset($params['min_price'])) {
            $min_price = $this->validator->validate_price($params['min_price']);
            if (!is_wp_error($min_price)) {
                $sanitized['min_price'] = $min_price;
            }
        }
        
        if (isset($params['max_price'])) {
            $max_price = $this->validator->validate_price($params['max_price']);
            if (!is_wp_error($max_price)) {
                $sanitized['max_price'] = $max_price;
            }
        }
        
        // Pagination parameters
        if (isset($params['limit'])) {
            $sanitized['limit'] = $this->validator->validate_limit($params['limit']);
        }
        
        if (isset($params['offset'])) {
            $sanitized['offset'] = $this->validator->validate_offset($params['offset']);
        }
        
        // Sort parameters
        if (isset($params['sort_by'])) {
            $sanitized['sort_by'] = $this->validator->validate_sort_field($params['sort_by']);
        }
        
        if (isset($params['sort_order'])) {
            $sanitized['sort_order'] = $this->validator->validate_sort_order($params['sort_order']);
        }
        
        return $sanitized;
    }
    
    /**
     * Validate checkout request data
     */
    public function validate_checkout_request(array $data): array|WP_Error {
        $errors = [];
        
        // Amount validation
        if (empty($data['amount_kg'])) {
            $errors[] = 'Amount is required';
        } elseif (!$this->validator->is_valid_amount($data['amount_kg'])) {
            $errors[] = 'Invalid amount format';
        }
        
        // URL validation
        if (empty($data['success_url'])) {
            $errors[] = 'Success URL is required';
        } elseif (!$this->validator->is_valid_url($data['success_url'])) {
            $errors[] = 'Invalid success URL';
        }
        
        if (empty($data['cancel_url'])) {
            $errors[] = 'Cancel URL is required';
        } elseif (!$this->validator->is_valid_url($data['cancel_url'])) {
            $errors[] = 'Invalid cancel URL';
        }
        
        // Email validation (if provided)
        if (!empty($data['customer_email']) && !$this->validator->is_valid_email($data['customer_email'])) {
            $errors[] = 'Invalid email address';
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }
        
        // Sanitize valid data
        return [
            'amount_kg' => (float) $data['amount_kg'],
            'portfolio_id' => sanitize_text_field($data['portfolio_id'] ?? ''),
            'success_url' => esc_url_raw($data['success_url']),
            'cancel_url' => esc_url_raw($data['cancel_url']),
            'customer_email' => sanitize_email($data['customer_email'] ?? ''),
            'customer_name' => sanitize_text_field($data['customer_name'] ?? ''),
            'metadata' => $this->sanitize_metadata($data['metadata'] ?? [])
        ];
    }
    
    /**
     * Validate API credentials
     */
    public function validate_api_credentials(array $credentials, string $vendor): bool|WP_Error {
        switch ($vendor) {
            case 'cnaught':
                return $this->validate_cnaught_credentials($credentials);
            case 'toucan':
                return $this->validate_toucan_credentials($credentials);
            default:
                return new WP_Error('invalid_vendor', 'Unknown vendor');
        }
    }
    
    /**
     * Validate CNaught credentials
     */
    private function validate_cnaught_credentials(array $credentials): bool|WP_Error {
        if (empty($credentials['api_key'])) {
            return new WP_Error('missing_api_key', 'API key is required');
        }
        
        if (empty($credentials['client_id'])) {
            return new WP_Error('missing_client_id', 'Client ID is required');
        }
        
        // Validate API key format
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $credentials['api_key'])) {
            return new WP_Error('invalid_api_key', 'Invalid API key format');
        }
        
        return true;
    }
    
    /**
     * Validate Toucan credentials
     */
    private function validate_toucan_credentials(array $credentials): bool|WP_Error {
        // Toucan might not require credentials for public subgraph access
        if (!empty($credentials['api_key'])) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $credentials['api_key'])) {
                return new WP_Error('invalid_api_key', 'Invalid API key format');
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize metadata array
     */
    private function sanitize_metadata(array $metadata): array {
        $sanitized = [];
        
        foreach ($metadata as $key => $value) {
            $clean_key = sanitize_key($key);
            
            if (is_string($value)) {
                $sanitized[$clean_key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$clean_key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$clean_key] = $this->sanitize_metadata($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Verify nonce for AJAX requests
     */
    public function verify_ajax_nonce(string $nonce, string $action): bool {
        return wp_verify_nonce($nonce, $action) !== false;
    }
    
    /**
     * Generate secure nonce
     */
    public function generate_nonce(string $action): string {
        return wp_create_nonce($action);
    }
    
    /**
     * Rate limiting for API requests
     */
    public function check_rate_limit(string $identifier, int $limit = 100, int $window = 3600): bool {
        $transient_key = 'carbon_marketplace_rate_limit_' . md5($identifier);
        $current_count = get_transient($transient_key) ?: 0;
        
        if ($current_count >= $limit) {
            return false;
        }
        
        set_transient($transient_key, $current_count + 1, $window);
        return true;
    }
    
    /**
     * Sanitize input based on type
     */
    public function sanitize_input($value, string $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'key':
                return sanitize_key($value);
            case 'slug':
                return sanitize_title($value);
            case 'html':
                return wp_kses_post($value);
            case 'text':
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Validate nonce
     */
    public function validate_nonce(string $nonce, string $action): bool {
        return wp_verify_nonce($nonce, $action) !== false;
    }
    
    /**
     * Handle AJAX input validation
     */
    public function handle_input_validation(): void {
        if (!$this->verify_ajax_nonce($_POST['nonce'] ?? '', 'carbon_marketplace_validate')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $input_type = sanitize_text_field($_POST['input_type'] ?? '');
        $input_value = $_POST['input_value'] ?? '';
        
        $validation_result = $this->validator->validate_input($input_value, $input_type);
        
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }
        
        wp_send_json_success([
            'valid' => true,
            'sanitized_value' => $validation_result
        ]);
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt_data(string $data): string {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data); // Fallback to base64
        }
        
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt_data(string $encrypted_data): string {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_data); // Fallback from base64
        }
        
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $key = $this->get_encryption_key();
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Get encryption key
     */
    private function get_encryption_key(): string {
        $key = get_option('carbon_marketplace_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, false);
            update_option('carbon_marketplace_encryption_key', $key);
        }
        
        return $key;
    }
    
    /**
     * Log security events
     */
    public function log_security_event(string $event_type, array $data = []): void {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'data' => $data
        ];
        
        error_log('Carbon Marketplace Security Event: ' . json_encode($log_entry));
        
        // Store in database for audit trail
        global $wpdb;
        $table_name = $wpdb->prefix . 'carbon_marketplace_security_log';
        
        $wpdb->insert(
            $table_name,
            [
                'event_type' => $event_type,
                'user_id' => get_current_user_id() ?: null,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'event_data' => json_encode($data),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip(): string {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Create security tables
     */
    public function create_security_tables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Security log table
        $security_log_table = $wpdb->prefix . 'carbon_marketplace_security_log';
        $sql = "CREATE TABLE $security_log_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            user_id bigint(20) UNSIGNED NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            event_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY ip_address (ip_address),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}