<?php
/**
 * Final Model Validation
 * Comprehensive test of all model functionality
 */

echo "Carbon Marketplace Model Validation\n";
echo "===================================\n\n";

// Mock WordPress functions
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

// Include model files
require_once __DIR__ . '/includes/models/interface-model.php';
require_once __DIR__ . '/includes/models/abstract-base-model.php';
require_once __DIR__ . '/includes/models/class-project.php';
require_once __DIR__ . '/includes/models/class-portfolio.php';
require_once __DIR__ . '/includes/models/class-search-query.php';
require_once __DIR__ . '/includes/models/class-order.php';

$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;

function run_test($description, $test_function) {
    global $total_tests, $passed_tests, $failed_tests;
    $total_tests++;
    
    try {
        $result = $test_function();
        if ($result) {
            echo "✓ $description\n";
            $passed_tests++;
        } else {
            echo "✗ $description\n";
            $failed_tests++;
        }
    } catch (Exception $e) {
        echo "✗ $description - Exception: " . $e->getMessage() . "\n";
        $failed_tests++;
    } catch (Error $e) {
        echo "✗ $description - Error: " . $e->getMessage() . "\n";
        $failed_tests++;
    }
}

// Test Project Model
echo "Testing Project Model:\n";

run_test("Project creation with valid data", function() {
    $data = [
        'id' => 'proj_123',
        'vendor' => 'cnaught',
        'name' => 'Forest Conservation Project',
        'description' => 'A forest conservation project in Brazil',
        'location' => 'Brazil',
        'project_type' => 'forestry',
        'methodology' => 'VCS',
        'price_per_kg' => 15.50,
        'available_quantity' => 1000,
        'images' => ['image1.jpg', 'image2.jpg'],
        'sdgs' => [13, 15],
        'registry_url' => 'https://registry.verra.org/project/123',
    ];
    
    $project = new CarbonMarketplace\Models\Project($data);
    return $project->id === 'proj_123' && $project->vendor === 'cnaught';
});

run_test("Project validation with valid data", function() {
    $project = new CarbonMarketplace\Models\Project([
        'id' => 'proj_123',
        'vendor' => 'cnaught',
        'name' => 'Test Project',
        'price_per_kg' => 10.00,
        'available_quantity' => 500,
    ]);
    return $project->validate() && empty($project->get_validation_errors());
});

run_test("Project validation with missing required fields", function() {
    $project = new CarbonMarketplace\Models\Project();
    return !$project->validate() && !empty($project->get_validation_errors());
});

run_test("Project serialization (to_array)", function() {
    $project = new CarbonMarketplace\Models\Project([
        'id' => 'proj_123',
        'vendor' => 'cnaught',
        'name' => 'Test Project',
    ]);
    $array = $project->to_array();
    return is_array($array) && $array['id'] === 'proj_123';
});

run_test("Project deserialization (from_array)", function() {
    $data = ['id' => 'proj_123', 'vendor' => 'cnaught', 'name' => 'Test Project'];
    $project = CarbonMarketplace\Models\Project::from_array($data);
    return $project instanceof CarbonMarketplace\Models\Project && $project->id === 'proj_123';
});

run_test("Project JSON serialization", function() {
    $project = new CarbonMarketplace\Models\Project([
        'id' => 'proj_123',
        'vendor' => 'cnaught',
        'name' => 'Test Project',
    ]);
    $json = $project->to_json();
    $decoded = json_decode($json, true);
    return is_string($json) && $decoded['id'] === 'proj_123';
});

run_test("Project availability check", function() {
    $available = new CarbonMarketplace\Models\Project(['available_quantity' => 100]);
    $unavailable = new CarbonMarketplace\Models\Project(['available_quantity' => 0]);
    return $available->is_available() && !$unavailable->is_available();
});

run_test("Project price formatting", function() {
    $project = new CarbonMarketplace\Models\Project(['price_per_kg' => 15.50]);
    return $project->get_formatted_price() === '$15.50/kg';
});

// Test Portfolio Model
echo "\nTesting Portfolio Model:\n";

run_test("Portfolio creation with valid data", function() {
    $data = [
        'id' => 'port_123',
        'vendor' => 'cnaught',
        'name' => 'Mixed Portfolio',
        'base_price_per_kg' => 12.00,
        'is_active' => true,
    ];
    $portfolio = new CarbonMarketplace\Models\Portfolio($data);
    return $portfolio->id === 'port_123' && $portfolio->is_active === true;
});

run_test("Portfolio validation", function() {
    $portfolio = new CarbonMarketplace\Models\Portfolio([
        'id' => 'port_123',
        'vendor' => 'cnaught',
        'name' => 'Test Portfolio',
        'base_price_per_kg' => 10.00,
    ]);
    return $portfolio->validate();
});

run_test("Portfolio project management", function() {
    $portfolio = new CarbonMarketplace\Models\Portfolio([
        'id' => 'port_123',
        'vendor' => 'test',
        'name' => 'Test',
    ]);
    $portfolio->add_project('proj_1');
    $portfolio->add_project('proj_2');
    return $portfolio->get_project_count() === 2 && $portfolio->has_projects();
});

run_test("Portfolio project removal", function() {
    $portfolio = new CarbonMarketplace\Models\Portfolio([
        'id' => 'port_123',
        'vendor' => 'test',
        'name' => 'Test',
        'projects' => ['proj_1', 'proj_2', 'proj_3']
    ]);
    $portfolio->remove_project('proj_2');
    return $portfolio->get_project_count() === 2;
});

