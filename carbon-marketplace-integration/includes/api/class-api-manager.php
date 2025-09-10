<?php
/**
 * API Manager
 *
 * Coordinates calls across multiple vendor APIs and provides data aggregation
 * and normalization functionality.
 *
 * @package CarbonMarketplace
 * @subpackage API
 */

namespace CarbonMarketplace\API;

use CarbonMarketplace\Models\Portfolio;
use CarbonMarketplace\Models\Project;
use CarbonMarketplace\Models\Quote;
use CarbonMarketplace\Models\QuoteRequest;
use CarbonMarketplace\Models\CheckoutRequest;
use CarbonMarketplace\Models\CheckoutSession;
use WP_Error;

/**
 * API Manager class for multi-vendor coordination
 */
class ApiManager {

    /**
     * Registered API clients
     *
     * @var array
     */
    private $clients = [];

    /**
     * Default configuration
     *
     * @var array
     */
    private $config;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct($config = []) {
        $defaults = [
            'timeout' => 30,
            'max_retries' => 3,
            'parallel_requests' => true,
            'normalize_data' => true,
        ];
        
        // Use WordPress function if available, otherwise manual merge
        if (function_exists('wp_parse_args')) {
            $this->config = wp_parse_args($config, $defaults);
        } else {
            $this->config = array_merge($defaults, $config);
        }
    }

    /**
     * Register an API client
     *
     * @param string $vendor_name Vendor identifier
     * @param BaseApiClient $client API client instance
     * @return bool True on success, false on failure
     */
    public function register_client($vendor_name, BaseApiClient $client) {
        if (empty($vendor_name) || !($client instanceof BaseApiClient)) {
            return false;
        }

        $this->clients[$vendor_name] = $client;
        return true;
    }

    /**
     * Unregister an API client
     *
     * @param string $vendor_name Vendor identifier
     * @return bool True on success, false if vendor not found
     */
    public function unregister_client($vendor_name) {
        if (!isset($this->clients[$vendor_name])) {
            return false;
        }

        unset($this->clients[$vendor_name]);
        return true;
    }

    /**
     * Get registered client
     *
     * @param string $vendor_name Vendor identifier
     * @return BaseApiClient|null Client instance or null if not found
     */
    public function get_client($vendor_name) {
        return $this->clients[$vendor_name] ?? null;
    }

    /**
     * Get all registered clients
     *
     * @return array Array of client instances keyed by vendor name
     */
    public function get_all_clients() {
        return $this->clients;
    }

    /**
     * Fetch portfolios from all vendors
     *
     * @return array|WP_Error Array of Portfolio objects or error
     */
    public function fetch_all_portfolios() {
        if (empty($this->clients)) {
            return new WP_Error('no_clients', 'No API clients registered');
        }

        $all_portfolios = [];
        $errors = [];

        foreach ($this->clients as $vendor_name => $client) {
            try {
                // Check if client has portfolios method
                if (!method_exists($client, 'get_portfolios')) {
                    continue;
                }

                $portfolios = $client->get_portfolios();
                
                if (is_wp_error($portfolios)) {
                    $errors[$vendor_name] = $portfolios->get_error_message();
                    continue;
                }

                if (is_array($portfolios)) {
                    $normalized_portfolios = $this->normalize_portfolios($portfolios, $vendor_name);
                    $all_portfolios = array_merge($all_portfolios, $normalized_portfolios);
                }

            } catch (\Exception $e) {
                $errors[$vendor_name] = $e->getMessage();
            }
        }

        // Return error if all clients failed
        if (empty($all_portfolios) && !empty($errors)) {
            return new WP_Error('all_clients_failed', 'All API clients failed', $errors);
        }

        return $all_portfolios;
    }

