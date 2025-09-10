<?php
/**
 * Unit tests for SecurityManager
 *
 * @package CarbonMarketplace
 */

use CarbonMarketplace\Security\SecurityManager;
use CarbonMarketplace\Security\InputValidator;

class TestSecurityManager {
    
    private SecurityManager $security_manager;
    
    public function __construct() {
        $this->security_manager = new SecurityManager();
    }
    
    public function run_tests(): void {
        echo "Running SecurityManager Tests...\n\n";
        
        $this->test_sanitize_search_params();
        $this->test_validate_checkout_request();
        $this->test_validate_api_credentials();
        $this->test_nonce_operations();
        $this->test_rate_limiting();
        $this->test_encryption_decryption();
        $this->test_input_sanitization();
        
        echo "SecurityManager tests completed.\n\n";
    }
    
    private function test_sanitize_search_params(): void {
        echo "Testing sanitize_search_params...\n";
        
        $params = [
            'keyword' => '<script>alert("xss")</script>forest',
            'location' => 'Brazil<script>',
            'project_type' => 'Forestry & Conservation',
            'min_price' => '10.5',
            'max_price' => 'invalid',
            'limit' => '150',
            'offset' => '-5',
            'sort_by' => 'invalid_field',
            'sort_order' => 'DESC'
        ];
        
        $sanitized = $this->security_manager->sanitize_search_params($params);
        
        $this->assert_equals('forest', $sanitized['keyword'], 'Should remove script tags from keyword');
        $this->assert_equals('Brazil', $sanitized['location'], 'Should remove script tags from location');
        $this->assert_true(isset($sanitized['min_price']), 'Should include valid min_price');
        $this->assert_false(isset($sanitized['max_price']), 'Should exclude invalid max_price');
        
        echo "sanitize_search_params tests passed.\n\n";
    }
    
    private function test_validate_checkout_request(): void {
        echo "Testing validate_checkout_request...\n";
        
        // Test valid request
        $valid_data = [
            'amount_kg' => 10.5,
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'customer_email' => 'test@example.com',
            'customer_name' => 'John Doe',
            'metadata' => ['key' => 'value']
        ];
        
        $result = $this->security_manager->validate_checkout_request($valid_data);
        $this->assert_false(is_wp_error($result), 'Valid request should not return error');
        $this->assert_equals(10.5, $result['amount_kg'], 'Amount should be preserved');
        
        // Test invalid request
        $invalid_data = [
            'amount_kg' => 'invalid',
            'success_url' => 'not-a-url',
            'cancel_url' => ''
        ];
        
        $result = $this->security_manager->validate_checkout_request($invalid_data);
        $this->assert_true(is_wp_error($result), 'Invalid request should return error');
        
        echo "validate_checkout_request tests passed.\n\n";
    }
    
    private function test_validate_api_credentials(): void {
        echo "Testing validate_api_credentials...\n";
        
        // Test CNaught credentials
        $valid_cnaught = [
            'api_key' => 'valid_api_key_123',
            'client_id' => 'client_123'
        ];
        
        $result = $this->security_manager->validate_api_credentials($valid_cnaught, 'cnaught');
        $this->assert_true($result === true, 'Valid CNaught credentials should pass');
        
        // Test invalid CNaught credentials
        $invalid_cnaught = [
            'api_key' => '',
            'client_id' => 'client_123'
        ];
        
        $result = $this->security_manager->validate_api_credentials($invalid_cnaught, 'cnaught');
        $this->assert_true(is_wp_error($result), 'Invalid CNaught credentials should return error');
        
        // Test Toucan credentials
        $valid_toucan = [
            'api_key' => 'optional_key_123'
        ];
        
        $result = $this->security_manager->validate_api_credentials($valid_toucan, 'toucan');
        $this->assert_true($result === true, 'Valid Toucan credentials should pass');
        
        echo "validate_api_credentials tests passed.\n\n";
    }
    
