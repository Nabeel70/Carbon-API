<?php
/**
 * Model Validation Script
 * Simple validation to check if models are working correctly
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

// Define plugin constants
define('CARBON_MARKETPLACE_PLUGIN_DIR', __DIR__);

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

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Include the autoloader
require_once __DIR__ . '/includes/class-autoloader.php';

// Initialize autoloader
CarbonMarketplace\Autoloader::init();

echo "Carbon Marketplace Model Validation\n";
echo "===================================\n\n";

try {
    // Test Project Model
    echo "Testing Project Model...\n";
    $project_data = [
        'id' => 'proj_123',
        'vendor' => 'cnaught',
        'name' => 'Test Forest Project',
        'description' => 'A test forest conservation project',
        'location' => 'Brazil',
        'project_type' => 'forestry',
        'methodology' => 'VCS',
        'price_per_kg' => 15.50,
        'available_quantity' => 1000,
        'images' => ['image1.jpg', 'image2.jpg'],
        'sdgs' => [13, 15],
        'registry_url' => 'https://registry.verra.org/project/123',
    ];
    
    $project = new CarbonMarketplace\Models\Project($project_data);
    
    if ($project->validate()) {
        echo "✓ Project validation passed\n";
    } else {
        echo "✗ Project validation failed: " . implode(', ', $project->get_validation_errors()) . "\n";
    }
    
    $project_array = $project->to_array();
    if (is_array($project_array) && $project_array['id'] === 'proj_123') {
        echo "✓ Project serialization works\n";
    } else {
        echo "✗ Project serialization failed\n";
    }
    
    $project_from_array = CarbonMarketplace\Models\Project::from_array($project_data);
    if ($project_from_array->id === 'proj_123') {
        echo "✓ Project deserialization works\n";
    } else {
        echo "✗ Project deserialization failed\n";
    }
    
    // Test Portfolio Model
    echo "\nTesting Portfolio Model...\n";
    $portfolio_data = [
        'id' => 'port_123',
        'vendor' => 'cnaught',
        'name' => 'Mixed Portfolio',
        'description' => 'A diverse portfolio of projects',
        'projects' => ['proj_1', 'proj_2'],
        'base_price_per_kg' => 12.00,
        'is_active' => true,
    ];
    
    $portfolio = new CarbonMarketplace\Models\Portfolio($portfolio_data);
    
    if ($portfolio->validate()) {
        echo "✓ Portfolio validation passed\n";
    } else {
        echo "✗ Portfolio validation failed: " . implode(', ', $portfolio->get_validation_errors()) . "\n";
    }
    
    $portfolio->add_project('proj_3');
    if ($portfolio->get_project_count() === 3) {
        echo "✓ Portfolio project management works\n";
    } else {
        echo "✗ Portfolio project management failed\n";
    }
    
    // Test SearchQuery Model
    echo "\nTesting SearchQuery Model...\n";
    $query_data = [
        'keyword' => 'forest',
        'location' => 'Brazil',
        'project_type' => 'forestry',
        'min_price' => 10.00,
        'max_price' => 20.00,
        'limit' => 25,
        'sort_by' => 'price_per_kg',
        'sort_order' => 'desc',
    ];
    
    $query = new CarbonMarketplace\Models\SearchQuery($query_data);
    
    if ($query->validate()) {
        echo "✓ SearchQuery validation passed\n";
    } else {
        echo "✗ SearchQuery validation failed: " . implode(', ', $query->get_validation_errors()) . "\n";
    }
    
    if ($query->has_filters()) {
        echo "✓ SearchQuery filter detection works\n";
    } else {
        echo "✗ SearchQuery filter detection failed\n";
    }
    
    $active_filters = $query->get_active_filters();
    if (isset($active_filters['keyword']) && $active_filters['keyword'] === 'forest') {
        echo "✓ SearchQuery active filters work\n";
    } else {
        echo "✗ SearchQuery active filters failed\n";
    }
    
    // Test Order Model
    echo "\nTesting Order Model...\n";
    $order_data = [
        'id' => 'order_123',
        'vendor_order_id' => 'vendor_456',
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
    
    $order = new CarbonMarketplace\Models\Order($order_data);
    
    if ($order->validate()) {
        echo "✓ Order validation passed\n";
    } else {
        echo "✗ Order validation failed: " . implode(', ', $order->get_validation_errors()) . "\n";
    }
    
    if ($order->is_pending()) {
        echo "✓ Order status detection works\n";
    } else {
        echo "✗ Order status detection failed\n";
    }
    
    $order->mark_completed();
    if ($order->is_completed()) {
        echo "✓ Order status change works\n";
    } else {
        echo "✗ Order status change failed\n";
    }
    
    $formatted_total = $order->get_formatted_total();
    if (strpos($formatted_total, '$157.50') !== false) {
        echo "✓ Order price formatting works\n";
    } else {
        echo "✗ Order price formatting failed: got '$formatted_total'\n";
    }
    
    echo "\n===================================\n";
    echo "Model validation completed!\n";
    
} catch (Exception $e) {
    echo "Error during validation: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}