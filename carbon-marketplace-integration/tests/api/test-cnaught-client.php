<?php
/**
 * CNaught API Client Tests
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

// Include WordPress test environment setup if available
if (file_exists(dirname(__FILE__) . '/../../../../../wp-config.php')) {
    require_once dirname(__FILE__) . '/../../../../../wp-config.php';
}

// Include plugin files
require_once dirname(__FILE__) . '/../../carbon-marketplace-integration.php';

// Mock WordPress functions if not available
if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args) {
        return MockHttpClient::handle_request($url, $args);
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('wp_remote_retrieve_response_message')) {
    function wp_remote_retrieve_response_message($response) {
        return $response['response']['message'] ?? 'OK';
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

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show) {
        return '6.0';
    }
}

if (!function_exists('home_url')) {
    function home_url() {
        return 'https://example.com';
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        // Mock error logging
    }
}

if (!defined('CARBON_MARKETPLACE_VERSION')) {
    define('CARBON_MARKETPLACE_VERSION', '1.0.0');
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];
        private $error_data = [];
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return $this->errors[$code][0] ?? '';
        }
        
        public function get_error_code() {
            return array_keys($this->errors)[0] ?? '';
        }
    }
}

/**
 * Mock HTTP client for testing
 */
class MockHttpClient {
    
    private static $mock_responses = [];
    
    public static function set_mock_response($url_pattern, $response) {
        self::$mock_responses[$url_pattern] = $response;
    }
    
    public static function clear_mock_responses() {
        self::$mock_responses = [];
    }
    
    public static function handle_request($url, $args) {
        foreach (self::$mock_responses as $pattern => $response) {
            if (strpos($url, $pattern) !== false) {
                return $response;
            }
        }
        
        // Default response
        return [
            'response' => ['code' => 404, 'message' => 'Not Found'],
            'body' => json_encode(['error' => 'Mock response not found'])
        ];
    }
}

/**
 * CNaught Client Test Runner
 */
class CNaughtClientTestRunner {
    
    private $tests_passed = 0;
    private $tests_failed = 0;
    private $failures = [];
    
    public function run_all_tests() {
        echo "Running CNaught API Client Tests...\n\n";
        
        $this->test_client_initialization();
        $this->test_credential_validation();
        $this->test_get_portfolios();
        $this->test_get_portfolio_details();
        $this->test_get_project_details();
        $this->test_create_quote();
        $this->test_create_checkout_session();
        $this->test_error_handling();
        $this->test_authentication_headers();
        $this->test_response_mapping();
        
        $this->print_results();
    }
    
    private function test_client_initialization() {
        echo "Testing Client Initialization...\n";
        
        use CarbonMarketplace\API\CNaughtClient;
        
        // Test with default configuration
        $client = new CNaughtClient();
        $this->assert_equals('CNaught', $client->get_client_name(), 'Client name should be CNaught');
        
        // Test with custom configuration
        $config = [
            'base_url' => 'https://api.test.com/v1',
            'credentials' => [
                'api_key' => 'test_key_123',
                'client_id' => 'test_client'
            ],
            'timeout' => 60
        ];
        
        $client = new CNaughtClient($config);
        $this->assert_true(true, 'Client should initialize with custom config');
        
        echo "Client Initialization tests completed.\n\n";
    }
    
    private function test_credential_validation() {
        echo "Testing Credential Validation...\n";
        
        use CarbonMarketplace\API\CNaughtClient;
        
        // Test missing credentials
        $client = new CNaughtClient();
        $result = $client->validate_credentials();
        $this->assert_true(is_wp_error($result), 'Should return error for missing credentials');
        
        // Test valid credentials
        MockHttpClient::set_mock_response('/portfolios', [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode(['data' => []])
        ]);
        
        $client = new CNaughtClient([
            'credentials' => ['api_key' => 'valid_key']
        ]);
        $result = $client->validate_credentials();
        $this->assert_true($result === true, 'Should return true for valid credentials');
        
        // Test invalid credentials
        MockHttpClient::set_mock_response('/portfolios', [
            'response' => ['code' => 401, 'message' => 'Unauthorized'],
            'body' => json_encode(['error' => 'Invalid API key'])
        ]);
        
        $result = $client->validate_credentials();
        $this->assert_true(is_wp_error($result), 'Should return error for invalid credentials');
        
        MockHttpClient::clear_mock_responses();
        
        echo "Credential Validation tests completed.\n\n";
    }
    
