<?php
/**
 * Test runner for API tests
 *
 * @package CarbonMarketplace
 * @subpackage Tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress test environment if running standalone
    $wp_tests_dir = getenv('WP_TESTS_DIR');
    if (!$wp_tests_dir) {
        $wp_tests_dir = '/tmp/wordpress-tests-lib';
    }
    
    if (file_exists($wp_tests_dir . '/includes/functions.php')) {
        require_once $wp_tests_dir . '/includes/functions.php';
    }
}

// Load plugin files
require_once dirname(__DIR__) . '/includes/class-autoloader.php';
CarbonMarketplace\Autoloader::init();

// Simple test runner
class ApiTestRunner {
    
    private $tests_passed = 0;
    private $tests_failed = 0;
    private $failures = array();
    
    public function run_tests() {
        echo "Running Carbon Marketplace API Tests...\n\n";
        
        $this->run_base_api_client_tests();
        $this->run_cnaught_client_tests();
        $this->run_toucan_client_tests();
        
        $this->print_results();
    }
    
    private function run_base_api_client_tests() {
        echo "Testing BaseApiClient...\n";
        
        try {
            require_once __DIR__ . '/api/test-base-api-client.php';
            
            $test = new CarbonMarketplace\Tests\API\TestBaseApiClient();
            $test->setUp();
            
            $this->run_test_method($test, 'test_client_initialization');
            $this->run_test_method($test, 'test_build_url');
            $this->run_test_method($test, 'test_build_request_args');
            $this->run_test_method($test, 'test_build_request_args_post_json');
            $this->run_test_method($test, 'test_is_success_status');
            $this->run_test_method($test, 'test_should_retry');
            $this->run_test_method($test, 'test_calculate_retry_delay');
            $this->run_test_method($test, 'test_validate_credentials');
            $this->run_test_method($test, 'test_get_auth_headers');
            $this->run_test_method($test, 'test_client_configuration');
            $this->run_test_method($test, 'test_default_configuration');
            
        } catch (Exception $e) {
            $this->failures[] = "BaseApiClient test setup failed: " . $e->getMessage();
            $this->tests_failed++;
        }
        
        echo "\n";
    }
    
    private function run_cnaught_client_tests() {
        echo "Testing CNaught API Client...\n";
        
        try {
            require_once __DIR__ . '/api/test-cnaught-client.php';
            
            $test = new CarbonMarketplace\Tests\API\TestCNaughtClient();
            $test->setUp();
            
            $this->run_test_method($test, 'test_client_initialization');
            $this->run_test_method($test, 'test_get_auth_headers');
            $this->run_test_method($test, 'test_validate_credentials_valid');
            $this->run_test_method($test, 'test_validate_credentials_invalid');
            $this->run_test_method($test, 'test_get_portfolios');
            $this->run_test_method($test, 'test_get_portfolio_details');
            $this->run_test_method($test, 'test_get_project_details');
            $this->run_test_method($test, 'test_create_quote');
            $this->run_test_method($test, 'test_create_checkout_session');
            $this->run_test_method($test, 'test_get_order_details');
            $this->run_test_method($test, 'test_handle_webhook_order_completed');
            $this->run_test_method($test, 'test_handle_webhook_retirement_completed');
            $this->run_test_method($test, 'test_get_project_details_invalid_id');
            $this->run_test_method($test, 'test_create_quote_missing_parameters');
            $this->run_test_method($test, 'test_request_logging');
            $this->run_test_method($test, 'test_client_without_api_key');
            
        } catch (Exception $e) {
            $this->failures[] = "CNaught API Client test setup failed: " . $e->getMessage();
            $this->tests_failed++;
        }
        
        echo "\n";
    }
    
    private function run_toucan_client_tests() {
        echo "Testing Toucan API Client...\n";
        
        try {
            require_once __DIR__ . '/api/test-toucan-client.php';
            
            $test = new CarbonMarketplace\Tests\API\TestToucanClient();
            $test->setUp();
            
            $this->run_test_method($test, 'test_client_initialization');
            $this->run_test_method($test, 'test_get_auth_headers');
            $this->run_test_method($test, 'test_validate_credentials');
            $this->run_test_method($test, 'test_fetch_all_tco2_tokens');
            $this->run_test_method($test, 'test_fetch_tco2_token_by_id');
            $this->run_test_method($test, 'test_fetch_pool_contents');
            $this->run_test_method($test, 'test_fetch_token_price_on_dex');
            $this->run_test_method($test, 'test_get_available_pools');
            $this->run_test_method($test, 'test_fetch_tco2_token_by_id_invalid');
            $this->run_test_method($test, 'test_fetch_tco2_token_by_id_not_found');
            $this->run_test_method($test, 'test_fetch_token_price_no_data');
            $this->run_test_method($test, 'test_query_logging');
            $this->run_test_method($test, 'test_client_without_api_key');
            $this->run_test_method($test, 'test_token_supply_parsing');
            
        } catch (Exception $e) {
            $this->failures[] = "Toucan API Client test setup failed: " . $e->getMessage();
            $this->tests_failed++;
        }
        
        echo "\n";
    }
    
    private function run_test_method($test_instance, $method_name) {
        try {
            $test_instance->$method_name();
            echo "  ✓ {$method_name}\n";
            $this->tests_passed++;
        } catch (Exception $e) {
            echo "  ✗ {$method_name}: " . $e->getMessage() . "\n";
            $this->failures[] = "{$method_name}: " . $e->getMessage();
            $this->tests_failed++;
        }
    }
    
    private function print_results() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Test Results:\n";
        echo "Passed: {$this->tests_passed}\n";
        echo "Failed: {$this->tests_failed}\n";
        
        if (!empty($this->failures)) {
            echo "\nFailures:\n";
            foreach ($this->failures as $failure) {
                echo "  - {$failure}\n";
            }
        }
        
        if ($this->tests_failed === 0) {
            echo "\n🎉 All tests passed!\n";
        } else {
            echo "\n❌ Some tests failed.\n";
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $runner = new ApiTestRunner();
    $runner->run_tests();
}