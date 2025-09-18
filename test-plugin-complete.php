<?php
/**
 * Comprehensive test to check if plugin can be activated without errors
 */

// Mock WordPress functions that are needed for full testing
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

// Mock WordPress actions/filters
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        // Just store for testing
        return true;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) {
        return true;
    }
}

// Mock other WordPress functions
if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
        return true;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) {
        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin') {
        return 'http://example.com/wp-admin/admin-ajax.php';
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test_nonce';
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false) {
        return true;
    }
}

if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $shortcode = '') {
        $out = array();
        foreach ($pairs as $name => $default) {
            $out[$name] = isset($atts[$name]) ? $atts[$name] : $default;
        }
        return $out;
    }
}

if (!function_exists('ob_start')) {
    function ob_start($output_callback = null, $chunk_size = 0, $flags = PHP_OUTPUT_HANDLER_STDFLAGS) {
        return \ob_start($output_callback, $chunk_size, $flags);
    }
}

if (!function_exists('ob_get_clean')) {
    function ob_get_clean() {
        return \ob_get_clean();
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = array()) {
        return false; // Always return false so events get scheduled in test
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = array()) {
        return true;
    }
}

if (!function_exists('time')) {
    function time() {
        return \time();
    }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules($hard = true) {
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = '') {
        if (is_array($defaults)) {
            return array_merge($defaults, (array) $args);
        }
        return $args;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return ($thing instanceof WP_Error);
    }
}

if (!function_exists('add_option')) {
    function add_option($option, $value, $deprecated = '', $autoload = 'yes') {
        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook, $args = array()) {
        return true;
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// Define mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct($code = '', $message = '', $data = '') {
            // Mock implementation
        }
    }
}

// Define plugin constants
define('CARBON_MARKETPLACE_VERSION', '1.0.0');
define('CARBON_MARKETPLACE_PLUGIN_FILE', __FILE__);
define('CARBON_MARKETPLACE_PLUGIN_DIR', __DIR__ . '/carbon-marketplace-integration/');
define('CARBON_MARKETPLACE_PLUGIN_URL', 'http://example.com/wp-content/plugins/carbon-marketplace-integration/');
define('CARBON_MARKETPLACE_PLUGIN_BASENAME', 'carbon-marketplace-integration/carbon-marketplace-integration.php');

try {
    echo "=== Testing Full Plugin Loading ===\n";
    
    // Include the autoloader
    require_once CARBON_MARKETPLACE_PLUGIN_DIR . 'includes/class-autoloader.php';
    
    // Initialize autoloader
    CarbonMarketplace\Autoloader::init();
    echo "✓ Autoloader initialized successfully\n";
    
    // Test loading individual components
    $database = new CarbonMarketplace\Core\Database();
    echo "✓ Database class loaded successfully\n";
    
    $cache_manager = new CarbonMarketplace\Cache\CacheManager();
    echo "✓ CacheManager class loaded successfully\n";
    
    $api_manager = new CarbonMarketplace\Api\ApiManager($cache_manager);
    echo "✓ ApiManager class loaded successfully\n";
    
    $search_engine = new CarbonMarketplace\Search\SearchEngine($database);
    echo "✓ SearchEngine class loaded successfully\n";
    
    $ajax_handler = new CarbonMarketplace\Ajax\SearchAjaxHandler($search_engine);
    echo "✓ SearchAjaxHandler class loaded successfully\n";
    
    $checkout_manager = new CarbonMarketplace\Checkout\CheckoutManager($api_manager, $database);
    echo "✓ CheckoutManager class loaded successfully\n";
    
    $webhook_handler = new CarbonMarketplace\Webhooks\WebhookHandler($checkout_manager, $database);
    echo "✓ WebhookHandler class loaded successfully\n";
    
    // Test model classes
    $search_query = new CarbonMarketplace\Models\SearchQuery(['keyword' => 'forest']);
    echo "✓ SearchQuery model loaded successfully\n";
    
    $project = new CarbonMarketplace\Models\Project([]);
    echo "✓ Project model loaded successfully\n";
    
    // Test main plugin class
    $plugin = CarbonMarketplace\CarbonMarketplace::get_instance();
    echo "✓ Main plugin class instantiated successfully\n";
    
    // Test activation method
    CarbonMarketplace\CarbonMarketplace::activate();
    echo "✓ Plugin activation method executed successfully\n";
    
    // Test initialization 
    $plugin->init();
    echo "✓ Plugin initialization completed successfully\n";
    
    echo "\n=== All Tests Passed! ===\n";
    echo "✓ Plugin should now be able to activate in WordPress without fatal errors\n";
    echo "✓ All critical classes are loading properly\n";
    echo "✓ Component initialization order is correct\n";
    echo "✓ Namespace handling is working\n";
    
    echo "\n=== Shortcode Test ===\n";
    $search_shortcode = $plugin->render_search_shortcode(['show_filters' => 'true', 'results_per_page' => '20', 'layout' => 'grid']);
    if (!empty($search_shortcode)) {
        echo "✓ Search shortcode renders template successfully\n";
    } else {
        echo "⚠ Search shortcode returned empty (template file may be missing)\n";
    }
    
    echo "\nPlugin is ready for WordPress activation!\n";
    
} catch (Error $e) {
    echo "✗ Fatal Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}