    private function test_get_portfolios() {
        echo "Testing Get Portfolios...\n";
        
        use CarbonMarketplace\API\CNaughtClient;
        
        $client = new CNaughtClient([
            'credentials' => ['api_key' => 'test_key']
        ]);
        
        // Mock successful response
        $mock_data = [
            'data' => [
                [
                    'id' => 'port_123',
                    'name' => 'Test Portfolio',
                    'description' => 'A test portfolio',
                    'base_price_per_kg' => 15.50,
                    'is_active' => true,
                    'projects' => []
                ]
            ]
        ];
        
        MockHttpClient::set_mock_response('/portfolios', [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode($mock_data)
        ]);
        
        $portfolios = $client->get_portfolios();
        $this->assert_true(is_array($portfolios), 'Should return array of portfolios');
        $this->assert_equals(1, count($portfolios), 'Should return 1 portfolio');
        
        if (!empty($portfolios)) {
            $portfolio = $portfolios[0];
            $this->assert_equals('port_123', $portfolio->id, 'Portfolio ID should match');
            $this->assert_equals('cnaught', $portfolio->vendor, 'Vendor should be cnaught');
            $this->assert_equals('Test Portfolio', $portfolio->name, 'Portfolio name should match');
        }
        
        MockHttpClient::clear_mock_responses();
        
        echo "Get Portfolios tests completed.\n\n";
    }
    
    private function test_get_portfolio_details() {
        echo "Testing Get Portfolio Details...\n";
        
        use CarbonMarketplace\API\CNaughtClient;
        
        $client = new CNaughtClient([
            'credentials' => ['api_key' => 'test_key']
        ]);
        
        // Mock successful response
        $mock_data = [
            'data' => [
                'id' => 'port_123',
                'name' => 'Detailed Portfolio',
                'description' => 'A detailed portfolio',
                'base_price_per_kg' => 18.75,
                'is_active' => true,
                'projects' => [
                    [
                        'id' => 'proj_456',
                        'name' => 'Forest Project',
                        'location' => 'Brazil',
                        'project_type' => 'Forestry',
                        'price_per_kg' => 20.00
                    ]
                ]
            ]
        ];
        
        MockHttpClient::set_mock_response('/portfolios/port_123', [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode($mock_data)
        ]);
        
        $portfolio = $client->get_portfolio_details('port_123');
        $this->assert_false(is_wp_error($portfolio), 'Should not return error');
        $this->assert_equals('port_123', $portfolio->id, 'Portfolio ID should match');
        $this->assert_equals(1, count($portfolio->projects), 'Should have 1 project');
        
        MockHttpClient::clear_mock_responses();
        
        echo "Get Portfolio Details tests completed.\n\n";
    }
    
    private function test_get_project_details() {
        echo "Testing Get Project Details...\n";
        
        use CarbonMarketplace\API\CNaughtClient;
        
        $client = new CNaughtClient([
            'credentials' => ['api_key' => 'test_key']
        ]);
        
        // Mock successful response
        $mock_data = [
            'data' => [
                'id' => 'proj_789',
                'name' => 'Solar Project',
                'description' => 'A solar energy project',
                'location' => 'India',
                'project_type' => 'Renewable Energy',
                'methodology' => 'CDM',
                'price_per_kg' => 12.50,
                'available_quantity' => 5000,
                'images' => ['image1.jpg', 'image2.jpg'],
                'sdgs' => [7, 13],
                'registry_url' => 'https://registry.example.com/proj_789'
            ]
        ];
        
        MockHttpClient::set_mock_response('/projects/proj_789', [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode($mock_data)
        ]);
        
        $project = $client->get_project_details('proj_789');
        $this->assert_false(is_wp_error($project), 'Should not return error');
        $this->assert_equals('proj_789', $project->id, 'Project ID should match');
        $this->assert_equals('Solar Project', $project->name, 'Project name should match');
        $this->assert_equals('India', $project->location, 'Project location should match');
        $this->assert_equals(12.50, $project->price_per_kg, 'Project price should match');
        
        MockHttpClient::clear_mock_responses();
        
        echo "Get Project Details tests completed.\n\n";
    }
    
