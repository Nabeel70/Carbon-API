<?php
/**
 * Plugin Name: Carbon Marketplace Integration
 * Plugin URI: https://example.com/carbon-marketplace-integration
 * Description: A comprehensive WordPress plugin that integrates with multiple carbon credit vendor APIs (CNaught and Toucan) to create a meta search engine for carbon offset projects.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: carbon-marketplace
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package CarbonMarketplace
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CARBON_MARKETPLACE_VERSION', '1.0.0');
define('CARBON_MARKETPLACE_PLUGIN_FILE', __FILE__);
define('CARBON_MARKETPLACE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CARBON_MARKETPLACE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CARBON_MARKETPLACE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include the autoloader
require_once CARBON_MARKETPLACE_PLUGIN_DIR . 'includes/class-autoloader.php';

// Initialize autoloader
CarbonMarketplace\Autoloader::init();

// Main plugin class
use CarbonMarketplace\CarbonMarketplace;

/**
 * Initialize the plugin
 */
function carbon_marketplace_init() {
    $plugin = CarbonMarketplace::get_instance();
    $plugin->init();
}

// Hook into WordPress
add_action('plugins_loaded', 'carbon_marketplace_init');

// Activation hook
register_activation_hook(__FILE__, array('CarbonMarketplace\CarbonMarketplace', 'activate'));

// Deactivation hook
register_deactivation_hook(__FILE__, array('CarbonMarketplace\CarbonMarketplace', 'deactivate'));

// Uninstall hook is handled in uninstall.php