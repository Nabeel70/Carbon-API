<?php
/**
 * Uninstall script for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include the main plugin class
require_once plugin_dir_path(__FILE__) . 'includes/class-autoloader.php';
CarbonMarketplace\Autoloader::init();

// Call the uninstall method
CarbonMarketplace\CarbonMarketplace::uninstall();