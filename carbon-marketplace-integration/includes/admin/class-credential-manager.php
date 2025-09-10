<?php
/**
 * Credential Manager for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace\Admin
 */

namespace CarbonMarketplace\Admin;

/**
 * Credential Manager class
 */
class CredentialManager {
    
    /**
     * Encryption key option name
     */
    const ENCRYPTION_KEY_OPTION = 'carbon_marketplace_encryption_key';
    
    /**
     * Initialize credential manager
     */
    public function init() {
        add_action('admin_init', array($this, 'maybe_generate_encryption_key'));
        add_filter('pre_update_option_carbon_marketplace_cnaught_api_key', array($this, 'encrypt_credential'), 10, 2);
        add_filter('option_carbon_marketplace_cnaught_api_key', array($this, 'decrypt_credential'));
        add_filter('pre_update_option_carbon_marketplace_toucan_api_key', array($this, 'encrypt_credential'), 10, 2);
        add_filter('option_carbon_marketplace_toucan_api_key', array($this, 'decrypt_credential'));
    }
    
    /**
     * Generate encryption key if it doesn't exist
     */
    public function maybe_generate_encryption_key() {
        if (!get_option(self::ENCRYPTION_KEY_OPTION)) {
            $key = $this->generate_encryption_key();
            update_option(self::ENCRYPTION_KEY_OPTION, $key);
        }
    }
    
    /**
     * Generate a secure encryption key
     *
     * @return string
     */
    private function generate_encryption_key() {
        if (function_exists('random_bytes')) {
            return base64_encode(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return base64_encode(openssl_random_pseudo_bytes(32));
        } else {
            // Fallback for older PHP versions
            return base64_encode(hash('sha256', uniqid(mt_rand(), true), true));
        }
    }
    
    /**
     * Encrypt credential before storing
     *
     * @param string $value New value
     * @param string $old_value Old value
     * @return string
     */
    public function encrypt_credential($value, $old_value = '') {
        if (empty($value)) {
            return $value;
        }
        
        // Don't re-encrypt already encrypted values
        if ($this->is_encrypted($value)) {
            return $value;
        }
        
        return $this->encrypt($value);
    }
    
    /**
     * Decrypt credential when retrieving
     *
     * @param string $value Encrypted value
     * @return string
     */
    public function decrypt_credential($value) {
        if (empty($value) || !$this->is_encrypted($value)) {
            return $value;
        }
        
        return $this->decrypt($value);
    }
    
    /**
     * Encrypt a string
     *
     * @param string $data Data to encrypt
     * @return string
     */
    private function encrypt($data) {
        $key = get_option(self::ENCRYPTION_KEY_OPTION);
        if (!$key) {
            return $data; // Return unencrypted if no key
        }
        
        $key = base64_decode($key);
        
        if (function_exists('openssl_encrypt')) {
            $iv = openssl_random_pseudo_bytes(16);
            $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
            return base64_encode($iv . $encrypted);
        } else {
            // Fallback to base64 encoding (not secure, but better than nothing)
            return base64_encode($data);
        }
    }
    
    /**
     * Decrypt a string
     *
     * @param string $data Encrypted data
     * @return string
     */
    private function decrypt($data) {
        $key = get_option(self::ENCRYPTION_KEY_OPTION);
        if (!$key) {
            return $data; // Return as-is if no key
        }
        
        $key = base64_decode($key);
        $data = base64_decode($data);
        
        if (function_exists('openssl_decrypt') && strlen($data) > 16) {
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            return $decrypted !== false ? $decrypted : '';
        } else {
            // Fallback from base64 encoding
            return $data;
        }
    }
    
    /**
     * Check if a value is encrypted
     *
     * @param string $value Value to check
     * @return bool
     */
    private function is_encrypted($value) {
        // Simple heuristic: encrypted values are base64 encoded and longer
        return strlen($value) > 20 && base64_encode(base64_decode($value, true)) === $value;
    }
    
    /**
     * Validate API credentials
     *
     * @param string $vendor Vendor name
     * @param array $credentials Credentials to validate
     * @return bool|WP_Error
     */
    public function validate_credentials($vendor, $credentials) {
        switch ($vendor) {
            case 'cnaught':
                return $this->validate_cnaught_credentials($credentials);
            case 'toucan':
                return $this->validate_toucan_credentials($credentials);
            default:
                return new \WP_Error('invalid_vendor', __('Invalid vendor specified', 'carbon-marketplace'));
        }
    }
    
    /**
     * Validate CNaught credentials
     *
     * @param array $credentials Credentials array
     * @return bool|WP_Error
     */
    private function validate_cnaught_credentials($credentials) {
        $api_key = $credentials['api_key'] ?? '';
        $sandbox_mode = $credentials['sandbox_mode'] ?? false;
        
        if (empty($api_key)) {
            return new \WP_Error('missing_api_key', __('CNaught API key is required', 'carbon-marketplace'));
        }
        
        // Basic format validation
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $api_key)) {
            return new \WP_Error('invalid_api_key_format', __('CNaught API key format is invalid', 'carbon-marketplace'));
        }
        
