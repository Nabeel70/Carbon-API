<?php
/**
 * Simple Model Test
 * Direct inclusion and testing of model classes
 */

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

// Include model files directly
require_once __DIR__ . '/includes/models/interface-model.php';
require_once __DIR__ . '/includes/models/abstract-base-model.php';
require_once __DIR__ . '/includes/models/class-project.php';
require_once __DIR__ . '/includes/models/class-portfolio.php';
require_once __DIR__ . '/includes/models/class-search-query.php';
require_once __DIR__ . '/includes/models/class-order.php';

echo "Testing Carbon Marketplace Models\n";
echo "=================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

function test_assert($condition, $message) {
    global $tests_passed, $tests_failed;
    if ($condition) {
        echo "✓ $message\n";
        $tests_passed++;
    } else {
        echo "✗ $message\n";
        $tests_failed++;
    }
}

try {
    // Test Project Model
    echo "Testing Project Model:\n";
    
    $project_data = [
        'id' => 'proj_123',
        'vendor' => 'cnaught',
        'name' => 'Test Forest Project',
        'price_per_kg' => 15.50,
        'available_quantity' => 1000,
    ];
    
    $project = new CarbonMarketplace\Models\Project($project_data);
    test_assert($project->id === 'proj_123', 'Project ID assignment');
    test_assert($project->validate(), 'Project validation');
    test_assert($project->is_available(), 'Project availability check');
    test_assert(strpos($project->get_formatted_price(), '15.50') !== false, 'Project price formatting');
    
    $project_array = $project->to_array();
    test_assert(is_array($project_array), 'Project to_array conversion');
    test_assert($project_array['id'] === 'proj_123', 'Project array contains correct data');
    
    $project_from_array = CarbonMarketplace\Models\Project::from_array($project_data);
    test_assert($project_from_array instanceof CarbonMarketplace\Models\Project, 'Project from_array creation');
    
    echo "\nTesting Portfolio Model:\n";
    
    $portfolio_data = [
        'id' => 'port_123',
        'vendor' => 'cnaught',
        'name' => 'Test Portfolio',
        'base_price_per_kg' => 12.00,
        'is_active' => true,
    ];
    
    $portfolio = new CarbonMarketplace\Models\Portfolio($portfolio_data);
    test_assert($portfolio->id === 'port_123', 'Portfolio ID assignment');
    test_assert($portfolio->validate(), 'Portfolio validation');
    test_assert($portfolio->is_active === true, 'Portfolio active status');
    
    $portfolio->add_project('proj_1');
    test_assert($portfolio->get_project_count() === 1, 'Portfolio project addition');
    test_assert($portfolio->has_projects(), 'Portfolio has projects check');
    
    echo "\nTesting SearchQuery Model:\n";
    
    $query_data = [
        'keyword' => 'forest',
        'location' => 'Brazil',
        'min_price' => 10.00,
        'max_price' => 20.00,
        'limit' => 25,
    ];
    
    $query = new CarbonMarketplace\Models\SearchQuery($query_data);
    test_assert($query->keyword === 'forest', 'SearchQuery keyword assignment');
    test_assert($query->validate(), 'SearchQuery validation');
    test_assert($query->has_filters(), 'SearchQuery has filters check');
    
    $active_filters = $query->get_active_filters();
    test_assert(isset($active_filters['keyword']), 'SearchQuery active filters');
    
    $next_page = $query->get_next_page();
    test_assert($next_page->offset === 25, 'SearchQuery pagination');
    
    echo "\nTesting Order Model:\n";
    
    $order_data = [
        'id' => 'order_123',
        'vendor_order_id' => 'vendor_456',
        'vendor' => 'cnaught',
        'amount_kg' => 10.0,
        'total_price' => 150.00,
        'status' => 'pending',
    ];
    
    $order = new CarbonMarketplace\Models\Order($order_data);
    test_assert($order->id === 'order_123', 'Order ID assignment');
    test_assert($order->validate(), 'Order validation');
    test_assert($order->is_pending(), 'Order status check');
    
    $order->mark_completed();
    test_assert($order->is_completed(), 'Order status change');
    
    $formatted_total = $order->get_formatted_total();
    test_assert(strpos($formatted_total, '150.00') !== false, 'Order price formatting');
    
    $order->add_project_allocation('proj_1', 5.0);
    test_assert($order->get_total_allocated() === 5.0, 'Order project allocation');
    
    echo "\n=================================\n";
    echo "Tests completed!\n";
    echo "Passed: $tests_passed\n";
    echo "Failed: $tests_failed\n";
    
    if ($tests_failed === 0) {
        echo "All tests passed! ✓\n";
    } else {
        echo "Some tests failed! ✗\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}