    /**
     * Fetch projects from all vendors
     *
     * @param array $filters Optional filters to apply
     * @return array|WP_Error Array of Project objects or error
     */
    public function fetch_all_projects($filters = []) {
        if (empty($this->clients)) {
            return new WP_Error('no_clients', 'No API clients registered');
        }

        $all_projects = [];
        $errors = [];

        foreach ($this->clients as $vendor_name => $client) {
            try {
                $projects = [];

                // Try different methods to get projects
                if (method_exists($client, 'get_all_projects')) {
                    $projects = $client->get_all_projects($filters);
                } elseif (method_exists($client, 'fetch_all_tco2_tokens')) {
                    // For Toucan-style clients
                    $projects = $client->fetch_all_tco2_tokens();
                } elseif (method_exists($client, 'get_portfolios')) {
                    // Get projects from portfolios
                    $portfolios = $client->get_portfolios();
                    if (!is_wp_error($portfolios)) {
                        $projects = $this->extract_projects_from_portfolios($portfolios);
                    }
                }

                if (is_wp_error($projects)) {
                    $errors[$vendor_name] = $projects->get_error_message();
                    continue;
                }

                if (is_array($projects)) {
                    $normalized_projects = $this->normalize_projects($projects, $vendor_name);
                    $all_projects = array_merge($all_projects, $normalized_projects);
                }

            } catch (\Exception $e) {
                $errors[$vendor_name] = $e->getMessage();
            }
        }

        // Apply filters if specified
        if (!empty($filters) && !empty($all_projects)) {
            $all_projects = $this->apply_project_filters($all_projects, $filters);
        }

        // Return error if all clients failed
        if (empty($all_projects) && !empty($errors)) {
            return new WP_Error('all_clients_failed', 'All API clients failed', $errors);
        }

        return $all_projects;
    }

    /**
     * Get project details from specific vendor
     *
     * @param string $project_id Project ID
     * @param string $vendor_name Vendor name
     * @return Project|WP_Error Project object or error
     */
    public function get_project_details($project_id, $vendor_name) {
        $client = $this->get_client($vendor_name);
        
        if (!$client) {
            return new WP_Error('client_not_found', "Client for vendor '{$vendor_name}' not found");
        }

        if (!method_exists($client, 'get_project_details')) {
            return new WP_Error('method_not_supported', "Project details not supported by vendor '{$vendor_name}'");
        }

        try {
            $project = $client->get_project_details($project_id);
            
            if (is_wp_error($project)) {
                return $project;
            }

            return $this->normalize_project($project, $vendor_name);

        } catch (\Exception $e) {
            return new WP_Error('project_fetch_failed', $e->getMessage());
        }
    }

    /**
     * Get quote from best available vendor
     *
     * @param QuoteRequest $request Quote request
     * @return Quote|WP_Error Quote object or error
     */
    public function get_quote(QuoteRequest $request) {
        if (!$request->validate()) {
            return new WP_Error('invalid_request', 'Invalid quote request: ' . implode(', ', $request->get_validation_errors()));
        }

        $quotes = [];
        $errors = [];

        // If specific vendor requested, use only that vendor
        $preferred_vendor = $request->portfolio_id ? $this->extract_vendor_from_portfolio_id($request->portfolio_id) : null;
        if (!empty($preferred_vendor)) {
            $client = $this->get_client($preferred_vendor);
            
            if (!$client) {
                return new WP_Error('client_not_found', "Client for vendor '{$preferred_vendor}' not found");
            }

            if (!method_exists($client, 'create_quote')) {
                return new WP_Error('method_not_supported', "Quotes not supported by vendor '{$preferred_vendor}'");
            }

            try {
                return $client->create_quote($request);
            } catch (\Exception $e) {
                return new WP_Error('quote_failed', $e->getMessage());
            }
        }

        // Try all vendors and return best quote
        foreach ($this->clients as $vendor_name => $client) {
            if (!method_exists($client, 'create_quote')) {
                continue;
            }

            try {
                $quote = $client->create_quote($request);
                
                if (is_wp_error($quote)) {
                    $errors[$vendor_name] = $quote->get_error_message();
                    continue;
                }

                $quotes[] = $quote;

            } catch (\Exception $e) {
                $errors[$vendor_name] = $e->getMessage();
            }
        }

        if (empty($quotes)) {
            return new WP_Error('no_quotes', 'No quotes available', $errors);
        }

        // Return the best quote (lowest price)
        return $this->select_best_quote($quotes);
    }

