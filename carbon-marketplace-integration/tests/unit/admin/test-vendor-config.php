<?php
/**
 * Unit tests for Vendor Configuration
 *
 * @package CarbonMarketplace\Tests\Admin
 */

use PHPUnit\Framework\TestCase;
use CarbonMarketplace\Admin\VendorConfig;
use CarbonMarketplace\Admin\CredentialManager;
use CarbonMarketplace\Api\ApiManager;

class VendorConfigTest extends TestCase {
    
    private $vendor_config;
    private $mock_credential_manager;
    private $mock_api_manager;
    
    protected function setUp(): void {
        // Mock WordPress functions
        if (!function_exists('add_action')) {
            function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
        }
        if (!function_exists('wp_enqueue_script')) {
            function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {}
        }
        if (!function_exists('wp_localize_script')) {
            function wp_localize_script($handle, $object_name, $l10n) {}
        }
        if (!function_exists('admin_url')) {
            function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
        }
        if (!function_exists('wp_create_nonce')) {
            function wp_create_nonce($action) { return 'test_nonce'; }
        }
        if (!function_exists('check_ajax_referer')) {
            function check_ajax_referer($action, $query_arg = false, $die = true) { return true; }
        }
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) { return true; }
        }
        if (!function_exists('wp_send_json_success')) {
            function wp_send_json_success($data = null) { 
                echo json_encode(['success' => true, 'data' => $data]);
                exit;
            }
        }
        if (!function_exists('wp_send_json_error')) {
            function wp_send_json_error($data = null) { 
                echo json_encode(['success' => false, 'data' => $data]);
                exit;
            }
        }
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) { return trim(strip_tags($str)); }
        }
        if (!function_exists('get_admin_page_title')) {
            function get_admin_page_title() { return 'Vendor Configuration'; }
        }
        if (!function_exists('__')) {
            function __($text, $domain = 'default') { return $text; }
        }
        if (!function_exists('_e')) {
            function _e($text, $domain = 'default') { echo $text; }
        }
        if (!function_exists('esc_html')) {
            function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
        }
        if (!function_exists('esc_attr')) {
            function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
        }
        if (!function_exists('esc_url')) {
            function esc_url($url) { return $url; }
        }
        if (!function_exists('esc_textarea')) {
            function esc_textarea($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
        }
        if (!function_exists('wp_nonce_field')) {
            function wp_nonce_field($action, $name = '_wpnonce', $referer = true, $echo = true) {
                $nonce = '<input type="hidden" name="' . $name . '" value="test_nonce">';
                if ($echo) echo $nonce;
                return $nonce;
            }
        }
        if (!function_exists('checked')) {
            function checked($checked, $current = true, $echo = true) {
                $result = $checked == $current ? 'checked="checked"' : '';
                if ($echo) echo $result;
                return $result;
            }
        }
        if (!function_exists('selected')) {
            function selected($selected, $current = true, $echo = true) {
                $result = $selected == $current ? 'selected="selected"' : '';
                if ($echo) echo $result;
                return $result;
            }
        }
        if (!function_exists('filter_var')) {
            function filter_var($value, $filter) {
                if ($filter === FILTER_VALIDATE_BOOLEAN) {
                    return (bool) $value;
                }
                return $value;
            }
        }
        
        // Define constants
        if (!defined('CARBON_MARKETPLACE_URL')) {
            define('CARBON_MARKETPLACE_URL', 'https://example.com/wp-content/plugins/carbon-marketplace/');
        }
        if (!defined('CARBON_MARKETPLACE_VERSION')) {
            define('CARBON_MARKETPLACE_VERSION', '1.0.0');
        }
        if (!defined('FILTER_VALIDATE_BOOLEAN')) {
            define('FILTER_VALIDATE_BOOLEAN', 'boolean');
        }
        
        // Create mocks
        $this->mock_credential_manager = $this->createMock(CredentialManager::class);
        $this->mock_api_manager = $this->createMock(ApiManager::class);
        
        // Create vendor config instance
        $this->vendor_config = new VendorConfig($this->mock_credential_manager, $this->mock_api_manager);
    }
    
    public function test_vendor_config_initialization() {
        // Test that vendor config initializes correctly
        $this->assertInstanceOf(VendorConfig::class, $this->vendor_config);
    }
    
    public function test_init_hooks_registration() {
        // Test that init method registers hooks
        ob_start();
        $this->vendor_config->init();
        ob_end_clean();
        
        // Since we can't easily test hook registration without WordPress,
        // we just ensure no errors occur during init
        $this->assertTrue(true);
    }
    
    public function test_enqueue_vendor_config_scripts() {
        // Test script enqueuing
        ob_start();
        $this->vendor_config->enqueue_vendor_config_scripts('carbon-marketplace_page_test');
        ob_end_clean();
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }
    
    public function test_enqueue_vendor_config_scripts_wrong_page() {
        // Test that scripts are not enqueued on wrong page
        ob_start();
        $this->vendor_config->enqueue_vendor_config_scripts('edit.php');
        ob_end_clean();
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }
    
    public function test_render_vendor_config_page() {
        // Mock credential manager methods
        $this->mock_credential_manager->method('get_credentials')
            ->willReturnMap([
                ['cnaught', ['enabled' => true, 'api_key' => 'test_key']],
                ['toucan', ['enabled' => false, 'network' => 'polygon']]
            ]);
        
        $this->mock_credential_manager->method('get_vendor_status')
            ->willReturnMap([
                ['cnaught', ['enabled' => true, 'configured' => true, 'status' => 'enabled', 'message' => 'Enabled and configured']],
                ['toucan', ['enabled' => false, 'configured' => false, 'status' => 'disabled', 'message' => 'Disabled']]
            ]);
        
        ob_start();
        $this->vendor_config->render_vendor_config_page();
        $output = ob_get_clean();
        
        // Check that page contains expected elements
        $this->assertStringContainsString('vendor-config-container', $output);
        $this->assertStringContainsString('CNaught', $output);
        $this->assertStringContainsString('Toucan Protocol', $output);
        $this->assertStringContainsString('vendor-config-card', $output);
    }
    
    public function test_ajax_save_vendor_config_success() {
        // Mock successful credential update
        $this->mock_credential_manager->method('update_credentials')
            ->willReturn(true);
        
        $_POST = [
            'vendor' => 'cnaught',
            'config' => [
                'api_key' => 'test_api_key',
                'sandbox_mode' => '1'
            ],
            'nonce' => 'test_nonce'
        ];
        
        ob_start();
        
        try {
            $this->vendor_config->ajax_save_vendor_config();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_success
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Configuration saved successfully', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_ajax_save_vendor_config_missing_vendor() {
        $_POST = [
            'config' => ['api_key' => 'test'],
            'nonce' => 'test_nonce'
        ];
        
        ob_start();
        
        try {
            $this->vendor_config->ajax_save_vendor_config();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_error
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success']);
        $this->assertEquals('Vendor not specified', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_ajax_save_vendor_config_validation_error() {
        // Mock validation error
        $this->mock_credential_manager->method('update_credentials')
            ->willReturn(new WP_Error('validation_error', 'Invalid credentials'));
        
        $_POST = [
            'vendor' => 'cnaught',
            'config' => ['api_key' => 'invalid'],
            'nonce' => 'test_nonce'
        ];
        
        ob_start();
        
        try {
            $this->vendor_config->ajax_save_vendor_config();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_error
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid credentials', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_ajax_test_vendor_connection_success() {
        // Mock successful validation
        $this->mock_credential_manager->method('validate_credentials')
            ->willReturn(true);
        
        $_POST = [
            'vendor' => 'toucan',
            'config' => ['network' => 'polygon'],
            'nonce' => 'test_nonce'
        ];
        
        ob_start();
        
        try {
            $this->vendor_config->ajax_test_vendor_connection();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_success
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Connection test successful', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_ajax_test_vendor_connection_failure() {
        // Mock validation failure
        $this->mock_credential_manager->method('validate_credentials')
            ->willReturn(new WP_Error('connection_failed', 'Connection failed'));
        
        $_POST = [
            'vendor' => 'cnaught',
            'config' => ['api_key' => 'invalid_key'],
            'nonce' => 'test_nonce'
        ];
        
        ob_start();
        
        try {
            $this->vendor_config->ajax_test_vendor_connection();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_error
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success']);
        $this->assertEquals('Connection failed', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_ajax_toggle_vendor_enable() {
        // Mock credential operations
        $this->mock_credential_manager->method('get_credentials')
            ->willReturn(['enabled' => false, 'api_key' => 'test_key']);
        $this->mock_credential_manager->method('update_credentials')
            ->willReturn(true);
        
        $_POST = [
            'vendor' => 'cnaught',
            'enabled' => '1',
            'nonce' => 'test_nonce'
        ];
        
        ob_start();
        
        try {
            $this->vendor_config->ajax_toggle_vendor();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_success
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Vendor enabled', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_ajax_toggle_vendor_disable() {
        // Mock credential operations
        $this->mock_credential_manager->method('get_credentials')
            ->willReturn(['enabled' => true, 'api_key' => 'test_key']);
        $this->mock_credential_manager->method('update_credentials')
            ->willReturn(true);
        
        $_POST = [
            'vendor' => 'cnaught',
            'enabled' => '0',
            'nonce' => 'test_nonce'
        ];
        
        ob_start();
        
        try {
            $this->vendor_config->ajax_toggle_vendor();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_success
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Vendor disabled', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_ajax_reset_vendor() {
        // Mock credential deletion
        $this->mock_credential_manager->method('delete_credentials')
            ->willReturn(true);
        
        $_POST = [
            'vendor' => 'cnaught',
            'nonce' => 'test_nonce'
        ];
        
        ob_start();
        
        try {
            $this->vendor_config->ajax_reset_vendor();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_success
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Vendor configuration reset', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_ajax_reset_vendor_failure() {
        // Mock credential deletion failure
        $this->mock_credential_manager->method('delete_credentials')
            ->willReturn(false);
        
        $_POST = [
            'vendor' => 'cnaught',
            'nonce' => 'test_nonce'
        ];
        
        ob_start();
        
        try {
            $this->vendor_config->ajax_reset_vendor();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_error
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success']);
        $this->assertEquals('Failed to reset vendor configuration', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_get_vendor_summary() {
        // Mock vendor status
        $this->mock_credential_manager->method('get_vendor_status')
            ->willReturnMap([
                ['cnaught', ['enabled' => true, 'configured' => true, 'status' => 'enabled', 'message' => 'Enabled and configured']],
                ['toucan', ['enabled' => false, 'configured' => false, 'status' => 'disabled', 'message' => 'Disabled']]
            ]);
        
        $summary = $this->vendor_config->get_vendor_summary();
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('cnaught', $summary);
        $this->assertArrayHasKey('toucan', $summary);
        
        $this->assertEquals('CNaught', $summary['cnaught']['name']);
        $this->assertTrue($summary['cnaught']['enabled']);
        $this->assertTrue($summary['cnaught']['configured']);
        
        $this->assertEquals('Toucan Protocol', $summary['toucan']['name']);
        $this->assertFalse($summary['toucan']['enabled']);
        $this->assertFalse($summary['toucan']['configured']);
    }
    
    protected function tearDown(): void {
        // Clean up global variables
        $_POST = [];
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