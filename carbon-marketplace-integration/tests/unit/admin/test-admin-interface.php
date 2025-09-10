<?php
/**
 * Unit tests for Admin Interface
 *
 * @package CarbonMarketplace\Tests\Admin
 */

use PHPUnit\Framework\TestCase;
use CarbonMarketplace\Admin\AdminInterface;
use CarbonMarketplace\Api\ApiManager;
use CarbonMarketplace\Cache\CacheManager;

class AdminInterfaceTest extends TestCase {
    
    private $admin_interface;
    private $mock_api_manager;
    private $mock_cache_manager;
    
    protected function setUp(): void {
        // Mock WordPress functions
        if (!function_exists('add_action')) {
            function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
        }
        if (!function_exists('add_menu_page')) {
            function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url = '', $position = null) {}
        }
        if (!function_exists('add_submenu_page')) {
            function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function) {}
        }
        if (!function_exists('register_setting')) {
            function register_setting($option_group, $option_name, $args = array()) {}
        }
        if (!function_exists('add_settings_section')) {
            function add_settings_section($id, $title, $callback, $page) {}
        }
        if (!function_exists('add_settings_field')) {
            function add_settings_field($id, $title, $callback, $page, $section, $args = array()) {}
        }
        if (!function_exists('get_option')) {
            function get_option($option, $default = false) { return $default; }
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
        if (!function_exists('wp_create_nonce')) {
            function wp_create_nonce($action) { return 'test_nonce'; }
        }
        if (!function_exists('check_ajax_referer')) {
            function check_ajax_referer($action, $query_arg = false, $die = true) { return true; }
        }
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) { return true; }
        }
        if (!function_exists('wp_die')) {
            function wp_die($message) { throw new Exception($message); }
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
            function get_admin_page_title() { return 'Carbon Marketplace'; }
        }
        if (!function_exists('admin_url')) {
            function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
        }
        if (!function_exists('get_bloginfo')) {
            function get_bloginfo($show) { 
                if ($show === 'version') return '6.0';
                return 'Test Site';
            }
        }
        if (!function_exists('settings_fields')) {
            function settings_fields($option_group) {}
        }
        if (!function_exists('do_settings_sections')) {
            function do_settings_sections($page) {}
        }
        if (!function_exists('submit_button')) {
            function submit_button() { echo '<input type="submit" class="button-primary" value="Save Changes">'; }
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
        
        // Define constants
        if (!defined('CARBON_MARKETPLACE_VERSION')) {
            define('CARBON_MARKETPLACE_VERSION', '1.0.0');
        }
        
        // Create mocks
        $this->mock_api_manager = $this->createMock(ApiManager::class);
        $this->mock_cache_manager = $this->createMock(CacheManager::class);
        
        // Create admin interface instance
        $this->admin_interface = new AdminInterface($this->mock_api_manager, $this->mock_cache_manager);
    }
    
    public function test_admin_interface_initialization() {
        // Test that admin interface initializes correctly
        $this->assertInstanceOf(AdminInterface::class, $this->admin_interface);
    }
    
    public function test_init_hooks_registration() {
        // Test that init method registers hooks
        ob_start();
        $this->admin_interface->init();
        ob_end_clean();
        
        // Since we can't easily test hook registration without WordPress,
        // we just ensure no errors occur during init
        $this->assertTrue(true);
    }
    
    public function test_add_admin_menu() {
        // Test admin menu addition
        ob_start();
        $this->admin_interface->add_admin_menu();
        ob_end_clean();
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }
    
    public function test_register_settings() {
        // Test settings registration
        ob_start();
        $this->admin_interface->register_settings();
        ob_end_clean();
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }
    
    public function test_render_main_page() {
        // Mock cache manager statistics
        $this->mock_cache_manager->method('get_cache_statistics')
            ->willReturn([
                'total_items' => 100,
                'hit_rate' => 85,
                'memory_usage' => '2.5MB',
                'last_updated' => '2023-01-01 12:00:00'
            ]);
        
        ob_start();
        $this->admin_interface->render_main_page();
        $output = ob_get_clean();
        
        // Check that main page contains expected elements
        $this->assertStringContainsString('Carbon Marketplace', $output);
        $this->assertStringContainsString('System Status', $output);
        $this->assertStringContainsString('API Status', $output);
        $this->assertStringContainsString('Cache Status', $output);
        $this->assertStringContainsString('Quick Actions', $output);
    }
    
    public function test_render_settings_page() {
        // Test settings page rendering
        $_GET['tab'] = 'general';
        
        ob_start();
        $this->admin_interface->render_settings_page();
        $output = ob_get_clean();
        
        // Check that settings page contains expected elements
        $this->assertStringContainsString('nav-tab-wrapper', $output);
        $this->assertStringContainsString('General', $output);
        $this->assertStringContainsString('Cache', $output);
        $this->assertStringContainsString('CNaught API', $output);
        $this->assertStringContainsString('Toucan API', $output);
        
        // Clean up
        unset($_GET['tab']);
    }
    
    public function test_render_vendors_page() {
        ob_start();
        $this->admin_interface->render_vendors_page();
        $output = ob_get_clean();
        
        // Check that vendors page contains expected elements
        $this->assertStringContainsString('vendor-status-cards', $output);
    }
    
    public function test_render_cache_page() {
        // Mock cache statistics
        $this->mock_cache_manager->method('get_cache_statistics')
            ->willReturn([
                'total_items' => 50,
                'hit_rate' => 90,
                'memory_usage' => '1.2MB',
                'last_updated' => '2023-01-01 10:00:00'
            ]);
        
        ob_start();
        $this->admin_interface->render_cache_page();
        $output = ob_get_clean();
        
        // Check that cache page contains expected elements
        $this->assertStringContainsString('Cache Statistics', $output);
        $this->assertStringContainsString('Cache Actions', $output);
        $this->assertStringContainsString('cache-management', $output);
    }
    
    public function test_render_analytics_page() {
        ob_start();
        $this->admin_interface->render_analytics_page();
        $output = ob_get_clean();
        
        // Check that analytics page renders
        $this->assertStringContainsString('analytics-dashboard', $output);
        $this->assertStringContainsString('Analytics dashboard coming soon', $output);
    }
    
    public function test_render_checkbox_field() {
        $args = [
            'option_name' => 'test_option',
            'description' => 'Test checkbox field'
        ];
        
        ob_start();
        $this->admin_interface->render_checkbox_field($args);
        $output = ob_get_clean();
        
        // Check checkbox field rendering
        $this->assertStringContainsString('type="checkbox"', $output);
        $this->assertStringContainsString('name="test_option"', $output);
        $this->assertStringContainsString('Test checkbox field', $output);
    }
    
    public function test_render_text_field() {
        $args = [
            'option_name' => 'test_text_option',
            'description' => 'Test text field'
        ];
        
        ob_start();
        $this->admin_interface->render_text_field($args);
        $output = ob_get_clean();
        
        // Check text field rendering
        $this->assertStringContainsString('type="text"', $output);
        $this->assertStringContainsString('name="test_text_option"', $output);
        $this->assertStringContainsString('Test text field', $output);
    }
    
    public function test_render_password_field() {
        $args = [
            'option_name' => 'test_password_option',
            'description' => 'Test password field'
        ];
        
        ob_start();
        $this->admin_interface->render_password_field($args);
        $output = ob_get_clean();
        
        // Check password field rendering
        $this->assertStringContainsString('type="password"', $output);
        $this->assertStringContainsString('name="test_password_option"', $output);
        $this->assertStringContainsString('Test password field', $output);
    }
    
    public function test_render_number_field() {
        $args = [
            'option_name' => 'test_number_option',
            'description' => 'Test number field',
            'min' => 1,
            'max' => 100
        ];
        
        ob_start();
        $this->admin_interface->render_number_field($args);
        $output = ob_get_clean();
        
        // Check number field rendering
        $this->assertStringContainsString('type="number"', $output);
        $this->assertStringContainsString('name="test_number_option"', $output);
        $this->assertStringContainsString('min="1"', $output);
        $this->assertStringContainsString('max="100"', $output);
        $this->assertStringContainsString('Test number field', $output);
    }
    
    public function test_render_select_field() {
        $args = [
            'option_name' => 'test_select_option',
            'description' => 'Test select field',
            'options' => [
                'option1' => 'Option 1',
                'option2' => 'Option 2'
            ]
        ];
        
        ob_start();
        $this->admin_interface->render_select_field($args);
        $output = ob_get_clean();
        
        // Check select field rendering
        $this->assertStringContainsString('<select', $output);
        $this->assertStringContainsString('name="test_select_option"', $output);
        $this->assertStringContainsString('Option 1', $output);
        $this->assertStringContainsString('Option 2', $output);
        $this->assertStringContainsString('Test select field', $output);
    }
    
    public function test_ajax_test_credentials_success() {
        // Mock successful credential validation
        $this->mock_api_manager->method('validate_client_credentials')
            ->willReturn(true);
        
        $_POST = [
            'vendor' => 'cnaught',
            'nonce' => 'test_nonce'
        ];
        
        ob_start();
        
        try {
            $this->admin_interface->ajax_test_credentials();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_success
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Connection successful', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_ajax_test_credentials_failure() {
        // Mock failed credential validation
        $this->mock_api_manager->method('validate_client_credentials')
            ->willReturn(new WP_Error('invalid_credentials', 'Invalid API credentials'));
        
        $_POST = [
            'vendor' => 'cnaught',
            'nonce' => 'test_nonce'
        ];
        
        ob_start();
        
        try {
            $this->admin_interface->ajax_test_credentials();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_error
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid API credentials', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_ajax_clear_cache() {
        // Mock cache clearing
        $this->mock_cache_manager->method('invalidate_all_cache')
            ->willReturn(true);
        
        $_POST = ['nonce' => 'test_nonce'];
        
        ob_start();
        
        try {
            $this->admin_interface->ajax_clear_cache();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_success
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Cache cleared successfully', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_ajax_sync_data() {
        // Mock data synchronization
        $this->mock_api_manager->method('fetch_all_portfolios')
            ->willReturn([]);
        $this->mock_api_manager->method('fetch_all_projects')
            ->willReturn([]);
        $this->mock_cache_manager->method('warm_cache')
            ->willReturn(['success' => true]);
        
        $_POST = ['nonce' => 'test_nonce'];
        
        ob_start();
        
        try {
            $this->admin_interface->ajax_sync_data();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json_success
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Data synchronization completed', $response['data']);
        
        // Clean up
        $_POST = [];
    }
    
    public function test_show_admin_notices() {
        // Test admin notices display
        ob_start();
        $this->admin_interface->show_admin_notices();
        $output = ob_get_clean();
        
        // Should show notice when no APIs are configured
        $this->assertStringContainsString('notice', $output);
        $this->assertStringContainsString('configure at least one API vendor', $output);
    }
    
    protected function tearDown(): void {
        // Clean up global variables
        $_GET = [];
        $_POST = [];
    }
}

// Mock WP_Error class for testing
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $error_message;
        
        public function __construct($code, $message) {
            $this->error_message = $message;
        }
        
        public function get_error_message() {
            return $this->error_message;
        }
    }
}