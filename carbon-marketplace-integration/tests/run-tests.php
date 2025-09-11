<?php
/**
 * Simple test runner for Carbon Marketplace models
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

// Include WordPress test environment setup if available
if (file_exists(dirname(__FILE__) . '/../../../../wp-config.php')) {
    require_once dirname(__FILE__) . '/../../../../wp-config.php';
}

// Include plugin files
require_once dirname(__FILE__) . '/../carbon-marketplace-integration.php';


// Mock WordPress functions if not available
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

// Test runner class
class SimpleTestRunner {
    
    private $tests_passed = 0;
    private $tests_failed = 0;
    private $failures = [];
    
    public function run_all_tests() {
        echo "Running Carbon Marketplace Model Tests...\n\n";
        
        $this->test_project_model();
        $this->test_portfolio_model();
        $this->test_search_query_model();
        $this->test_order_model();
        
        $this->print_results();
    }
    
    private function test_project_model() {
        echo "Testing Project Model...\n";
        
        
        // Test project creation
        $data = [
            'id' => 'proj_123',
            'vendor' => 'cnaught',
            'name' => 'Test Project',
            'price_per_kg' => 15.50,
            'available_quantity' => 1000,
        ];
        
        $project = new Project($data);
        $this->assert_equals('proj_123', $project->id, 'Project ID should match');
        $this->assert_equals('cnaught', $project->vendor, 'Project vendor should match');
        $this->assert_equals(15.50, $project->price_per_kg, 'Project price should match');
        
        // Test validation
        $this->assert_true($project->validate(), 'Valid project should pass validation');
        
        // Test invalid project
        $invalid_project = new Project();
        $this->assert_false($invalid_project->validate(), 'Invalid project should fail validation');
        
        // Test serialization
        $array = $project->to_array();
        $this->assert_true(is_array($array), 'to_array should return array');
        $this->assert_equals('proj_123', $array['id'], 'Array should contain correct ID');
        
        echo "Project Model tests completed.\n\n";
    }
    
    private function test_portfolio_model() {
        echo "Testing Portfolio Model...\n";
        
        
        // Test portfolio creation
        $data = [
            'id' => 'port_123',
            'vendor' => 'cnaught',
            'name' => 'Test Portfolio',
            'base_price_per_kg' => 12.00,
            'is_active' => true,
        ];
        
        $portfolio = new Portfolio($data);
        $this->assert_equals('port_123', $portfolio->id, 'Portfolio ID should match');
        $this->assert_equals('cnaught', $portfolio->vendor, 'Portfolio vendor should match');
        $this->assert_true($portfolio->is_active, 'Portfolio should be active');
        
        // Test validation
        $this->assert_true($portfolio->validate(), 'Valid portfolio should pass validation');
        
        // Test project management
        $portfolio->add_project('proj_1');
        $this->assert_equals(1, $portfolio->get_project_count(), 'Should have 1 project after adding');
        
        echo "Portfolio Model tests completed.\n\n";
    }
    
    private function test_search_query_model() {
        echo "Testing SearchQuery Model...\n";
        
        
        // Test search query creation
        $data = [
            'keyword' => 'forest',
            'location' => 'Brazil',
            'limit' => 25,
        ];
        
        $query = new SearchQuery($data);
        $this->assert_equals('forest', $query->keyword, 'Keyword should match');
        $this->assert_equals('Brazil', $query->location, 'Location should match');
        $this->assert_equals(25, $query->limit, 'Limit should match');
        
        // Test validation
        $this->assert_true($query->validate(), 'Valid search query should pass validation');
        
        // Test filters
        $this->assert_true($query->has_filters(), 'Query with keyword should have filters');
        
        echo "SearchQuery Model tests completed.\n\n";
    }
    
    private function test_order_model() {
        echo "Testing Order Model...\n";
        
        
        // Test order creation
        $data = [
            'id' => 'order_123',
            'vendor_order_id' => 'vendor_456',
            'vendor' => 'cnaught',
            'amount_kg' => 10.0,
            'total_price' => 150.00,
            'status' => 'pending',
        ];
        
        $order = new Order($data);
        $this->assert_equals('order_123', $order->id, 'Order ID should match');
        $this->assert_equals('pending', $order->status, 'Order status should match');
        $this->assert_true($order->is_pending(), 'Order should be pending');
        
        // Test validation
        $this->assert_true($order->validate(), 'Valid order should pass validation');
        
        // Test status changes
        $order->mark_completed();
        $this->assert_true($order->is_completed(), 'Order should be completed after marking');
        
        echo "Order Model tests completed.\n\n";
    }
    
    private function assert_equals($expected, $actual, $message) {
        if ($expected === $actual) {
            $this->tests_passed++;
            echo "  ✓ $message\n";
        } else {
            $this->tests_failed++;
            $this->failures[] = "$message - Expected: $expected, Got: $actual";
            echo "  ✗ $message - Expected: $expected, Got: $actual\n";
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
        echo "Test Results:\n";
        echo "Passed: {$this->tests_passed}\n";
        echo "Failed: {$this->tests_failed}\n";
        
        if ($this->tests_failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $failure) {
                echo "  - $failure\n";
            }
        }
        
        echo "\n" . ($this->tests_failed === 0 ? "All tests passed! ✓" : "Some tests failed! ✗") . "\n";
    }
}

// Run the tests
$runner = new SimpleTestRunner();
$runner->run_all_tests();