    /**
     * Create checkout session with specific vendor
     *
     * @param CheckoutRequest $request Checkout request
     * @return CheckoutSession|WP_Error CheckoutSession object or error
     */
    public function create_checkout_session(CheckoutRequest $request) {
        if (!$request->validate()) {
            return new WP_Error('invalid_request', 'Invalid checkout request: ' . implode(', ', $request->get_validation_errors()));
        }

        // Determine vendor from portfolio_id or project_id if not explicitly set
        $vendor = $request->portfolio_id ? $this->extract_vendor_from_portfolio_id($request->portfolio_id) : 
                 ($request->project_id ? $this->extract_vendor_from_project_id($request->project_id) : null);
        
        if (empty($vendor)) {
            return new WP_Error('vendor_required', 'Vendor cannot be determined from request data');
        }

        $client = $this->get_client($vendor);
        
        if (!$client) {
            return new WP_Error('client_not_found', "Client for vendor '{$vendor}' not found");
        }

        if (!method_exists($client, 'create_checkout_session')) {
            return new WP_Error('method_not_supported', "Checkout sessions not supported by vendor '{$vendor}'");
        }

        try {
            return $client->create_checkout_session($request);
        } catch (\Exception $e) {
            return new WP_Error('checkout_failed', $e->getMessage());
        }
    }

    /**
     * Normalize portfolios from different vendors
     *
     * @param array $portfolios Raw portfolio data
     * @param string $vendor_name Vendor name
     * @return array Normalized Portfolio objects
     */
    private function normalize_portfolios($portfolios, $vendor_name) {
        if (!$this->config['normalize_data']) {
            return $portfolios;
        }

        $normalized = [];

        foreach ($portfolios as $portfolio) {
            if ($portfolio instanceof Portfolio) {
                // Ensure vendor is set
                if (empty($portfolio->vendor)) {
                    $portfolio->vendor = $vendor_name;
                }
                $normalized[] = $portfolio;
            } elseif (is_array($portfolio)) {
                // Convert array to Portfolio object
                $portfolio['vendor'] = $vendor_name;
                $portfolio_obj = new Portfolio($portfolio);
                if ($portfolio_obj->validate()) {
                    $normalized[] = $portfolio_obj;
                }
            }
        }

        return $normalized;
    }

    /**
     * Normalize projects from different vendors
     *
     * @param array $projects Raw project data
     * @param string $vendor_name Vendor name
     * @return array Normalized Project objects
     */
    private function normalize_projects($projects, $vendor_name) {
        if (!$this->config['normalize_data']) {
            return $projects;
        }

        $normalized = [];

        foreach ($projects as $project) {
            if ($project instanceof Project) {
                // Ensure vendor is set
                if (empty($project->vendor)) {
                    $project->vendor = $vendor_name;
                }
                $normalized[] = $project;
            } elseif (is_array($project)) {
                // Convert array to Project object
                $project['vendor'] = $vendor_name;
                $project_obj = new Project($project);
                if ($project_obj->validate()) {
                    $normalized[] = $project_obj;
                }
            }
        }

        return $normalized;
    }

    /**
     * Normalize single project
     *
     * @param mixed $project Raw project data
     * @param string $vendor_name Vendor name
     * @return Project|null Normalized Project object or null
     */
    private function normalize_project($project, $vendor_name) {
        if ($project instanceof Project) {
            if (empty($project->vendor)) {
                $project->vendor = $vendor_name;
            }
            return $project;
        }

        if (is_array($project)) {
            $project['vendor'] = $vendor_name;
            $project_obj = new Project($project);
            return $project_obj->validate() ? $project_obj : null;
        }

        return null;
    }