// Test SearchQuery Model
echo "\nTesting SearchQuery Model:\n";

run_test("SearchQuery creation with filters", function() {
    $query = new CarbonMarketplace\Models\SearchQuery([
        'keyword' => 'forest',
        'location' => 'Brazil',
        'min_price' => 10.00,
        'max_price' => 20.00,
    ]);
    return $query->keyword === 'forest' && $query->location === 'Brazil';
});

run_test("SearchQuery validation", function() {
    $query = new CarbonMarketplace\Models\SearchQuery([
        'keyword' => 'forest',
        'limit' => 25,
        'sort_by' => 'name',
        'sort_order' => 'asc',
    ]);
    return $query->validate();
});

run_test("SearchQuery filter detection", function() {
    $empty_query = new CarbonMarketplace\Models\SearchQuery();
    $query_with_filters = new CarbonMarketplace\Models\SearchQuery(['keyword' => 'forest']);
    return !$empty_query->has_filters() && $query_with_filters->has_filters();
});

run_test("SearchQuery active filters", function() {
    $query = new CarbonMarketplace\Models\SearchQuery([
        'keyword' => 'forest',
        'location' => 'Brazil',
    ]);
    $filters = $query->get_active_filters();
    return isset($filters['keyword']) && $filters['keyword'] === 'forest';
});

run_test("SearchQuery pagination", function() {
    $query = new CarbonMarketplace\Models\SearchQuery(['limit' => 20, 'offset' => 40]);
    $next_page = $query->get_next_page();
    return $next_page->offset === 60;
});

// Test Order Model
echo "\nTesting Order Model:\n";

run_test("Order creation with valid data", function() {
    $data = [
        'id' => 'order_123',
        'vendor_order_id' => 'vendor_456',
        'vendor' => 'cnaught',
        'amount_kg' => 10.5,
        'total_price' => 157.50,
        'status' => 'pending',
    ];
    $order = new CarbonMarketplace\Models\Order($data);
    return $order->id === 'order_123' && $order->amount_kg === 10.5;
});

run_test("Order validation", function() {
    $order = new CarbonMarketplace\Models\Order([
        'id' => 'order_123',
        'vendor_order_id' => 'vendor_456',
        'vendor' => 'cnaught',
        'amount_kg' => 10.0,
        'total_price' => 150.00,
    ]);
    return $order->validate();
});

run_test("Order status methods", function() {
    $order = new CarbonMarketplace\Models\Order(['status' => 'pending']);
    $is_pending = $order->is_pending();
    $order->mark_completed();
    return $is_pending && $order->is_completed();
});

run_test("Order price calculations", function() {
    $order = new CarbonMarketplace\Models\Order([
        'amount_kg' => 10.0,
        'total_price' => 150.00,
        'currency' => 'USD'
    ]);
    return $order->get_price_per_kg() === 15.0 && 
           strpos($order->get_formatted_total(), '$150.00') !== false;
});

run_test("Order project allocations", function() {
    $order = new CarbonMarketplace\Models\Order();
    $order->add_project_allocation('proj_1', 5.0);
    $order->add_project_allocation('proj_2', 3.5);
    return $order->get_total_allocated() === 8.5;
});

// Test Interface Compliance
echo "\nTesting Interface Compliance:\n";

run_test("All models implement ModelInterface", function() {
    $project = new CarbonMarketplace\Models\Project();
    $portfolio = new CarbonMarketplace\Models\Portfolio();
    $query = new CarbonMarketplace\Models\SearchQuery();
    $order = new CarbonMarketplace\Models\Order();
    
    return $project instanceof CarbonMarketplace\Models\ModelInterface &&
           $portfolio instanceof CarbonMarketplace\Models\ModelInterface &&
           $query instanceof CarbonMarketplace\Models\ModelInterface &&
           $order instanceof CarbonMarketplace\Models\ModelInterface;
});

run_test("All models extend BaseModel", function() {
    $project = new CarbonMarketplace\Models\Project();
    $portfolio = new CarbonMarketplace\Models\Portfolio();
    $query = new CarbonMarketplace\Models\SearchQuery();
    $order = new CarbonMarketplace\Models\Order();
    
    return $project instanceof CarbonMarketplace\Models\BaseModel &&
           $portfolio instanceof CarbonMarketplace\Models\BaseModel &&
           $query instanceof CarbonMarketplace\Models\BaseModel &&
           $order instanceof CarbonMarketplace\Models\BaseModel;
});

// Print Results
echo "\n===================================\n";
echo "Test Results Summary:\n";
echo "Total Tests: $total_tests\n";
echo "Passed: $passed_tests\n";
echo "Failed: $failed_tests\n";
echo "Success Rate: " . round(($passed_tests / $total_tests) * 100, 1) . "%\n";

if ($failed_tests === 0) {
    echo "\n🎉 All tests passed! The model implementation is complete and working correctly.\n";
} else {
    echo "\n⚠️  Some tests failed. Please review the implementation.\n";
}

echo "\nModel Features Implemented:\n";
echo "✓ Project model with validation and serialization\n";
echo "✓ Portfolio model with project management\n";
echo "✓ SearchQuery model with filtering and pagination\n";
echo "✓ Order model with status management and allocations\n";
echo "✓ Base model interface for consistent data handling\n";
echo "✓ Comprehensive validation logic\n";
echo "✓ JSON serialization/deserialization\n";
echo "✓ Data transformation methods\n";
echo "✓ Business logic methods (pricing, availability, etc.)\n";