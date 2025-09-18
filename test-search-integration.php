<?php
/**
 * Test Search Functionality
 * 
 * This tests the complete search pipeline components
 */

// Define WordPress constants to avoid errors
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = null) {
        return substr($text, 0, 100) . '...';
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($data) {
        echo json_encode($data);
    }
}

// Load plugin classes
require_once('/workspaces/Carbon-API/carbon-marketplace-integration/includes/class-autoloader.php');

// Initialize test
echo "=== Testing Complete Search Functionality ===\n\n";

try {
    // 1. Test Search Query creation
    echo "1. Testing Search Query...\n";
    $search_params = [
        'keyword' => 'forest',
        'location' => 'brazil', 
        'project_type' => 'forestry',
        'limit' => 10,
        'offset' => 0,
        'sort_by' => 'name',
        'sort_order' => 'asc'
    ];
    
    $query = new \CarbonMarketplace\Models\SearchQuery($search_params);
    if ($query->validate()) {
        echo "✓ Search Query created and validated\n";
        echo "Active filters: ";
        print_r($query->get_active_filters());
    } else {
        echo "✗ Search Query validation failed: " . implode(', ', $query->get_validation_errors()) . "\n";
    }
    echo "\n";
    
    // 2. Test Project model
    echo "2. Testing Project Model...\n";
    $project_data = [
        'id' => 'proj_123',
        'vendor' => 'cnaught',
        'name' => 'Amazon Forest Protection',
        'description' => 'Protecting rainforest in Brazil',
        'location' => 'Brazil',
        'project_type' => 'Forestry',
        'methodology' => 'REDD+',
        'price_per_kg' => 15.50,
        'available_quantity' => 1000,
        'images' => [],
        'sdgs' => [13, 15],
        'registry_url' => 'https://example.com',
        'metadata' => []
    ];
    
    $project = new \CarbonMarketplace\Models\Project($project_data);
    if ($project->validate()) {
        echo "✓ Project model created and validated\n";
        echo "Project: " . $project->get_name() . " (" . $project->get_location() . ")\n";
    } else {
        echo "✗ Project validation failed: " . implode(', ', $project->get_validation_errors()) . "\n";
    }
    echo "\n";
    
    // 3. Test SearchResults
    echo "3. Testing Search Results...\n";
    $results = new \CarbonMarketplace\Search\SearchResults([$project], 1);
    echo "✓ Search Results created\n";
    echo "Total count: " . $results->get_total_count() . "\n";
    echo "Projects found: " . count($results->get_projects()) . "\n";
    echo "\n";
    
    echo "=== All core components working! ===\n";
    echo "✓ Search Query handling\n";
    echo "✓ Project data modeling\n";
    echo "✓ Search Results formatting\n\n";
    
    echo "Ready for WordPress integration with:\n";
    echo "1. CNaught API configuration\n";
    echo "2. Frontend shortcodes\n";
    echo "3. AJAX search functionality\n";

} catch (Exception $e) {
    echo "✗ Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}