<?php
/**
 * Unit tests for Toucan API Client
 *
 * @package CarbonMarketplace
 * @subpackage Tests
 */

namespace CarbonMarketplace\Tests\API;

use CarbonMarketplace\API\ToucanClient;
use CarbonMarketplace\Models\Project;
use CarbonMarketplace\Models\Portfolio;
use CarbonMarketplace\Models\TokenPrice;
use WP_Error;
use PHPUnit\Framework\TestCase;

/**
 * Mock Toucan client for testing
 */
class MockToucanClient extends ToucanClient {
    
    private $mock_responses = array();
    private $request_log = array();
    
    public function set_mock_response($query_pattern, $response) {
        $this->mock_responses[$query_pattern] = $response;
    }
    
    public function get_request_log() {
        return $this->request_log;
    }
    
    protected function execute_graphql_query($query, $variables = array()) {
        // Log the request
        $this->request_log[] = array(
            'query' => $query,
            'variables' => $variables
        );
        
        // Find matching mock response
        foreach ($this->mock_responses as $pattern => $response) {
            if (strpos($query, $pattern) !== false) {
                return $response;
            }
        }
        
        // Default success response
        return array('data' => array());
    }
}

/**
 * Test class for Toucan API Client
 */
class TestToucanClient extends TestCase {

    /**
     * Mock Toucan client instance
     *
     * @var MockToucanClient
     */
    private $client;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        $config = array(
            'base_url' => 'https://api.thegraph.com',
            'credentials' => array(
                'api_key' => 'test-api-key'
            ),
            'timeout' => 30
        );
        
