<?php
/**
 * Search AJAX Handler Class
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Ajax;

use CarbonMarketplace\Search\SearchEngine;
use CarbonMarketplace\Models\SearchQuery;
use CarbonMarketplace\Api\ApiManager;

/**
 * SearchAjaxHandler class for handling AJAX search requests
 */
class SearchAjaxHandler {
    
    /**
     * SearchEngine instance
     *
     * @var SearchEngine
     */
    private $search_engine;
    
    /**
     * ApiManager instance
     *
     * @var ApiManager
     */
    private $api_manager;
    
    /**
     * Nonce action for search requests
     *
     * @var string
     */
    private $nonce_action = 'carbon_marketplace_search';
    
    /**
     * Constructor
     *
     * @param SearchEngine $search_engine SearchEngine instance
     * @param ApiManager $api_manager ApiManager instance
     */
    public function __construct(SearchEngine $search_engine = null, ApiManager $api_manager = null) {
        $this->api_manager = $api_manager ?: new ApiManager();
        $this->search_engine = $search_engine ?: new SearchEngine($this->api_manager);
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Register AJAX handlers for both logged-in and non-logged-in users
        \add_action('wp_ajax_carbon_marketplace_search', [$this, 'handle_search_request']);
        \add_action('wp_ajax_nopriv_carbon_marketplace_search', [$this, 'handle_search_request']);
        
        // Enqueue scripts for AJAX
        \add_action('wp_enqueue_scripts', [$this, 'enqueue_ajax_scripts']);
    }
    
    /**
     * Handle search AJAX request
     */
    public function handle_search_request(): void {
        try {
            // Verify nonce for security
            if (!$this->verify_nonce()) {
                $this->send_error_response('Invalid security token', 403);
                return;
            }
            
            // Get and sanitize input parameters
            $search_params = $this->get_sanitized_search_params();
            
            // Validate required parameters
            if ($search_params === false) {
                $this->send_error_response('Invalid search parameters', 400);
                return;
            }
            
            // Create search query
            $query = new SearchQuery($search_params);
            
            // Validate search query
            if (!$query->validate()) {
                $this->send_error_response('Invalid search query: ' . implode(', ', $query->get_validation_errors()), 400);
                return;
            }
            
            // Perform search
            $results = $this->search_engine->search($query);
            
            // Check for search errors
            if ($results->has_errors()) {
                $this->send_error_response('Search failed: ' . implode(', ', $results->get_errors()), 500);
                return;
            }
            
            // Prepare response data
            $response_data = [
                'success' => true,
                'data' => [
                    'projects' => $this->format_projects_for_response($results->get_projects()),
                    'total_count' => $results->get_total_count(),
                    'filters_applied' => $query->get_active_filters(),
                    'pagination' => [
                        'current_page' => floor($query->offset / $query->limit) + 1,
                        'total_pages' => ceil($results->get_total_count() / $query->limit),
                        'per_page' => $query->limit,
                        'total_items' => $results->get_total_count()
                    ],
                ],
            ];
            
            // Add search time metadata
            $response_data['data']['response_time'] = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            
            $this->send_json_response($response_data);
            
        } catch (\Exception $e) {
            error_log('SearchAjaxHandler::handle_search_request error: ' . $e->getMessage());
            $this->send_error_response('An unexpected error occurred', 500);
        }
    }
    
    /**
     * Format projects for JSON response
     *
     * @param array $projects Array of Project objects
     * @return array Formatted project data
     */
    private function format_projects_for_response(array $projects): array {
        $formatted = [];
        
        foreach ($projects as $project) {
            $formatted[] = [
                'id' => $project->get_id(),
                'name' => $project->get_name(),
                'description' => wp_trim_words($project->get_description(), 30),
                'location' => $project->get_location(),
                'project_type' => $project->get_project_type(),
                'methodology' => $project->get_methodology(),
                'price_per_kg' => $project->get_price_per_kg(),
                'available_quantity' => $project->get_available_quantity(),
                'images' => $project->get_images(),
                'sdgs' => $project->get_sdgs(),
                'vendor' => $project->get_vendor()
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Enqueue AJAX scripts
     */
    public function enqueue_ajax_scripts(): void {
        // Enqueue jQuery if not already loaded
        wp_enqueue_script('jquery');
        
        // Create inline script for AJAX configuration
        $ajax_config = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce($this->nonce_action),
            'actions' => [
                'search' => 'carbon_marketplace_search',
            ],
        ];
        
        wp_localize_script('jquery', 'carbonMarketplaceAjax', $ajax_config);
    }
    
    /**
     * Verify nonce for security
     *
     * @return bool True if nonce is valid
     */
    private function verify_nonce(): bool {
        $nonce = $_POST['nonce'] ?? '';
        return wp_verify_nonce($nonce, $this->nonce_action);
    }
    
    /**
     * Get and sanitize search parameters
     *
     * @return array|false Sanitized parameters or false on error
     */
    private function get_sanitized_search_params() {
        $params = [];
        
        // Sanitize text fields
        $text_fields = ['keyword', 'location', 'project_type', 'vendor', 'sort_by', 'sort_order'];
        foreach ($text_fields as $field) {
            if (isset($_POST[$field]) && !empty($_POST[$field])) {
                $params[$field] = sanitize_text_field($_POST[$field]);
            }
        }
        
        // Sanitize numeric fields
        $numeric_fields = ['min_price', 'max_price', 'limit', 'offset'];
        foreach ($numeric_fields as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                $value = floatval($_POST[$field]);
                if ($value >= 0) {
                    $params[$field] = $value;
                }
            }
        }
        
        // Set default values
        $params['limit'] = isset($params['limit']) ? min(max((int) $params['limit'], 1), 100) : 20;
        $params['offset'] = isset($params['offset']) ? max((int) $params['offset'], 0) : 0;
        
        // Validate sort parameters
        $valid_sort_fields = ['name', 'price_per_kg', 'location', 'project_type', 'created_at'];
        if (isset($params['sort_by']) && !in_array($params['sort_by'], $valid_sort_fields)) {
            $params['sort_by'] = 'name';
        }
        
        $valid_sort_orders = ['asc', 'desc'];
        if (isset($params['sort_order']) && !in_array($params['sort_order'], $valid_sort_orders)) {
            $params['sort_order'] = 'asc';
        }
        
        return $params;
    }
    
    /**
     * Send JSON response
     *
     * @param array $data Response data
     */
    private function send_json_response(array $data): void {
        wp_send_json($data);
    }
    
    /**
     * Send error response
     *
     * @param string $message Error message
     * @param int $status_code HTTP status code
     */
    private function send_error_response(string $message, int $status_code = 400): void {
        status_header($status_code);
        wp_send_json([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $status_code,
            ],
        ]);
    }
}