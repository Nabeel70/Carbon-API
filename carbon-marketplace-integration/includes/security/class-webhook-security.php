<?php
/**
 * Webhook Security Manager for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Security;

use WP_Error;

/**
 * Manages webhook security including signature verification and replay attack prevention
 */
class WebhookSecurity {
    
    private const REPLAY_WINDOW = 300; // 5 minutes
    
    /**
     * Verify webhook signature
     */
    public function verify_signature(string $payload, string $signature, string $secret, string $algorithm = 'sha256'): bool {
        if (empty($signature) || empty($secret)) {
            return false;
        }
        
        // Remove algorithm prefix if present (e.g., "sha256=")
        if (strpos($signature, '=') !== false) {
            list($algo, $signature) = explode('=', $signature, 2);
            $algorithm = $algo;
        }
        
        $expected_signature = hash_hmac($algorithm, $payload, $secret);
        
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Verify CNaught webhook signature
     */
    public function verify_cnaught_signature(string $payload, string $signature): bool {
        $secret = $this->get_webhook_secret('cnaught');
        
        if (empty($secret)) {
            // Log warning but allow in development
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CNaught webhook secret not configured');
            }
            return true;
        }
        
        return $this->verify_signature($payload, $signature, $secret);
    }
    
    /**
     * Verify Toucan webhook signature
     */
    public function verify_toucan_signature(string $payload, string $signature): bool {
        $secret = $this->get_webhook_secret('toucan');
        
        if (empty($secret)) {
            // Log warning but allow in development
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Toucan webhook secret not configured');
            }
            return true;
        }
        
