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
use CarbonMarketplace\Core\Database;

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
     * Nonce action for search requests
     *
     * @var string
     */
    private $nonce_action = 'carbon_marketplace_nonce';
    
    /**
     * Constructor
     *
     * @param SearchEngine $search_engine SearchEngine instance
     */
    public function __construct(SearchEngine $search_engine = null) {
        $this->search_engine = $search_engine ?: new SearchEngine();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Register AJAX handlers for both logged-in and non-logged-in users
        \add_action('wp_ajax_carbon_marketplace_search', [$this, 'handle_search_request']);
        \add_action('wp_ajax_nopriv_carbon_marketplace_search', [$this, 'handle_search_request']);
        
        \add_action('wp_ajax_carbon_marketplace_suggestions', [$this, 'handle_suggestions_request']);
        \add_action('wp_ajax_nopriv_carbon_marketplace_suggestions', [$this, 'handle_suggestions_request']);
        
        \add_action('wp_ajax_carbon_marketplace_project_details', [$this, 'handle_project_details_request']);
        \add_action('wp_ajax_nopriv_carbon_marketplace_project_details', [$this, 'handle_project_details_request']);
        
        \add_action('wp_ajax_carbon_marketplace_get_project_detail', [$this, 'handle_project_details_request']);
        \add_action('wp_ajax_nopriv_carbon_marketplace_get_project_detail', [$this, 'handle_project_details_request']);
        
        // Enqueue scripts for AJAX
        \add_action('wp_enqueue_scripts', [$this, 'enqueue_ajax_scripts']);
    }
    
    /**
     * Handle search AJAX request
     */
    public function handle_search_request() {
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
            
            // For now, return a simple response until SearchQuery and SearchEngine are fully implemented
            $response_data = [
                'success' => true,
                'data' => [
                    'html' => '<div class="search-message">Search functionality is being implemented. Parameters received: ' . json_encode($search_params) . '</div>',
                    'count_text' => 'Search results will be available soon',
                    'has_more' => false
                ],
            ];
            
            $this->send_json_response($response_data);
            
        } catch (\Exception $e) {
            error_log('SearchAjaxHandler::handle_search_request error: ' . $e->getMessage());
            $this->send_error_response('An unexpected error occurred', 500);
        }
    }
    
    /**
     * Handle suggestions AJAX request
     */
    public function handle_suggestions_request() {
        try {
            // Verify nonce for security
            if (!$this->verify_nonce()) {
                $this->send_error_response('Invalid security token', 403);
                return;
            }
            
            // Get and sanitize input
            $input = sanitize_text_field($_POST['input'] ?? '');
            $limit = (int) ($_POST['limit'] ?? 10);
            
            // Validate input
            if (empty($input) || strlen($input) < 2) {
                $this->send_json_response([
                    'success' => true,
                    'data' => ['suggestions' => []],
                ]);
                return;
            }
            
            // Limit the number of suggestions
            $limit = max(1, min($limit, 20));
            
            // Get suggestions
            $suggestions = $this->search_engine->get_suggestions($input, $limit);
            
            $response_data = [
                'success' => true,
                'data' => [
                    'suggestions' => $suggestions,
                    'input' => $input,
                ],
            ];
            
            $this->send_json_response($response_data);
            
        } catch (\Exception $e) {
            error_log('SearchAjaxHandler::handle_suggestions_request error: ' . $e->getMessage());
            $this->send_error_response('An unexpected error occurred', 500);
        }
    }
    
    /**
     * Handle project details AJAX request
     */
    public function handle_project_details_request() {
        try {
            // Verify nonce for security
            if (!$this->verify_nonce()) {
                $this->send_error_response('Invalid security token', 403);
                return;
            }
            
            // Get and sanitize project ID
            $project_id = sanitize_text_field($_POST['project_id'] ?? '');
            
            if (empty($project_id)) {
                $this->send_error_response('Project ID is required', 400);
                return;
            }
            
            // For now, return a simple response
            $response_data = [
                'success' => true,
                'data' => [
                    'html' => '<div class="project-detail-placeholder">Project details for ID: ' . $project_id . ' will be available once API integration is complete.</div>',
                ],
            ];
            
            $this->send_json_response($response_data);
            
        } catch (\Exception $e) {
            error_log('SearchAjaxHandler::handle_project_details_request error: ' . $e->getMessage());
            $this->send_error_response('An unexpected error occurred', 500);
        }
    }
    
    /**
     * Enqueue AJAX scripts
     */
    public function enqueue_ajax_scripts() {
        // Enqueue jQuery if not already loaded
        wp_enqueue_script('jquery');
        
        // Create inline script for AJAX configuration
        $ajax_config = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce($this->nonce_action),
            'actions' => [
                'search' => 'carbon_marketplace_search',
                'suggestions' => 'carbon_marketplace_suggestions',
                'project_details' => 'carbon_marketplace_project_details',
            ],
        ];
        
        wp_localize_script('jquery', 'carbonMarketplaceAjax', $ajax_config);
    }
    
    /**
     * Verify nonce for security
     *
     * @return bool True if nonce is valid
     */
    private function verify_nonce() {
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
    private function send_json_response(array $data) {
        wp_send_json($data);
    }
    
    /**
     * Send error response
     *
     * @param string $message Error message
     * @param int $status_code HTTP status code
     */
    private function send_error_response(string $message, int $status_code = 400) {
        status_header($status_code);
        wp_send_json([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $status_code,
            ],
        ]);
    }
    
    /**
     * Get search statistics for admin
     *
     * @return array Search statistics
     */
    public function get_search_statistics() {
        // This could be expanded to track search metrics
        return [
            'total_searches' => get_option('carbon_marketplace_total_searches', 0),
            'popular_keywords' => get_option('carbon_marketplace_popular_keywords', []),
            'average_results' => get_option('carbon_marketplace_average_results', 0),
        ];
    }
    
    /**
     * Track search for analytics
     *
     * @param SearchQuery $query Search query
     * @param int $result_count Number of results
     */
    private function track_search(SearchQuery $query, int $result_count) {
        // Increment total searches
        $total_searches = get_option('carbon_marketplace_total_searches', 0);
        update_option('carbon_marketplace_total_searches', $total_searches + 1);
        
        // Track popular keywords
        if (!empty($query->keyword)) {
            $popular_keywords = get_option('carbon_marketplace_popular_keywords', []);
            $keyword = strtolower($query->keyword);
            $popular_keywords[$keyword] = ($popular_keywords[$keyword] ?? 0) + 1;
            
            // Keep only top 100 keywords
            arsort($popular_keywords);
            $popular_keywords = array_slice($popular_keywords, 0, 100, true);
            
            update_option('carbon_marketplace_popular_keywords', $popular_keywords);
        }
        
        // Update average results
        $current_average = get_option('carbon_marketplace_average_results', 0);
        $new_average = (($current_average * ($total_searches - 1)) + $result_count) / $total_searches;
        update_option('carbon_marketplace_average_results', round($new_average, 2));
    }
}