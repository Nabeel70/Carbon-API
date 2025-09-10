<?php
/**
 * Unit tests for Credential Manager
 *
 * @package CarbonMarketplace\Tests\Admin
 */

use PHPUnit\Framework\TestCase;
use CarbonMarketplace\Admin\CredentialManager;

class CredentialManagerTest extends TestCase {
    
    private $credential_manager;
    private $test_options = array();
    
    protected function setUp(): void {
        // Mock WordPress functions
        if (!function_exists('add_action')) {
            function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
        }
        if (!function_exists('add_filter')) {
            function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
        }
        if (!function_exists('get_option')) {
            function get_option($option, $default = false) {
                global $test_options;
                return isset($test_options[$option]) ? $test_options[$option] : $default;
            }
        }
        if (!function_exists('update_option')) {
            function update_option($option, $value) {
                global $test_options;
                $test_options[$option] = $value;
                return true;
            }
        }
        if (!function_exists('delete_option')) {
            function delete_option($option) {
                global $test_options;
                unset($test_options[$option]);
                return true;
            }
        }
        if (!function_exists('wp_remote_get')) {
            function wp_remote_get($url, $args = array()) {
                // Mock successful response
                return array(
                    'response' => array('code' => 200),
                    'body' => json_encode(array('data' => array()))
                );
            }
        }
        if (!function_exists('wp_remote_post')) {
            function wp_remote_post($url, $args = array()) {
                // Mock successful response
                return array(
                    'response' => array('code' => 200),
                    'body' => json_encode(array('data' => array('_meta' => array('block' => array('number' => 12345)))))
                );
            }
        }
        if (!function_exists('wp_remote_retrieve_response_code')) {
            function wp_remote_retrieve_response_code($response) {
                return $response['response']['code'] ?? 200;
            }
        }
        if (!function_exists('wp_remote_retrieve_body')) {
            function wp_remote_retrieve_body($response) {
                return $response['body'] ?? '';
            }
        }
        if (!function_exists('is_wp_error')) {
            function is_wp_error($thing) {
                return $thing instanceof WP_Error;
            }
        }
        if (!function_exists('__')) {
            function __($text, $domain = 'default') { return $text; }
        }
        
        // Set global test options
        global $test_options;
        $test_options = $this->test_options;
        
        // Create credential manager instance
        $this->credential_manager = new CredentialManager();
    }
    
    public function test_credential_manager_initialization() {
        // Test that credential manager initializes correctly
        $this->assertInstanceOf(CredentialManager::class, $this->credential_manager);
    }
    
    public function test_init_hooks_registration() {
        // Test that init method registers hooks
        ob_start();
        $this->credential_manager->init();
        ob_end_clean();
        
        // Since we can't easily test hook registration without WordPress,
        // we just ensure no errors occur during init
        $this->assertTrue(true);
    }
    
    public function test_maybe_generate_encryption_key() {
        // Test encryption key generation
        $this->credential_manager->maybe_generate_encryption_key();
        
        global $test_options;
        $this->assertArrayHasKey('carbon_marketplace_encryption_key', $test_options);
        $this->assertNotEmpty($test_options['carbon_marketplace_encryption_key']);
    }
    
    public function test_encrypt_decrypt_credential() {
        // Generate encryption key first
        $this->credential_manager->maybe_generate_encryption_key();
        
        $original_value = 'test_api_key_12345';
        
        // Test encryption
        $encrypted = $this->credential_manager->encrypt_credential($original_value);
        $this->assertNotEquals($original_value, $encrypted);
        
        // Test decryption
        $decrypted = $this->credential_manager->decrypt_credential($encrypted);
        $this->assertEquals($original_value, $decrypted);
    }
    
    public function test_encrypt_empty_value() {
        // Test that empty values are not encrypted
        $empty_value = '';
        $result = $this->credential_manager->encrypt_credential($empty_value);
        $this->assertEquals($empty_value, $result);
    }
    
    public function test_validate_cnaught_credentials_success() {
        $credentials = array(
            'api_key' => 'valid_api_key_123',
            'sandbox_mode' => true
        );
        
        $result = $this->credential_manager->validate_credentials('cnaught', $credentials);
        $this->assertTrue($result);
    }
    
    public function test_validate_cnaught_credentials_missing_key() {
        $credentials = array(
            'sandbox_mode' => true
        );
        
        $result = $this->credential_manager->validate_credentials('cnaught', $credentials);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('missing_api_key', $result->get_error_code());
    }
    