    /**
     * Extract projects from portfolios
     *
     * @param array $portfolios Portfolio data
     * @return array Extracted projects
     */
    private function extract_projects_from_portfolios($portfolios) {
        $projects = [];

        foreach ($portfolios as $portfolio) {
            if ($portfolio instanceof Portfolio && !empty($portfolio->projects)) {
                $projects = array_merge($projects, $portfolio->projects);
            } elseif (is_array($portfolio) && !empty($portfolio['projects'])) {
                $projects = array_merge($projects, $portfolio['projects']);
            }
        }

        return $projects;
    }

    /**
     * Apply filters to projects
     *
     * @param array $projects Project array
     * @param array $filters Filter criteria
     * @return array Filtered projects
     */
    private function apply_project_filters($projects, $filters) {
        return array_filter($projects, function($project) use ($filters) {
            if (!($project instanceof Project)) {
                return false;
            }

            // Location filter
            if (!empty($filters['location'])) {
                if (stripos($project->location, $filters['location']) === false) {
                    return false;
                }
            }

            // Project type filter
            if (!empty($filters['project_type'])) {
                if (stripos($project->project_type, $filters['project_type']) === false) {
                    return false;
                }
            }

            // Price range filter
            if (isset($filters['min_price']) && $project->price_per_kg < $filters['min_price']) {
                return false;
            }

            if (isset($filters['max_price']) && $project->price_per_kg > $filters['max_price']) {
                return false;
            }

            // Availability filter
            if (!empty($filters['available_only']) && !$project->is_available()) {
                return false;
            }

            return true;
        });
    }

    /**
     * Select best quote from multiple quotes
     *
     * @param array $quotes Array of Quote objects
     * @return Quote|null Best quote (lowest price) or null if no quotes
     */
    private function select_best_quote($quotes) {
        if (empty($quotes)) {
            return null;
        }

        if (count($quotes) === 1) {
            return $quotes[0];
        }

        // Sort by total price (ascending)
        usort($quotes, fn($a, $b) => $a->total_price <=> $b->total_price);

        return $quotes[0];
    }

    /**
     * Get aggregated statistics from all vendors
     *
     * @return array Statistics data
     */
    public function get_aggregated_stats() {
        $stats = [
            'total_vendors' => count($this->clients),
            'total_portfolios' => 0,
            'total_projects' => 0,
            'price_range' => ['min' => null, 'max' => null],
            'vendors' => [],
        ];

        foreach ($this->clients as $vendor_name => $client) {
            $vendor_stats = [
                'name' => $vendor_name,
                'portfolios' => 0,
                'projects' => 0,
                'status' => 'unknown',
            ];

            try {
                // Test client connectivity
                $validation = $client->validate_credentials();
                $vendor_stats['status'] = is_wp_error($validation) ? 'error' : 'active';

                // Get portfolio count
                if (method_exists($client, 'get_portfolios')) {
                    $portfolios = $client->get_portfolios();
                    if (!is_wp_error($portfolios) && is_array($portfolios)) {
                        $vendor_stats['portfolios'] = count($portfolios);
                        $stats['total_portfolios'] += $vendor_stats['portfolios'];
                    }
                }

            } catch (\Exception $e) {
                $vendor_stats['status'] = 'error';
                $vendor_stats['error'] = $e->getMessage();
            }

            $stats['vendors'][$vendor_name] = $vendor_stats;
        }

        return $stats;
    }

