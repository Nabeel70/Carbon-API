<?php
/**
 * Toucan API Client
 *
 * Client for interacting with the Toucan Protocol GraphQL subgraph.
 * Handles TCO2 tokens, pool contents, and pricing information.
 *
 * @package CarbonMarketplace
 * @subpackage API
 */

namespace CarbonMarketplace\API;

use CarbonMarketplace\API\BaseApiClient;
use CarbonMarketplace\API\ApiException;
use CarbonMarketplace\Models\Project;
use CarbonMarketplace\Models\Portfolio;
use CarbonMarketplace\Models\TokenPrice;
use WP_Error;

/**
 * Toucan API Client class
 */
class ToucanClient extends BaseApiClient {

    /**
     * GraphQL endpoint
     *
     * @var string
     */
    protected $graphql_endpoint = '/subgraphs/name/toucanprotocol/matic';

    /**
     * Supported pool addresses
     *
     * @var array
     */
    protected $pool_addresses = array(
        'BCT' => '0x2f800db0fdb5223b3c3f354886d907a671414a7f', // Base Carbon Tonne
        'NCT' => '0xd838290e877e0188a4a44700463419ed96c16107'  // Nature Carbon Tonne
    );

    /**
     * Initialize the Toucan client
     */
    protected function init() {
        // Set default base URL if not provided (Polygon subgraph)
        if (empty($this->base_url)) {
            $this->base_url = 'https://api.thegraph.com';
        }

        // Set Toucan-specific rate limits (The Graph has rate limits)
        if (empty($this->rate_limit_config)) {
            $this->rate_limit_config = array(
                'requests_per_second' => 2, // Conservative for The Graph
                'burst_limit' => 10
            );
        }
    }

    /**
     * Get authentication headers (The Graph doesn't require auth for public subgraphs)
     *
     * @return array Authentication headers
     */
    public function get_auth_headers() {
        $headers = array();
        
        // Add API key if provided for hosted service
        if (!empty($this->credentials['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $this->credentials['api_key'];
        }

        return $headers;
    }

    /**
     * Validate API credentials
     *
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_credentials() {
        try {
            // Test connection by making a simple query
            $query = '{
                _meta {
                    block {
                        number
                    }
                }
            }';
            
            $response = $this->execute_graphql_query($query);
            
            if (is_wp_error($response)) {
                return $response;
            }

            return isset($response['data']['_meta']);
        } catch (Exception $e) {
            return new WP_Error('credential_validation_failed', $e->getMessage());
        }
    }

    /**
     * Get client name
     *
     * @return string Client name
     */
    public function get_client_name() {
        return 'Toucan Protocol API Client';
    }

    /**
     * Fetch all TCO2 tokens
     *
     * @param int $limit Number of tokens to fetch
     * @param int $skip Number of tokens to skip
     * @return array|WP_Error Array of TCO2 tokens or error
     */
    public function fetch_all_tco2_tokens($limit = 100, $skip = 0) {
        $query = '{
            tco2Tokens(first: ' . intval($limit) . ', skip: ' . intval($skip) . ', orderBy: createdAt, orderDirection: desc) {
                id
                name
                symbol
                address
                projectVintageId
                createdAt
                totalSupply
                projectVintage {
                    id
                    name
                    startTime
                    endTime
                    project {
                        id
                        projectId
                        standard
                        methodology
                        region
                        storageMethod
                        method
                        emissionType
                        category
                        uri
                    }
                }
                poolBalances {
                    pool {
                        id
                        name
                        symbol
                    }
                    balance
                }
            }
        }';

        try {
            $response = $this->execute_graphql_query($query);
            
            if (is_wp_error($response)) {
                return $response;
            }

            return $this->normalize_tco2_tokens($response['data']['tco2Tokens'] ?? array());
        } catch (Exception $e) {
            $this->log_error('Failed to fetch TCO2 tokens', array('error' => $e->getMessage()));
            return new WP_Error('tco2_tokens_fetch_failed', $e->getMessage());
        }
    }

    /**
     * Fetch TCO2 token by ID
     *
     * @param string $token_id Token ID (address)
     * @return Project|WP_Error Project object or error
     */
    public function fetch_tco2_token_by_id($token_id) {
        if (empty($token_id)) {
            return new WP_Error('invalid_token_id', 'Token ID is required');
        }

        $query = '{
            tco2Token(id: "' . strtolower($token_id) . '") {
                id
                name
                symbol
                address
                projectVintageId
                createdAt
                totalSupply
                projectVintage {
                    id
                    name
                    startTime
                    endTime
                    project {
                        id
                        projectId
                        standard
                        methodology
                        region
                        storageMethod
                        method
                        emissionType
                        category
                        uri
                    }
                }
                poolBalances {
                    pool {
                        id
                        name
                        symbol
                    }
                    balance
                }
            }
        }';

        try {
            $response = $this->execute_graphql_query($query);
            
            if (is_wp_error($response)) {
                return $response;
            }

            $token_data = $response['data']['tco2Token'] ?? null;
            if (!$token_data) {
                return new WP_Error('token_not_found', 'TCO2 token not found');
            }

            return $this->normalize_tco2_token($token_data);
        } catch (Exception $e) {
            $this->log_error('Failed to fetch TCO2 token', array(
                'token_id' => $token_id,
                'error' => $e->getMessage()
            ));
            return new WP_Error('tco2_token_fetch_failed', $e->getMessage());
        }
    }

