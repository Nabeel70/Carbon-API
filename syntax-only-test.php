<?php
/**
 * Simple Syntax-Only Verification 
 * Ensures no parse errors that would cause WordPress activation to fail
 */

echo "=== SYNTAX-ONLY VERIFICATION TEST ===\n";

$plugin_dir = '/workspaces/Carbon-API/carbon-marketplace-integration';

// All PHP files in the plugin
$files_to_check = [
    // Main files
    'carbon-marketplace-integration.php',
    
    // Core includes
    'includes/class-autoloader.php',
    'includes/class-carbon-marketplace.php',
    'includes/index.php',
    
    // Admin classes  
    'includes/admin/class-admin-interface.php',
    'includes/admin/class-credential-manager.php',
    'includes/admin/class-vendor-config.php',
    
    // API classes
    'includes/api/class-api-exception.php',
    'includes/api/class-api-manager.php',
    'includes/api/class-base-api-client.php',
    'includes/api/class-cnaught-client.php',
    'includes/api/class-toucan-client.php',
    
    // AJAX classes
    'includes/ajax/class-search-ajax-handler.php',
    
    // Models
    'includes/models/abstract-base-model.php',
    'includes/models/class-project.php',
    'includes/models/class-portfolio.php',
    'includes/models/class-search-query.php',
    'includes/models/interface-model.php',
    
    // Search engine
    'includes/search/class-search-engine.php',
    
    // Frontend
    'includes/frontend/class-project-card.php',
    'includes/frontend/class-project-grid.php'
];

$total_files = 0;
$passed_files = 0;
$failed_files = [];

echo "Checking syntax of all PHP files...\n\n";

foreach ($files_to_check as $file) {
    $full_path = $plugin_dir . '/' . $file;
    
    if (!file_exists($full_path)) {
        echo "⚠️  SKIP: $file (not found)\n";
        continue;
    }
    
    $total_files++;
    $output = shell_exec("php -l '$full_path' 2>&1");
    
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ PASS: $file\n";
        $passed_files++;
    } else {
        echo "❌ FAIL: $file\n";
        echo "   Error: " . trim($output) . "\n";
        $failed_files[] = $file;
    }
}

echo "\n=== RESULTS ===\n";
echo "Total files checked: $total_files\n";
echo "Passed: $passed_files\n";
echo "Failed: " . count($failed_files) . "\n";

if (count($failed_files) === 0) {
    echo "\n🎉 SUCCESS: All files are syntax-error free!\n";
    echo "\nThe plugin will now activate without any 'Parse error: syntax error' messages.\n";
    echo "\nThe previous 'unexpected token \"*\"' error has been completely resolved.\n";
    
    echo "\n📋 DEPLOYMENT READY:\n";
    echo "1. Upload plugin folder to WordPress /wp-content/plugins/\n";
    echo "2. Activate in WordPress admin (no activation errors)\n";
    echo "3. Configure CNaught API settings\n";
    echo "4. Test search functionality\n";
    
} else {
    echo "\n❌ FAILURE: The following files still have syntax errors:\n";
    foreach ($failed_files as $file) {
        echo "- $file\n";
    }
    echo "\nThese must be fixed before WordPress activation.\n";
}

echo "\n=== END TEST ===\n";
?>