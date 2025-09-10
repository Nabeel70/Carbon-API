<?php
/**
 * Unit tests for BaseApiClient
 *
 * @package CarbonMarketplace
 * @subpackage Tests
 */

namespace CarbonMarketplace\Tests\API;

use CarbonMarketplace\API\BaseApiClient;
use WP_Error;
use PHPUnit\Framework\TestCase;

/**
 * Mock API client for testing
 */
class MockApiClient extends BaseApiClient {
    
    public function get_auth_headers() {
        return array('Authorization' => 'Bearer test-token');
    }
    
    public function validate_credentials() {
        return !empty($this->credentials['api_key']);
    }
    
    public function get_client_name() {
        return 'Mock API Client';
    }
    
    // Expose protected methods for testing
    public function public_make_request($method, $endpoint, $data = array(), $headers = array()) {
        return $this->make_request($method, $endpoint, $data, $headers);
    }
    
    public function public_build_url($endpoint) {
        return $this->build_url($endpoint);
    }
    
    public function public_build_request_args($method, $data, $headers) {
        return $this->build_request_args($method, $data, $headers);
    }
    
    public function public_calculate_retry_delay($attempt, $status_code = 0) {
        return $this->calculate_retry_delay($attempt, $status_code);
    }
    
    public function public_should_retry($status_code) {
        return $this->should_retry($status_code);
    }
    
    public function public_is_success_status($status_code) {
        return $this->is_success_status($status_code);
    }
}

/**
 * Test class for BaseApiClient
 */
class TestBaseApiClient extends TestCase {

    /**
     * Mock API client instance
     *
     * @var MockApiClient
     */
    private $client;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        $config = array(
            'base_url' => 'https://api.example.com',
            'credentials' => array('api_key' => 'test-key'),
            'timeout' => 30,
            'max_retries' => 3,
            'rate_limit' => array(
                'requests_per_second' => 10,
                'burst_limit' => 50
            )
        );
        
        $this->client = new MockApiClient($config);
    }

    /**
     * Test client initialization
     */
    public function test_client_initialization() {
        $this->assertInstanceOf(MockApiClient::class, $this->client);
        $this->assertEquals('Mock API Client', $this->client->get_client_name());
        $this->assertTrue($this->client->validate_credentials());
    }

    /**
     * Test URL building
     */
    public function test_build_url() {
        $this->assertEquals(
            'https://api.example.com/test/endpoint',
            $this->client->public_build_url('test/endpoint')
        );
        
        $this->assertEquals(
            'https://api.example.com/test/endpoint',
            $this->client->public_build_url('/test/endpoint')
        );
    }

    /**
     * Test request arguments building
     */
    public function test_build_request_args() {
        $args = $this->client->public_build_request_args('GET', array(), array());
        
        $this->assertEquals('GET', $args['method']);
        $this->assertEquals(30, $args['timeout']);
        $this->assertArrayHasKey('headers', $args);
        $this->assertEquals('application/json', $args['headers']['Accept']);
    }

    /**
     * Test POST request arguments with JSON data
     */
    public function test_build_request_args_post_json() {
        $data = array('key' => 'value');
        $headers = array('Content-Type' => 'application/json');
        
        $args = $this->client->public_build_request_args('POST', $data, $headers);
        
        $this->assertEquals('POST', $args['method']);
        $this->assertEquals('{"key":"value"}', $args['body']);
        $this->assertEquals('application/json', $args['headers']['Content-Type']);
    }

    /**
     * Test success status detection
     */
    public function test_is_success_status() {
        $this->assertTrue($this->client->public_is_success_status(200));
        $this->assertTrue($this->client->public_is_success_status(201));
        $this->assertTrue($this->client->public_is_success_status(204));
        $this->assertFalse($this->client->public_is_success_status(400));
        $this->assertFalse($this->client->public_is_success_status(404));
        $this->assertFalse($this->client->public_is_success_status(500));
    }

    /**
     * Test retry logic for different status codes
     */
    public function test_should_retry() {
        // Should retry server errors
        $this->assertTrue($this->client->public_should_retry(500));
        $this->assertTrue($this->client->public_should_retry(502));
        $this->assertTrue($this->client->public_should_retry(503));
        
        // Should retry rate limiting
        $this->assertTrue($this->client->public_should_retry(429));
        
        // Should not retry client errors
        $this->assertFalse($this->client->public_should_retry(400));
        $this->assertFalse($this->client->public_should_retry(401));
        $this->assertFalse($this->client->public_should_retry(404));
    }

    /**
     * Test retry delay calculation
     */
    public function test_calculate_retry_delay() {
        // Test exponential backoff
        $delay1 = $this->client->public_calculate_retry_delay(1);
        $delay2 = $this->client->public_calculate_retry_delay(2);
        $delay3 = $this->client->public_calculate_retry_delay(3);
        
        $this->assertGreaterThanOrEqual(1, $delay1);
        $this->assertGreaterThanOrEqual(2, $delay2);
        $this->assertGreaterThanOrEqual(4, $delay3);
        
        // Test rate limiting delay
        $rate_limit_delay = $this->client->public_calculate_retry_delay(1, 429);
        $this->assertGreaterThanOrEqual(5, $rate_limit_delay);
        
        // Test maximum delay cap
        $max_delay = $this->client->public_calculate_retry_delay(10);
        $this->assertLessThanOrEqual(60, $max_delay);
    }

    /**
     * Test credential validation
     */
    public function test_validate_credentials() {
        $this->assertTrue($this->client->validate_credentials());
        
        // Test with invalid credentials
        $invalid_client = new MockApiClient(array(
            'base_url' => 'https://api.example.com',
            'credentials' => array()
        ));
        
        $this->assertFalse($invalid_client->validate_credentials());
    }

    /**
     * Test authentication headers
     */
    public function test_get_auth_headers() {
        $headers = $this->client->get_auth_headers();
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer test-token', $headers['Authorization']);
    }

    /**
     * Test client configuration
     */
    public function test_client_configuration() {
        $config = array(
            'base_url' => 'https://custom.api.com',
            'timeout' => 60,
            'max_retries' => 5,
            'user_agent' => 'Custom User Agent'
        );
        
        $client = new MockApiClient($config);
        $this->assertInstanceOf(MockApiClient::class, $client);
    }

    /**
     * Test default configuration values
     */
    public function test_default_configuration() {
        $client = new MockApiClient();
        $this->assertInstanceOf(MockApiClient::class, $client);
    }
}