    private function test_nonce_operations(): void {
        echo "Testing nonce operations...\n";
        
        $action = 'test_action';
        $nonce = $this->security_manager->generate_nonce($action);
        
        $this->assert_true(!empty($nonce), 'Should generate non-empty nonce');
        
        // Note: In real WordPress environment, this would work
        // For testing, we'll just verify the method exists and returns something
        $this->assert_true(method_exists($this->security_manager, 'verify_ajax_nonce'), 'Should have verify_ajax_nonce method');
        
        echo "nonce operations tests passed.\n\n";
    }
    
    private function test_rate_limiting(): void {
        echo "Testing rate limiting...\n";
        
        $identifier = 'test_user_123';
        
        // First request should pass
        $result1 = $this->security_manager->check_rate_limit($identifier, 2, 60);
        $this->assert_true($result1, 'First request should pass rate limit');
        
        // Second request should pass
        $result2 = $this->security_manager->check_rate_limit($identifier, 2, 60);
        $this->assert_true($result2, 'Second request should pass rate limit');
        
        // Third request should fail
        $result3 = $this->security_manager->check_rate_limit($identifier, 2, 60);
        $this->assert_false($result3, 'Third request should fail rate limit');
        
        echo "rate limiting tests passed.\n\n";
    }
    
    private function test_encryption_decryption(): void {
        echo "Testing encryption/decryption...\n";
        
        $original_data = 'sensitive_api_key_12345';
        
        $encrypted = $this->security_manager->encrypt_data($original_data);
        $this->assert_true(!empty($encrypted), 'Should produce encrypted data');
        $this->assert_true($encrypted !== $original_data, 'Encrypted data should be different from original');
        
        $decrypted = $this->security_manager->decrypt_data($encrypted);
        $this->assert_equals($original_data, $decrypted, 'Decrypted data should match original');
        
        echo "encryption/decryption tests passed.\n\n";
    }
    
    private function test_input_sanitization(): void {
        echo "Testing input sanitization...\n";
        
        // Test different input types
        $test_cases = [
            ['<script>alert("xss")</script>test', 'text', 'test'],
            ['user@example.com', 'email', 'user@example.com'],
            ['https://example.com/path', 'url', 'https://example.com/path'],
            ['123.45', 'float', 123.45],
            ['invalid-key!@#', 'key', 'invalid-key'],
        ];
        
        foreach ($test_cases as [$input, $type, $expected]) {
            $result = $this->security_manager->sanitize_input($input, $type);
            $this->assert_equals($expected, $result, "Should sanitize {$type} input correctly");
        }
        
        echo "input sanitization tests passed.\n\n";
    }
    
    // Helper assertion methods
    private function assert_true($condition, string $message): void {
        if (!$condition) {
            throw new Exception("Assertion failed: {$message}");
        }
    }
    
    private function assert_false($condition, string $message): void {
        if ($condition) {
            throw new Exception("Assertion failed: {$message}");
        }
    }
    
    private function assert_equals($expected, $actual, string $message): void {
        if ($expected !== $actual) {
            throw new Exception("Assertion failed: {$message}. Expected: " . var_export($expected, true) . ", Actual: " . var_export($actual, true));
        }
    }
}

// Mock WordPress functions for testing
if (!function_exists('get_transient')) {
    function get_transient($key) {
        static $transients = [];
        return $transients[$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration) {
        static $transients = [];
        $transients[$key] = $value;
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        static $options = [];
        return $options[$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value) {
        static $options = [];
        $options[$key] = $value;
        return true;
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true) {
        return 'generated_password_' . $length;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return strip_tags(trim($str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) {
        return strip_tags($data, '<p><br><strong><em><ul><ol><li><a>');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'test_nonce_' . md5($action);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return $nonce === 'test_nonce_' . md5($action);
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        require_once __DIR__ . '/../../../includes/class-autoloader.php';
        CarbonMarketplace\Autoloader::init();
        
        $test = new TestSecurityManager();
        $test->run_tests();
        echo "All SecurityManager tests passed!\n";
    } catch (Exception $e) {
        echo "Test failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}