        $this->client = new MockToucanClient($config);
    }

    /**
     * Test client initialization
     */
    public function test_client_initialization() {
        $this->assertInstanceOf(MockToucanClient::class, $this->client);
        $this->assertEquals('Toucan Protocol API Client', $this->client->get_client_name());
    }

    /**
     * Test authentication headers
     */
    public function test_get_auth_headers() {
        $headers = $this->client->get_auth_headers();
        
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer test-api-key', $headers['Authorization']);
    }

    /**
     * Test credential validation
     */
    public function test_validate_credentials() {
        $this->client->set_mock_response('_meta', array(
            'data' => array(
                '_meta' => array(
                    'block' => array('number' => 12345)
                )
            )
        ));
        
        $result = $this->client->validate_credentials();
        $this->assertTrue($result);
    }

    /**
     * Test fetch all TCO2 tokens
     */
    public function test_fetch_all_tco2_tokens() {
        $mock_tokens = array(
            array(
                'id' => '0x1234567890abcdef',
                'name' => 'TCO2-VCS-1234-2020',
                'symbol' => 'TCO2-VCS-1234-2020',
                'address' => '0x1234567890abcdef',
                'totalSupply' => '1000000000000000000000', // 1000 tokens in wei
                'projectVintage' => array(
                    'id' => 'vintage-1',
                    'name' => 'Amazon Forest Protection 2020',
                    'startTime' => 1577836800, // 2020-01-01
                    'endTime' => 1609459199,   // 2020-12-31
                    'project' => array(
                        'id' => 'project-1',
                        'projectId' => 'VCS-1234',
                        'standard' => 'VCS',
                        'methodology' => 'VM0007',
                        'region' => 'Brazil',
                        'category' => 'Forestry',
                        'emissionType' => 'Removal',
                        'uri' => 'https://registry.verra.org/project/1234'
                    )
                ),
                'poolBalances' => array()
            )
        );
        
        $this->client->set_mock_response('tco2Tokens', array(
            'data' => array('tco2Tokens' => $mock_tokens)
        ));
        
        $result = $this->client->fetch_all_tco2_tokens();
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Project::class, $result[0]);
        $this->assertEquals('0x1234567890abcdef', $result[0]->get_id());
        $this->assertEquals('toucan', $result[0]->get_vendor());
        $this->assertEquals('Amazon Forest Protection 2020', $result[0]->get_name());
        $this->assertEquals('Brazil', $result[0]->get_location());
        $this->assertEquals('Forestry', $result[0]->get_project_type());
    }

    /**
     * Test fetch TCO2 token by ID
     */
    public function test_fetch_tco2_token_by_id() {
        $mock_token = array(
            'id' => '0x1234567890abcdef',
            'name' => 'TCO2-VCS-1234-2020',
            'symbol' => 'TCO2-VCS-1234-2020',
            'address' => '0x1234567890abcdef',
            'totalSupply' => '1000000000000000000000',
            'projectVintage' => array(
                'id' => 'vintage-1',
                'name' => 'Amazon Forest Protection 2020',
                'startTime' => 1577836800,
                'endTime' => 1609459199,
                'project' => array(
                    'id' => 'project-1',
                    'projectId' => 'VCS-1234',
                    'standard' => 'VCS',
                    'methodology' => 'VM0007',
                    'region' => 'Brazil',
                    'category' => 'Forestry'
                )
            )
        );
        
        $this->client->set_mock_response('tco2Token(id:', array(
            'data' => array('tco2Token' => $mock_token)
        ));
        
        $result = $this->client->fetch_tco2_token_by_id('0x1234567890abcdef');
        
        $this->assertInstanceOf(Project::class, $result);
        $this->assertEquals('0x1234567890abcdef', $result->get_id());
        $this->assertEquals('Amazon Forest Protection 2020', $result->get_name());
        
        $metadata = $result->get_metadata();
        $this->assertEquals('0x1234567890abcdef', $metadata['token_address']);
        $this->assertEquals('VCS-1234', $metadata['project_id']);
        $this->assertEquals('VCS', $metadata['standard']);
    }

    /**
     * Test fetch pool contents
     */
    public function test_fetch_pool_contents() {
        $mock_pool_contents = array(
            array(
                'id' => 'pool-token-1',
                'amount' => '500000000000000000000', // 500 tokens in wei
                'token' => array(
                    'id' => '0x1234567890abcdef',
                    'name' => 'TCO2-VCS-1234-2020',
                    'symbol' => 'TCO2-VCS-1234-2020',
                    'address' => '0x1234567890abcdef',
                    'projectVintage' => array(
                        'name' => 'Amazon Forest Protection 2020',
                        'project' => array(
                            'projectId' => 'VCS-1234',
                            'region' => 'Brazil',
                            'category' => 'Forestry'
                        )
                    )
                ),
                'pool' => array(
                    'id' => '0x2f800db0fdb5223b3c3f354886d907a671414a7f',
                    'name' => 'Base Carbon Tonne',
                    'symbol' => 'BCT',
                    'totalSupply' => '10000000000000000000000'
                )
            )
        );
        
        $this->client->set_mock_response('pooledTCO2Tokens', array(
            'data' => array('pooledTCO2Tokens' => $mock_pool_contents)
        ));
        
        $result = $this->client->fetch_pool_contents('0x2f800db0fdb5223b3c3f354886d907a671414a7f');
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        
        $pool_item = $result[0];
        $this->assertInstanceOf(Project::class, $pool_item['token']);
        $this->assertEquals(500.0, $pool_item['amount']);
        $this->assertEquals('Base Carbon Tonne', $pool_item['pool_info']['name']);
        $this->assertEquals('BCT', $pool_item['pool_info']['symbol']);
    }

    /**
     * Test fetch token price on DEX
     */
    public function test_fetch_token_price_on_dex() {
        $mock_swaps = array(
            array(
                'id' => 'swap-1',
                'timestamp' => time(),
                'token0' => array(
                    'id' => '0x1234567890abcdef',
                    'symbol' => 'TCO2',
                    'decimals' => 18
                ),
                'token1' => array(
                    'id' => '0x2791bca1f2de4661ed88a30c99a7a9449aa84174',
                    'symbol' => 'USDC',
                    'decimals' => 6
                ),
                'amount0In' => '0',
                'amount0Out' => '1000000000000000000', // 1 TCO2
                'amount1In' => '25000000', // 25 USDC
                'amount1Out' => '0',
                'amountUSD' => '25.0'
            )
        );
        
        $this->client->set_mock_response('swaps', array(
            'data' => array('swaps' => $mock_swaps)
        ));
        
        $result = $this->client->fetch_token_price_on_dex('0x1234567890abcdef');
        
        $this->assertInstanceOf(TokenPrice::class, $result);
        $this->assertEquals('0x1234567890abcdef', $result->get_token_address());
        $this->assertEquals(25.0, $result->get_price_usd());
        $this->assertEquals('USD', $result->get_currency());
        $this->assertEquals('toucan_dex_swaps', $result->get_data_source());
    }

    /**
     * Test get available pools
     */
    public function test_get_available_pools() {
        $mock_pools = array(
            array(
                'id' => '0x2f800db0fdb5223b3c3f354886d907a671414a7f',
                'name' => 'Base Carbon Tonne',
                'symbol' => 'BCT',
                'totalSupply' => '10000000000000000000000',
                'pooledTCO2Tokens' => array(
                    array(
                        'amount' => '1000000000000000000000',
                        'token' => array(
                            'name' => 'TCO2-VCS-1234-2020',
                            'symbol' => 'TCO2-VCS-1234-2020'
                        )
                    )
                )
            )
        );
        
        $this->client->set_mock_response('pools', array(
            'data' => array('pools' => $mock_pools)
        ));
        
        $result = $this->client->get_available_pools();
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Portfolio::class, $result[0]);
        $this->assertEquals('0x2f800db0fdb5223b3c3f354886d907a671414a7f', $result[0]->get_id());
        $this->assertEquals('toucan', $result[0]->get_vendor());
        $this->assertEquals('Base Carbon Tonne', $result[0]->get_name());
    }

    /**
     * Test error handling for invalid token ID
     */
    public function test_fetch_tco2_token_by_id_invalid() {
        $result = $this->client->fetch_tco2_token_by_id('');
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_token_id', $result->get_error_code());
    }

    /**
     * Test error handling for token not found
     */
    public function test_fetch_tco2_token_by_id_not_found() {
        $this->client->set_mock_response('tco2Token(id:', array(
            'data' => array('tco2Token' => null)
        ));
        
        $result = $this->client->fetch_tco2_token_by_id('0xnonexistent');
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('token_not_found', $result->get_error_code());
    }

    /**
     * Test error handling for no price data
     */
    public function test_fetch_token_price_no_data() {
        $this->client->set_mock_response('swaps', array(
            'data' => array('swaps' => array())
        ));
        
        $result = $this->client->fetch_token_price_on_dex('0x1234567890abcdef');
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('no_price_data', $result->get_error_code());
    }

    /**
     * Test GraphQL query logging
     */
    public function test_query_logging() {
        $this->client->fetch_all_tco2_tokens(50, 0);
        
        $log = $this->client->get_request_log();
        $this->assertCount(1, $log);
        $this->assertStringContainsString('tco2Tokens', $log[0]['query']);
        $this->assertStringContainsString('first: 50', $log[0]['query']);
        $this->assertStringContainsString('skip: 0', $log[0]['query']);
    }

    /**
     * Test client without API key
     */
    public function test_client_without_api_key() {
        $client = new MockToucanClient(array(
            'base_url' => 'https://api.thegraph.com',
            'credentials' => array()
        ));
        
        $headers = $client->get_auth_headers();
        $this->assertEmpty($headers);
    }

    /**
     * Test token supply parsing
     */
    public function test_token_supply_parsing() {
        // Test with a token that has 1000 tokens (1000 * 10^18 wei)
        $mock_token = array(
            'id' => '0x1234567890abcdef',
            'name' => 'Test Token',
            'totalSupply' => '1000000000000000000000', // 1000 tokens in wei
            'projectVintage' => array(
                'name' => 'Test Project',
                'project' => array(
                    'region' => 'Test Region',
                    'category' => 'Test Category'
                )
            )
        );
        
        $this->client->set_mock_response('tco2Token(id:', array(
            'data' => array('tco2Token' => $mock_token)
        ));
        
        $result = $this->client->fetch_tco2_token_by_id('0x1234567890abcdef');
        
        $this->assertInstanceOf(Project::class, $result);
        $this->assertEquals(1000, $result->get_available_quantity());
    }
}