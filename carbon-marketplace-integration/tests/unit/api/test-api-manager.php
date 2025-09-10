<?php
/**
 * Unit tests for ApiManager class
 *
 * @package CarbonMarketplace
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;
use CarbonMarketplace\API\ApiManager;
use CarbonMarketplace\API\BaseApiClient;
use CarbonMarketplace\Models\Portfolio;
use CarbonMarketplace\Models\Project;
use CarbonMarketplace\Models\Quote;
use CarbonMarketplace\Models\QuoteRequest;
use CarbonMarketplace\Models\CheckoutRequest;

/**
 * Test class for ApiManager
 */
class TestApiManager extends TestCase {

    /**
     * ApiManager instance
     *
     * @var ApiManager
     */
    private $api_manager;

    /**
     * Mock API client
     *
     * @var BaseApiClient
     */
    private $mock_client;

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->api_manager = new ApiManager([
            'normalize_data' => true,
            'parallel_requests' => false, // Disable for testing
        ]);

        // Create mock client
        $this->mock_client = $this->createMock(BaseApiClient::class);
    }

    /**
     * Test client registration
     */
    public function test_register_client() {
        $result = $this->api_manager->register_client('test_vendor', $this->mock_client);
        $this->assertTrue($result);

        $registered_client = $this->api_manager->get_client('test_vendor');
        $this->assertSame($this->mock_client, $registered_client);
    }

    /**
     * Test client registration with invalid parameters
     */
    public function test_register_client_invalid_params() {
        $result = $this->api_manager->register_client('', $this->mock_client);
        $this->assertFalse($result);

        $result = $this->api_manager->register_client('test_vendor', null);
        $this->assertFalse($result);
    }

    /**
     * Test client unregistration
     */
    public function test_unregister_client() {
        $this->api_manager->register_client('test_vendor', $this->mock_client);
        
        $result = $this->api_manager->unregister_client('test_vendor');
        $this->assertTrue($result);

        $client = $this->api_manager->get_client('test_vendor');
        $this->assertNull($client);
    }

    /**
     * Test unregistering non-existent client
     */
    public function test_unregister_nonexistent_client() {
        $result = $this->api_manager->unregister_client('nonexistent');
        $this->assertFalse($result);
    }

    /**
     * Test fetching portfolios from all vendors
     */
    public function test_fetch_all_portfolios_success() {
        $mock_portfolios = [
            new Portfolio([
                'id' => 'portfolio1',
                'vendor' => 'test_vendor',
                'name' => 'Test Portfolio 1',
                'description' => 'Test description',
                'projects' => [],
                'base_price_per_kg' => 10.50,
                'is_active' => true,
            ]),
            new Portfolio([
                'id' => 'portfolio2',
                'vendor' => 'test_vendor',
                'name' => 'Test Portfolio 2',
                'description' => 'Test description 2',
                'projects' => [],
                'base_price_per_kg' => 12.00,
                'is_active' => true,
            ]),
        ];

        $this->mock_client->method('get_portfolios')
                          ->willReturn($mock_portfolios);

        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $result = $this->api_manager->fetch_all_portfolios();
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Portfolio::class, $result[0]);
        $this->assertEquals('portfolio1', $result[0]->id);
    }

    /**
     * Test fetching portfolios with no clients registered
     */
    public function test_fetch_all_portfolios_no_clients() {
        $result = $this->api_manager->fetch_all_portfolios();
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('no_clients', $result->get_error_code());
    }

    /**
     * Test fetching portfolios with client error
     */
    public function test_fetch_all_portfolios_client_error() {
        $this->mock_client->method('get_portfolios')
                          ->willReturn(new WP_Error('api_error', 'API failed'));

        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $result = $this->api_manager->fetch_all_portfolios();
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('all_clients_failed', $result->get_error_code());
    }

    /**
     * Test fetching projects from all vendors
     */
    public function test_fetch_all_projects_success() {
        $mock_projects = [
            new Project([
                'id' => 'project1',
                'vendor' => 'test_vendor',
                'name' => 'Test Project 1',
                'description' => 'Test project description',
                'location' => 'Test Location',
                'project_type' => 'Forestry',
                'methodology' => 'VCS',
                'price_per_kg' => 15.00,
                'available_quantity' => 1000,
                'images' => [],
                'sdgs' => [],
                'registry_url' => 'https://example.com',
            ]),
        ];

        $this->mock_client->method('get_all_projects')
                          ->willReturn($mock_projects);

        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $result = $this->api_manager->fetch_all_projects();
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Project::class, $result[0]);
        $this->assertEquals('project1', $result[0]->id);
    }

    /**
     * Test fetching projects with filters
     */
    public function test_fetch_all_projects_with_filters() {
        $mock_projects = [
            new Project([
                'id' => 'project1',
                'vendor' => 'test_vendor',
                'name' => 'Forest Project',
                'description' => 'Forest conservation',
                'location' => 'Brazil',
                'project_type' => 'Forestry',
                'methodology' => 'VCS',
                'price_per_kg' => 15.00,
                'available_quantity' => 1000,
                'images' => [],
                'sdgs' => [],
                'registry_url' => 'https://example.com',
            ]),
            new Project([
                'id' => 'project2',
                'vendor' => 'test_vendor',
                'name' => 'Solar Project',
                'description' => 'Solar energy',
                'location' => 'India',
                'project_type' => 'Renewable Energy',
                'methodology' => 'CDM',
                'price_per_kg' => 20.00,
                'available_quantity' => 500,
                'images' => [],
                'sdgs' => [],
                'registry_url' => 'https://example.com',
            ]),
        ];

        $this->mock_client->method('get_all_projects')
                          ->willReturn($mock_projects);

        $this->api_manager->register_client('test_vendor', $this->mock_client);

        // Test location filter
        $result = $this->api_manager->fetch_all_projects(['location' => 'Brazil']);
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('project1', $result[0]->id);

        // Test project type filter
        $result = $this->api_manager->fetch_all_projects(['project_type' => 'Renewable']);
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('project2', $result[0]->id);

        // Test price range filter
        $result = $this->api_manager->fetch_all_projects(['min_price' => 18.00]);
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('project2', $result[0]->id);
    }

    /**
     * Test getting project details
     */
    public function test_get_project_details_success() {
        $mock_project = new Project([
            'id' => 'project1',
            'vendor' => 'test_vendor',
            'name' => 'Test Project',
            'description' => 'Detailed project info',
            'location' => 'Test Location',
            'project_type' => 'Forestry',
            'methodology' => 'VCS',
            'price_per_kg' => 15.00,
            'available_quantity' => 1000,
            'images' => [],
            'sdgs' => [],
            'registry_url' => 'https://example.com',
        ]);

        $this->mock_client->method('get_project_details')
                          ->with('project1')
                          ->willReturn($mock_project);

        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $result = $this->api_manager->get_project_details('project1', 'test_vendor');
        
        $this->assertInstanceOf(Project::class, $result);
        $this->assertEquals('project1', $result->id);
        $this->assertEquals('test_vendor', $result->vendor);
    }

    /**
     * Test getting project details with invalid vendor
     */
    public function test_get_project_details_invalid_vendor() {
        $result = $this->api_manager->get_project_details('project1', 'nonexistent_vendor');
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('client_not_found', $result->get_error_code());
    }

    /**
     * Test getting quote
     */
    public function test_get_quote_success() {
        $quote_request = new QuoteRequest([
            'amount_kg' => 10.0,
            'currency' => 'USD',
        ]);

        $mock_quote = new Quote([
            'id' => 'quote1',
            'vendor' => 'test_vendor',
            'amount_kg' => 10.0,
            'price_per_kg' => 15.00,
            'total_price' => 150.00,
            'currency' => 'USD',
            'project_allocations' => [],
        ]);

        $this->mock_client->method('create_quote')
                          ->with($quote_request)
                          ->willReturn($mock_quote);

        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $result = $this->api_manager->get_quote($quote_request);
        
        $this->assertInstanceOf(Quote::class, $result);
        $this->assertEquals('quote1', $result->id);
        $this->assertEquals(150.00, $result->total_price);
    }

    /**
     * Test getting quote with invalid request
     */
    public function test_get_quote_invalid_request() {
        $quote_request = $this->createMock(QuoteRequest::class);
        $quote_request->method('validate')->willReturn(false);
        $quote_request->method('get_validation_errors')->willReturn(['Invalid amount']);

        $result = $this->api_manager->get_quote($quote_request);
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_request', $result->get_error_code());
    }

    /**
     * Test selecting best quote from multiple quotes
     */
    public function test_get_quote_multiple_vendors() {
        $quote_request = new QuoteRequest([
            'amount_kg' => 10.0,
            'currency' => 'USD',
        ]);

        // Create two mock clients with different quotes
        $mock_client1 = $this->createMock(BaseApiClient::class);
        $mock_client2 = $this->createMock(BaseApiClient::class);

        $quote1 = new Quote([
            'id' => 'quote1',
            'vendor' => 'vendor1',
            'amount_kg' => 10.0,
            'price_per_kg' => 15.00,
            'total_price' => 150.00,
            'currency' => 'USD',
            'project_allocations' => [],
        ]);

        $quote2 = new Quote([
            'id' => 'quote2',
            'vendor' => 'vendor2',
            'amount_kg' => 10.0,
            'price_per_kg' => 12.00,
            'total_price' => 120.00, // Lower price - should be selected
            'currency' => 'USD',
            'project_allocations' => [],
        ]);

        $mock_client1->method('create_quote')->willReturn($quote1);
        $mock_client2->method('create_quote')->willReturn($quote2);

        $this->api_manager->register_client('vendor1', $mock_client1);
        $this->api_manager->register_client('vendor2', $mock_client2);

        $result = $this->api_manager->get_quote($quote_request);
        
        $this->assertInstanceOf(Quote::class, $result);
        $this->assertEquals('quote2', $result->id); // Should select the cheaper quote
        $this->assertEquals(120.00, $result->total_price);
    }

    /**
     * Test creating checkout session
     */
    public function test_create_checkout_session_success() {
        $checkout_request = new CheckoutRequest([
            'portfolio_id' => 'test_vendor_portfolio123',
            'amount_kg' => 10.0,
            'currency' => 'USD',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $mock_session = $this->createMock(\CarbonMarketplace\Models\CheckoutSession::class);

        $this->mock_client->method('create_checkout_session')
                          ->with($checkout_request)
                          ->willReturn($mock_session);

        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $result = $this->api_manager->create_checkout_session($checkout_request);
        
        $this->assertSame($mock_session, $result);
    }

    /**
     * Test creating checkout session without vendor information
     */
    public function test_create_checkout_session_no_vendor() {
        $checkout_request = new CheckoutRequest([
            'amount_kg' => 10.0,
            'currency' => 'USD',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            // No portfolio_id or project_id to determine vendor
        ]);

        $result = $this->api_manager->create_checkout_session($checkout_request);
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('vendor_required', $result->get_error_code());
    }

    /**
     * Test getting aggregated statistics
     */
    public function test_get_aggregated_stats() {
        $this->mock_client->method('validate_credentials')->willReturn(true);
        $this->mock_client->method('get_portfolios')->willReturn([
            new Portfolio(['id' => 'p1', 'vendor' => 'test_vendor', 'name' => 'Portfolio 1']),
            new Portfolio(['id' => 'p2', 'vendor' => 'test_vendor', 'name' => 'Portfolio 2']),
        ]);

        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $stats = $this->api_manager->get_aggregated_stats();
        
        $this->assertIsArray($stats);
        $this->assertEquals(1, $stats['total_vendors']);
        $this->assertEquals(2, $stats['total_portfolios']);
        $this->assertArrayHasKey('vendors', $stats);
        $this->assertArrayHasKey('test_vendor', $stats['vendors']);
        $this->assertEquals('active', $stats['vendors']['test_vendor']['status']);
        $this->assertEquals(2, $stats['vendors']['test_vendor']['portfolios']);
    }

    /**
     * Test validating all clients
     */
    public function test_validate_all_clients() {
        $this->mock_client->method('validate_credentials')->willReturn(true);

        $mock_client2 = $this->createMock(BaseApiClient::class);
        $mock_client2->method('validate_credentials')
                     ->willReturn(new WP_Error('invalid_creds', 'Invalid credentials'));

        $this->api_manager->register_client('valid_vendor', $this->mock_client);
        $this->api_manager->register_client('invalid_vendor', $mock_client2);

        $results = $this->api_manager->validate_all_clients();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('valid_vendor', $results);
        $this->assertArrayHasKey('invalid_vendor', $results);
        
        $this->assertTrue($results['valid_vendor']['valid']);
        $this->assertEquals('Valid', $results['valid_vendor']['message']);
        
        $this->assertFalse($results['invalid_vendor']['valid']);
        $this->assertEquals('Invalid credentials', $results['invalid_vendor']['message']);
    }

    /**
     * Test data aggregation functionality
     */
    public function test_aggregate_project_data() {
        $mock_projects = [
            new Project([
                'id' => 'project1',
                'vendor' => 'vendor1',
                'name' => 'Forest Project',
                'location' => 'Brazil',
                'project_type' => 'Forestry',
                'price_per_kg' => 15.00,
                'available_quantity' => 1000,
            ]),
            new Project([
                'id' => 'project2',
                'vendor' => 'vendor2',
                'name' => 'Solar Project',
                'location' => 'India',
                'project_type' => 'Renewable Energy',
                'price_per_kg' => 20.00,
                'available_quantity' => 500,
            ]),
        ];

        $this->mock_client->method('get_all_projects')
                          ->willReturn($mock_projects);

        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $result = $this->api_manager->aggregate_project_data();
        
        $this->assertIsArray($result);
        $this->assertEquals(2, $result['total_count']);
        $this->assertArrayHasKey('vendor_counts', $result);
        $this->assertArrayHasKey('price_range', $result);
        $this->assertEquals(15.00, $result['price_range']['min']);
        $this->assertEquals(20.00, $result['price_range']['max']);
        $this->assertArrayHasKey('project_types', $result);
        $this->assertArrayHasKey('locations', $result);
    }

    /**
     * Test vendor configuration retrieval
     */
    public function test_get_vendor_config() {
        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $config = $this->api_manager->get_vendor_config('test_vendor');
        
        $this->assertIsArray($config);
        $this->assertEquals('test_vendor', $config['name']);
        $this->assertArrayHasKey('supports_portfolios', $config);
        $this->assertArrayHasKey('supports_projects', $config);
        $this->assertArrayHasKey('supports_quotes', $config);
        $this->assertArrayHasKey('supports_checkout', $config);
        $this->assertArrayHasKey('supports_webhooks', $config);
    }

    /**
     * Test vendor configuration for non-existent vendor
     */
    public function test_get_vendor_config_nonexistent() {
        $config = $this->api_manager->get_vendor_config('nonexistent_vendor');
        
        $this->assertIsArray($config);
        $this->assertEmpty($config);
    }

    /**
     * Test data normalization
     */
    public function test_normalize_vendor_data() {
        $raw_portfolios = [
            [
                'id' => 'portfolio1',
                'name' => 'Test Portfolio',
                'description' => 'Test description',
                'projects' => [],
                'base_price_per_kg' => 10.50,
                'is_active' => true,
            ],
        ];

        $result = $this->api_manager->normalize_vendor_data($raw_portfolios, 'portfolios', 'test_vendor');
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Portfolio::class, $result[0]);
        $this->assertEquals('test_vendor', $result[0]->vendor);
    }

    /**
     * Test parallel API calls execution
     */
    public function test_execute_parallel_calls() {
        $this->mock_client->method('get_portfolios')
                          ->willReturn([new Portfolio(['id' => 'p1', 'name' => 'Portfolio 1'])]);

        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $calls = [
            'portfolios' => [
                'vendor' => 'test_vendor',
                'method' => 'get_portfolios',
                'params' => [],
            ],
        ];

        $results = $this->api_manager->execute_parallel_calls($calls);
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('portfolios', $results);
        $this->assertIsArray($results['portfolios']);
    }

    /**
     * Test parallel calls with invalid method
     */
    public function test_execute_parallel_calls_invalid_method() {
        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $calls = [
            'invalid' => [
                'vendor' => 'test_vendor',
                'method' => 'nonexistent_method',
                'params' => [],
            ],
        ];

        $results = $this->api_manager->execute_parallel_calls($calls);
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('invalid', $results);
        $this->assertInstanceOf(WP_Error::class, $results['invalid']);
        $this->assertEquals('invalid_call', $results['invalid']->get_error_code());
    }

    /**
     * Test vendor extraction from portfolio ID
     */
    public function test_vendor_extraction_from_portfolio_id() {
        $checkout_request = new CheckoutRequest([
            'portfolio_id' => 'cnaught_portfolio123',
            'amount_kg' => 10.0,
            'currency' => 'USD',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $mock_session = $this->createMock(\CarbonMarketplace\Models\CheckoutSession::class);

        $this->mock_client->method('create_checkout_session')
                          ->willReturn($mock_session);

        $this->api_manager->register_client('cnaught', $this->mock_client);

        $result = $this->api_manager->create_checkout_session($checkout_request);
        
        $this->assertSame($mock_session, $result);
    }

    /**
     * Test fetching projects from portfolios fallback
     */
    public function test_fetch_projects_from_portfolios_fallback() {
        $mock_portfolios = [
            new Portfolio([
                'id' => 'portfolio1',
                'vendor' => 'test_vendor',
                'name' => 'Test Portfolio',
                'projects' => [
                    new Project([
                        'id' => 'project1',
                        'vendor' => 'test_vendor',
                        'name' => 'Test Project',
                        'location' => 'Test Location',
                        'project_type' => 'Forestry',
                        'price_per_kg' => 15.00,
                    ]),
                ],
            ]),
        ];

        // Mock client that doesn't have get_all_projects but has get_portfolios
        $this->mock_client->method('get_portfolios')
                          ->willReturn($mock_portfolios);

        $this->api_manager->register_client('test_vendor', $this->mock_client);

        $result = $this->api_manager->fetch_all_projects();
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Project::class, $result[0]);
        $this->assertEquals('project1', $result[0]->id);
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void {
        parent::tearDown();
        $this->api_manager = null;
        $this->mock_client = null;
    }
}