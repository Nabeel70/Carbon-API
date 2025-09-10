<?php
/**
 * Elementor Integration for Carbon Marketplace
 *
 * @package CarbonMarketplace\Elementor
 */

namespace CarbonMarketplace\Elementor;

use CarbonMarketplace\Elementor\SearchWidget;

/**
 * Elementor Integration class
 */
class ElementorIntegration {
    
    /**
     * Initialize Elementor integration
     */
    public function init() {
        // Check if Elementor is active
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', array($this, 'elementor_missing_notice'));
            return;
        }
        
        // Register hooks
        add_action('elementor/widgets/register', array($this, 'register_widgets'));
        add_action('elementor/elements/categories_registered', array($this, 'register_widget_categories'));
        add_action('elementor/frontend/after_enqueue_styles', array($this, 'enqueue_frontend_styles'));
        add_action('elementor/frontend/after_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('elementor/editor/after_enqueue_styles', array($this, 'enqueue_editor_styles'));
        
        // Register AJAX handlers
        add_action('wp_ajax_carbon_marketplace_search_projects', array($this, 'ajax_search_projects'));
        add_action('wp_ajax_nopriv_carbon_marketplace_search_projects', array($this, 'ajax_search_projects'));
    }
    
    /**
     * Show notice if Elementor is not active
     */
    public function elementor_missing_notice() {
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
        
        $message = sprintf(
            esc_html__('Carbon Marketplace requires %1$s to be installed and activated.', 'carbon-marketplace'),
            '<strong>' . esc_html__('Elementor', 'carbon-marketplace') . '</strong>'
        );
        
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }
    
    /**
     * Register widget categories
     */
    public function register_widget_categories($elements_manager) {
        $elements_manager->add_category(
            'carbon-marketplace',
            array(
                'title' => __('Carbon Marketplace', 'carbon-marketplace'),
                'icon' => 'fa fa-leaf',
            )
        );
    }
    
    /**
     * Register widgets
     */
    public function register_widgets($widgets_manager) {
        // Register Search Widget
        $widgets_manager->register(new SearchWidget());
        
        // Register additional widgets here as they are created
        // $widgets_manager->register(new ProjectGridWidget());
        // $widgets_manager->register(new ProjectDetailWidget());
    }
    
