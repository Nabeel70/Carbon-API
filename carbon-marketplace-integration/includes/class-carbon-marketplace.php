<?php
/**
 * Main plugin class for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace;

use CarbonMarketplace\Core\Database;
use CarbonMarketplace\Core\Migration;
use CarbonMarketplace\Api\ApiManager;
use CarbonMarketplace\Api\CNaughtClient;
use CarbonMarketplace\Api\ToucanClient;
use CarbonMarketplace\Cache\CacheManager;
use CarbonMarketplace\Search\SearchEngine;
use CarbonMarketplace\Ajax\SearchAjaxHandler;
use CarbonMarketplace\Admin\AdminInterface;
use CarbonMarketplace\Checkout\CheckoutManager;
use CarbonMarketplace\Webhooks\WebhookHandler;

/**
 * Main plugin class
 */
class CarbonMarketplace {
    
    /**
     * Plugin instance
     *
     * @var CarbonMarketplace
     */
    private static $instance = null;
    
    /**
     * Database instance
     *
     * @var Database
     */
    private $database;
    
    /**
     * API Manager instance
     *
     * @var ApiManager
     */
    private $api_manager;
    
    /**
     * Cache Manager instance
     *
     * @var CacheManager
     */
    private $cache_manager;
    
    /**
     * Search Engine instance
     *
     * @var SearchEngine
     */
    private $search_engine;
    
    /**
     * AJAX Handler instance
     *
     * @var SearchAjaxHandler
     */
    private $ajax_handler;
    
    /**
     * Admin Interface instance
     *
     * @var AdminInterface
     */
    private $admin_interface;
    
    /**
     * Checkout Manager instance
     *
     * @var \CarbonMarketplace\Checkout\CheckoutManager
     */
    private $checkout_manager;
    
    /**
     * Webhook Handler instance
     *
     * @var WebhookHandler
     */
    private $webhook_handler;
    
    /**
     * Get plugin instance (Singleton pattern)
     *
     * @return CarbonMarketplace
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        // Initialize core components
        $this->init_core_components();
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain
        \add_action('init', array($this, 'load_textdomain'));
        
        // Initialize components
        $this->init_hooks();
        $this->register_shortcodes();
        $this->register_admin_menus();
        $this->register_webhook_endpoints();
        $this->enqueue_scripts();
        
        // Initialize admin interface
        if (is_admin()) {
            $this->admin_interface->init();
        }
        
        // Initialize webhook handler
        $this->webhook_handler->init();
    }
    
    /**
     * Initialize core components
     */
    private function init_core_components() {
        $this->database = new Database();
        $this->cache_manager = new CacheManager();
        $this->api_manager = new ApiManager($this->cache_manager);
        $this->init_api_clients(); // Initialize API clients
        $this->checkout_manager = new CheckoutManager($this->api_manager, $this->database);
        $this->search_engine = new SearchEngine($this->database);
        $this->ajax_handler = new SearchAjaxHandler($this->search_engine, $this->api_manager);
        $this->admin_interface = new AdminInterface($this->api_manager, $this->cache_manager);
        $this->webhook_handler = new WebhookHandler($this->checkout_manager, $this->database);
    }
    
    /**
     * Initialize API clients based on plugin settings
     */
    private function init_api_clients() {
        // Initialize CNaught client if enabled and configured
        $cnaught_enabled = \get_option('carbon_marketplace_cnaught_enabled', false);
        $cnaught_api_key = \get_option('carbon_marketplace_cnaught_api_key', '');
        
        if ($cnaught_enabled && !empty($cnaught_api_key)) {
            $cnaught_sandbox = \get_option('carbon_marketplace_cnaught_sandbox_mode', false);
            
            $cnaught_config = array(
                'api_key' => $cnaught_api_key,
                'base_url' => $cnaught_sandbox 
                    ? 'https://sandbox.cnaught.com/api/v1' 
                    : 'https://api.cnaught.com/v1',
            );
            
            $cnaught_client = new CNaughtClient($cnaught_config);
            $this->api_manager->register_client('cnaught', $cnaught_client);
        }
        
        // Initialize Toucan client if enabled
        $toucan_enabled = \get_option('carbon_marketplace_toucan_enabled', false);
        
        if ($toucan_enabled) {
            $toucan_api_key = \get_option('carbon_marketplace_toucan_api_key', '');
            $toucan_network = \get_option('carbon_marketplace_toucan_network', 'polygon');
            $toucan_wallet = \get_option('carbon_marketplace_toucan_wallet_address', '');
            
            $toucan_config = array(
                'api_key' => $toucan_api_key, // Optional for The Graph
                'network' => $toucan_network,
                'wallet_address' => $toucan_wallet,
            );
            
            $toucan_client = new ToucanClient($toucan_config);
            $this->api_manager->register_client('toucan', $toucan_client);
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Schedule cache refresh
        \add_action('carbon_marketplace_cache_refresh', array($this->cache_manager, 'refresh_cache'));
        
        // Schedule data sync
        \add_action('carbon_marketplace_data_sync', array($this->api_manager, 'sync_all_data'));
        
        // Handle plugin updates
        \add_action('upgrader_process_complete', array($this, 'handle_plugin_update'), 10, 2);
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        \add_shortcode('carbon_marketplace_search', array($this, 'render_search_shortcode'));
        \add_shortcode('carbon_marketplace_projects', array($this, 'render_projects_shortcode'));
        \add_shortcode('carbon_marketplace_project_detail', array($this, 'render_project_detail_shortcode'));
    }
    
    /**
     * Register admin menus
     */
    public function register_admin_menus() {
        if (is_admin()) {
            \add_action('admin_menu', array($this->admin_interface, 'add_admin_menu'));
        }
    }
    
    /**
     * Register webhook endpoints
     */
    public function register_webhook_endpoints() {
        \add_action('rest_api_init', array($this->webhook_handler, 'register_routes'));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        \add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        \add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        \wp_enqueue_style(
            'carbon-marketplace-frontend',
            CARBON_MARKETPLACE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            CARBON_MARKETPLACE_VERSION
        );
        
        \wp_enqueue_script(
            'carbon-marketplace-frontend',
            CARBON_MARKETPLACE_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            CARBON_MARKETPLACE_VERSION,
            true
        );
        
        // Localize script for AJAX
        \wp_localize_script('carbon-marketplace-frontend', 'carbonMarketplace', array(
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'nonce' => \wp_create_nonce('carbon_marketplace_nonce'),
            'strings' => array(
                'loading' => \__('Loading...', 'carbon-marketplace'),
                'error' => \__('An error occurred. Please try again.', 'carbon-marketplace'),
                'noResults' => \__('No projects found matching your criteria.', 'carbon-marketplace'),
            ),
        ));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'carbon-marketplace') === false) {
            return;
        }
        