    /**
     * Fetch pool contents
     *
     * @param string $pool_address Pool contract address
     * @param int $limit Number of tokens to fetch
     * @return array|WP_Error Array of pool contents or error
     */
    public function fetch_pool_contents($pool_address, $limit = 100) {
        if (empty($pool_address)) {
            return new WP_Error('invalid_pool_address', 'Pool address is required');
        }

        $query = '{
            pooledTCO2Tokens(
                where: { pool: "' . strtolower($pool_address) . '" }
                first: ' . intval($limit) . '
                orderBy: amount
                orderDirection: desc
            ) {
                id
                amount
                token {
                    id
                    name
                    symbol
                    address
                    projectVintage {
                        id
                        name
                        startTime
                        endTime
                        project {
                            id
                            projectId
                            standard
                            methodology
                            region
                            storageMethod
                            method
                            emissionType
                            category
                        }
                    }
                }
                pool {
                    id
                    name
                    symbol
                    totalSupply
                }
            }
        }';

        try {
            $response = $this->execute_graphql_query($query);
            
            if (is_wp_error($response)) {
                return $response;
            }

            return $this->normalize_pool_contents($response['data']['pooledTCO2Tokens'] ?? array());
        } catch (Exception $e) {
            $this->log_error('Failed to fetch pool contents', array(
                'pool_address' => $pool_address,
                'error' => $e->getMessage()
            ));
            return new WP_Error('pool_contents_fetch_failed', $e->getMessage());
        }
    }

    /**
     * Fetch token price on DEX
     *
     * @param string $token_address Token contract address
     * @return TokenPrice|WP_Error TokenPrice object or error
     */
    public function fetch_token_price_on_dex($token_address) {
        if (empty($token_address)) {
            return new WP_Error('invalid_token_address', 'Token address is required');
        }

        // Query for recent swaps to determine price
        $query = '{
            swaps(
                where: { 
                    or: [
                        { token0: "' . strtolower($token_address) . '" }
                        { token1: "' . strtolower($token_address) . '" }
                    ]
                }
                first: 10
                orderBy: timestamp
                orderDirection: desc
            ) {
                id
                timestamp
                token0 {
                    id
                    symbol
                    decimals
                }
                token1 {
                    id
                    symbol
                    decimals
                }
                amount0In
                amount0Out
                amount1In
                amount1Out
                amountUSD
            }
        }';

        try {
            $response = $this->execute_graphql_query($query);
            
            if (is_wp_error($response)) {
                return $response;
            }

            $swaps = $response['data']['swaps'] ?? array();
            if (empty($swaps)) {
                return new WP_Error('no_price_data', 'No recent price data available');
            }

            return $this->calculate_token_price($token_address, $swaps);
        } catch (Exception $e) {
            $this->log_error('Failed to fetch token price', array(
                'token_address' => $token_address,
                'error' => $e->getMessage()
            ));
            return new WP_Error('token_price_fetch_failed', $e->getMessage());
        }
    }

    /**
     * Get available pools
     *
     * @return array|WP_Error Array of available pools or error
     */
    public function get_available_pools() {
        $query = '{
            pools(first: 10, orderBy: totalSupply, orderDirection: desc) {
                id
                name
                symbol
                totalSupply
                pooledTCO2Tokens(first: 5) {
                    amount
                    token {
                        name
                        symbol
                    }
                }
            }
        }';

        try {
            $response = $this->execute_graphql_query($query);
            
            if (is_wp_error($response)) {
                return $response;
            }

            return $this->normalize_pools($response['data']['pools'] ?? array());
        } catch (Exception $e) {
            $this->log_error('Failed to fetch pools', array('error' => $e->getMessage()));
            return new WP_Error('pools_fetch_failed', $e->getMessage());
        }
    }

    /**
     * Execute GraphQL query
     *
     * @param string $query GraphQL query
     * @param array $variables Query variables
     * @return array|WP_Error Response data or error
     */
    protected function execute_graphql_query($query, $variables = array()) {
        $request_data = array(
            'query' => $query
        );

        if (!empty($variables)) {
            $request_data['variables'] = $variables;
        }

        $headers = array_merge(
            $this->get_default_headers(),
            $this->get_auth_headers(),
            array('Content-Type' => 'application/json')
        );

        return $this->make_request('POST', $this->graphql_endpoint, $request_data, $headers);
    }

    /**
     * Normalize TCO2 tokens data
     *
     * @param array $tokens Raw tokens data
     * @return array Normalized tokens
     */
    protected function normalize_tco2_tokens($tokens) {
        if (!is_array($tokens)) {
            return array();
        }

        return array_map(array($this, 'normalize_tco2_token'), $tokens);
    }

    /**
     * Normalize TCO2 token data to Project format
     *
     * @param array $token Raw token data
     * @return Project Normalized project object
     */
    protected function normalize_tco2_token($token) {
        $project_vintage = $token['projectVintage'] ?? array();
        $project = $project_vintage['project'] ?? array();

        return new Project(array(
            'id' => $token['id'] ?? '',
            'vendor' => 'toucan',
            'name' => $project_vintage['name'] ?? $token['name'] ?? '',
            'description' => $this->build_project_description($project, $project_vintage),
            'location' => $project['region'] ?? '',
            'project_type' => $project['category'] ?? '',
            'methodology' => $project['methodology'] ?? '',
            'price_per_kg' => 0, // Price needs to be fetched separately from DEX
            'available_quantity' => $this->parse_token_supply($token['totalSupply'] ?? '0'),
            'images' => array(),
            'sdgs' => array(),
            'registry_url' => $project['uri'] ?? '',
            'metadata' => array(
                'token_address' => $token['address'] ?? '',
                'token_symbol' => $token['symbol'] ?? '',
                'project_id' => $project['projectId'] ?? '',
                'standard' => $project['standard'] ?? '',
                'vintage_start' => $project_vintage['startTime'] ?? '',
                'vintage_end' => $project_vintage['endTime'] ?? '',
                'emission_type' => $project['emissionType'] ?? '',
                'storage_method' => $project['storageMethod'] ?? '',
                'pool_balances' => $token['poolBalances'] ?? array()
            )
        ));
    }

    /**
     * Normalize pool contents data
     *
     * @param array $pool_contents Raw pool contents data
     * @return array Normalized pool contents
     */
    protected function normalize_pool_contents($pool_contents) {
        if (!is_array($pool_contents)) {
            return array();
        }

        return array_map(function($item) {
            return array(
                'token' => $this->normalize_tco2_token($item['token'] ?? array()),
                'amount' => $this->parse_token_amount($item['amount'] ?? '0'),
                'pool_info' => array(
                    'id' => $item['pool']['id'] ?? '',
                    'name' => $item['pool']['name'] ?? '',
                    'symbol' => $item['pool']['symbol'] ?? '',
                    'total_supply' => $this->parse_token_supply($item['pool']['totalSupply'] ?? '0')
                )
            );
        }, $pool_contents);
    }

    /**
     * Normalize pools data
     *
     * @param array $pools Raw pools data
     * @return array Normalized pools
     */
    protected function normalize_pools($pools) {
        if (!is_array($pools)) {
            return array();
        }

        return array_map(function($pool) {
            return new Portfolio(array(
                'id' => $pool['id'] ?? '',
                'vendor' => 'toucan',
                'name' => $pool['name'] ?? '',
                'description' => "Toucan Protocol {$pool['name']} pool containing various carbon projects",
                'projects' => array(), // Projects would be loaded separately
                'base_price_per_kg' => 0, // Price needs to be fetched from DEX
                'is_active' => true,
                'metadata' => array(
                    'pool_address' => $pool['id'] ?? '',
                    'symbol' => $pool['symbol'] ?? '',
                    'total_supply' => $this->parse_token_supply($pool['totalSupply'] ?? '0'),
                    'pooled_tokens' => $pool['pooledTCO2Tokens'] ?? array()
                )
            ));
        }, $pools);
    }

    /**
     * Calculate token price from swap data
     *
     * @param string $token_address Token address
     * @param array $swaps Swap data
     * @return TokenPrice Token price object
     */
    protected function calculate_token_price($token_address, $swaps) {
        $total_usd_value = 0;
        $total_token_amount = 0;
        $token_address = strtolower($token_address);

        foreach ($swaps as $swap) {
            $token0_address = strtolower($swap['token0']['id'] ?? '');
            $token1_address = strtolower($swap['token1']['id'] ?? '');
            
            $amount_usd = floatval($swap['amountUSD'] ?? 0);
            
            if ($token0_address === $token_address) {
                $token_amount = floatval($swap['amount0In'] ?? 0) + floatval($swap['amount0Out'] ?? 0);
            } elseif ($token1_address === $token_address) {
                $token_amount = floatval($swap['amount1In'] ?? 0) + floatval($swap['amount1Out'] ?? 0);
            } else {
                continue;
            }

            if ($token_amount > 0 && $amount_usd > 0) {
                $total_usd_value += $amount_usd;
                $total_token_amount += $token_amount;
            }
        }

        $price_per_token = $total_token_amount > 0 ? $total_usd_value / $total_token_amount : 0;

        return new TokenPrice(array(
            'token_address' => $token_address,
            'price_usd' => $price_per_token,
            'currency' => 'USD',
            'last_updated' => current_time('mysql'),
            'data_source' => 'toucan_dex_swaps',
            'metadata' => array(
                'total_swaps' => count($swaps),
                'total_usd_volume' => $total_usd_value,
                'total_token_volume' => $total_token_amount
            )
        ));
    }

    /**
     * Build project description from project and vintage data
     *
     * @param array $project Project data
     * @param array $project_vintage Project vintage data
     * @return string Project description
     */
    protected function build_project_description($project, $project_vintage) {
        $parts = array();
        
        if (!empty($project['methodology'])) {
            $parts[] = "Methodology: {$project['methodology']}";
        }
        
        if (!empty($project['standard'])) {
            $parts[] = "Standard: {$project['standard']}";
        }
        
        if (!empty($project_vintage['startTime']) && !empty($project_vintage['endTime'])) {
            $start_year = date('Y', $project_vintage['startTime']);
            $end_year = date('Y', $project_vintage['endTime']);
            $parts[] = "Vintage: {$start_year}-{$end_year}";
        }
        
        if (!empty($project['emissionType'])) {
            $parts[] = "Emission Type: {$project['emissionType']}";
        }

        return implode(' | ', $parts);
    }

    /**
     * Parse token supply from string (handles big numbers)
     *
     * @param string $supply Token supply string
     * @return int Parsed supply
     */
    protected function parse_token_supply($supply) {
        // Convert from wei (18 decimals) to whole tokens
        return intval(floatval($supply) / pow(10, 18));
    }

    /**
     * Parse token amount from string (handles big numbers)
     *
     * @param string $amount Token amount string
     * @return float Parsed amount
     */
    protected function parse_token_amount($amount) {
        // Convert from wei (18 decimals) to whole tokens
        return floatval($amount) / pow(10, 18);
    }
}