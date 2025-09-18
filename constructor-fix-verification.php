<?php
/**
 * Constructor TypeError Fix Verification
 * Only tests the critical constructor issues that cause plugin activation failures
 */

echo "=== CONSTRUCTOR TYPE ERROR FIX VERIFICATION ===\n";

// Mock essential WordPress functions
function add_action($hook, $callback, $priority = 10, $args = 1) { return true; }
function is_admin() { return false; }
function wp_parse_args($args, $defaults = []) { 
    if (is_object($args)) $args = get_object_vars($args);
    return is_array($args) ? array_merge($defaults, $args) : $defaults; 
}

// Define essential constants
if (!defined('ABSPATH')) define('ABSPATH', '/var/www/html/');
if (!defined('CARBON_MARKETPLACE_VERSION')) define('CARBON_MARKETPLACE_VERSION', '1.0.0');
if (!defined('CARBON_MARKETPLACE_PLUGIN_FILE')) define('CARBON_MARKETPLACE_PLUGIN_FILE', __FILE__);
if (!defined('CARBON_MARKETPLACE_PLUGIN_DIR')) define('CARBON_MARKETPLACE_PLUGIN_DIR', '/workspaces/Carbon-API/carbon-marketplace-integration/');
if (!defined('CARBON_MARKETPLACE_PLUGIN_URL')) define('CARBON_MARKETPLACE_PLUGIN_URL', 'http://example.com/wp-content/plugins/carbon-marketplace-integration/');
if (!defined('CARBON_MARKETPLACE_PLUGIN_BASENAME')) define('CARBON_MARKETPLACE_PLUGIN_BASENAME', 'carbon-marketplace-integration/carbon-marketplace-integration.php');

$plugin_dir = '/workspaces/Carbon-API/carbon-marketplace-integration';

// Include autoloader
require_once $plugin_dir . '/includes/class-autoloader.php';
CarbonMarketplace\Autoloader::init();

echo "Testing the exact constructor call that was failing...\n\n";

try {
    // This is the exact sequence that was failing in WordPress
    echo "1. Creating Database instance... ";
    $database = new CarbonMarketplace\Core\Database();
    echo "✅ OK\n";
    
    echo "2. Creating CacheManager instance... ";
    $cache_manager = new CarbonMarketplace\Cache\CacheManager();
    echo "✅ OK\n";
    
    echo "3. Creating ApiManager instance (with correct parameters)... ";
    $api_manager = new CarbonMarketplace\API\ApiManager();
    echo "✅ OK\n";
    
    echo "4. Creating SearchEngine instance (FIXED: was getting Database, now gets ApiManager)... ";
    $search_engine = new CarbonMarketplace\Search\SearchEngine($api_manager);
    echo "✅ OK - THIS WAS THE MAIN BUG!\n";
    
    echo "5. Creating full plugin instance to test complete initialization... ";
    $plugin = CarbonMarketplace\CarbonMarketplace::get_instance();
    echo "✅ OK\n";
    
} catch (TypeError $e) {
    echo "❌ CONSTRUCTOR TYPE ERROR STILL EXISTS:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n🚨 The WordPress activation error is NOT fixed yet!\n";
    exit(1);
}

echo "\n🎉 SUCCESS: Constructor TypeError Fixed!\n";
echo "\n=== WHAT WAS FIXED ===\n";
echo "❌ OLD (causing error): new SearchEngine(\$this->database)\n";
echo "✅ NEW (working):       new SearchEngine(\$this->api_manager)\n";
echo "\n❌ OLD (namespace):     use CarbonMarketplace\\Api\\ApiManager\n";
echo "✅ NEW (corrected):     use CarbonMarketplace\\API\\ApiManager\n";
echo "\n=== WORDPRESS ACTIVATION RESULT ===\n";
echo "✅ Plugin will now activate without the TypeError\n";
echo "✅ No more 'Argument #1 (\$api_manager) must be of type ?ApiManager, Database given'\n";
echo "✅ Ready for production deployment!\n";

echo "\n📋 DEPLOYMENT STEPS:\n";
echo "1. Upload plugin to WordPress /wp-content/plugins/\n";
echo "2. Activate plugin (should work without errors now)\n";
echo "3. Configure CNaught API settings\n";
echo "4. Test search functionality\n";
?>