    public function test_validate_cnaught_credentials_invalid_format() {
        $credentials = array(
            'api_key' => 'invalid key with spaces!',
            'sandbox_mode' => false
        );
        
        $result = $this->credential_manager->validate_credentials('cnaught', $credentials);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_api_key_format', $result->get_error_code());
    }
    
    public function test_validate_toucan_credentials_success() {
        $credentials = array(
            'api_key' => 'optional_graph_api_key',
            'network' => 'polygon'
        );
        
        $result = $this->credential_manager->validate_credentials('toucan', $credentials);
        $this->assertTrue($result);
    }
    
    public function test_validate_toucan_credentials_no_api_key() {
        $credentials = array(
            'network' => 'polygon'
        );
        
        $result = $this->credential_manager->validate_credentials('toucan', $credentials);
        $this->assertTrue($result); // API key is optional for Toucan
    }
    
    public function test_validate_toucan_credentials_invalid_network() {
        $credentials = array(
            'network' => 'invalid_network'
        );
        
        $result = $this->credential_manager->validate_credentials('toucan', $credentials);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_network', $result->get_error_code());
    }
    
    public function test_validate_invalid_vendor() {
        $credentials = array();
        
        $result = $this->credential_manager->validate_credentials('invalid_vendor', $credentials);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_vendor', $result->get_error_code());
    }
    
    public function test_get_credentials_cnaught() {
        global $test_options;
        $test_options['carbon_marketplace_cnaught_api_key'] = 'test_key';
        $test_options['carbon_marketplace_cnaught_sandbox_mode'] = true;
        $test_options['carbon_marketplace_cnaught_enabled'] = true;
        
        $credentials = $this->credential_manager->get_credentials('cnaught');
        
        $this->assertEquals('test_key', $credentials['api_key']);
        $this->assertTrue($credentials['sandbox_mode']);
        $this->assertTrue($credentials['enabled']);
    }
    
    public function test_get_credentials_toucan() {
        global $test_options;
        $test_options['carbon_marketplace_toucan_api_key'] = 'graph_key';
        $test_options['carbon_marketplace_toucan_network'] = 'mumbai';
        $test_options['carbon_marketplace_toucan_enabled'] = true;
        
        $credentials = $this->credential_manager->get_credentials('toucan');
        
        $this->assertEquals('graph_key', $credentials['api_key']);
        $this->assertEquals('mumbai', $credentials['network']);
        $this->assertTrue($credentials['enabled']);
    }
    
    public function test_get_credentials_invalid_vendor() {
        $credentials = $this->credential_manager->get_credentials('invalid_vendor');
        $this->assertEmpty($credentials);
    }
    
    public function test_update_credentials_cnaught() {
        $credentials = array(
            'api_key' => 'new_test_key',
            'sandbox_mode' => false,
            'enabled' => true
        );
        
        $result = $this->credential_manager->update_credentials('cnaught', $credentials);
        $this->assertTrue($result);
        
        global $test_options;
        $this->assertEquals('new_test_key', $test_options['carbon_marketplace_cnaught_api_key']);
        $this->assertFalse($test_options['carbon_marketplace_cnaught_sandbox_mode']);
        $this->assertTrue($test_options['carbon_marketplace_cnaught_enabled']);
    }
    
    public function test_update_credentials_toucan() {
        $credentials = array(
            'api_key' => 'new_graph_key',
            'network' => 'polygon',
            'enabled' => true
        );
        
        $result = $this->credential_manager->update_credentials('toucan', $credentials);
        $this->assertTrue($result);
        
        global $test_options;
        $this->assertEquals('new_graph_key', $test_options['carbon_marketplace_toucan_api_key']);
        $this->assertEquals('polygon', $test_options['carbon_marketplace_toucan_network']);
        $this->assertTrue($test_options['carbon_marketplace_toucan_enabled']);
    }
    
    public function test_delete_credentials_cnaught() {
        global $test_options;
        $test_options['carbon_marketplace_cnaught_api_key'] = 'test_key';
        $test_options['carbon_marketplace_cnaught_sandbox_mode'] = true;
        $test_options['carbon_marketplace_cnaught_enabled'] = true;
        
        $result = $this->credential_manager->delete_credentials('cnaught');
        $this->assertTrue($result);
        
        $this->assertArrayNotHasKey('carbon_marketplace_cnaught_api_key', $test_options);
        $this->assertArrayNotHasKey('carbon_marketplace_cnaught_sandbox_mode', $test_options);
        $this->assertArrayNotHasKey('carbon_marketplace_cnaught_enabled', $test_options);
    }
    
