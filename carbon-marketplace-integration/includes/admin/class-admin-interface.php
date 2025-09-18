<?php
/**
 * Admin Interface for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace\Admin
 */

namespace CarbonMarketplace\Admin;

use CarbonMarketplace\Api\ApiManager;
use CarbonMarketplace\Cache\CacheManager;

/**
 * Admin Interface class
 */
class AdminInterface {
    
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
     * Constructor
     *
     * @param ApiManager $api_manager API Manager instance
     * @param CacheManager $cache_manager Cache Manager instance
     */
    public function __construct(ApiManager $api_manager, CacheManager $cache_manager) {
        $this->api_manager = $api_manager;
        $this->cache_manager = $cache_manager;
    }
    
    /**
     * Initialize admin interface
     */
    public function init() {
        \add_action('admin_menu', array($this, 'add_admin_menu'));
        \add_action('admin_init', array($this, 'register_settings'));
        \add_action('admin_notices', array($this, 'show_admin_notices'));
        \add_action('wp_ajax_carbon_marketplace_test_credentials', array($this, 'ajax_test_credentials'));
        \add_action('wp_ajax_carbon_marketplace_clear_cache', array($this, 'ajax_clear_cache'));
        \add_action('wp_ajax_carbon_marketplace_sync_data', array($this, 'ajax_sync_data'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        \add_menu_page(
            \__('Carbon Marketplace', 'carbon-marketplace'),
            \__('Carbon Marketplace', 'carbon-marketplace'),
            'manage_options',
            'carbon-marketplace',
            array($this, 'render_main_page'),
            'dashicons-admin-site-alt3',
            30
        );
        
        \add_submenu_page(
            'carbon-marketplace',
            \__('Settings', 'carbon-marketplace'),
            \__('Settings', 'carbon-marketplace'),
            'manage_options',
            'carbon-marketplace-settings',
            array($this, 'render_settings_page')
        );
        
        \add_submenu_page(
            'carbon-marketplace',
            \__('API Vendors', 'carbon-marketplace'),
            \__('API Vendors', 'carbon-marketplace'),
            'manage_options',
            'carbon-marketplace-vendors',
            array($this, 'render_vendors_page')
        );
        
        \add_submenu_page(
            'carbon-marketplace',
            \__('Cache Management', 'carbon-marketplace'),
            \__('Cache Management', 'carbon-marketplace'),
            'manage_options',
            'carbon-marketplace-cache',
            array($this, 'render_cache_page')
        );
        
        \add_submenu_page(
            'carbon-marketplace',
            \__('Analytics', 'carbon-marketplace'),
            \__('Analytics', 'carbon-marketplace'),
            'manage_options',
            'carbon-marketplace-analytics',
            array($this, 'render_analytics_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        \register_setting('carbon_marketplace_general', 'carbon_marketplace_enable_cache');
        \register_setting('carbon_marketplace_general', 'carbon_marketplace_enable_logging');
        \register_setting('carbon_marketplace_general', 'carbon_marketplace_remove_data_on_uninstall');
        
        // Cache settings
        \register_setting('carbon_marketplace_cache', 'carbon_marketplace_cache_ttl_portfolios');
        \register_setting('carbon_marketplace_cache', 'carbon_marketplace_cache_ttl_projects');
        \register_setting('carbon_marketplace_cache', 'carbon_marketplace_cache_ttl_quotes');
        \register_setting('carbon_marketplace_cache', 'carbon_marketplace_cache_ttl_search');
        \register_setting('carbon_marketplace_cache', 'carbon_marketplace_cache_max_size');
        \register_setting('carbon_marketplace_cache', 'carbon_marketplace_cache_compression');
        
        // CNaught API settings
        \register_setting('carbon_marketplace_cnaught', 'carbon_marketplace_cnaught_api_key');
        \register_setting('carbon_marketplace_cnaught', 'carbon_marketplace_cnaught_sandbox_mode');
        \register_setting('carbon_marketplace_cnaught', 'carbon_marketplace_cnaught_enabled');
        
        // Toucan API settings
        \register_setting('carbon_marketplace_toucan', 'carbon_marketplace_toucan_api_key');
        \register_setting('carbon_marketplace_toucan', 'carbon_marketplace_toucan_network');
        \register_setting('carbon_marketplace_toucan', 'carbon_marketplace_toucan_enabled');
        
        // Add settings sections
        \add_settings_section(
            'carbon_marketplace_general_section',
            \__('General Settings', 'carbon-marketplace'),
            array($this, 'render_general_section'),
            'carbon_marketplace_general'
        );
        
        \add_settings_section(
            'carbon_marketplace_cache_section',
            \__('Cache Settings', 'carbon-marketplace'),
            array($this, 'render_cache_section'),
            'carbon_marketplace_cache'
        );
        
        \add_settings_section(
            'carbon_marketplace_cnaught_section',
            \__('CNaught API Settings', 'carbon-marketplace'),
            array($this, 'render_cnaught_section'),
            'carbon_marketplace_cnaught'
        );
        
        \add_settings_section(
            'carbon_marketplace_toucan_section',
            \__('Toucan API Settings', 'carbon-marketplace'),
            array($this, 'render_toucan_section'),
            'carbon_marketplace_toucan'
        );
        
        // Add settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // General settings fields
        \add_settings_field(
            'enable_cache',
            \__('Enable Cache', 'carbon-marketplace'),
            array($this, 'render_checkbox_field'),
            'carbon_marketplace_general',
            'carbon_marketplace_general_section',
            array(
                'option_name' => 'carbon_marketplace_enable_cache',
                'description' => \__('Enable caching to improve performance', 'carbon-marketplace'),
            )
        );
        
        \add_settings_field(
            'enable_logging',
            \__('Enable Logging', 'carbon-marketplace'),
            array($this, 'render_checkbox_field'),
            'carbon_marketplace_general',
            'carbon_marketplace_general_section',
            array(
                'option_name' => 'carbon_marketplace_enable_logging',
                'description' => \__('Enable logging for debugging purposes', 'carbon-marketplace'),
            )
        );
        
        \add_settings_field(
            'remove_data_on_uninstall',
            \__('Remove Data on Uninstall', 'carbon-marketplace'),
            array($this, 'render_checkbox_field'),
            'carbon_marketplace_general',
            'carbon_marketplace_general_section',
            array(
                'option_name' => 'carbon_marketplace_remove_data_on_uninstall',
                'description' => \__('Remove all plugin data when uninstalling', 'carbon-marketplace'),
            )
        );
        
        // Cache settings fields
        \add_settings_field(
            'cache_ttl_portfolios',
            \__('Portfolios Cache TTL (seconds)', 'carbon-marketplace'),
            array($this, 'render_number_field'),
            'carbon_marketplace_cache',
            'carbon_marketplace_cache_section',
            array(
                'option_name' => 'carbon_marketplace_cache_ttl_portfolios',
                'description' => \__('How long to cache portfolio data', 'carbon-marketplace'),
                'min' => 60,
                'max' => 86400,
            )
        );
        
        \add_settings_field(
            'cache_ttl_projects',
            \__('Projects Cache TTL (seconds)', 'carbon-marketplace'),
            array($this, 'render_number_field'),
            'carbon_marketplace_cache',
            'carbon_marketplace_cache_section',
            array(
                'option_name' => 'carbon_marketplace_cache_ttl_projects',
                'description' => \__('How long to cache project data', 'carbon-marketplace'),
                'min' => 60,
                'max' => 86400,
            )
        );
        
        \add_settings_field(
            'cache_ttl_quotes',
            \__('Quotes Cache TTL (seconds)', 'carbon-marketplace'),
            array($this, 'render_number_field'),
            'carbon_marketplace_cache',
            'carbon_marketplace_cache_section',
            array(
                'option_name' => 'carbon_marketplace_cache_ttl_quotes',
                'description' => \__('How long to cache quote data', 'carbon-marketplace'),
                'min' => 30,
                'max' => 3600,
            )
        );
        
        // CNaught API fields
        \add_settings_field(
            'cnaught_enabled',
            \__('Enable CNaught API', 'carbon-marketplace'),
            array($this, 'render_checkbox_field'),
            'carbon_marketplace_cnaught',
            'carbon_marketplace_cnaught_section',
            array(
                'option_name' => 'carbon_marketplace_cnaught_enabled',
                'description' => \__('Enable integration with CNaught API', 'carbon-marketplace'),
            )
        );
        
        \add_settings_field(
            'cnaught_api_key',
            \__('CNaught API Key', 'carbon-marketplace'),
            array($this, 'render_password_field'),
            'carbon_marketplace_cnaught',
            'carbon_marketplace_cnaught_section',
            array(
                'option_name' => 'carbon_marketplace_cnaught_api_key',
                'description' => \__('Your CNaught API key', 'carbon-marketplace'),
            )
        );
        
        \add_settings_field(
            'cnaught_sandbox_mode',
            \__('Sandbox Mode', 'carbon-marketplace'),
            array($this, 'render_checkbox_field'),
            'carbon_marketplace_cnaught',
            'carbon_marketplace_cnaught_section',
            array(
                'option_name' => 'carbon_marketplace_cnaught_sandbox_mode',
                'description' => \__('Use CNaught sandbox environment for testing', 'carbon-marketplace'),
            )
        );
        
        // Toucan API fields
        \add_settings_field(
            'toucan_enabled',
            \__('Enable Toucan API', 'carbon-marketplace'),
            array($this, 'render_checkbox_field'),
            'carbon_marketplace_toucan',
            'carbon_marketplace_toucan_section',
            array(
                'option_name' => 'carbon_marketplace_toucan_enabled',
                'description' => \__('Enable integration with Toucan Protocol', 'carbon-marketplace'),
            )
        );
        
        \add_settings_field(
            'toucan_api_key',
            \__('The Graph API Key (Optional)', 'carbon-marketplace'),
            array($this, 'render_text_field'),
            'carbon_marketplace_toucan',
            'carbon_marketplace_toucan_section',
            array(
                'option_name' => 'carbon_marketplace_toucan_api_key',
                'description' => \__('Optional API key for The Graph hosted service', 'carbon-marketplace'),
            )
        );
        
        \add_settings_field(
            'toucan_network',
            \__('Network', 'carbon-marketplace'),
            array($this, 'render_select_field'),
            'carbon_marketplace_toucan',
            'carbon_marketplace_toucan_section',
            array(
                'option_name' => 'carbon_marketplace_toucan_network',
                'description' => \__('Blockchain network to use', 'carbon-marketplace'),
                'options' => array(
                    'polygon' => \__('Polygon Mainnet', 'carbon-marketplace'),
                    'mumbai' => \__('Polygon Mumbai (Testnet)', 'carbon-marketplace'),
                ),
            )
        );
    }
    
    /**
     * Render main page
     */
    public function render_main_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="carbon-marketplace-dashboard">
                <div class="dashboard-widgets">
                    <div class="dashboard-widget">
                        <h3><?php _e('System Status', 'carbon-marketplace'); ?></h3>
                        <?php $this->render_system_status(); ?>
                    </div>
                    
                    <div class="dashboard-widget">
                        <h3><?php _e('API Status', 'carbon-marketplace'); ?></h3>
                        <?php $this->render_api_status(); ?>
                    </div>
                    
                    <div class="dashboard-widget">
                        <h3><?php _e('Cache Status', 'carbon-marketplace'); ?></h3>
                        <?php $this->render_cache_status(); ?>
                    </div>
                    
                    <div class="dashboard-widget">
                        <h3><?php _e('Quick Actions', 'carbon-marketplace'); ?></h3>
                        <?php $this->render_quick_actions(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $active_tab = $_GET['tab'] ?? 'general';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=carbon-marketplace-settings&tab=general" 
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'carbon-marketplace'); ?>
                </a>
                <a href="?page=carbon-marketplace-settings&tab=cache" 
                   class="nav-tab <?php echo $active_tab === 'cache' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Cache', 'carbon-marketplace'); ?>
                </a>
                <a href="?page=carbon-marketplace-settings&tab=cnaught" 
                   class="nav-tab <?php echo $active_tab === 'cnaught' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('CNaught API', 'carbon-marketplace'); ?>
                </a>
                <a href="?page=carbon-marketplace-settings&tab=toucan" 
                   class="nav-tab <?php echo $active_tab === 'toucan' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Toucan API', 'carbon-marketplace'); ?>
                </a>
            </nav>
            
            <form method="post" action="options.php">
                <?php
                switch ($active_tab) {
                    case 'general':
                        settings_fields('carbon_marketplace_general');
                        do_settings_sections('carbon_marketplace_general');
                        break;
                    case 'cache':
                        settings_fields('carbon_marketplace_cache');
                        do_settings_sections('carbon_marketplace_cache');
                        break;
                    case 'cnaught':
                        settings_fields('carbon_marketplace_cnaught');
                        do_settings_sections('carbon_marketplace_cnaught');
                        break;
                    case 'toucan':
                        settings_fields('carbon_marketplace_toucan');
                        do_settings_sections('carbon_marketplace_toucan');
                        break;
                }
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render vendors page
     */
    public function render_vendors_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="vendor-status-cards">
                <?php $this->render_vendor_card('cnaught'); ?>
                <?php $this->render_vendor_card('toucan'); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render cache page
     */
    public function render_cache_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="cache-management">
                <div class="cache-stats">
                    <h3><?php _e('Cache Statistics', 'carbon-marketplace'); ?></h3>
                    <?php $this->render_cache_statistics(); ?>
                </div>
                
                <div class="cache-actions">
                    <h3><?php _e('Cache Actions', 'carbon-marketplace'); ?></h3>
                    <?php $this->render_cache_actions(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="analytics-dashboard">
                <p><?php _e('Analytics dashboard coming soon...', 'carbon-marketplace'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render system status
     */
    private function render_system_status() {
        $status_items = array(
            'PHP Version' => PHP_VERSION,
            'WordPress Version' => get_bloginfo('version'),
            'Plugin Version' => CARBON_MARKETPLACE_VERSION,
            'Cache Enabled' => \get_option('carbon_marketplace_enable_cache', true) ? 'Yes' : 'No',
            'Logging Enabled' => \get_option('carbon_marketplace_enable_logging', true) ? 'Yes' : 'No',
        );
        
        echo '<table class="widefat">';
        foreach ($status_items as $label => $value) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($label) . '</strong></td>';
            echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    /**
     * Render API status
     */
    private function render_api_status() {
        $apis = array(
            'CNaught' => array(
                'enabled' => \get_option('carbon_marketplace_cnaught_enabled', false),
                'configured' => !empty(get_option('carbon_marketplace_cnaught_api_key')),
            ),
            'Toucan' => array(
                'enabled' => \get_option('carbon_marketplace_toucan_enabled', false),
                'configured' => true, // Toucan doesn't require API key
            ),
        );
        
        echo '<table class="widefat">';
        foreach ($apis as $name => $status) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($name) . '</strong></td>';
            echo '<td>';
            if ($status['enabled'] && $status['configured']) {
                echo '<span class="status-enabled">Enabled</span>';
            } elseif ($status['enabled'] && !$status['configured']) {
                echo '<span class="status-warning">Enabled but not configured</span>';
            } else {
                echo '<span class="status-disabled">Disabled</span>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    /**
     * Render cache status
     */
    private function render_cache_status() {
        if (!get_option('carbon_marketplace_enable_cache', true)) {
            echo '<p>Cache is disabled.</p>';
            return;
        }
        
        // Get cache statistics from cache manager
        $stats = $this->cache_manager->get_cache_statistics();
        
        echo '<table class="widefat">';
        echo '<tr><td><strong>Total Cached Items</strong></td><td>' . esc_html($stats['total_items'] ?? 0) . '</td></tr>';
        echo '<tr><td><strong>Cache Hit Rate</strong></td><td>' . esc_html(($stats['hit_rate'] ?? 0) . '%') . '</td></tr>';
        echo '<tr><td><strong>Memory Usage</strong></td><td>' . esc_html($stats['memory_usage'] ?? 'Unknown') . '</td></tr>';
        echo '</table>';
    }
    
    /**
     * Render quick actions
     */
    private function render_quick_actions() {
        ?>
        <div class="quick-actions">
            <button type="button" class="button button-secondary" id="clear-cache-btn">
                <?php _e('Clear Cache', 'carbon-marketplace'); ?>
            </button>
            <button type="button" class="button button-secondary" id="sync-data-btn">
                <?php _e('Sync Data', 'carbon-marketplace'); ?>
            </button>
            <button type="button" class="button button-secondary" id="test-apis-btn">
                <?php _e('Test APIs', 'carbon-marketplace'); ?>
            </button>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#clear-cache-btn').click(function() {
                $.post(ajaxurl, {
                    action: 'carbon_marketplace_clear_cache',
                    nonce: '<?php echo wp_create_nonce('carbon_marketplace_admin'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Cache cleared successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            
            $('#sync-data-btn').click(function() {
                $(this).prop('disabled', true).text('Syncing...');
                $.post(ajaxurl, {
                    action: 'carbon_marketplace_sync_data',
                    nonce: '<?php echo wp_create_nonce('carbon_marketplace_admin'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Data sync completed');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }).always(function() {
                    $('#sync-data-btn').prop('disabled', false).text('Sync Data');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render vendor card
     */
    private function render_vendor_card($vendor) {
        $vendor_info = $this->get_vendor_info($vendor);
        ?>
        <div class="vendor-card">
            <h3><?php echo esc_html($vendor_info['name']); ?></h3>
            <div class="vendor-status">
                <span class="status-indicator <?php echo $vendor_info['status_class']; ?>"></span>
                <?php echo esc_html($vendor_info['status_text']); ?>
            </div>
            <div class="vendor-actions">
                <button type="button" class="button test-credentials" data-vendor="<?php echo esc_attr($vendor); ?>">
                    <?php _e('Test Connection', 'carbon-marketplace'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get vendor information
     */
    private function get_vendor_info($vendor) {
        switch ($vendor) {
            case 'cnaught':
                $enabled = \get_option('carbon_marketplace_cnaught_enabled', false);
                $configured = !empty(get_option('carbon_marketplace_cnaught_api_key'));
                return array(
                    'name' => 'CNaught',
                    'status_class' => $enabled && $configured ? 'status-ok' : 'status-error',
                    'status_text' => $enabled && $configured ? 'Connected' : 'Not configured',
                );
                
            case 'toucan':
                $enabled = \get_option('carbon_marketplace_toucan_enabled', false);
                return array(
                    'name' => 'Toucan Protocol',
                    'status_class' => $enabled ? 'status-ok' : 'status-error',
                    'status_text' => $enabled ? 'Connected' : 'Disabled',
                );
                
            default:
                return array(
                    'name' => 'Unknown',
                    'status_class' => 'status-error',
                    'status_text' => 'Unknown vendor',
                );
        }
    }
    
    /**
     * Render field methods
     */
    public function render_checkbox_field($args) {
        $option_name = $args['option_name'];
        $value = \get_option($option_name, false);
        $description = $args['description'] ?? '';
        
        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($option_name) . '" value="1" ' . checked(1, $value, false) . ' />';
        echo ' ' . esc_html($description);
        echo '</label>';
    }
    
    public function render_text_field($args) {
        $option_name = $args['option_name'];
        $value = \get_option($option_name, '');
        $description = $args['description'] ?? '';
        
        echo '<input type="text" name="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    public function render_password_field($args) {
        $option_name = $args['option_name'];
        $value = \get_option($option_name, '');
        $description = $args['description'] ?? '';
        
        echo '<input type="password" name="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    public function render_number_field($args) {
        $option_name = $args['option_name'];
        $value = \get_option($option_name, '');
        $description = $args['description'] ?? '';
        $min = $args['min'] ?? '';
        $max = $args['max'] ?? '';
        
        echo '<input type="number" name="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" class="small-text"';
        if ($min !== '') echo ' min="' . esc_attr($min) . '"';
        if ($max !== '') echo ' max="' . esc_attr($max) . '"';
        echo ' />';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    public function render_select_field($args) {
        $option_name = $args['option_name'];
        $value = \get_option($option_name, '');
        $description = $args['description'] ?? '';
        $options = $args['options'] ?? array();
        
        echo '<select name="' . esc_attr($option_name) . '">';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>';
            echo esc_html($option_label);
            echo '</option>';
        }
        echo '</select>';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    /**
     * Section render methods
     */
    public function render_general_section() {
        echo '<p>' . \__('Configure general plugin settings.', 'carbon-marketplace') . '</p>';
    }
    
    public function render_cache_section() {
        echo '<p>' . \__('Configure caching behavior to optimize performance.', 'carbon-marketplace') . '</p>';
    }
    
    public function render_cnaught_section() {
        echo '<p>' . \__('Configure CNaught API integration settings.', 'carbon-marketplace') . '</p>';
    }
    
    public function render_toucan_section() {
        echo '<p>' . \__('Configure Toucan Protocol integration settings.', 'carbon-marketplace') . '</p>';
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if APIs are configured
        $cnaught_enabled = \get_option('carbon_marketplace_cnaught_enabled', false);
        $cnaught_configured = !empty(get_option('carbon_marketplace_cnaught_api_key'));
        $toucan_enabled = \get_option('carbon_marketplace_toucan_enabled', false);
        
        if (($cnaught_enabled && !$cnaught_configured) || (!$cnaught_enabled && !$toucan_enabled)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('Carbon Marketplace: Please configure at least one API vendor in the settings.', 'carbon-marketplace'); ?>
                    <a href="<?php echo admin_url('admin.php?page=carbon-marketplace-settings'); ?>">
                        <?php _e('Configure now', 'carbon-marketplace'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * AJAX handler for testing credentials
     */
    public function ajax_test_credentials() {
        \check_ajax_referer('carbon_marketplace_admin', 'nonce');
        
        if (!\current_user_can('manage_options')) {
            \wp_die(\__('Insufficient permissions', 'carbon-marketplace'));
        }
        
        $vendor = sanitize_text_field($_POST['vendor'] ?? '');
        
        try {
            $result = $this->api_manager->validate_client_credentials($vendor);
            
            if (is_wp_error($result)) {
                \wp_send_json_error($result->get_error_message());
            } else {
                \wp_send_json_success(\__('Connection successful', 'carbon-marketplace'));
            }
        } catch (Exception $e) {
            \wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_cache() {
        \check_ajax_referer('carbon_marketplace_admin', 'nonce');
        
        if (!\current_user_can('manage_options')) {
            \wp_die(\__('Insufficient permissions', 'carbon-marketplace'));
        }
        
        try {
            $this->cache_manager->invalidate_all_cache();
            \wp_send_json_success(\__('Cache cleared successfully', 'carbon-marketplace'));
        } catch (Exception $e) {
            \wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for syncing data
     */
    public function ajax_sync_data() {
        \check_ajax_referer('carbon_marketplace_admin', 'nonce');
        
        if (!\current_user_can('manage_options')) {
            \wp_die(\__('Insufficient permissions', 'carbon-marketplace'));
        }
        
        try {
            // Trigger data synchronization
            $portfolios = $this->api_manager->fetch_all_portfolios();
            $projects = $this->api_manager->fetch_all_projects();
            
            // Warm cache with new data
            $this->cache_manager->warm_cache([
                [
                    'type' => 'portfolios',
                    'callback' => function() { return $this->api_manager->fetch_all_portfolios(); }
                ],
                [
                    'type' => 'projects', 
                    'callback' => function() { return $this->api_manager->fetch_all_projects(); }
                ]
            ]);
            
            \wp_send_json_success(\__('Data synchronization completed', 'carbon-marketplace'));
        } catch (Exception $e) {
            \wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Render cache statistics
     */
    private function render_cache_statistics() {
        $stats = $this->cache_manager->get_cache_statistics();
        
        ?>
        <table class="widefat">
            <tr>
                <td><strong><?php _e('Total Cached Items', 'carbon-marketplace'); ?></strong></td>
                <td><?php echo esc_html($stats['total_items'] ?? 0); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Cache Hit Rate', 'carbon-marketplace'); ?></strong></td>
                <td><?php echo esc_html(($stats['hit_rate'] ?? 0) . '%'); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Memory Usage', 'carbon-marketplace'); ?></strong></td>
                <td><?php echo esc_html($stats['memory_usage'] ?? 'Unknown'); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Last Updated', 'carbon-marketplace'); ?></strong></td>
                <td><?php echo esc_html($stats['last_updated'] ?? 'Never'); ?></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render cache actions
     */
    private function render_cache_actions() {
        ?>
        <div class="cache-actions-grid">
            <div class="cache-action-item">
                <h4><?php _e('Clear All Cache', 'carbon-marketplace'); ?></h4>
                <p><?php _e('Remove all cached data and force fresh API calls.', 'carbon-marketplace'); ?></p>
                <button type="button" class="button button-secondary" id="clear-all-cache">
                    <?php _e('Clear All', 'carbon-marketplace'); ?>
                </button>
            </div>
            
            <div class="cache-action-item">
                <h4><?php _e('Clear Portfolios Cache', 'carbon-marketplace'); ?></h4>
                <p><?php _e('Clear only portfolio data cache.', 'carbon-marketplace'); ?></p>
                <button type="button" class="button button-secondary" data-cache-type="portfolios">
                    <?php _e('Clear Portfolios', 'carbon-marketplace'); ?>
                </button>
            </div>
            
            <div class="cache-action-item">
                <h4><?php _e('Clear Projects Cache', 'carbon-marketplace'); ?></h4>
                <p><?php _e('Clear only project data cache.', 'carbon-marketplace'); ?></p>
                <button type="button" class="button button-secondary" data-cache-type="projects">
                    <?php _e('Clear Projects', 'carbon-marketplace'); ?>
                </button>
            </div>
            
            <div class="cache-action-item">
                <h4><?php _e('Warm Cache', 'carbon-marketplace'); ?></h4>
                <p><?php _e('Pre-load cache with fresh data from APIs.', 'carbon-marketplace'); ?></p>
                <button type="button" class="button button-primary" id="warm-cache">
                    <?php _e('Warm Cache', 'carbon-marketplace'); ?>
                </button>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Clear specific cache type
            $('[data-cache-type]').click(function() {
                var cacheType = $(this).data('cache-type');
                var button = $(this);
                
                button.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'carbon_marketplace_clear_cache_type',
                    cache_type: cacheType,
                    nonce: '<?php echo wp_create_nonce('carbon_marketplace_admin'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Cache cleared successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }).always(function() {
                    button.prop('disabled', false);
                });
            });
            
            // Warm cache
            $('#warm-cache').click(function() {
                var button = $(this);
                button.prop('disabled', true).text('Warming Cache...');
                
                $.post(ajaxurl, {
                    action: 'carbon_marketplace_warm_cache',
                    nonce: '<?php echo wp_create_nonce('carbon_marketplace_admin'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Cache warmed successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }).always(function() {
                    button.prop('disabled', false).text('Warm Cache');
                });
            });
        });
        </script>
        <?php
    }
}