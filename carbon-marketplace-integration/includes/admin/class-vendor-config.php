<?php
/**
 * Vendor Configuration Interface for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace\Admin
 */

namespace CarbonMarketplace\Admin;

use CarbonMarketplace\Admin\CredentialManager;
use CarbonMarketplace\Api\ApiManager;

/**
 * Vendor Configuration class
 */
class VendorConfig {
    
    /**
     * Credential Manager instance
     *
     * @var CredentialManager
     */
    private $credential_manager;
    
    /**
     * API Manager instance
     *
     * @var ApiManager
     */
    private $api_manager;
    
    /**
     * Constructor
     *
     * @param CredentialManager $credential_manager Credential Manager instance
     * @param ApiManager $api_manager API Manager instance
     */
    public function __construct(CredentialManager $credential_manager, ApiManager $api_manager) {
        $this->credential_manager = $credential_manager;
        $this->api_manager = $api_manager;
    }
    
    /**
     * Initialize vendor configuration
     */
    public function init() {
        add_action('wp_ajax_carbon_marketplace_save_vendor_config', array($this, 'ajax_save_vendor_config'));
        add_action('wp_ajax_carbon_marketplace_test_vendor_connection', array($this, 'ajax_test_vendor_connection'));
        add_action('wp_ajax_carbon_marketplace_toggle_vendor', array($this, 'ajax_toggle_vendor'));
        add_action('wp_ajax_carbon_marketplace_reset_vendor', array($this, 'ajax_reset_vendor'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_vendor_config_scripts'));
    }
    
    /**
     * Enqueue vendor configuration scripts
     */
    public function enqueue_vendor_config_scripts($hook) {
        if (strpos($hook, 'carbon-marketplace') === false) {
            return;
        }
        
        wp_enqueue_script(
            'carbon-marketplace-vendor-config',
            CARBON_MARKETPLACE_URL . 'assets/js/vendor-config.js',
            array('jquery'),
            CARBON_MARKETPLACE_VERSION,
            true
        );
        
        wp_localize_script('carbon-marketplace-vendor-config', 'carbonMarketplaceVendor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('carbon_marketplace_vendor'),
            'strings' => array(
                'saving' => \__('Saving...', 'carbon-marketplace'),
                'testing' => \__('Testing...', 'carbon-marketplace'),
                'success' => \__('Success', 'carbon-marketplace'),
                'error' => \__('Error', 'carbon-marketplace'),
                'confirmReset' => \__('Are you sure you want to reset this vendor configuration?', 'carbon-marketplace'),
                'connectionSuccess' => \__('Connection successful!', 'carbon-marketplace'),
                'connectionFailed' => \__('Connection failed. Please check your credentials.', 'carbon-marketplace'),
            )
        ));
    }
    
    /**
     * Render vendor configuration page
     */
    public function render_vendor_config_page() {
        $vendors = $this->get_vendor_definitions();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="vendor-config-container">
                <?php foreach ($vendors as $vendor_id => $vendor_info): ?>
                    <?php $this->render_vendor_config_card($vendor_id, $vendor_info); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render individual vendor configuration card
     */
    private function render_vendor_config_card($vendor_id, $vendor_info) {
        $credentials = $this->credential_manager->get_credentials($vendor_id);
        $status = $this->credential_manager->get_vendor_status($vendor_id);
        ?>
        <div class="vendor-config-card" data-vendor="<?php echo esc_attr($vendor_id); ?>">
            <div class="vendor-header">
                <div class="vendor-title">
                    <h2><?php echo esc_html($vendor_info['name']); ?></h2>
                    <div class="vendor-status">
                        <span class="status-indicator <?php echo esc_attr($status['status']); ?>"></span>
                        <span class="status-text"><?php echo esc_html($status['message']); ?></span>
                    </div>
                </div>
                <div class="vendor-toggle">
                    <label class="toggle-switch">
                        <input type="checkbox" 
                               class="vendor-enabled-toggle" 
                               <?php checked($credentials['enabled'] ?? false); ?>
                               data-vendor="<?php echo esc_attr($vendor_id); ?>">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="vendor-description">
                <p><?php echo esc_html($vendor_info['description']); ?></p>
            </div>
            
            <div class="vendor-config-form" <?php echo !($credentials['enabled'] ?? false) ? 'style="display:none;"' : ''; ?>>
                <form class="vendor-form" data-vendor="<?php echo esc_attr($vendor_id); ?>">
                    <?php wp_nonce_field('carbon_marketplace_vendor', 'vendor_nonce'); ?>
                    
                    <?php foreach ($vendor_info['fields'] as $field_id => $field_config): ?>
                        <div class="form-field">
                            <label for="<?php echo esc_attr($vendor_id . '_' . $field_id); ?>">
                                <?php echo esc_html($field_config['label']); ?>
                                <?php if ($field_config['required'] ?? false): ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php $this->render_field($vendor_id, $field_id, $field_config, $credentials); ?>
                            
                            <?php if (!empty($field_config['description'])): ?>
                                <p class="field-description"><?php echo esc_html($field_config['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="form-actions">
                        <button type="button" class="button button-secondary test-connection-btn">
                            <?php _e('Test Connection', 'carbon-marketplace'); ?>
                        </button>
                        <button type="submit" class="button button-primary save-config-btn">
                            <?php _e('Save Configuration', 'carbon-marketplace'); ?>
                        </button>
                        <button type="button" class="button button-link-delete reset-config-btn">
                            <?php _e('Reset', 'carbon-marketplace'); ?>
                        </button>
                    </div>
                    
                    <div class="connection-result" style="display:none;"></div>
                </form>
            </div>
            
            <?php if (!empty($vendor_info['documentation'])): ?>
                <div class="vendor-documentation">
                    <h4><?php _e('Documentation', 'carbon-marketplace'); ?></h4>
                    <ul>
                        <?php foreach ($vendor_info['documentation'] as $doc): ?>
                            <li>
                                <a href="<?php echo esc_url($doc['url']); ?>" target="_blank">
                                    <?php echo esc_html($doc['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render form field
     */
    private function render_field($vendor_id, $field_id, $field_config, $credentials) {
        $field_name = $vendor_id . '_' . $field_id;
        $field_value = $credentials[$field_id] ?? ($field_config['default'] ?? '');
        $field_type = $field_config['type'] ?? 'text';
        
        switch ($field_type) {
            case 'password':
                ?>
                <input type="password" 
                       id="<?php echo esc_attr($field_name); ?>" 
                       name="<?php echo esc_attr($field_id); ?>" 
                       value="<?php echo esc_attr($field_value); ?>" 
                       class="regular-text"
                       <?php echo ($field_config['required'] ?? false) ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'select':
                ?>
                <select id="<?php echo esc_attr($field_name); ?>" 
                        name="<?php echo esc_attr($field_id); ?>" 
                        <?php echo ($field_config['required'] ?? false) ? 'required' : ''; ?>>
                    <?php foreach ($field_config['options'] as $option_value => $option_label): ?>
                        <option value="<?php echo esc_attr($option_value); ?>" 
                                <?php selected($field_value, $option_value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;
                
            case 'checkbox':
                ?>
                <label>
                    <input type="checkbox" 
                           id="<?php echo esc_attr($field_name); ?>" 
                           name="<?php echo esc_attr($field_id); ?>" 
                           value="1" 
                           <?php checked($field_value); ?>>
                    <?php echo esc_html($field_config['checkbox_label'] ?? ''); ?>
                </label>
                <?php
                break;
                
            case 'textarea':
                ?>
                <textarea id="<?php echo esc_attr($field_name); ?>" 
                          name="<?php echo esc_attr($field_id); ?>" 
                          rows="<?php echo esc_attr($field_config['rows'] ?? 3); ?>" 
                          class="large-text"
                          <?php echo ($field_config['required'] ?? false) ? 'required' : ''; ?>><?php echo esc_textarea($field_value); ?></textarea>
                <?php
                break;
                
            default: // text
                ?>
                <input type="text" 
                       id="<?php echo esc_attr($field_name); ?>" 
                       name="<?php echo esc_attr($field_id); ?>" 
                       value="<?php echo esc_attr($field_value); ?>" 
                       class="regular-text"
                       <?php echo ($field_config['required'] ?? false) ? 'required' : ''; ?>>
                <?php
                break;
        }
    }
    
    /**
     * Get vendor definitions
     */
    private function get_vendor_definitions() {
        return array(
            'cnaught' => array(
                'name' => 'CNaught',
                'description' => 'CNaught provides high-quality carbon credits through their API platform. Configure your API credentials to access their portfolio of verified carbon offset projects.',
                'fields' => array(
                    'api_key' => array(
                        'label' => 'API Key',
                        'type' => 'password',
                        'required' => true,
                        'description' => 'Your CNaught API key from the developer dashboard'
                    ),
                    'sandbox_mode' => array(
                        'label' => 'Environment',
                        'type' => 'select',
                        'options' => array(
                            '0' => 'Production',
                            '1' => 'Sandbox (Testing)'
                        ),
                        'default' => '1',
                        'description' => 'Use sandbox for testing, production for live transactions'
                    )
                ),
                'documentation' => array(
                    array(
                        'title' => 'CNaught API Documentation',
                        'url' => 'https://docs.cnaught.com'
                    ),
                    array(
                        'title' => 'Get API Key',
                        'url' => 'https://app.cnaught.com/developers'
                    )
                )
            ),
            'toucan' => array(
                'name' => 'Toucan Protocol',
                'description' => 'Toucan Protocol brings carbon credits on-chain through tokenized carbon credits (TCO2). Access decentralized carbon markets through The Graph subgraph.',
                'fields' => array(
                    'api_key' => array(
                        'label' => 'The Graph API Key (Optional)',
                        'type' => 'password',
                        'required' => false,
                        'description' => 'Optional API key for The Graph hosted service (higher rate limits)'
                    ),
                    'network' => array(
                        'label' => 'Network',
                        'type' => 'select',
                        'options' => array(
                            'polygon' => 'Polygon Mainnet',
                            'mumbai' => 'Polygon Mumbai (Testnet)'
                        ),
                        'default' => 'polygon',
                        'description' => 'Blockchain network to query for carbon credits'
                    )
                ),
                'documentation' => array(
                    array(
                        'title' => 'Toucan Protocol Documentation',
                        'url' => 'https://docs.toucan.earth'
                    ),
                    array(
                        'title' => 'The Graph API Keys',
                        'url' => 'https://thegraph.com/studio/apikeys/'
                    ),
                    array(
                        'title' => 'Subgraph Explorer',
                        'url' => 'https://thegraph.com/explorer/subgraphs/FU5APMSSCqcRy9jy56aXJiGV3PQmFQHg2tzukvSJBgwW'
                    )
                )
            )
        );
    }
    
    /**
     * AJAX handler for saving vendor configuration
     */
    public function ajax_save_vendor_config() {
        check_ajax_referer('carbon_marketplace_vendor', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(\__('Insufficient permissions', 'carbon-marketplace'));
        }
        
        $vendor = sanitize_text_field($_POST['vendor'] ?? '');
        $config_data = $_POST['config'] ?? array();
        
        if (empty($vendor)) {
            wp_send_json_error(\__('Vendor not specified', 'carbon-marketplace'));
        }
        
        // Sanitize configuration data
        $sanitized_config = $this->sanitize_vendor_config($vendor, $config_data);
        
        // Add enabled flag
        $sanitized_config['enabled'] = true;
        
        // Update credentials
        $result = $this->credential_manager->update_credentials($vendor, $sanitized_config);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Update API manager with new credentials
        $this->update_api_manager_client($vendor);
        
        wp_send_json_success(\__('Configuration saved successfully', 'carbon-marketplace'));
    }
    
    /**
     * AJAX handler for testing vendor connection
     */
    public function ajax_test_vendor_connection() {
        check_ajax_referer('carbon_marketplace_vendor', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(\__('Insufficient permissions', 'carbon-marketplace'));
        }
        
        $vendor = sanitize_text_field($_POST['vendor'] ?? '');
        $config_data = $_POST['config'] ?? array();
        
        if (empty($vendor)) {
            wp_send_json_error(\__('Vendor not specified', 'carbon-marketplace'));
        }
        
        // Sanitize configuration data
        $sanitized_config = $this->sanitize_vendor_config($vendor, $config_data);
        
        // Test credentials
        $result = $this->credential_manager->validate_credentials($vendor, $sanitized_config);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(\__('Connection test successful', 'carbon-marketplace'));
    }
    
    /**
     * AJAX handler for toggling vendor enabled status
     */
    public function ajax_toggle_vendor() {
        check_ajax_referer('carbon_marketplace_vendor', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(\__('Insufficient permissions', 'carbon-marketplace'));
        }
        
        $vendor = sanitize_text_field($_POST['vendor'] ?? '');
        $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        if (empty($vendor)) {
            wp_send_json_error(\__('Vendor not specified', 'carbon-marketplace'));
        }
        
        // Get current credentials
        $credentials = $this->credential_manager->get_credentials($vendor);
        $credentials['enabled'] = $enabled;
        
        // Update credentials
        $result = $this->credential_manager->update_credentials($vendor, $credentials);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Update API manager
        if ($enabled) {
            $this->update_api_manager_client($vendor);
        } else {
            $this->api_manager->unregister_client($vendor);
        }
        
        $status_message = $enabled ? \__('Vendor enabled', 'carbon-marketplace') : \__('Vendor disabled', 'carbon-marketplace');
        wp_send_json_success($status_message);
    }
    
    /**
     * AJAX handler for resetting vendor configuration
     */
    public function ajax_reset_vendor() {
        check_ajax_referer('carbon_marketplace_vendor', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(\__('Insufficient permissions', 'carbon-marketplace'));
        }
        
        $vendor = sanitize_text_field($_POST['vendor'] ?? '');
        
        if (empty($vendor)) {
            wp_send_json_error(\__('Vendor not specified', 'carbon-marketplace'));
        }
        
        // Delete credentials
        $result = $this->credential_manager->delete_credentials($vendor);
        
        if (!$result) {
            wp_send_json_error(\__('Failed to reset vendor configuration', 'carbon-marketplace'));
        }
        
        // Remove from API manager
        $this->api_manager->unregister_client($vendor);
        
        wp_send_json_success(\__('Vendor configuration reset', 'carbon-marketplace'));
    }
    
    /**
     * Sanitize vendor configuration data
     */
    private function sanitize_vendor_config($vendor, $config_data) {
        $vendor_definitions = $this->get_vendor_definitions();
        $vendor_fields = $vendor_definitions[$vendor]['fields'] ?? array();
        
        $sanitized = array();
        
        foreach ($vendor_fields as $field_id => $field_config) {
            $value = $config_data[$field_id] ?? '';
            
            switch ($field_config['type'] ?? 'text') {
                case 'password':
                case 'text':
                case 'textarea':
                    $sanitized[$field_id] = sanitize_text_field($value);
                    break;
                    
                case 'select':
                    $valid_options = array_keys($field_config['options'] ?? array());
                    $sanitized[$field_id] = in_array($value, $valid_options) ? $value : ($field_config['default'] ?? '');
                    break;
                    
                case 'checkbox':
                    $sanitized[$field_id] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                    
                default:
                    $sanitized[$field_id] = sanitize_text_field($value);
                    break;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Update API manager client
     */
    private function update_api_manager_client($vendor) {
        $credentials = $this->credential_manager->get_credentials($vendor);
        
        if (!$credentials['enabled']) {
            return;
        }
        
        try {
            switch ($vendor) {
                case 'cnaught':
                    $client = new \CarbonMarketplace\Api\CNaughtClient();
                    $client->set_api_key($credentials['api_key']);
                    $client->set_sandbox_mode($credentials['sandbox_mode'] ?? false);
                    $this->api_manager->register_client('cnaught', $client);
                    break;
                    
                case 'toucan':
                    $client = new \CarbonMarketplace\Api\ToucanClient();
                    if (!empty($credentials['api_key'])) {
                        $client->set_api_key($credentials['api_key']);
                    }
                    $client->set_network($credentials['network'] ?? 'polygon');
                    $this->api_manager->register_client('toucan', $client);
                    break;
            }
        } catch (Exception $e) {
            error_log('Failed to update API manager client for ' . $vendor . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Get vendor configuration summary
     */
    public function get_vendor_summary() {
        $vendors = $this->get_vendor_definitions();
        $summary = array();
        
        foreach ($vendors as $vendor_id => $vendor_info) {
            $status = $this->credential_manager->get_vendor_status($vendor_id);
            $summary[$vendor_id] = array(
                'name' => $vendor_info['name'],
                'enabled' => $status['enabled'],
                'configured' => $status['configured'],
                'status' => $status['status'],
                'message' => $status['message']
            );
        }
        
        return $summary;
    }
}