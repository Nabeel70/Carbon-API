<?php
/**
 * Basic test to check if plugin classes can be loaded without fatal errors
 */

// Mock WordPress functions that are needed
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.com/wp-content/plugins/carbon-marketplace-integration/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return 'carbon-marketplace-integration/carbon-marketplace-integration.php';
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// Define plugin constants
define('CARBON_MARKETPLACE_VERSION', '1.0.0');
define('CARBON_MARKETPLACE_PLUGIN_FILE', __FILE__);
define('CARBON_MARKETPLACE_PLUGIN_DIR', __DIR__ . '/carbon-marketplace-integration/');
define('CARBON_MARKETPLACE_PLUGIN_URL', 'http://example.com/wp-content/plugins/carbon-marketplace-integration/');
define('CARBON_MARKETPLACE_PLUGIN_BASENAME', 'carbon-marketplace-integration/carbon-marketplace-integration.php');

try {
    echo "Testing plugin class loading...\n";
    
    // Include the autoloader
    require_once CARBON_MARKETPLACE_PLUGIN_DIR . 'includes/class-autoloader.php';
    
    // Initialize autoloader
    CarbonMarketplace\Autoloader::init();
    
    echo "✓ Autoloader initialized successfully\n";
    
    // Test the expected file path manually
    $expected_path = CARBON_MARKETPLACE_PLUGIN_DIR . 'includes/core/class-database.php';
    echo "Expected database file path: $expected_path\n";
    echo "File exists: " . (file_exists($expected_path) ? 'YES' : 'NO') . "\n";
    
    // Test the fixed autoloader now
    $database = new CarbonMarketplace\Core\Database();
    echo "✓ Database class loaded via autoloader\n";
    
    // We can't fully test the main plugin class without WordPress functions
    echo "✓ Basic plugin structure appears to be valid\n";
    
    echo "\nAll basic tests passed! Plugin should be able to activate.\n";
    
} catch (Error $e) {
    echo "✗ Fatal Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}