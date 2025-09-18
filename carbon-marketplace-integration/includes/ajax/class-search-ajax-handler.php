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
     * API Manager instance
     *
     * @var ApiManager
     */
    private $api_manager;
    
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
     * @param ApiManager $api_manager ApiManager instance
     */
    public function __construct(SearchEngine $search_engine = null, ApiManager $api_manager = null) {
        $this->search_engine = $search_engine ?: new SearchEngine();
        $this->api_manager = $api_manager;
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Register AJAX handlers for both logged-in and non-logged-in users
        \add_action('wp_ajax_carbon_marketplace_search', [$this, 'handle_search_request']);
        \add_action('wp_ajax_nopriv_carbon_marketplace_search', [$this, 'handle_search_request']);
        
        \add_action('wp_ajax_carbon_marketplace_suggestions', [$this, 'handle_suggestions_request']);
        \add_action('wp_ajax_nopriv_carbon_marketplace_suggestions', [$this, 'handle_suggestions_request']);
        
        \add_action('wp_ajax_carbon_marketplace_project_details', [$this, 'handle_project_details_request']);
        \add_action('wp_ajax_nopriv_carbon_marketplace_project_details', [$this, 'handle_project_details_request']);
        
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
            
            // Perform search - use API Manager for live data if available
            if ($this->api_manager && $this->api_manager->get_all_clients()) {
                // Fetch live data from all registered API clients
                $projects = $this->api_manager->fetch_all_projects($search_params);
                
                if (is_wp_error($projects)) {
                    $this->send_error_response('API search failed: ' . $projects->get_error_message(), 500);
                    return;
                }
                
                // Filter projects based on search criteria
                $filtered_projects = $this->filter_projects($projects, $query);
                
                // Create results object
                $results = new \CarbonMarketplace\Search\SearchResults($filtered_projects, $query);
            } else {
                // Fallback to local search engine
                $results = $this->search_engine->search($query);
                
                // Check for search errors
                if ($results->has_errors()) {
                    $this->send_error_response('Search failed: ' . implode(', ', $results->get_errors()), 500);
                    return;
                }
            }
            
            // Prepare response data
            $response_data = [
                'success' => true,
                'data' => [
                    'projects' => $results->get_project_summaries(),
                    'pagination' => $results->get_pagination_info($query->limit, $query->offset),
                    'filters_applied' => $query->get_active_filters(),
                    'search_metadata' => $results->get_metadata(),
                ],
            ];
            
            // Add search time metadata
            $response_data['data']['search_metadata']['response_time'] = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            
            $this->send_json_response($response_data);
            
        } catch (\Exception $e) {
            error_log('SearchAjaxHandler::handle_search_request error: ' . $e->getMessage());
            $this->send_error_response('An unexpected error occurred', 500);
        }
    }
    
    /**
     * Handle suggestions AJAX request
     */
    public function handle_suggestions_request(): void {
        try {
            // Verify nonce for security
            if (!$this->verify_nonce()) {
                $this->send_error_response('Invalid security token', 403);
                return;
            }
            
            // Get and sanitize input
            $input = \sanitize_text_field($_POST['input'] ?? '');
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
    public function handle_project_details_request(): void {
        try {
            // Verify nonce for security
            if (!$this->verify_nonce()) {
                $this->send_error_response('Invalid security token', 403);
                return;
            }
            
            // Get and sanitize project ID
            $project_id = \sanitize_text_field($_POST['project_id'] ?? '');
            
            if (empty($project_id)) {
                $this->send_error_response('Project ID is required', 400);
                return;
            }
            
            // Get project from database
            $database = new Database();
            $project_data = $database->get_project((int) $project_id);
            
            if (!$project_data) {
                $this->send_error_response('Project not found', 404);
                return;
            }
            
            // Convert to Project object for consistent formatting
            $project = \CarbonMarketplace\Models\Project::from_array($project_data);
            
            $response_data = [
                'success' => true,
                'data' => [
                    'project' => $project->to_array(),
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
    public function enqueue_ajax_scripts(): void {
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
    private function verify_nonce(): bool {
        $nonce = $_POST['nonce'] ?? '';
        return \wp_verify_nonce($nonce, $this->nonce_action);
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
                $params[$field] = \sanitize_text_field($_POST[$field]);
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
        \wp_send_json($data);
    }
    
    /**
     * Send error response
     *
     * @param string $message Error message
     * @param int $status_code HTTP status code
     */
    private function send_error_response(string $message, int $status_code = 400): void {
        \status_header($status_code);
        \wp_send_json([
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
    public function get_search_statistics(): array {
        // This could be expanded to track search metrics
        return [
            'total_searches' => \get_option('carbon_marketplace_total_searches', 0),
            'popular_keywords' => \get_option('carbon_marketplace_popular_keywords', []),
            'average_results' => \get_option('carbon_marketplace_average_results', 0),
        ];
    }
    
    /**
     * Track search for analytics
     *
     * @param SearchQuery $query Search query
     * @param int $result_count Number of results
     */
    private function track_search(SearchQuery $query, int $result_count): void {
        // Increment total searches
        $total_searches = \get_option('carbon_marketplace_total_searches', 0);
        \update_option('carbon_marketplace_total_searches', $total_searches + 1);
        
        // Track popular keywords
        if (!empty($query->keyword)) {
            $popular_keywords = \get_option('carbon_marketplace_popular_keywords', []);
            $keyword = strtolower($query->keyword);
            $popular_keywords[$keyword] = ($popular_keywords[$keyword] ?? 0) + 1;
            
            // Keep only top 100 keywords
            arsort($popular_keywords);
            $popular_keywords = array_slice($popular_keywords, 0, 100, true);
            
            \update_option('carbon_marketplace_popular_keywords', $popular_keywords);
        }
        
        // Update average results
        $current_average = \get_option('carbon_marketplace_average_results', 0);
        $new_average = (($current_average * ($total_searches - 1)) + $result_count) / $total_searches;
        \update_option('carbon_marketplace_average_results', round($new_average, 2));
    }
    
    /**
     * Filter projects based on search query criteria
     *
     * @param array $projects Array of Project objects
     * @param SearchQuery $query Search query
     * @return array Filtered projects
     */
    private function filter_projects($projects, $query) {
        if (empty($projects)) {
            return array();
        }
        
        $filtered = $projects;
        
        // Filter by search keyword
        if (!empty($query->keyword)) {
            $keyword = strtolower($query->keyword);
            $filtered = array_filter($filtered, function($project) use ($keyword) {
                return strpos(strtolower($project->name), $keyword) !== false ||
                       strpos(strtolower($project->description), $keyword) !== false ||
                       strpos(strtolower($project->location), $keyword) !== false;
            });
        }
        
        // Filter by location
        if (!empty($query->location)) {
            $filtered = array_filter($filtered, function($project) use ($query) {
                return stripos($project->location, $query->location) !== false;
            });
        }
        
        // Filter by project type
        if (!empty($query->project_type)) {
            $filtered = array_filter($filtered, function($project) use ($query) {
                return stripos($project->project_type, $query->project_type) !== false;
            });
        }
        
        // Filter by vendor
        if (!empty($query->vendor)) {
            $filtered = array_filter($filtered, function($project) use ($query) {
                return $project->vendor === $query->vendor;
            });
        }
        
        // Filter by price range
        if (isset($query->min_price) && $query->min_price > 0) {
            $filtered = array_filter($filtered, function($project) use ($query) {
                return $project->price_per_kg >= $query->min_price;
            });
        }
        
        if (isset($query->max_price) && $query->max_price > 0) {
            $filtered = array_filter($filtered, function($project) use ($query) {
                return $project->price_per_kg <= $query->max_price;
            });
        }
        
        // Apply pagination
        $offset = $query->offset ?? 0;
        $limit = $query->limit ?? 20;
        
        return array_slice($filtered, $offset, $limit);
    }
}