        \wp_enqueue_style(
            'carbon-marketplace-admin',
            CARBON_MARKETPLACE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CARBON_MARKETPLACE_VERSION
        );
        
        \wp_enqueue_script(
            'carbon-marketplace-admin',
            CARBON_MARKETPLACE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CARBON_MARKETPLACE_VERSION,
            true
        );
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        \load_plugin_textdomain(
            'carbon-marketplace',
            false,
            dirname(CARBON_MARKETPLACE_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Render search shortcode
     */
    public function render_search_shortcode($atts) {
        $atts = \shortcode_atts(array(
            'show_filters' => 'true',
            'results_per_page' => '20',
            'layout' => 'grid',
        ), $atts, 'carbon_marketplace_search');
        
        ob_start();
        include CARBON_MARKETPLACE_PLUGIN_DIR . 'templates/search-form.php';
        return ob_get_clean();
    }
    
    /**
     * Render projects shortcode
     */
    public function render_projects_shortcode($atts) {
        $atts = \shortcode_atts(array(
            'limit' => '20',
            'vendor' => '',
            'project_type' => '',
            'location' => '',
        ), $atts, 'carbon_marketplace_projects');
        
        ob_start();
        include CARBON_MARKETPLACE_PLUGIN_DIR . 'templates/projects-grid.php';
        return ob_get_clean();
    }
    
    /**
     * Render project detail shortcode
     */
    public function render_project_detail_shortcode($atts) {
        $atts = \shortcode_atts(array(
            'project_id' => '',
            'vendor' => '',
        ), $atts, 'carbon_marketplace_project_detail');
        
        ob_start();
        include CARBON_MARKETPLACE_PLUGIN_DIR . 'templates/project-detail.php';
        return ob_get_clean();
    }
    
    /**
     * Handle plugin updates
     */
    public function handle_plugin_update($upgrader_object, $options) {
        if ($options['action'] == 'update' && $options['type'] == 'plugin') {
            if (isset($options['plugins'])) {
                foreach ($options['plugins'] as $plugin) {
                    if ($plugin == CARBON_MARKETPLACE_PLUGIN_BASENAME) {
                        // Run migrations if needed
                        $migration = new Migration();
                        $migration->run_migrations();
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Run database migrations
        $migration = new Migration();
        $migration->run_migrations();
        
        // Schedule cron events
        if (!\wp_next_scheduled('carbon_marketplace_cache_refresh')) {
            \wp_schedule_event(time(), 'hourly', 'carbon_marketplace_cache_refresh');
        }
        
        if (!\wp_next_scheduled('carbon_marketplace_data_sync')) {
            \wp_schedule_event(time(), 'twicedaily', 'carbon_marketplace_data_sync');
        }
        
        // Flush rewrite rules
        \flush_rewrite_rules();
        
        // Set default options
        self::set_default_options();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled events
        \wp_clear_scheduled_hook('carbon_marketplace_cache_refresh');
        \wp_clear_scheduled_hook('carbon_marketplace_data_sync');
        
        // Flush rewrite rules
        \flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall (called from uninstall.php)
     */
    public static function uninstall() {
        // Remove all plugin data if user chooses to
        $remove_data = \get_option('carbon_marketplace_remove_data_on_uninstall', false);
        
        if ($remove_data) {
            // Remove database tables
            $database = new Database();
            $database->drop_tables();
            
            // Remove all plugin options
            self::remove_all_options();
            
            // Clear all transients
            $cache_manager = new CacheManager();
            $cache_manager->invalidate_all_cache();
        }
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = array(
            'carbon_marketplace_cache_ttl_portfolios' => 900, // 15 minutes
            'carbon_marketplace_cache_ttl_projects' => 3600, // 1 hour
            'carbon_marketplace_cache_ttl_quotes' => 300, // 5 minutes
            'carbon_marketplace_enable_cache' => true,
            'carbon_marketplace_enable_logging' => true,
            'carbon_marketplace_remove_data_on_uninstall' => false,
        );
        
        foreach ($defaults as $option => $value) {
            if (\get_option($option) === false) {
                \add_option($option, $value);
            }
        }
    }
    
    /**
     * Remove all plugin options
     */
    private static function remove_all_options() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'carbon_marketplace_%'"
        );
    }
    
    /**
     * Get database instance
     */
    public function get_database() {
        return $this->database;
    }
    
    /**
     * Get API manager instance
     */
    public function get_api_manager() {
        return $this->api_manager;
    }
    
    /**
     * Get cache manager instance
     */
    public function get_cache_manager() {
        return $this->cache_manager;
    }
    
    /**
     * Get search engine instance
     */
    public function get_search_engine() {
        return $this->search_engine;
    }
}