    public function test_delete_credentials_toucan() {
        global $test_options;
        $test_options['carbon_marketplace_toucan_api_key'] = 'graph_key';
        $test_options['carbon_marketplace_toucan_network'] = 'polygon';
        $test_options['carbon_marketplace_toucan_enabled'] = true;
        
        $result = $this->credential_manager->delete_credentials('toucan');
        $this->assertTrue($result);
        
        $this->assertArrayNotHasKey('carbon_marketplace_toucan_api_key', $test_options);
        $this->assertArrayNotHasKey('carbon_marketplace_toucan_network', $test_options);
        $this->assertArrayNotHasKey('carbon_marketplace_toucan_enabled', $test_options);
    }
    
    public function test_get_configured_vendors() {
        global $test_options;
        
        // Configure CNaught
        $test_options['carbon_marketplace_cnaught_enabled'] = true;
        $test_options['carbon_marketplace_cnaught_api_key'] = 'test_key';
        
        // Configure Toucan
        $test_options['carbon_marketplace_toucan_enabled'] = true;
        
        $vendors = $this->credential_manager->get_configured_vendors();
        
        $this->assertContains('cnaught', $vendors);
        $this->assertContains('toucan', $vendors);
    }
    
    public function test_has_configured_vendors() {
        global $test_options;
        
        // No vendors configured
        $this->assertFalse($this->credential_manager->has_configured_vendors());
        
        // Configure one vendor
        $test_options['carbon_marketplace_toucan_enabled'] = true;
        $this->assertTrue($this->credential_manager->has_configured_vendors());
    }
    
    public function test_get_vendor_status_disabled() {
        $status = $this->credential_manager->get_vendor_status('cnaught');
        
        $this->assertFalse($status['enabled']);
        $this->assertFalse($status['configured']);
        $this->assertEquals('disabled', $status['status']);
        $this->assertEquals('Disabled', $status['message']);
    }
    
    public function test_get_vendor_status_enabled_configured() {
        global $test_options;
        $test_options['carbon_marketplace_cnaught_enabled'] = true;
        $test_options['carbon_marketplace_cnaught_api_key'] = 'test_key';
        
        $status = $this->credential_manager->get_vendor_status('cnaught');
        
        $this->assertTrue($status['enabled']);
        $this->assertTrue($status['configured']);
        $this->assertEquals('enabled', $status['status']);
        $this->assertEquals('Enabled and configured', $status['message']);
    }
    
    public function test_get_vendor_status_enabled_not_configured() {
        global $test_options;
        $test_options['carbon_marketplace_cnaught_enabled'] = true;
        // No API key set
        
        $status = $this->credential_manager->get_vendor_status('cnaught');
        
        $this->assertTrue($status['enabled']);
        $this->assertFalse($status['configured']);
        $this->assertEquals('error', $status['status']);
        $this->assertEquals('Enabled but not configured', $status['message']);
    }
    
    public function test_export_credentials() {
        global $test_options;
        $test_options['carbon_marketplace_cnaught_api_key'] = 'cnaught_key';
        $test_options['carbon_marketplace_toucan_api_key'] = 'toucan_key';
        $test_options['carbon_marketplace_encryption_key'] = 'encryption_key';
        
        $exported = $this->credential_manager->export_credentials();
        
        $this->assertArrayHasKey('cnaught', $exported);
        $this->assertArrayHasKey('toucan', $exported);
        $this->assertArrayHasKey('encryption_key', $exported);
        $this->assertEquals('encryption_key', $exported['encryption_key']);
    }
    
    public function test_import_credentials() {
        $data = array(
            'cnaught' => array(
                'api_key' => 'imported_cnaught_key',
                'sandbox_mode' => true,
                'enabled' => true
            ),
            'toucan' => array(
                'api_key' => 'imported_toucan_key',
                'network' => 'polygon',
                'enabled' => true
            ),
            'encryption_key' => 'imported_encryption_key'
        );
        
        $result = $this->credential_manager->import_credentials($data);
        $this->assertTrue($result);
        
        global $test_options;
        $this->assertEquals('imported_encryption_key', $test_options['carbon_marketplace_encryption_key']);
    }
    
    public function test_import_credentials_invalid_data() {
        $result = $this->credential_manager->import_credentials('invalid_data');
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_data', $result->get_error_code());
    }
    
    protected function tearDown(): void {
        // Clean up global variables
        global $test_options;
        $test_options = array();
    }
}

// Mock WP_Error class for testing
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $error_code;
        private $error_message;
        
        public function __construct($code, $message) {
            $this->error_code = $code;
            $this->error_message = $message;
        }
        
        public function get_error_code() {
            return $this->error_code;
        }
        
        public function get_error_message() {
            return $this->error_message;
        }
    }
}