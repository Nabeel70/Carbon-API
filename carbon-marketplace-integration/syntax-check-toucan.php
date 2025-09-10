<?php
/**
 * Syntax check for Toucan API Client
 */

// Define required constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
    define('CARBON_MARKETPLACE_VERSION', '1.0.0');
    define('CARBON_MARKETPLACE_PLUGIN_DIR', dirname(__FILE__) . '/');
}

echo "Checking Toucan API Client syntax...\n";

// Check if files exist
$files_to_check = array(
    'includes/api/class-toucan-client.php',
    'includes/api/class-base-api-client.php',
    'includes/models/class-project.php',
    'includes/models/class-portfolio.php',
    'includes/models/class-token-price.php',
    'tests/api/test-toucan-client.php'
);

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✓ {$file} exists\n";
        
        // Check syntax
        $output = array();
        $return_var = 0;
        exec("php -l {$file} 2>&1", $output, $return_var);
        
        if ($return_var === 0) {
            echo "  ✓ Syntax OK\n";
        } else {
            echo "  ✗ Syntax Error: " . implode("\n", $output) . "\n";
        }
    } else {
        echo "✗ {$file} missing\n";
    }
}

// Try to load the autoloader
try {
    require_once 'includes/class-autoloader.php';
    CarbonMarketplace\Autoloader::init();
    echo "✓ Autoloader loaded successfully\n";
} catch (Exception $e) {
    echo "✗ Autoloader failed: " . $e->getMessage() . "\n";
}

// Try to instantiate the Toucan client
try {
    $client = new CarbonMarketplace\API\ToucanClient();
    echo "✓ ToucanClient instantiated successfully\n";
} catch (Exception $e) {
    echo "✗ ToucanClient instantiation failed: " . $e->getMessage() . "\n";
}

echo "\nSyntax check complete.\n";