        return $this->verify_signature($payload, $signature, $secret);
    }
    
    /**
     * Prevent replay attacks using timestamp validation
     */
    public function prevent_replay_attack(string $timestamp, string $webhook_id = ''): bool {
        $current_time = time();
        $webhook_time = (int) $timestamp;
        
        // Check if timestamp is within acceptable window
        if (abs($current_time - $webhook_time) > self::REPLAY_WINDOW) {
            $this->log_security_event('webhook_replay_attempt', [
                'timestamp' => $timestamp,
                'webhook_id' => $webhook_id,
                'time_diff' => abs($current_time - $webhook_time)
            ]);
            return false;
        }
        
        // Check if we've already processed this webhook
        if (!empty($webhook_id) && $this->is_webhook_processed($webhook_id)) {
            $this->log_security_event('webhook_duplicate_attempt', [
                'webhook_id' => $webhook_id,
                'timestamp' => $timestamp
            ]);
            return false;
        }
        
        // Mark webhook as processed
        if (!empty($webhook_id)) {
            $this->mark_webhook_processed($webhook_id);
        }
        
        return true;
    }
    
    /**
     * Validate webhook payload structure
     */
    public function validate_webhook_payload(array $payload, string $vendor): bool|WP_Error {
        switch ($vendor) {
            case 'cnaught':
                return $this->validate_cnaught_payload($payload);
            case 'toucan':
                return $this->validate_toucan_payload($payload);
            default:
                return new WP_Error('invalid_vendor', 'Unknown webhook vendor');
        }
    }
    
    /**
     * Validate CNaught webhook payload
     */
    private function validate_cnaught_payload(array $payload): bool|WP_Error {
        // Required fields for CNaught webhooks
        $required_fields = ['event_type', 'data'];
        
        foreach ($required_fields as $field) {
            if (!isset($payload[$field])) {
                return new WP_Error('missing_field', "Missing required field: {$field}");
            }
        }
        
        // Validate event type
        $valid_events = [
            'checkout.session.completed',
            'order.fulfilled',
            'order.retired',
            'order.cancelled'
        ];
        
        if (!in_array($payload['event_type'], $valid_events)) {
            return new WP_Error('invalid_event_type', 'Invalid event type');
        }
        
        // Validate data structure based on event type
        switch ($payload['event_type']) {
            case 'checkout.session.completed':
                if (empty($payload['data']['session_id'])) {
                    return new WP_Error('missing_session_id', 'Session ID is required');
                }
                break;
                
            case 'order.fulfilled':
            case 'order.retired':
                if (empty($payload['data']['order_id'])) {
                    return new WP_Error('missing_order_id', 'Order ID is required');
                }
                break;
        }
        
        return true;
    }
    
    /**
     * Validate Toucan webhook payload
     */
    private function validate_toucan_payload(array $payload): bool|WP_Error {
        // Required fields for Toucan webhooks
        $required_fields = ['type', 'data'];
        
        foreach ($required_fields as $field) {
            if (!isset($payload[$field])) {
                return new WP_Error('missing_field', "Missing required field: {$field}");
            }
        }
        
        // Validate event type
        $valid_events = [
            'retirement.completed',
            'token.transferred',
            'pool.updated'
        ];
        
        if (!in_array($payload['type'], $valid_events)) {
            return new WP_Error('invalid_event_type', 'Invalid event type');
        }
        
        return true;
    }
    
    /**
     * Rate limit webhook requests
     */
    public function check_webhook_rate_limit(string $vendor, string $ip_address): bool {
        $key = "webhook_rate_limit_{$vendor}_{$ip_address}";
        $current_count = get_transient($key) ?: 0;
        
        // Allow 100 webhooks per hour per vendor per IP
        $limit = 100;
        $window = 3600;
        
        if ($current_count >= $limit) {
            $this->log_security_event('webhook_rate_limit_exceeded', [
                'vendor' => $vendor,
                'ip_address' => $ip_address,
                'count' => $current_count
            ]);
            return false;
        }
        
        set_transient($key, $current_count + 1, $window);
        return true;
    }
    
    /**
     * Sanitize webhook payload
     */
    public function sanitize_webhook_payload(array $payload): array {
        $sanitized = [];
        
        foreach ($payload as $key => $value) {
            $clean_key = sanitize_key($key);
            
            if (is_string($value)) {
                $sanitized[$clean_key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$clean_key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$clean_key] = $this->sanitize_webhook_payload($value);
            } elseif (is_bool($value)) {
                $sanitized[$clean_key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get webhook secret for vendor
     */
    private function get_webhook_secret(string $vendor): string {
        $secrets = get_option('carbon_marketplace_webhook_secrets', []);
        return $secrets[$vendor] ?? '';
    }
    
    /**
     * Set webhook secret for vendor
     */
    public function set_webhook_secret(string $vendor, string $secret): bool {
        $secrets = get_option('carbon_marketplace_webhook_secrets', []);
        $secrets[$vendor] = $secret;
        
        return update_option('carbon_marketplace_webhook_secrets', $secrets);
    }
    
    /**
     * Check if webhook has been processed
     */
    private function is_webhook_processed(string $webhook_id): bool {
        $processed_key = 'webhook_processed_' . md5($webhook_id);
        return get_transient($processed_key) !== false;
    }
    
    /**
     * Mark webhook as processed
     */
    private function mark_webhook_processed(string $webhook_id): void {
        $processed_key = 'webhook_processed_' . md5($webhook_id);
        // Store for replay window duration
        set_transient($processed_key, time(), self::REPLAY_WINDOW);
    }
    
    /**
     * Validate webhook source IP
     */
    public function validate_webhook_source(string $ip_address, string $vendor): bool {
        $allowed_ips = $this->get_allowed_webhook_ips($vendor);
        
        if (empty($allowed_ips)) {
            return true; // No IP restrictions configured
        }
        
        foreach ($allowed_ips as $allowed_ip) {
            if ($this->ip_in_range($ip_address, $allowed_ip)) {
                return true;
            }
        }
        
        $this->log_security_event('webhook_invalid_source_ip', [
            'vendor' => $vendor,
            'ip_address' => $ip_address,
            'allowed_ips' => $allowed_ips
        ]);
        
        return false;
    }
    
    /**
     * Get allowed webhook IPs for vendor
     */
    private function get_allowed_webhook_ips(string $vendor): array {
        $ip_config = get_option('carbon_marketplace_webhook_ips', []);
        return $ip_config[$vendor] ?? [];
    }
    
    /**
     * Check if IP is in range (supports CIDR notation)
     */
    private function ip_in_range(string $ip, string $range): bool {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $mask) = explode('/', $range);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->ipv4_in_range($ip, $subnet, $mask);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6_in_range($ip, $subnet, $mask);
        }
        
        return false;
    }
    
    /**
     * Check if IPv4 is in range
     */
    private function ipv4_in_range(string $ip, string $subnet, int $mask): bool {
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - $mask);
        
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }
    
    /**
     * Check if IPv6 is in range
     */
    private function ipv6_in_range(string $ip, string $subnet, int $mask): bool {
        $ip_bin = inet_pton($ip);
        $subnet_bin = inet_pton($subnet);
        
        if ($ip_bin === false || $subnet_bin === false) {
            return false;
        }
        
        $bytes = intval($mask / 8);
        $bits = $mask % 8;
        
        // Compare full bytes
        if ($bytes > 0 && substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes)) {
            return false;
        }
        
        // Compare remaining bits
        if ($bits > 0) {
            $ip_byte = ord($ip_bin[$bytes]);
            $subnet_byte = ord($subnet_bin[$bytes]);
            $mask_byte = 0xFF << (8 - $bits);
            
            return ($ip_byte & $mask_byte) === ($subnet_byte & $mask_byte);
        }
        
        return true;
    }
    
    /**
     * Log security events
     */
    private function log_security_event(string $event_type, array $data): void {
        error_log("Webhook Security Event [{$event_type}]: " . json_encode($data));
        
        // Store in database for audit
        global $wpdb;
        $table_name = $wpdb->prefix . 'carbon_marketplace_security_log';
        
        $wpdb->insert(
            $table_name,
            [
                'event_type' => $event_type,
                'user_id' => null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'event_data' => json_encode($data),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Generate webhook signature for outgoing webhooks
     */
    public function generate_signature(string $payload, string $secret, string $algorithm = 'sha256'): string {
        return hash_hmac($algorithm, $payload, $secret);
    }
    
    /**
     * Create webhook security configuration
     */
    public function create_webhook_config(string $vendor, array $config): bool {
        $webhook_config = get_option('carbon_marketplace_webhook_config', []);
        
        $webhook_config[$vendor] = array_merge([
            'secret' => '',
            'allowed_ips' => [],
            'rate_limit' => 100,
            'rate_window' => 3600,
            'signature_algorithm' => 'sha256',
            'replay_window' => self::REPLAY_WINDOW
        ], $config);
        
        return update_option('carbon_marketplace_webhook_config', $webhook_config);
    }
    
    /**
     * Get webhook security statistics
     */
    public function get_security_statistics(): array {
        global $wpdb;
        
        $security_table = $wpdb->prefix . 'carbon_marketplace_security_log';
        
        $stats = [];
        
        // Total security events
        $stats['total_events'] = $wpdb->get_var("SELECT COUNT(*) FROM {$security_table}");
        
        // Events by type
        $event_types = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count FROM {$security_table} GROUP BY event_type ORDER BY count DESC"
        );
        
        $stats['events_by_type'] = [];
        foreach ($event_types as $event) {
            $stats['events_by_type'][$event->event_type] = $event->count;
        }
        
        // Recent events (last 24 hours)
        $stats['recent_events'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$security_table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // Top IP addresses
        $top_ips = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as count FROM {$security_table} GROUP BY ip_address ORDER BY count DESC LIMIT 10"
        );
        
        $stats['top_ips'] = [];
        foreach ($top_ips as $ip) {
            $stats['top_ips'][$ip->ip_address] = $ip->count;
        }
        
        return $stats;
    }
}