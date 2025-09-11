<?php
/**
 * CNaught API Client for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace\Api
 */

namespace CarbonMarketplace\Api;

use CarbonMarketplace\Models\Project;
use CarbonMarketplace\Models\Portfolio;
use CarbonMarketplace\Models\Quote;
use CarbonMarketplace\Models\CheckoutSession;
use CarbonMarketplace\Models\Order;
use WP_Error;

/**
 * CNaught API Client
 */
class CNaughtClient extends BaseApiClient {
    
    /**
     * API version
     */
    const API_VERSION = 'v1';
    
    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct($config = array()) {
        // Set default configuration for CNaught API
        $default_config = array(
            'base_url' => 'https://api.cnaught.com/' . self::API_VERSION,
            'rate_limits' => array(
                'requests_per_second' => 100, // CNaught allows 100 requests per second
                'burst_limit' => 1000,
            ),
            'timeout' => 30,
        );
        
        $config = array_merge($default_config, $config);
        parent::__construct($config);
    }
    
    /**
     * Get authentication headers
     *
     * @return array Authentication headers
     */
    protected function get_auth_headers() {
        $headers = array();
        
        if (!empty($this->credentials['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $this->credentials['api_key'];
        }
        
        if (!empty($this->credentials['client_id'])) {
            $headers['X-Client-ID'] = $this->credentials['client_id'];
        }
        
        return $headers;
    }
    
    /**
     * Get client name
     *
     * @return string Client name
     */
    public function get_client_name() {
        return 'CNaught';
    }
    
    /**
     * Validate API credentials
     *
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_credentials() {
        if (empty($this->credentials['api_key'])) {
            return new WP_Error(
                'missing_credentials',
                'CNaught API key is required',
                array('field' => 'api_key')
            );
        }
        
        // Test credentials with a simple API call
        $response = $this->make_request('GET', 'portfolios', array('limit' => 1));
        
        if (is_wp_error($response)) {
            if ($response->get_error_code() === 'client_error') {
                $data = $response->get_error_data();
                if (isset($data['status']) && $data['status'] === 401) {
                    return new WP_Error(
                        'invalid_credentials',
                        'Invalid CNaught API key',
                        array('field' => 'api_key')
                    );
                }
            }
            return $response;
        }
        
        return true;
    }
    
    /**
     * Get all portfolios
     *
     * @param array $params Query parameters
     * @return array|WP_Error Array of Portfolio objects or error
     */
    public function get_portfolios($params = array()) {
        $default_params = array(
            'limit' => 100,
            'offset' => 0,
        );
        
        $params = array_merge($default_params, $params);
        $response = $this->make_request('GET', 'portfolios', $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->normalize_portfolios($response['data'] ?? array());
    }
    
    /**
     * Get portfolio details by ID
     *
     * @param string $portfolio_id Portfolio ID
     * @return Portfolio|WP_Error Portfolio object or error
     */
    public function get_portfolio_details($portfolio_id) {
        if (empty($portfolio_id)) {
            return new WP_Error(
                'invalid_parameter',
                'Portfolio ID is required',
                array('parameter' => 'portfolio_id')
            );
        }
        
        $response = $this->make_request('GET', "portfolios/{$portfolio_id}");
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->normalize_portfolio($response);
    }
    
    /**
     * Get project details by ID
     *
     * @param string $project_id Project ID
     * @return Project|WP_Error Project object or error
     */
    public function get_project_details($project_id) {
        if (empty($project_id)) {
            return new WP_Error(
                'invalid_parameter',
                'Project ID is required',
                array('parameter' => 'project_id')
            );
        }
        
        $response = $this->make_request('GET', "projects/{$project_id}");
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->normalize_project($response);
    }
    
    /**
     * Create a quote for emissions mass
     *
     * @param array $quote_data Quote request data
     * @return Quote|WP_Error Quote object or error
     */
    public function create_quote($quote_data) {
        $required_fields = array('amount_kg');
        
        foreach ($required_fields as $field) {
            if (!isset($quote_data[$field])) {
                return new WP_Error(
                    'missing_parameter',
                    "Required parameter '{$field}' is missing",
                    array('parameter' => $field)
                );
            }
        }
        
        // Validate amount
        if (!is_numeric($quote_data['amount_kg']) || $quote_data['amount_kg'] <= 0) {
            return new WP_Error(
                'invalid_parameter',
                'Amount must be a positive number',
                array('parameter' => 'amount_kg')
            );
        }
        
        $request_data = array(
            'amount_kg' => (float) $quote_data['amount_kg'],
        );
        
        // Add optional portfolio ID
        if (!empty($quote_data['portfolio_id'])) {
            $request_data['portfolio_id'] = $quote_data['portfolio_id'];
        }
        
        $response = $this->make_request('POST', 'quotes', $request_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->normalize_quote($response);
    }
    
    /**
     * Create checkout session
     *
     * @param array $checkout_data Checkout session data
     * @return CheckoutSession|WP_Error CheckoutSession object or error
     */
    public function create_checkout_session($checkout_data) {
        $required_fields = array('amount_kg', 'success_url');
        
        foreach ($required_fields as $field) {
            if (!isset($checkout_data[$field])) {
                return new WP_Error(
                    'missing_parameter',
                    "Required parameter '{$field}' is missing",
                    array('parameter' => $field)
                );
            }
        }
        
        // Validate amount
        if (!is_numeric($checkout_data['amount_kg']) || $checkout_data['amount_kg'] <= 0) {
            return new WP_Error(
                'invalid_parameter',
                'Amount must be a positive number',
                array('parameter' => 'amount_kg')
            );
        }
        
        // Validate URLs
        if (!filter_var($checkout_data['success_url'], FILTER_VALIDATE_URL)) {
            return new WP_Error(
                'invalid_parameter',
                'Success URL must be a valid URL',
                array('parameter' => 'success_url')
            );
        }
        
        $request_data = array(
            'amount_kg' => (float) $checkout_data['amount_kg'],
            'success_url' => $checkout_data['success_url'],
        );
        
        // Add optional parameters
        $optional_fields = array(
            'cancel_url',
            'portfolio_id',
            'customer_email',
            'customer_name',
            'metadata',
            'notification_config',
        );
        
        foreach ($optional_fields as $field) {
            if (isset($checkout_data[$field])) {
                $request_data[$field] = $checkout_data[$field];
            }
        }
        
        // Validate cancel URL if provided
        if (isset($request_data['cancel_url']) && !filter_var($request_data['cancel_url'], FILTER_VALIDATE_URL)) {
            return new WP_Error(
                'invalid_parameter',
                'Cancel URL must be a valid URL',
                array('parameter' => 'cancel_url')
            );
        }
        
        $response = $this->make_request('POST', 'checkout/sessions', $request_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->normalize_checkout_session($response);
    }
    
    /**
     * Get checkout session by ID
     *
     * @param string $session_id Checkout session ID
     * @return CheckoutSession|WP_Error CheckoutSession object or error
     */
    public function get_checkout_session($session_id) {
        if (empty($session_id)) {
            return new WP_Error(
                'invalid_parameter',
                'Session ID is required',
                array('parameter' => 'session_id')
            );
        }
        
        $response = $this->make_request('GET', "checkout/sessions/{$session_id}");
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->normalize_checkout_session($response);
    }
    
    /**
     * Get orders with pagination
     *
     * @param array $params Query parameters
     * @return array|WP_Error Array of Order objects or error
     */
    public function get_orders($params = array()) {
        $default_params = array(
            'limit' => 50,
            'offset' => 0,
        );
        
        $params = array_merge($default_params, $params);
        $response = $this->make_request('GET', 'orders', $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->normalize_orders($response['data'] ?? array());
    }
    
    /**
     * Get order by ID
     *
     * @param string $order_id Order ID
     * @return Order|WP_Error Order object or error
     */
    public function get_order($order_id) {
        if (empty($order_id)) {
            return new WP_Error(
                'invalid_parameter',
                'Order ID is required',
                array('parameter' => 'order_id')
            );
        }
        
        $response = $this->make_request('GET', "orders/{$order_id}");
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->normalize_order($response);
    }
    
    /**
     * Get impact data
     *
     * @param array $params Query parameters
     * @return array|WP_Error Impact data or error
     */
    public function get_impact_data($params = array()) {
        $response = $this->make_request('GET', 'impact/data', $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Create subaccount
     *
     * @param array $subaccount_data Subaccount data
     * @return array|WP_Error Subaccount data or error
     */
    public function create_subaccount($subaccount_data) {
        $required_fields = array('name');
        
        foreach ($required_fields as $field) {
            if (!isset($subaccount_data[$field])) {
                return new WP_Error(
                    'missing_parameter',
                    "Required parameter '{$field}' is missing",
                    array('parameter' => $field)
                );
            }
        }
        
        $response = $this->make_request('POST', 'subaccounts', $subaccount_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Normalize portfolios array
     *
     * @param array $portfolios Raw portfolio data
     * @return array Array of Portfolio objects
     */
    private function normalize_portfolios($portfolios) {
        return array_map(array($this, 'normalize_portfolio'), $portfolios);
    }
    
    /**
     * Normalize single portfolio
     *
     * @param array $portfolio_data Raw portfolio data
     * @return Portfolio Portfolio object
     */
    private function normalize_portfolio($portfolio_data) {
        return new Portfolio(array(
            'id' => $portfolio_data['id'] ?? '',
            'vendor' => 'cnaught',
            'name' => $portfolio_data['name'] ?? '',
            'description' => $portfolio_data['description'] ?? '',
            'projects' => $this->extract_projects_from_portfolio($portfolio_data),
            'base_price_per_kg' => (float) ($portfolio_data['price_per_kg'] ?? 0),
            'is_active' => $portfolio_data['is_active'] ?? true,
            'metadata' => array(
                'categories' => $portfolio_data['categories'] ?? array(),
                'total_supply' => $portfolio_data['total_supply'] ?? 0,
                'available_supply' => $portfolio_data['available_supply'] ?? 0,
                'currency' => $portfolio_data['currency'] ?? 'USD',
            ),
        ));
    }
    
    /**
     * Extract projects from portfolio data
     *
     * @param array $portfolio_data Portfolio data
     * @return array Array of project IDs or Project objects
     */
    private function extract_projects_from_portfolio($portfolio_data) {
        if (!isset($portfolio_data['projects'])) {
            return array();
        }
        
        $projects = array();
        foreach ($portfolio_data['projects'] as $project_data) {
            if (is_array($project_data)) {
                $projects[] = $this->normalize_project($project_data);
            } else {
                $projects[] = $project_data; // Just project ID
            }
        }
        
        return $projects;
    }
    
    /**
     * Normalize single project
     *
     * @param array $project_data Raw project data
     * @return Project Project object
     */
    private function normalize_project($project_data) {
        return new Project(array(
            'id' => $project_data['id'] ?? '',
            'vendor' => 'cnaught',
            'name' => $project_data['name'] ?? '',
            'description' => $project_data['description'] ?? '',
            'location' => $this->extract_location($project_data),
            'project_type' => $project_data['category'] ?? $project_data['project_type'] ?? '',
            'methodology' => $project_data['methodology'] ?? '',
            'price_per_kg' => (float) ($project_data['price_per_kg'] ?? 0),
            'available_quantity' => (int) ($project_data['available_quantity'] ?? 0),
            'images' => $project_data['images'] ?? array(),
            'sdgs' => $project_data['sdgs'] ?? array(),
            'registry_url' => $project_data['registry_url'] ?? '',
            'metadata' => array(
                'standard' => $project_data['standard'] ?? '',
                'vintage' => $project_data['vintage'] ?? '',
                'verification_body' => $project_data['verification_body'] ?? '',
                'project_developer' => $project_data['project_developer'] ?? '',
                'emission_type' => $project_data['emission_type'] ?? '',
                'additional_certifications' => $project_data['additional_certifications'] ?? array(),
            ),
        ));
    }
    
    /**
     * Extract location from project data
     *
     * @param array $project_data Project data
     * @return string Location string
     */
    private function extract_location($project_data) {
        if (isset($project_data['location'])) {
            if (is_string($project_data['location'])) {
                return $project_data['location'];
            }
            
            if (is_array($project_data['location'])) {
                $location_parts = array();
                
                if (!empty($project_data['location']['city'])) {
                    $location_parts[] = $project_data['location']['city'];
                }
                
                if (!empty($project_data['location']['state'])) {
                    $location_parts[] = $project_data['location']['state'];
                }
                
                if (!empty($project_data['location']['country'])) {
                    $location_parts[] = $project_data['location']['country'];
                }
                
                return implode(', ', $location_parts);
            }
        }
        
        // Fallback to country or region
        return $project_data['country'] ?? $project_data['region'] ?? '';
    }
    
    /**
     * Normalize quote
     *
     * @param array $quote_data Raw quote data
     * @return Quote Quote object
     */
    private function normalize_quote($quote_data) {
        return new Quote(array(
            'id' => $quote_data['id'] ?? '',
            'vendor' => 'cnaught',
            'amount_kg' => (float) ($quote_data['amount_kg'] ?? 0),
            'price_per_kg' => (float) ($quote_data['price_per_kg'] ?? 0),
            'total_price' => (float) ($quote_data['total_price'] ?? 0),
            'currency' => $quote_data['currency'] ?? 'USD',
            'expires_at' => $quote_data['expires_at'] ?? null,
            'portfolio_id' => $quote_data['portfolio_id'] ?? null,
            'metadata' => array(
                'fees' => $quote_data['fees'] ?? array(),
                'taxes' => $quote_data['taxes'] ?? array(),
                'breakdown' => $quote_data['breakdown'] ?? array(),
            ),
        ));
    }
    
    /**
     * Normalize checkout session
     *
     * @param array $session_data Raw session data
     * @return CheckoutSession CheckoutSession object
     */
    private function normalize_checkout_session($session_data) {
        return new CheckoutSession(array(
            'id' => $session_data['id'] ?? '',
            'vendor' => 'cnaught',
            'checkout_url' => $session_data['checkout_url'] ?? '',
            'status' => $session_data['status'] ?? 'pending',
            'amount_kg' => (float) ($session_data['amount_kg'] ?? 0),
            'total_price' => (float) ($session_data['total_price'] ?? 0),
            'currency' => $session_data['currency'] ?? 'USD',
            'success_url' => $session_data['success_url'] ?? '',
            'cancel_url' => $session_data['cancel_url'] ?? '',
            'expires_at' => $session_data['expires_at'] ?? null,
            'metadata' => array(
                'customer_email' => $session_data['customer_email'] ?? '',
                'customer_name' => $session_data['customer_name'] ?? '',
                'portfolio_id' => $session_data['portfolio_id'] ?? null,
                'order_id' => $session_data['order_id'] ?? null,
            ),
        ));
    }
    
    /**
     * Normalize orders array
     *
     * @param array $orders Raw order data
     * @return array Array of Order objects
     */
    private function normalize_orders($orders) {
        return array_map(array($this, 'normalize_order'), $orders);
    }
    
    /**
     * Normalize single order
     *
     * @param array $order_data Raw order data
     * @return Order Order object
     */
    private function normalize_order($order_data) {
        return new Order(array(
            'id' => $order_data['id'] ?? '',
            'vendor_order_id' => $order_data['id'] ?? '',
            'vendor' => 'cnaught',
            'amount_kg' => (float) ($order_data['amount_kg'] ?? 0),
            'total_price' => (float) ($order_data['total_price'] ?? 0),
            'currency' => $order_data['currency'] ?? 'USD',
            'status' => $order_data['status'] ?? 'pending',
            'retirement_certificate' => $order_data['retirement_certificate'] ?? null,
            'project_allocations' => $order_data['project_allocations'] ?? array(),
            'created_at' => $order_data['created_at'] ?? null,
            'completed_at' => $order_data['completed_at'] ?? null,
            'metadata' => array(
                'customer_email' => $order_data['customer_email'] ?? '',
                'customer_name' => $order_data['customer_name'] ?? '',
                'portfolio_id' => $order_data['portfolio_id'] ?? null,
                'checkout_session_id' => $order_data['checkout_session_id'] ?? null,
                'retirement_serials' => $order_data['retirement_serials'] ?? array(),
            ),
        ));
    }
    
    /**
     * Map portfolio response data to Portfolio object
     *
     * @param array $portfolio_data Raw portfolio data from API
     * @return Portfolio Portfolio object
     */
    protected function map_portfolio_response($portfolio_data) {
        return new Portfolio(array(
            'id' => $portfolio_data['id'] ?? '',
            'vendor_portfolio_id' => $portfolio_data['id'] ?? '',
            'vendor' => 'cnaught',
            'name' => $portfolio_data['name'] ?? '',
            'description' => $portfolio_data['description'] ?? '',
            'base_price_per_kg' => (float) ($portfolio_data['base_price_per_kg'] ?? 0),
            'currency' => $portfolio_data['currency'] ?? 'USD',
            'is_active' => (bool) ($portfolio_data['is_active'] ?? true),
            'project_count' => (int) ($portfolio_data['project_count'] ?? 0),
            'total_available_quantity' => (float) ($portfolio_data['total_available_quantity'] ?? 0),
            'metadata' => array(
                'minimum_purchase' => $portfolio_data['minimum_purchase'] ?? null,
                'maximum_purchase' => $portfolio_data['maximum_purchase'] ?? null,
                'supported_currencies' => $portfolio_data['supported_currencies'] ?? array('USD'),
            ),
        ));
    }
    
    /**
     * Map project response data to Project object
     *
     * @param array $project_data Raw project data from API
     * @return Project Project object
     */
    protected function map_project_response($project_data) {
        return new Project(array(
            'id' => $project_data['id'] ?? '',
            'vendor_project_id' => $project_data['id'] ?? '',
            'vendor' => 'cnaught',
            'name' => $project_data['name'] ?? '',
            'description' => $project_data['description'] ?? '',
            'location' => $project_data['location'] ?? '',
            'country' => $project_data['country'] ?? '',
            'project_type' => $project_data['project_type'] ?? '',
            'methodology' => $project_data['methodology'] ?? '',
            'price_per_kg' => (float) ($project_data['price_per_kg'] ?? 0),
            'currency' => $project_data['currency'] ?? 'USD',
            'available_quantity' => (float) ($project_data['available_quantity'] ?? 0),
            'registry_name' => $project_data['registry_name'] ?? '',
            'registry_url' => $project_data['registry_url'] ?? '',
            'vintage_year' => (int) ($project_data['vintage_year'] ?? 0),
            'sdgs' => $project_data['sdgs'] ?? array(),
            'images' => $project_data['images'] ?? array(),
            'metadata' => array(
                'verification_standard' => $project_data['verification_standard'] ?? '',
                'project_status' => $project_data['project_status'] ?? 'active',
                'additional_certifications' => $project_data['additional_certifications'] ?? array(),
            ),
        ));
    }
    
    /**
     * Map quote response data to Quote object
     *
     * @param array $quote_data Raw quote data from API
     * @return Quote Quote object
     */
    protected function map_quote_response($quote_data) {
        return new Quote(array(
            'id' => $quote_data['id'] ?? '',
            'vendor_quote_id' => $quote_data['id'] ?? '',
            'vendor' => 'cnaught',
            'amount_kg' => (float) ($quote_data['amount_kg'] ?? 0),
            'price_per_kg' => (float) ($quote_data['price_per_kg'] ?? 0),
            'total_price' => (float) ($quote_data['total_price'] ?? 0),
            'currency' => $quote_data['currency'] ?? 'USD',
            'valid_until' => $quote_data['valid_until'] ?? null,
            'portfolio_id' => $quote_data['portfolio_id'] ?? null,
            'breakdown' => $quote_data['breakdown'] ?? array(),
            'metadata' => array(
                'tax_amount' => $quote_data['tax_amount'] ?? 0,
                'fee_amount' => $quote_data['fee_amount'] ?? 0,
                'discount_amount' => $quote_data['discount_amount'] ?? 0,
            ),
        ));
    }
    
    /**
     * Map checkout session response data to CheckoutSession object
     *
     * @param array $session_data Raw session data from API
     * @return CheckoutSession CheckoutSession object
     */
    protected function map_checkout_session_response($session_data) {
        return new CheckoutSession(array(
            'id' => $session_data['id'] ?? '',
            'vendor_session_id' => $session_data['id'] ?? '',
            'vendor' => 'cnaught',
            'checkout_url' => $session_data['checkout_url'] ?? '',
            'amount_kg' => (float) ($session_data['amount_kg'] ?? 0),
            'total_price' => (float) ($session_data['total_price'] ?? 0),
            'currency' => $session_data['currency'] ?? 'USD',
            'status' => $session_data['status'] ?? 'pending',
            'expires_at' => $session_data['expires_at'] ?? null,
            'success_url' => $session_data['success_url'] ?? '',
            'cancel_url' => $session_data['cancel_url'] ?? '',
            'metadata' => array(
                'customer_email' => $session_data['customer_email'] ?? '',
                'customer_name' => $session_data['customer_name'] ?? '',
                'portfolio_id' => $session_data['portfolio_id'] ?? null,
                'order_id' => $session_data['order_id'] ?? null,
                'webhook_url' => $session_data['webhook_url'] ?? '',
            ),
        ));
    }
}