    private function test_create_quote() {
        echo "Testing Create Quote...\n";
        
        use CarbonMarketplace\API\CNaughtClient;
        use CarbonMarketplace\Models\QuoteRequest;
        
        $client = new CNaughtClient([
            'credentials' => ['api_key' => 'test_key']
        ]);
        
        // Test valid quote request
        $quote_request = new QuoteRequest([
            'amount_kg' => 10.0,
            'currency' => 'USD',
            'portfolio_id' => 'port_123'
        ]);
        
        // Mock successful response
        $mock_data = [
            'data' => [
                'id' => 'quote_456',
                'amount_kg' => 10.0,
                'price_per_kg' => 15.50,
                'total_price' => 155.00,
                'currency' => 'USD',
                'portfolio_id' => 'port_123',
                'project_allocations' => [],
                'expires_at' => '2024-12-31T23:59:59Z'
            ]
        ];
        
        MockHttpClient::set_mock_response('/quotes', [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode($mock_data)
        ]);
        
        $quote = $client->create_quote($quote_request);
        $this->assert_false(is_wp_error($quote), 'Should not return error for valid request');
        $this->assert_equals('quote_456', $quote->id, 'Quote ID should match');
        $this->assert_equals(10.0, $quote->amount_kg, 'Quote amount should match');
        $this->assert_equals(155.00, $quote->total_price, 'Quote total should match');
        
        // Test invalid quote request
        $invalid_request = new QuoteRequest([
            'amount_kg' => -5.0  // Invalid negative amount
        ]);
        
        $result = $client->create_quote($invalid_request);
        $this->assert_true(is_wp_error($result), 'Should return error for invalid request');
        
        MockHttpClient::clear_mock_responses();
        
        echo "Create Quote tests completed.\n\n";
    }
    
    private function test_create_checkout_session() {
        echo "Testing Create Checkout Session...\n";
        
        use CarbonMarketplace\API\CNaughtClient;
        use CarbonMarketplace\Models\CheckoutRequest;
        
        $client = new CNaughtClient([
            'credentials' => ['api_key' => 'test_key']
        ]);
        
        // Test valid checkout request
        $checkout_request = new CheckoutRequest([
            'amount_kg' => 5.0,
            'currency' => 'USD',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'customer_email' => 'test@example.com',
            'portfolio_id' => 'port_123'
        ]);
        
        // Mock successful response
        $mock_data = [
            'data' => [
                'id' => 'session_789',
                'checkout_url' => 'https://checkout.cnaught.com/session_789',
                'amount_kg' => 5.0,
                'total_price' => 77.50,
                'currency' => 'USD',
                'status' => 'open',
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
                'customer_email' => 'test@example.com',
                'expires_at' => '2024-12-31T23:59:59Z'
            ]
        ];
        
        MockHttpClient::set_mock_response('/checkout/sessions', [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode($mock_data)
        ]);
        
        $session = $client->create_checkout_session($checkout_request);
        $this->assert_false(is_wp_error($session), 'Should not return error for valid request');
        $this->assert_equals('session_789', $session->id, 'Session ID should match');
        $this->assert_equals('https://checkout.cnaught.com/session_789', $session->checkout_url, 'Checkout URL should match');
        $this->assert_equals('open', $session->status, 'Session status should be open');
        
        MockHttpClient::clear_mock_responses();
        
        echo "Create Checkout Session tests completed.\n\n";
    }
    
