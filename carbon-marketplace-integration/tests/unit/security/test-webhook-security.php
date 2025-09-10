<?php
/**
 * Unit tests for WebhookSecurity
 *
 * @package CarbonMarketplace
 */

use CarbonMarketplace\Security\WebhookSecurity;

class TestWebhookSecurity {
    
    private WebhookSecurity $webhook_security;
    
    public function __construct() {
        $this->webhook_security = new WebhookSecurity();
    }
    
    public function run_tests(): void {
        echo "Running WebhookSecurity Tests...\n\n";
        
        $this->test_signature_verification();
        $this->test_replay_attack_prevention();
        $this->test_payload_validation();
        $this->test_rate_limiting();
        $this->test_payload_sanitization();
        $this->test_ip_validation();
        $this->test_signature_generation();
        
        echo "WebhookSecurity tests completed.\n\n";
    }
    
    private function test_signature_verification(): void {
        echo "Testing signature verification...\n";
        
        $payload = '{"event_type":"test","data":{"id":"123"}}';
        $secret = 'test_secret_key';
        
        // Generate valid signature
        $valid_signature = hash_hmac('sha256', $payload, $secret);
        
        // Test valid signature
        $result = $this->webhook_security->verify_signature($payload, $valid_signature, $secret);
        $this->assert_true($result, 'Valid signature should pass verification');
        
        // Test invalid signature
        $invalid_signature = 'invalid_signature';
        $result = $this->webhook_security->verify_signature($payload, $invalid_signature, $secret);
        $this->assert_false($result, 'Invalid signature should fail verification');
        
        // Test signature with algorithm prefix
        $prefixed_signature = 'sha256=' . $valid_signature;
        $result = $this->webhook_security->verify_signature($payload, $prefixed_signature, $secret);
        $this->assert_true($result, 'Signature with algorithm prefix should pass verification');
        
        echo "signature verification tests passed.\n\n";
    }
    
    private function test_replay_attack_prevention(): void {
        echo "Testing replay attack prevention...\n";
        
        $current_time = time();
        
        // Test valid timestamp (within window)
        $valid_timestamp = (string) $current_time;
        $result = $this->webhook_security->prevent_replay_attack($valid_timestamp, 'webhook_123');
        $this->assert_true($result, 'Valid timestamp should pass replay check');
        
        // Test duplicate webhook ID
        $result = $this->webhook_security->prevent_replay_attack($valid_timestamp, 'webhook_123');
        $this->assert_false($result, 'Duplicate webhook ID should fail replay check');
        
        // Test old timestamp (outside window)
        $old_timestamp = (string) ($current_time - 400); // 400 seconds ago
        $result = $this->webhook_security->prevent_replay_attack($old_timestamp, 'webhook_456');
        $this->assert_false($result, 'Old timestamp should fail replay check');
        
        echo "replay attack prevention tests passed.\n\n";
    }
    
    private function test_payload_validation(): void {
        echo "Testing payload validation...\n";
        
        // Test valid CNaught payload
        $valid_cnaught_payload = [
            'event_type' => 'checkout.session.completed',
            'data' => [
                'session_id' => 'sess_123',
                'order_id' => 'order_456'
            ]
        ];
        
        $result = $this->webhook_security->validate_webhook_payload($valid_cnaught_payload, 'cnaught');
        $this->assert_true($result === true, 'Valid CNaught payload should pass validation');
        
        // Test invalid CNaught payload (missing event_type)
        $invalid_cnaught_payload = [
            'data' => [
                'session_id' => 'sess_123'
            ]
        ];
        
        $result = $this->webhook_security->validate_webhook_payload($invalid_cnaught_payload, 'cnaught');
        $this->assert_true(is_wp_error($result), 'Invalid CNaught payload should return error');
        
        // Test valid Toucan payload
        $valid_toucan_payload = [
            'type' => 'retirement.completed',
            'data' => [
                'transaction_hash' => '0x123...',
                'amount' => '1000000000000000000'
            ]
        ];
        
        $result = $this->webhook_security->validate_webhook_payload($valid_toucan_payload, 'toucan');
        $this->assert_true($result === true, 'Valid Toucan payload should pass validation');
        
        echo "payload validation tests passed.\n\n";
    }
    
    private function test_rate_limiting(): void {
        echo "Testing rate limiting...\n";
        
        $vendor = 'test_vendor';
        $ip_address = '192.168.1.1';
        
        // Mock the rate limiting by setting a low limit
        $reflection = new ReflectionClass($this->webhook_security);
        $method = $reflection->getMethod('check_webhook_rate_limit');
        $method->setAccessible(true);
        
        // First request should pass
        $result1 = $method->invoke($this->webhook_security, $vendor, $ip_address);
        $this->assert_true($result1, 'First request should pass rate limit');
        
        echo "rate limiting tests passed.\n\n";
    }
    
    private function test_payload_sanitization(): void {
        echo "Testing payload sanitization...\n";
        
        $payload = [
            'event_type' => '<script>alert("xss")</script>test',
            'data' => [
                'user_input' => '<img src=x onerror=alert(1)>',
                'amount' => 123.45,
                'valid_field' => 'clean_value',
                'nested' => [
                    'field' => '<script>nested_xss</script>clean'
                ]
            ]
        ];
        
        $sanitized = $this->webhook_security->sanitize_webhook_payload($payload);
        
        $this->assert_true(strpos($sanitized['event_type'], '<script>') === false, 'Should remove script tags from event_type');
        $this->assert_true(strpos($sanitized['data']['user_input'], '<img') === false, 'Should remove img tags from user_input');
        $this->assert_equals(123.45, $sanitized['data']['amount'], 'Should preserve numeric values');
        $this->assert_equals('clean_value', $sanitized['data']['valid_field'], 'Should preserve clean values');
        
        echo "payload sanitization tests passed.\n\n";
    }
    
    private function test_ip_validation(): void {
        echo "Testing IP validation...\n";
        
        // Test with no IP restrictions (should always pass)
        $result = $this->webhook_security->validate_webhook_source('192.168.1.1', 'test_vendor');
        $this->assert_true($result, 'Should pass when no IP restrictions configured');
        
        echo "IP validation tests passed.\n\n";
    }
    
    private function test_signature_generation(): void {
        echo "Testing signature generation...\n";
        
        $payload = '{"test":"data"}';
        $secret = 'test_secret';
        
        $signature = $this->webhook_security->generate_signature($payload, $secret);
        $expected = hash_hmac('sha256', $payload, $secret);
        
        $this->assert_equals($expected, $signature, 'Generated signature should match expected HMAC');
        
        echo "signature generation tests passed.\n\n";
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

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return strip_tags(trim($str));
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        require_once __DIR__ . '/../../../includes/class-autoloader.php';
        CarbonMarketplace\Autoloader::init();
        
        $test = new TestWebhookSecurity();
        $test->run_tests();
        echo "All WebhookSecurity tests passed!\n";
    } catch (Exception $e) {
        echo "Test failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}