    /**
     * Validate all registered clients
     *
     * @return array Validation results keyed by vendor name
     */
    public function validate_all_clients() {
        $results = [];

        foreach ($this->clients as $vendor_name => $client) {
            try {
                $validation = $client->validate_credentials();
                $results[$vendor_name] = [
                    'valid' => !is_wp_error($validation),
                    'message' => is_wp_error($validation) ? $validation->get_error_message() : 'Valid',
                ];
            } catch (\Exception $e) {
                $results[$vendor_name] = [
                    'valid' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Extract vendor name from portfolio ID
     *
     * @param string $portfolio_id Portfolio ID (may contain vendor prefix)
     * @return string|null Vendor name or null if not found
     */
    private function extract_vendor_from_portfolio_id($portfolio_id) {
        if (empty($portfolio_id)) {
            return null;
        }

        // Check if portfolio ID has vendor prefix (e.g., "cnaught_portfolio123")
        foreach ($this->clients as $vendor_name => $client) {
            if (strpos($portfolio_id, $vendor_name . '_') === 0) {
                return $vendor_name;
            }
        }

        // Try to find portfolio in cached data
        $cached_portfolios = $this->get_cached_portfolios();
        if ($cached_portfolios) {
            foreach ($cached_portfolios as $portfolio) {
                if (($portfolio instanceof Portfolio && $portfolio->id === $portfolio_id) ||
                    (is_array($portfolio) && $portfolio['id'] === $portfolio_id)) {
                    return $portfolio instanceof Portfolio ? $portfolio->vendor : $portfolio['vendor'];
                }
            }
        }

        return null;
    }

    /**
     * Extract vendor name from project ID
     *
     * @param string $project_id Project ID (may contain vendor prefix)
     * @return string|null Vendor name or null if not found
     */
    private function extract_vendor_from_project_id($project_id) {
        if (empty($project_id)) {
            return null;
        }

        // Check if project ID has vendor prefix (e.g., "cnaught_project123")
        foreach ($this->clients as $vendor_name => $client) {
            if (strpos($project_id, $vendor_name . '_') === 0) {
                return $vendor_name;
            }
        }

        // Try to find project in cached data
        $cached_projects = $this->get_cached_projects();
        if ($cached_projects) {
            foreach ($cached_projects as $project) {
                if (($project instanceof Project && $project->id === $project_id) ||
                    (is_array($project) && $project['id'] === $project_id)) {
                    return $project instanceof Project ? $project->vendor : $project['vendor'];
                }
            }
        }

        return null;
    }

    /**
     * Get cached portfolios (placeholder for cache integration)
     *
     * @return array|null Cached portfolios or null if not available
     */
    private function get_cached_portfolios() {
        // This would integrate with the CacheManager in a real implementation
        // For now, return null to indicate no cache available
        return null;
    }

    /**
     * Get cached projects (placeholder for cache integration)
     *
     * @return array|null Cached projects or null if not available
     */
    private function get_cached_projects() {
        // This would integrate with the CacheManager in a real implementation
        // For now, return null to indicate no cache available
        return null;
    }

    /**
     * Aggregate project data from multiple sources
     *
     * @param array $filters Optional filters to apply
     * @return array Aggregated project data with metadata
     */
    public function aggregate_project_data($filters = []) {
        $projects = $this->fetch_all_projects($filters);
        
        if (is_wp_error($projects)) {
            return [
                'projects' => [],
                'error' => $projects->get_error_message(),
                'total_count' => 0,
                'vendor_counts' => [],
                'price_range' => ['min' => null, 'max' => null],
            ];
        }

        $aggregated = [
            'projects' => $projects,
            'total_count' => count($projects),
            'vendor_counts' => [],
            'price_range' => ['min' => null, 'max' => null],
            'project_types' => [],
            'locations' => [],
        ];

        // Calculate aggregated statistics
        foreach ($projects as $project) {
            if (!($project instanceof Project)) {
                continue;
            }

            // Vendor counts
            $vendor = $project->vendor;
            $aggregated['vendor_counts'][$vendor] = ($aggregated['vendor_counts'][$vendor] ?? 0) + 1;

            // Price range
            if ($project->price_per_kg > 0) {
                if ($aggregated['price_range']['min'] === null || $project->price_per_kg < $aggregated['price_range']['min']) {
                    $aggregated['price_range']['min'] = $project->price_per_kg;
                }
                if ($aggregated['price_range']['max'] === null || $project->price_per_kg > $aggregated['price_range']['max']) {
                    $aggregated['price_range']['max'] = $project->price_per_kg;
                }
            }

            // Project types
            if (!empty($project->project_type)) {
                $aggregated['project_types'][$project->project_type] = ($aggregated['project_types'][$project->project_type] ?? 0) + 1;
            }

            // Locations
            if (!empty($project->location)) {
                $aggregated['locations'][$project->location] = ($aggregated['locations'][$project->location] ?? 0) + 1;
            }
        }

        return $aggregated;
    }

    /**
     * Normalize data across vendors for consistent API responses
     *
     * @param array $data Raw data from vendor APIs
     * @param string $data_type Type of data ('portfolios', 'projects', 'quotes')
     * @param string $vendor_name Vendor name
     * @return array Normalized data
     */
    public function normalize_vendor_data($data, $data_type, $vendor_name) {
        if (!$this->config['normalize_data']) {
            return $data;
        }

        switch ($data_type) {
            case 'portfolios':
                return $this->normalize_portfolios($data, $vendor_name);
            
            case 'projects':
                return $this->normalize_projects($data, $vendor_name);
            
            case 'quotes':
                return $this->normalize_quotes($data, $vendor_name);
            
            default:
                return $data;
        }
    }

    /**
     * Normalize quotes from different vendors
     *
     * @param array $quotes Raw quote data
     * @param string $vendor_name Vendor name
     * @return array Normalized Quote objects
     */
    private function normalize_quotes($quotes, $vendor_name) {
        $normalized = [];

        foreach ($quotes as $quote) {
            if ($quote instanceof Quote) {
                if (empty($quote->vendor)) {
                    $quote->vendor = $vendor_name;
                }
                $normalized[] = $quote;
            } elseif (is_array($quote)) {
                $quote['vendor'] = $vendor_name;
                $quote_obj = new Quote($quote);
                if ($quote_obj->validate()) {
                    $normalized[] = $quote_obj;
                }
            }
        }

        return $normalized;
    }

    /**
     * Get vendor-specific configuration
     *
     * @param string $vendor_name Vendor name
     * @return array Vendor configuration
     */
    public function get_vendor_config($vendor_name) {
        $client = $this->get_client($vendor_name);
        
        if (!$client) {
            return [];
        }

        return [
            'name' => $vendor_name,
            'supports_portfolios' => method_exists($client, 'get_portfolios'),
            'supports_projects' => method_exists($client, 'get_all_projects') || method_exists($client, 'fetch_all_tco2_tokens'),
            'supports_quotes' => method_exists($client, 'create_quote'),
            'supports_checkout' => method_exists($client, 'create_checkout_session'),
            'supports_webhooks' => method_exists($client, 'handle_webhook'),
        ];
    }

    /**
     * Execute parallel API calls for improved performance
     *
     * @param array $calls Array of API calls to execute
     * @return array Results from all API calls
     */
    public function execute_parallel_calls($calls) {
        if (!$this->config['parallel_requests'] || empty($calls)) {
            return $this->execute_sequential_calls($calls);
        }

        // For now, execute sequentially as parallel execution would require
        // more complex implementation with curl_multi or similar
        return $this->execute_sequential_calls($calls);
    }

    /**
     * Execute API calls sequentially
     *
     * @param array $calls Array of API calls to execute
     * @return array Results from all API calls
     */
    private function execute_sequential_calls($calls) {
        $results = [];

        foreach ($calls as $call_id => $call) {
            try {
                $vendor = $call['vendor'];
                $method = $call['method'];
                $params = $call['params'] ?? [];

                $client = $this->get_client($vendor);
                if (!$client || !method_exists($client, $method)) {
                    $results[$call_id] = new WP_Error('invalid_call', "Invalid API call: {$vendor}::{$method}");
                    continue;
                }

                $results[$call_id] = call_user_func_array([$client, $method], $params);

            } catch (\Exception $e) {
                $results[$call_id] = new WP_Error('call_failed', $e->getMessage());
            }
        }

        return $results;
    }
}