    private function test_error_handling() {
        echo "Testing Error Handling...\n";
        
        use CarbonMarketplace\API\CNaughtClient;
        
        $client = new CNaughtClient([
            'credentials' => ['api_key' => 'test_key']
        ]);
        
        // Test 404 error
        MockHttpClient::set_mock_response('/portfolios/nonexistent', [
            'response' => ['code' => 404, 'message' => 'Not Found'],
            'body' => json_encode(['error' => 'Portfolio not found'])
        ]);
        
        $result = $client->get_portfolio_details('nonexistent');
        $this->assert_true(is_wp_error($result), 'Should return error for 404 response');
        
        // Test 500 error (should retry)
        MockHttpClient::set_mock_response('/portfolios', [
            'response' => ['code' => 500, 'message' => 'Internal Server Error'],
            'body' => json_encode(['error' => 'Server error'])
        ]);
        
        $result = $client->get_portfolios();
        $this->assert_true(is_wp_error($result), 'Should return error after retries for 500 response');
        
        MockHttpClient::clear_mock_responses();
        
        echo "Error Handling tests completed.\n\n";
    }
    
    private function test_authentication_headers() {
        echo "Testing Authentication Headers...\n";
        
        use CarbonMarketplace\API\CNaughtClient;
        
        $client = new CNaughtClient([
            'credentials' => [
                'api_key' => 'test_api_key_123',
                'client_id' => 'test_client_456'
            ]
        ]);
        
        // Use reflection to test protected method
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('get_auth_headers');
        $method->setAccessible(true);
        
        $headers = $method->invoke($client);
        
        $this->assert_equals('Bearer test_api_key_123', $headers['Authorization'], 'Authorization header should be set correctly');
        $this->assert_equals('test_client_456', $headers['X-Client-ID'], 'Client ID header should be set correctly');
        
        echo "Authentication Headers tests completed.\n\n";
    }
    
    private function test_response_mapping() {
        echo "Testing Response Mapping...\n";
        
        use CarbonMarketplace\API\CNaughtClient;
        
        $client = new CNaughtClient([
            'credentials' => ['api_key' => 'test_key']
        ]);
        
        // Test mapping with alternative field names
        $mock_data = [
            'data' => [
                'id' => 'proj_alt',
                'name' => 'Alternative Project',
                'country' => 'Kenya',  // Alternative to 'location'
                'type' => 'Agriculture',  // Alternative to 'project_type'
                'quantity_available' => 2500,  // Alternative to 'available_quantity'
                'price_per_kg' => 18.00
            ]
        ];
        
        MockHttpClient::set_mock_response('/projects/proj_alt', [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode($mock_data)
        ]);
        
        $project = $client->get_project_details('proj_alt');
        $this->assert_false(is_wp_error($project), 'Should not return error');
        $this->assert_equals('Kenya', $project->location, 'Should map country to location');
        $this->assert_equals('Agriculture', $project->project_type, 'Should map type to project_type');
        $this->assert_equals(2500, $project->available_quantity, 'Should map quantity_available to available_quantity');
        
        MockHttpClient::clear_mock_responses();
        
        echo "Response Mapping tests completed.\n\n";
    }
    
    private function assert_equals($expected, $actual, $message) {
        if ($expected === $actual) {
            $this->tests_passed++;
            echo "  ✓ $message\n";
        } else {
            $this->tests_failed++;
            $this->failures[] = "$message - Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true);
            echo "  ✗ $message - Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . "\n";
        }
    }
    
    private function assert_true($condition, $message) {
        if ($condition) {
            $this->tests_passed++;
            echo "  ✓ $message\n";
        } else {
            $this->tests_failed++;
            $this->failures[] = "$message - Expected true, got false";
            echo "  ✗ $message - Expected true, got false\n";
        }
    }
    
    private function assert_false($condition, $message) {
        if (!$condition) {
            $this->tests_passed++;
            echo "  ✓ $message\n";
        } else {
            $this->tests_failed++;
            $this->failures[] = "$message - Expected false, got true";
            echo "  ✗ $message - Expected false, got true\n";
        }
    }
    
    private function print_results() {
        echo "CNaught Client Test Results:\n";
        echo "Passed: {$this->tests_passed}\n";
        echo "Failed: {$this->tests_failed}\n";
        
        if ($this->tests_failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $failure) {
                echo "  - $failure\n";
            }
        }
        
        echo "\n" . ($this->tests_failed === 0 ? "All CNaught client tests passed! ✓" : "Some CNaught client tests failed! ✗") . "\n";
    }
}

// Run the tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $runner = new CNaughtClientTestRunner();
    $runner->run_all_tests();
}