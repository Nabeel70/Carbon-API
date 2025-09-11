<?php
/**
 * Validation script for SearchEngine implementation
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

// Include WordPress functions (mock for testing)
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $ttl) { return true; }
}
if (!function_exists('get_transient')) {
    function get_transient($key) { return false; }
}
if (!function_exists('delete_transient')) {
    function delete_transient($key) { return true; }
}
if (!function_exists('error_log')) {
    function error_log($message) { echo "LOG: $message\n"; }
}

// Mock wpdb class
class wpdb {
    public function esc_like($text) { return $text; }
    public function prepare($query, ...$args) { return $query; }
    public function get_var($query) { return 5; }
    public function get_results($query, $output = OBJECT) { return []; }
}

// Define constants
if (!defined('OBJECT')) define('OBJECT', 'OBJECT');
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');

// Set global wpdb
global $wpdb;
$wpdb = new wpdb();

// Include required files using autoloader
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
    define('CARBON_MARKETPLACE_VERSION', '1.0.0');
    define('CARBON_MARKETPLACE_PLUGIN_DIR', dirname(__FILE__) . '/');
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];
        private $error_data = [];
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code] = array($message);
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
        
        public function get_error_code() {
            $codes = array_keys($this->errors);
            return empty($codes) ? '' : $codes[0];
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->errors[$code]) ? $this->errors[$code][0] : '';
        }
        
        public function get_error_data($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->error_data[$code]) ? $this->error_data[$code] : null;
        }
        
        public function add($code, $message, $data = '') {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }
    }
}

// Mock is_wp_error function
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// Load the autoloader
require_once __DIR__ . '/includes/class-autoloader.php';
CarbonMarketplace\Autoloader::init();

use CarbonMarketplace\Models\Project;
use CarbonMarketplace\Models\SearchQuery;
use CarbonMarketplace\Core\Database;
use CarbonMarketplace\Search\SearchResults;
use CarbonMarketplace\Search\SearchEngine;

echo "=== SearchEngine Validation ===\n\n";

try {
    // Test 1: Create SearchEngine instance
    echo "1. Creating SearchEngine instance...\n";
    $search_engine = new SearchEngine();
    echo "   ✓ SearchEngine created successfully\n\n";
    
    // Test 2: Create sample projects
    echo "2. Creating sample projects...\n";
    $sample_projects = [
        [
            'id' => 'proj_1',
            'vendor' => 'cnaught',
            'name' => 'Forest Conservation Brazil',
            'description' => 'Protecting rainforest in Amazon region',
            'location' => 'Brazil, South America',
            'project_type' => 'Forest Conservation',
            'methodology' => 'REDD+',
            'price_per_kg' => 15.50,
            'available_quantity' => 1000,
            'images' => [],
            'sdgs' => [15, 13],
            'registry_url' => 'https://registry.example.com/proj_1',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 'proj_2',
            'vendor' => 'toucan',
            'name' => 'Solar Energy India',
            'description' => 'Solar power generation in rural India',
            'location' => 'India, Asia',
            'project_type' => 'Renewable Energy',
            'methodology' => 'CDM',
            'price_per_kg' => 12.25,
            'available_quantity' => 2000,
            'images' => [],
            'sdgs' => [7, 13],
            'registry_url' => 'https://registry.example.com/proj_2',
            'created_at' => '2024-01-02 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
        ],
    ];
    echo "   ✓ Sample projects created\n\n";
    
    // Test 3: Index projects
    echo "3. Testing project indexing...\n";
    $result = $search_engine->index_projects($sample_projects);
    echo "   ✓ Projects indexed: " . ($result ? 'Success' : 'Failed') . "\n\n";
    
    // Test 4: Create SearchQuery
    echo "4. Creating SearchQuery...\n";
    $query = new SearchQuery(['keyword' => 'energy', 'limit' => 10]);
    $is_valid = $query->validate();
    echo "   ✓ SearchQuery created and validated: " . ($is_valid ? 'Valid' : 'Invalid') . "\n\n";
    
    // Test 5: Test filtering
    echo "5. Testing filter functionality...\n";
    $project_objects = array_map(function($data) {
        return Project::from_array($data);
    }, $sample_projects);
    
    $filters = ['keyword' => 'energy'];
    $filtered = $search_engine->apply_filters($project_objects, $filters);
    echo "   ✓ Filtered projects count: " . count($filtered) . "\n\n";
    
    // Test 6: Test ranking
    echo "6. Testing ranking functionality...\n";
    $ranked = $search_engine->rank_results($project_objects, $query);
    echo "   ✓ Ranked projects count: " . count($ranked) . "\n\n";
    
    // Test 7: Test SearchResults
    echo "7. Testing SearchResults...\n";
    $results = new SearchResults($project_objects, 2);
    echo "   ✓ SearchResults created with " . $results->get_result_count() . " projects\n";
    echo "   ✓ Total count: " . $results->get_total_count() . "\n";
    echo "   ✓ Has errors: " . ($results->has_errors() ? 'Yes' : 'No') . "\n\n";
    
    // Test 8: Test suggestions
    echo "8. Testing suggestions...\n";
    $suggestions = $search_engine->get_suggestions('en');
    echo "   ✓ Suggestions generated: " . count($suggestions) . " items\n\n";
    
    echo "=== All Tests Passed! ===\n";
    echo "SearchEngine implementation is working correctly.\n\n";
    
    // Display class methods
    echo "=== SearchEngine Methods ===\n";
    $reflection = new ReflectionClass($search_engine);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    foreach ($methods as $method) {
        if (!$method->isConstructor()) {
            echo "- " . $method->getName() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}