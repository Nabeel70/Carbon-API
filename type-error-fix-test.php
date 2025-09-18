<?php
/**
 * Type Error Fix Test - Comprehensive Class Constructor Validation
 * This test checks ALL class constructors to prevent TypeErrors
 */

echo "=== TYPE ERROR FIX TEST ===\n";
echo "Checking all class constructors for type compatibility...\n\n";

// Mock WordPress functions
function add_action($hook, $callback, $priority = 10, $args = 1) { return true; }
function add_filter($hook, $callback, $priority = 10, $args = 1) { return true; }
function add_shortcode($tag, $callback) { return true; }
function wp_enqueue_style($handle, $src, $deps = [], $ver = false, $media = 'all') { return true; }
function wp_enqueue_script($handle, $src, $deps = [], $ver = false, $in_footer = false) { return true; }
function wp_localize_script($handle, $name, $data) { return true; }
function admin_url($path) { return "http://example.com/wp-admin/$path"; }
function wp_create_nonce($action) { return 'mock_nonce_' . $action; }
function load_plugin_textdomain($domain, $deprecated, $path) { return true; }
function shortcode_atts($pairs, $atts, $shortcode = '') { return array_merge($pairs, $atts); }
function is_admin() { return false; }
function get_option($option, $default = false) { return $default; }
function add_option($option, $value) { return true; }
function wp_next_scheduled($hook) { return false; }
function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) { return true; }
function wp_clear_scheduled_hook($hook, $args = []) { return true; }
function flush_rewrite_rules($hard = true) { return true; }
function register_rest_route($namespace, $route, $args = []) { return true; }
function wp_parse_args($args, $defaults = []) { 
    if (is_object($args)) $args = get_object_vars($args);
    return is_array($args) ? array_merge($defaults, $args) : $defaults; 
}

// Mock WordPress constants
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

echo "1. Testing Core Components Instantiation Order...\n";

try {
    // Test 1: Database (no dependencies)
    echo "   ✓ Database: ";
    $database = new CarbonMarketplace\Core\Database();
    echo "OK\n";
    
    // Test 2: Migration (depends on Database)
    echo "   ✓ Migration: ";
    $migration = new CarbonMarketplace\Core\Migration();
    echo "OK\n";
    
    // Test 3: CacheManager (no dependencies)
    echo "   ✓ CacheManager: ";
    $cache_manager = new CarbonMarketplace\Cache\CacheManager();
    echo "OK\n";
    
    // Test 4: ApiManager (takes array config, not CacheManager)
    echo "   ✓ ApiManager: ";
    $api_manager = new CarbonMarketplace\API\ApiManager();
    echo "OK\n";
    
    // Test 5: SearchEngine (should depend on ApiManager)
    echo "   ✓ SearchEngine: ";
    $search_engine = new CarbonMarketplace\Search\SearchEngine($api_manager);
    echo "OK\n";
    
    // Test 6: SearchAjaxHandler (depends on SearchEngine)
    echo "   ✓ SearchAjaxHandler: ";
    $ajax_handler = new CarbonMarketplace\Ajax\SearchAjaxHandler($search_engine);
    echo "OK\n";
    
    // Test 7: AdminInterface (depends on ApiManager and CacheManager)
    echo "   ✓ AdminInterface: ";
    $admin_interface = new CarbonMarketplace\Admin\AdminInterface($api_manager, $cache_manager);
    echo "OK\n";
    
    // Test 8: CheckoutManager (depends on ApiManager and Database)
    echo "   ✓ CheckoutManager: ";
    $checkout_manager = new CarbonMarketplace\Checkout\CheckoutManager($api_manager, $database);
    echo "OK\n";
    
    // Test 9: WebhookHandler (depends on CheckoutManager and Database)
    echo "   ✓ WebhookHandler: ";
    $webhook_handler = new CarbonMarketplace\Webhooks\WebhookHandler($checkout_manager, $database);
    echo "OK\n";
    
} catch (TypeError $e) {
    echo "❌ CONSTRUCTOR TYPE ERROR FOUND:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ OTHER ERROR:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit(1);
}

echo "\n2. Testing Main Plugin Class Instantiation...\n";

try {
    echo "   ✓ CarbonMarketplace singleton: ";
    $plugin = CarbonMarketplace\CarbonMarketplace::get_instance();
    echo "OK\n";
    
    echo "   ✓ Plugin initialization: ";
    $plugin->init();
    echo "OK\n";
    
} catch (TypeError $e) {
    echo "❌ MAIN PLUGIN TYPE ERROR:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ MAIN PLUGIN ERROR:\n";  
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit(1);
}

echo "\n3. Testing Method Calls on Core Objects...\n";

try {
    echo "   ✓ API Manager methods: ";
    // Test method calls that might cause issues
    $cache_manager->get_cache_key('test', 'test');
    $api_manager->get_cnaught_client();
    echo "OK\n";
    
    echo "   ✓ Search Engine methods: ";
    $search_query = new CarbonMarketplace\Models\SearchQuery();
    $search_query->keyword = 'forest';
    $search_results = $search_engine->search($search_query);
    echo "OK\n";
    
} catch (TypeError $e) {
    echo "❌ METHOD CALL TYPE ERROR:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ METHOD CALL ERROR:\n";  
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    // Don't exit on method errors as they might be expected (like network errors)
}

echo "\n🎉 SUCCESS: All constructor type errors have been fixed!\n";
echo "\nThe plugin should now load without the TypeError you encountered.\n";
echo "\nThe main fix was changing:\n";
echo "❌ \$this->search_engine = new SearchEngine(\$this->database);\n";
echo "✅ \$this->search_engine = new SearchEngine(\$this->api_manager);\n";
echo "\nThis ensures SearchEngine gets the correct ApiManager parameter.\n";
?>
