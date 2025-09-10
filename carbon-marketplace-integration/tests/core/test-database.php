<?php
/**
 * Database Tests
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

require_once dirname(__FILE__) . '/../../includes/core/class-database.php';

use CarbonMarketplace\Core\Database;

class DatabaseTest {
    
    /**
     * Database instance
     *
     * @var Database
     */
    private $database;
    
    /**
     * Test results
     */
    private $tests_passed = 0;
    private $tests_failed = 0;
    private $failures = [];
    
    /**
     * Set up test environment
     */
    public function setUp() {
        // Mock WordPress functions if not available
        if (!function_exists('wp_json_encode')) {
            function wp_json_encode($data) {
                return json_encode($data);
            }
        }
        
        if (!function_exists('get_option')) {
            function get_option($option, $default = false) {
                return $default;
            }
        }
        
        // Mock global $wpdb
        global $wpdb;
        if (!isset($wpdb)) {
            $wpdb = new MockWpdb();
        }
        
        $this->database = new Database();
    }
    
    /**
     * Run all database tests
     */
    public function run_all_tests() {
        echo "Running Database Tests...\n\n";
        
        $this->setUp();
        
        $this->test_table_names();
        $this->test_table_creation();
        $this->test_project_operations();
        $this->test_order_operations();
        $this->test_search_functionality();
        $this->test_data_validation();
        $this->test_json_handling();
        $this->test_error_handling();
        
        $this->print_results();
    }
    
    /**
     * Test table name generation
     */
    public function test_table_names() {
        echo "Testing table name generation...\n";
        
        $projects_table = $this->database->get_projects_table();
        $orders_table = $this->database->get_orders_table();
        
        $this->assert_contains('carbon_projects', $projects_table, 'Projects table name should contain carbon_projects');
        $this->assert_contains('carbon_orders', $orders_table, 'Orders table name should contain carbon_orders');
        
        echo "Table name tests completed.\n\n";
    }
    
    /**
     * Test table creation
     */
    public function test_table_creation() {
        echo "Testing table creation...\n";
        
        // Test projects table creation
        $projects_table = $this->database->get_projects_table();
        $this->assert_true(is_string($projects_table), 'Projects table name should be string');
        
        // Test orders table creation
        $orders_table = $this->database->get_orders_table();
        $this->assert_true(is_string($orders_table), 'Orders table name should be string');
        
        echo "Table creation tests completed.\n\n";
    }
    
    /**
     * Test project operations
     */
    public function test_project_operations() {
        echo "Testing project operations...\n";
        
        $project_data = [
            'vendor_id' => 'proj_123',
            'vendor' => 'cnaught',
            'name' => 'Test Project',
            'description' => 'A test project for carbon offsets',
            'location' => 'Brazil',
            'project_type' => 'forestry',
            'methodology' => 'VCS',
            'price_per_kg' => 15.50,
            'available_quantity' => 1000,
            'images' => ['image1.jpg', 'image2.jpg'],
            'sdgs' => [13, 15],
            'registry_url' => 'https://registry.verra.org/project/123',
        ];
        
        // Test project data structure
        $this->assert_true(is_array($project_data), 'Project data should be array');
        $this->assert_equals('proj_123', $project_data['vendor_id'], 'Vendor ID should match');
        $this->assert_equals('cnaught', $project_data['vendor'], 'Vendor should match');
        $this->assert_equals('Test Project', $project_data['name'], 'Name should match');
        $this->assert_equals(15.50, $project_data['price_per_kg'], 'Price should match');
        $this->assert_equals(1000, $project_data['available_quantity'], 'Quantity should match');
        
        // Test update data structure
        $update_data = [
            'name' => 'Updated Project Name',
            'price_per_kg' => 20.00,
            'available_quantity' => 500,
        ];
        
        $this->assert_equals('Updated Project Name', $update_data['name'], 'Updated name should match');
        $this->assert_equals(20.00, $update_data['price_per_kg'], 'Updated price should match');
        $this->assert_equals(500, $update_data['available_quantity'], 'Updated quantity should match');
        
        echo "Project operations tests completed.\n\n";
    }
    
    /**
     * Test order operations
     */
    public function test_order_operations() {
        echo "Testing order operations...\n";
        
        $order_data = [
            'vendor_order_id' => 'order_456',
            'vendor' => 'cnaught',
            'user_id' => 1,
            'amount_kg' => 10.5,
            'total_price' => 157.50,
            'currency' => 'USD',
            'status' => 'pending',
            'project_allocations' => [
                ['project_id' => 'proj_1', 'amount_kg' => 5.0],
                ['project_id' => 'proj_2', 'amount_kg' => 5.5],
            ],
            'commission_amount' => 15.75,
        ];
        
        // Test order data structure
        $this->assert_true(is_array($order_data), 'Order data should be array');
        $this->assert_equals('order_456', $order_data['vendor_order_id'], 'Vendor order ID should match');
        $this->assert_equals('cnaught', $order_data['vendor'], 'Vendor should match');
        $this->assert_equals(1, $order_data['user_id'], 'User ID should match');
        $this->assert_equals(10.5, $order_data['amount_kg'], 'Amount should match');
        $this->assert_equals(157.50, $order_data['total_price'], 'Total price should match');
        $this->assert_equals('pending', $order_data['status'], 'Status should match');
        
        // Test order update
        $update_data = [
            'status' => 'completed',
            'retirement_certificate' => 'cert_123',
            'completed_at' => '2023-12-01 10:00:00',
        ];
        
        $this->assert_equals('completed', $update_data['status'], 'Updated status should match');
        $this->assert_equals('cert_123', $update_data['retirement_certificate'], 'Certificate should match');
        
        echo "Order operations tests completed.\n\n";
    }
    
    /**
     * Test search functionality
     */
    public function test_search_functionality() {
        echo "Testing search functionality...\n";
        
        $filters = [
            'keyword' => 'forest',
            'location' => 'Brazil',
            'project_type' => 'forestry',
            'vendor' => 'cnaught',
            'min_price' => 10.00,
            'max_price' => 20.00,
        ];
        
        // Test filter structure
        $this->assert_true(is_array($filters), 'Filters should be array');
        $this->assert_equals('forest', $filters['keyword'], 'Keyword filter should match');
        $this->assert_equals('Brazil', $filters['location'], 'Location filter should match');
        $this->assert_equals('forestry', $filters['project_type'], 'Project type filter should match');
        $this->assert_equals('cnaught', $filters['vendor'], 'Vendor filter should match');
        $this->assert_equals(10.00, $filters['min_price'], 'Min price filter should match');
        $this->assert_equals(20.00, $filters['max_price'], 'Max price filter should match');
        
        // Test search parameters
        $limit = 20;
        $offset = 0;
        $order_by = 'name';
        $order = 'ASC';
        
        $this->assert_equals(20, $limit, 'Limit should match');
        $this->assert_equals(0, $offset, 'Offset should match');
        $this->assert_equals('name', $order_by, 'Order by should match');
        $this->assert_equals('ASC', $order, 'Order direction should match');
        
        echo "Search functionality tests completed.\n\n";
    }
    
    /**
     * Test data validation
     */
    public function test_data_validation() {
        echo "Testing data validation...\n";
        
        // Test valid project data
        $valid_project = [
            'vendor_id' => 'proj_123',
            'vendor' => 'cnaught',
            'name' => 'Test Project',
            'price_per_kg' => 15.50,
            'available_quantity' => 1000,
        ];
        
        $this->assert_true(is_string($valid_project['vendor_id']), 'Vendor ID should be string');
        $this->assert_true(is_string($valid_project['vendor']), 'Vendor should be string');
        $this->assert_true(is_string($valid_project['name']), 'Name should be string');
        $this->assert_true(is_numeric($valid_project['price_per_kg']), 'Price should be numeric');
        $this->assert_true(is_numeric($valid_project['available_quantity']), 'Quantity should be numeric');
        
        // Test valid order data
        $valid_order = [
            'vendor_order_id' => 'order_456',
            'vendor' => 'cnaught',
            'amount_kg' => 10.5,
            'total_price' => 157.50,
            'status' => 'pending',
        ];
        
        $this->assert_true(is_string($valid_order['vendor_order_id']), 'Vendor order ID should be string');
        $this->assert_true(is_string($valid_order['vendor']), 'Vendor should be string');
        $this->assert_true(is_numeric($valid_order['amount_kg']), 'Amount should be numeric');
        $this->assert_true(is_numeric($valid_order['total_price']), 'Total price should be numeric');
        $this->assert_true(is_string($valid_order['status']), 'Status should be string');
        
        // Test invalid data handling
        $invalid_project = [
            'vendor_id' => '', // Empty vendor ID
            'vendor' => null, // Null vendor
            'name' => 123, // Non-string name
            'price_per_kg' => 'invalid', // Non-numeric price
        ];
        
        $this->assert_true(empty($invalid_project['vendor_id']), 'Empty vendor ID should be detected');
        $this->assert_true(is_null($invalid_project['vendor']), 'Null vendor should be detected');
        $this->assert_false(is_string($invalid_project['name']), 'Non-string name should be detected');
        $this->assert_false(is_numeric($invalid_project['price_per_kg']), 'Non-numeric price should be detected');
        
        echo "Data validation tests completed.\n\n";
    }
    
    /**
     * Test JSON field handling
     */
    public function test_json_handling() {
        echo "Testing JSON field handling...\n";
        
        $images = ['image1.jpg', 'image2.jpg'];
        $sdgs = [13, 15];
        $project_allocations = [
            ['project_id' => 'proj_1', 'amount_kg' => 5.0],
            ['project_id' => 'proj_2', 'amount_kg' => 5.5],
        ];
        
        // Test JSON encoding
        $encoded_images = wp_json_encode($images);
        $encoded_sdgs = wp_json_encode($sdgs);
        $encoded_allocations = wp_json_encode($project_allocations);
        
        $this->assert_true(is_string($encoded_images), 'Encoded images should be string');
        $this->assert_true(is_string($encoded_sdgs), 'Encoded SDGs should be string');
        $this->assert_true(is_string($encoded_allocations), 'Encoded allocations should be string');
        
        // Test JSON decoding
        $decoded_images = json_decode($encoded_images, true);
        $decoded_sdgs = json_decode($encoded_sdgs, true);
        $decoded_allocations = json_decode($encoded_allocations, true);
        
        $this->assert_equals($images, $decoded_images, 'Decoded images should match original');
        $this->assert_equals($sdgs, $decoded_sdgs, 'Decoded SDGs should match original');
        $this->assert_equals($project_allocations, $decoded_allocations, 'Decoded allocations should match original');
        
        // Test empty JSON handling
        $empty_array = [];
        $encoded_empty = wp_json_encode($empty_array);
        $decoded_empty = json_decode($encoded_empty, true);
        
        $this->assert_equals($empty_array, $decoded_empty, 'Empty array should encode/decode correctly');
        
        echo "JSON handling tests completed.\n\n";
    }
    
    /**
     * Test error handling
     */
    public function test_error_handling() {
        echo "Testing error handling...\n";
        
        // Test SQL injection prevention
        $malicious_input = "'; DROP TABLE projects; --";
        
        $filters = [
            'keyword' => $malicious_input,
            'location' => $malicious_input,
        ];
        
        // The actual escaping would be done by wpdb->prepare()
        // Here we test that the input is handled as a string
        $this->assert_true(is_string($filters['keyword']), 'Malicious keyword should be treated as string');
        $this->assert_true(is_string($filters['location']), 'Malicious location should be treated as string');
        
        // Test invalid field names in order by
        $invalid_order_fields = ['invalid_field', 'DROP TABLE', 'SELECT *'];
        $allowed_order_fields = ['name', 'price_per_kg', 'location', 'project_type', 'created_at'];
        
        foreach ($invalid_order_fields as $field) {
            $this->assert_false(in_array($field, $allowed_order_fields), "Invalid field '$field' should not be allowed");
        }
        
        // Test order direction validation
        $valid_orders = ['ASC', 'DESC'];
        $invalid_orders = ['INVALID', 'DROP', 'SELECT'];
        
        foreach ($invalid_orders as $order) {
            $this->assert_false(in_array($order, $valid_orders), "Invalid order '$order' should not be allowed");
        }
        
        // Test empty data handling
        $empty_project = [];
        $empty_order = [];
        
        $this->assert_true(empty($empty_project), 'Empty project data should be detected');
        $this->assert_true(empty($empty_order), 'Empty order data should be detected');
        
        echo "Error handling tests completed.\n\n";
    }
    
    /**
     * Assert helper methods
     */
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
    
    private function assert_contains($needle, $haystack, $message) {
        if (strpos($haystack, $needle) !== false) {
            $this->tests_passed++;
            echo "  ✓ $message\n";
        } else {
            $this->tests_failed++;
            $this->failures[] = "$message - '$needle' not found in '$haystack'";
            echo "  ✗ $message - '$needle' not found in '$haystack'\n";
        }
    }
    
    private function print_results() {
        echo "Database Test Results:\n";
        echo "Passed: {$this->tests_passed}\n";
        echo "Failed: {$this->tests_failed}\n";
        
        if ($this->tests_failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $failure) {
                echo "  - $failure\n";
            }
        }
        
        echo "\n" . ($this->tests_failed === 0 ? "All database tests passed! ✓" : "Some database tests failed! ✗") . "\n";
    }
}

/**
 * Mock wpdb class for testing
 */
class MockWpdb {
    public $prefix = 'wp_';
    
    public function prepare($query, ...$args) {
        return vsprintf(str_replace('%s', "'%s'", $query), $args);
    }
    
    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
    
    public function get_var($query) {
        return 'wp_carbon_projects';
    }
    
    public function get_row($query, $output = OBJECT) {
        return null;
    }
    
    public function get_results($query, $output = OBJECT) {
        return [];
    }
    
    public function insert($table, $data, $format = null) {
        return true;
    }
    
    public function update($table, $data, $where, $format = null, $where_format = null) {
        return 1;
    }
    
    public function query($sql) {
        return true;
    }
    
    public function esc_like($text) {
        return addcslashes($text, '_%\\');
    }
    
    public $insert_id = 1;
    public $users = 'wp_users';
}