        // Test API connection
        return $this->test_cnaught_connection($api_key, $sandbox_mode);
    }
    
    /**
     * Validate Toucan credentials
     *
     * @param array $credentials Credentials array
     * @return bool|WP_Error
     */
    private function validate_toucan_credentials($credentials) {
        $api_key = $credentials['api_key'] ?? '';
        $network = $credentials['network'] ?? 'polygon';
        
        // API key is optional for Toucan
        if (!empty($api_key)) {
            // Basic format validation for The Graph API key
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $api_key)) {
                return new \WP_Error('invalid_api_key_format', __('The Graph API key format is invalid', 'carbon-marketplace'));
            }
        }
        
        // Validate network
        $valid_networks = ['polygon', 'mumbai'];
        if (!in_array($network, $valid_networks)) {
            return new \WP_Error('invalid_network', __('Invalid network specified', 'carbon-marketplace'));
        }
        
        // Test API connection
        return $this->test_toucan_connection($api_key, $network);
    }
    
    /**
     * Test CNaught API connection
     *
     * @param string $api_key API key
     * @param bool $sandbox_mode Sandbox mode
     * @return bool|WP_Error
     */
    private function test_cnaught_connection($api_key, $sandbox_mode = false) {
        $base_url = $sandbox_mode ? 'https://api.sandbox.cnaught.com' : 'https://api.cnaught.com';
        $url = $base_url . '/portfolios';
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return new \WP_Error('connection_failed', __('Failed to connect to CNaught API', 'carbon-marketplace'));
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 401) {
            return new \WP_Error('invalid_credentials', __('Invalid CNaught API credentials', 'carbon-marketplace'));
        } elseif ($status_code === 403) {
            return new \WP_Error('access_denied', __('Access denied to CNaught API', 'carbon-marketplace'));
        } elseif ($status_code !== 200) {
            return new \WP_Error('api_error', sprintf(__('CNaught API returned status code: %d', 'carbon-marketplace'), $status_code));
        }
        
        return true;
    }
    
    /**
     * Test Toucan API connection
     *
     * @param string $api_key API key (optional)
     * @param string $network Network
     * @return bool|WP_Error
     */
    private function test_toucan_connection($api_key = '', $network = 'polygon') {
        // Determine subgraph URL based on network
        $subgraph_urls = array(
            'polygon' => 'https://gateway-arbitrum.network.thegraph.com/api/[api-key]/subgraphs/id/FU5APMSSCqcRy9jy56aXJiGV3PQmFQHg2tzukvSJBgwW',
            'mumbai' => 'https://gateway-arbitrum.network.thegraph.com/api/[api-key]/subgraphs/id/FKzFZuYHxyHiiDmdW9Qvwtet1Ad1ERsvjWMhhqd9V8pk',
        );
        
        $url = $subgraph_urls[$network] ?? $subgraph_urls['polygon'];
        
        if (!empty($api_key)) {
            $url = str_replace('[api-key]', $api_key, $url);
        } else {
            // Use public endpoint
            $url = str_replace('https://gateway-arbitrum.network.thegraph.com/api/[api-key]/', 'https://api.thegraph.com/', $url);
        }
        
        // Simple GraphQL query to test connection
        $query = '{ _meta { block { number } } }';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array('query' => $query)),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return new \WP_Error('connection_failed', __('Failed to connect to Toucan subgraph', 'carbon-marketplace'));
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 401) {
            return new \WP_Error('invalid_credentials', __('Invalid The Graph API key', 'carbon-marketplace'));
        } elseif ($status_code === 403) {
            return new \WP_Error('access_denied', __('Access denied to Toucan subgraph', 'carbon-marketplace'));
        } elseif ($status_code !== 200) {
            return new \WP_Error('api_error', sprintf(__('Toucan subgraph returned status code: %d', 'carbon-marketplace'), $status_code));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['errors'])) {
            return new \WP_Error('graphql_error', __('GraphQL query failed', 'carbon-marketplace'));
        }
        
        return true;
    }
    
    /**
     * Get decrypted credentials for a vendor
     *
     * @param string $vendor Vendor name
     * @return array
     */
    public function get_credentials($vendor) {
        switch ($vendor) {
            case 'cnaught':
                return array(
                    'api_key' => get_option('carbon_marketplace_cnaught_api_key', ''),
                    'sandbox_mode' => get_option('carbon_marketplace_cnaught_sandbox_mode', false),
                    'enabled' => get_option('carbon_marketplace_cnaught_enabled', false),
                );
                
            case 'toucan':
                return array(
                    'api_key' => get_option('carbon_marketplace_toucan_api_key', ''),
                    'network' => get_option('carbon_marketplace_toucan_network', 'polygon'),
                    'enabled' => get_option('carbon_marketplace_toucan_enabled', false),
                );
                
            default:
                return array();
        }
    }
    
    /**
     * Update credentials for a vendor
     *
     * @param string $vendor Vendor name
     * @param array $credentials Credentials array
     * @return bool|WP_Error
     */
    public function update_credentials($vendor, $credentials) {
        // Validate credentials first
        $validation_result = $this->validate_credentials($vendor, $credentials);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        switch ($vendor) {
            case 'cnaught':
                update_option('carbon_marketplace_cnaught_api_key', $credentials['api_key'] ?? '');
                update_option('carbon_marketplace_cnaught_sandbox_mode', $credentials['sandbox_mode'] ?? false);
                update_option('carbon_marketplace_cnaught_enabled', $credentials['enabled'] ?? false);
                break;
                
            case 'toucan':
                update_option('carbon_marketplace_toucan_api_key', $credentials['api_key'] ?? '');
                update_option('carbon_marketplace_toucan_network', $credentials['network'] ?? 'polygon');
                update_option('carbon_marketplace_toucan_enabled', $credentials['enabled'] ?? false);
                break;
                
            default:
                return new \WP_Error('invalid_vendor', __('Invalid vendor specified', 'carbon-marketplace'));
        }
        
        return true;
    }
    
    /**
     * Delete credentials for a vendor
     *
     * @param string $vendor Vendor name
     * @return bool
     */
    public function delete_credentials($vendor) {
        switch ($vendor) {
            case 'cnaught':
                delete_option('carbon_marketplace_cnaught_api_key');
                delete_option('carbon_marketplace_cnaught_sandbox_mode');
                delete_option('carbon_marketplace_cnaught_enabled');
                break;
                
            case 'toucan':
                delete_option('carbon_marketplace_toucan_api_key');
                delete_option('carbon_marketplace_toucan_network');
                delete_option('carbon_marketplace_toucan_enabled');
                break;
                
            default:
                return false;
        }
        
        return true;
    }
    
    /**
     * Get list of configured vendors
     *
     * @return array
     */
    public function get_configured_vendors() {
        $vendors = array();
        
        // Check CNaught
        $cnaught_enabled = get_option('carbon_marketplace_cnaught_enabled', false);
        $cnaught_api_key = get_option('carbon_marketplace_cnaught_api_key', '');
        if ($cnaught_enabled && !empty($cnaught_api_key)) {
            $vendors[] = 'cnaught';
        }
        
        // Check Toucan
        $toucan_enabled = get_option('carbon_marketplace_toucan_enabled', false);
        if ($toucan_enabled) {
            $vendors[] = 'toucan';
        }
        
        return $vendors;
    }
    
    /**
     * Check if any vendors are configured
     *
     * @return bool
     */
    public function has_configured_vendors() {
        return !empty($this->get_configured_vendors());
    }
    
    /**
     * Get vendor status information
     *
     * @param string $vendor Vendor name
     * @return array
     */
    public function get_vendor_status($vendor) {
        $credentials = $this->get_credentials($vendor);
        $enabled = $credentials['enabled'] ?? false;
        
        $status = array(
            'enabled' => $enabled,
            'configured' => false,
            'status' => 'disabled',
            'message' => __('Disabled', 'carbon-marketplace'),
        );
        
        if (!$enabled) {
            return $status;
        }
        
        switch ($vendor) {
            case 'cnaught':
                $api_key = $credentials['api_key'] ?? '';
                if (!empty($api_key)) {
                    $status['configured'] = true;
                    $status['status'] = 'enabled';
                    $status['message'] = __('Enabled and configured', 'carbon-marketplace');
                } else {
                    $status['status'] = 'error';
                    $status['message'] = __('Enabled but not configured', 'carbon-marketplace');
                }
                break;
                
            case 'toucan':
                $status['configured'] = true;
                $status['status'] = 'enabled';
                $status['message'] = __('Enabled and configured', 'carbon-marketplace');
                break;
        }
        
        return $status;
    }
    
    /**
     * Export credentials (for backup purposes)
     *
     * @return array
     */
    public function export_credentials() {
        return array(
            'cnaught' => $this->get_credentials('cnaught'),
            'toucan' => $this->get_credentials('toucan'),
            'encryption_key' => get_option(self::ENCRYPTION_KEY_OPTION),
        );
    }
    
    /**
     * Import credentials (for restore purposes)
     *
     * @param array $data Credentials data
     * @return bool|WP_Error
     */
    public function import_credentials($data) {
        if (!is_array($data)) {
            return new \WP_Error('invalid_data', __('Invalid credentials data', 'carbon-marketplace'));
        }
        
        // Import encryption key first
        if (isset($data['encryption_key'])) {
            update_option(self::ENCRYPTION_KEY_OPTION, $data['encryption_key']);
        }
        
        // Import vendor credentials
        foreach (['cnaught', 'toucan'] as $vendor) {
            if (isset($data[$vendor])) {
                $result = $this->update_credentials($vendor, $data[$vendor]);
                if (is_wp_error($result)) {
                    return $result;
                }
            }
        }
        
        return true;
    }
}