    /**
     * Enqueue frontend styles
     */
    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'carbon-marketplace-elementor-frontend',
            CARBON_MARKETPLACE_URL . 'assets/css/elementor-frontend.css',
            [],
            CARBON_MARKETPLACE_VERSION
        );
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script(
            'carbon-marketplace-elementor-frontend',
            CARBON_MARKETPLACE_URL . 'assets/js/elementor-frontend.js',
            ['jquery', 'elementor-frontend'],
            CARBON_MARKETPLACE_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('carbon-marketplace-elementor-frontend', 'carbonMarketplaceElementor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('carbon_marketplace_elementor'),
            'strings' => array(
                'loading' => __('Loading...', 'carbon-marketplace'),
                'error' => __('An error occurred', 'carbon-marketplace'),
                'noResults' => __('No results found', 'carbon-marketplace'),
            )
        ));
    }
    
    /**
     * Enqueue editor styles
     */
    public function enqueue_editor_styles() {
        wp_enqueue_style(
            'carbon-marketplace-elementor-editor',
            CARBON_MARKETPLACE_URL . 'assets/css/elementor-editor.css',
            [],
            CARBON_MARKETPLACE_VERSION
        );
    }
    
    /**
     * AJAX handler for project search
     */
    public function ajax_search_projects() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'carbon_marketplace_search')) {
            wp_send_json_error(__('Invalid security token', 'carbon-marketplace'));
        }
        
        try {
            // Get search parameters
            $search_params = $_POST['search_params'] ?? array();
            $config = $_POST['config'] ?? array();
            $page = intval($_POST['page'] ?? 1);
            $per_page = intval($_POST['per_page'] ?? 12);
            
            // Sanitize search parameters
            $search_params = $this->sanitize_search_params($search_params);
            
            // Get search engine instance
            $search_engine = $this->get_search_engine();
            
            // Build search query
            $search_query = new \CarbonMarketplace\Search\SearchQuery();
            $search_query->set_keyword($search_params['keyword'] ?? '');
            $search_query->set_location($search_params['location'] ?? '');
            $search_query->set_project_type($search_params['project_type'] ?? '');
            $search_query->set_min_price($search_params['min_price'] ?? null);
            $search_query->set_max_price($search_params['max_price'] ?? null);
            $search_query->set_limit($per_page);
            $search_query->set_offset(($page - 1) * $per_page);
            
            // Perform search
            $search_results = $search_engine->search($search_query);
            
            if ($search_results->has_errors()) {
                wp_send_json_error($search_results->get_error_message());
            }
            
            // Format results for frontend
            $formatted_results = $this->format_search_results($search_results, $config);
            
            wp_send_json_success($formatted_results);
            
        } catch (Exception $e) {
            error_log('Carbon Marketplace search error: ' . $e->getMessage());
            wp_send_json_error(__('Search failed. Please try again.', 'carbon-marketplace'));
        }
    }
    
    /**
     * Sanitize search parameters
     */
    private function sanitize_search_params($params) {
        $sanitized = array();
        
        if (isset($params['keyword'])) {
            $sanitized['keyword'] = sanitize_text_field($params['keyword']);
        }
        
        if (isset($params['location'])) {
            $sanitized['location'] = sanitize_text_field($params['location']);
        }
        
        if (isset($params['project_type'])) {
            $sanitized['project_type'] = sanitize_text_field($params['project_type']);
        }
        
        if (isset($params['min_price']) && is_numeric($params['min_price'])) {
            $sanitized['min_price'] = floatval($params['min_price']);
        }
        
        if (isset($params['max_price']) && is_numeric($params['max_price'])) {
            $sanitized['max_price'] = floatval($params['max_price']);
        }
        
        return $sanitized;
    }
    
    /**
     * Get search engine instance
     */
    private function get_search_engine() {
        // Get the search engine from the main plugin class
        $plugin = \CarbonMarketplace\CarbonMarketplace::get_instance();
        return $plugin->get_search_engine();
    }
    
    /**
     * Format search results for frontend
     */
    private function format_search_results($search_results, $config) {
        $projects = array();
        
        foreach ($search_results->get_projects() as $project) {
            $formatted_project = array(
                'id' => $project->get_id(),
                'name' => $project->get_name(),
                'description' => $project->get_description(),
                'location' => $project->get_location(),
                'project_type' => $project->get_project_type(),
                'price_per_kg' => $project->get_price_per_kg(),
                'vendor' => $project->get_vendor(),
                'available_quantity' => $project->get_available_quantity(),
            );
            
            // Add image if available and enabled
            if ($config['showImages'] ?? true) {
                $images = $project->get_images();
                $formatted_project['image'] = !empty($images) ? $images[0] : '';
            }
            
            $projects[] = $formatted_project;
        }
        
        return array(
            'projects' => $projects,
            'pagination' => array(
                'current_page' => $search_results->get_current_page(),
                'total_pages' => $search_results->get_total_pages(),
                'total_count' => $search_results->get_total_count(),
                'per_page' => $search_results->get_per_page(),
            ),
            'metadata' => $search_results->get_metadata(),
        );
    }
    
    /**
     * Check if Elementor is active
     */
    public static function is_elementor_active() {
        return did_action('elementor/loaded');
    }
    
    /**
     * Get minimum Elementor version required
     */
    public static function get_minimum_elementor_version() {
        return '3.0.0';
    }
    
    /**
     * Check if Elementor version is compatible
     */
    public static function is_elementor_version_compatible() {
        if (!self::is_elementor_active()) {
            return false;
        }
        
        return version_compare(ELEMENTOR_VERSION, self::get_minimum_elementor_version(), '>=');
    }
}