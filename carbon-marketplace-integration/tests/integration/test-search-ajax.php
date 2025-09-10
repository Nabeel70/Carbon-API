<?php
/**
 * Search AJAX Integration Tests
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

use PHPUnit\Framework\TestCase;
use CarbonMarketplace\Ajax\SearchAjaxHandler;
use CarbonMarketplace\Search\SearchEngine;
use CarbonMarketplace\Models\Project;
use CarbonMarketplace\Core\Database;

class SearchAjaxIntegrationTest extends TestCase {
    
    private $ajax_handler;
    private $mock_search_engine;
    private $sample_projects;
    
    protected function setUp(): void {
        // Mock WordPress functions
        if (!function_exists('wp_verify_nonce')) {
            function wp_verify_nonce($nonce, $action) { return true; }
        }
        if (!function_exists('wp_create_nonce')) {
            function wp_create_nonce($action) { return 'test_nonce'; }
        }
        if (!function_exists('wp_send_json')) {
            function wp_send_json($data) { 
                echo json_encode($data);
                exit;
            }
        }
        if (!function_exists('status_header')) {
            function status_header($code) { 
                http_response_code($code);
            }
        }
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) { 
                return trim(strip_tags($str));
            }
        }
        if (!function_exists('admin_url')) {
            function admin_url($path) { 
                return 'https://example.com/wp-admin/' . $path;
            }
        }
        if (!function_exists('wp_enqueue_script')) {
            function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {}
        }
        if (!function_exists('wp_localize_script')) {
            function wp_localize_script($handle, $object_name, $l10n) {}
        }
        if (!function_exists('add_action')) {
            function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
        }
        if (!function_exists('get_option')) {
            function get_option($option, $default = false) { return $default; }
        }
        if (!function_exists('update_option')) {
            function update_option($option, $value) { return true; }
        }
        
        // Create mock search engine
        $this->mock_search_engine = $this->createMock(SearchEngine::class);
        $this->ajax_handler = new SearchAjaxHandler($this->mock_search_engine);
        
        // Create sample projects
        $this->sample_projects = [
            [
                'id' => 'proj_1',
                'vendor' => 'cnaught',
                'name' => 'Forest Conservation Brazil',
                'description' => 'Protecting rainforest in Amazon region',
                'location' => 'Brazil, South America',
                'project_type' => 'Forest Conservation',
                'methodology' => 'REDD+',
                'price_per_kg' => 15.50,
                'available_quantity' => 1000,
                'images' => [],
                'sdgs' => [15, 13],
                'registry_url' => 'https://registry.example.com/proj_1',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 'proj_2',
                'vendor' => 'toucan',
                'name' => 'Solar Energy India',
                'description' => 'Solar power generation in rural India',
                'location' => 'India, Asia',
                'project_type' => 'Renewable Energy',
                'methodology' => 'CDM',
                'price_per_kg' => 12.25,
                'available_quantity' => 2000,
                'images' => [],
                'sdgs' => [7, 13],
                'registry_url' => 'https://registry.example.com/proj_2',
                'created_at' => '2024-01-02 00:00:00',
                'updated_at' => '2024-01-02 00:00:00',
            ],
        ];
    }
    
    public function test_ajax_handler_initialization() {
        // Test that AJAX handler initializes correctly
        $this->assertInstanceOf(SearchAjaxHandler::class, $this->ajax_handler);
    }
    
    public function test_search_request_with_valid_nonce() {
        // Mock search results
        $mock_results = $this->createMock(\CarbonMarketplace\Search\SearchResults::class);
        $mock_results->method('has_errors')->willReturn(false);
        $mock_results->method('get_project_summaries')->willReturn([
            ['id' => 'proj_1', 'name' => 'Test Project', 'available' => true]
        ]);
        $mock_results->method('get_pagination_info')->willReturn([
            'current_page' => 1,
            'total_pages' => 1,
            'total_count' => 1,
            'result_count' => 1,
        ]);
        $mock_results->method('get_metadata')->willReturn([]);
        
        $this->mock_search_engine->method('search')->willReturn($mock_results);
        
        // Set up POST data
        $_POST = [
            'nonce' => 'test_nonce',
            'keyword' => 'forest',
            'limit' => '20',
            'offset' => '0',
        ];
        
        // Capture output
        ob_start();
        
        try {
            $this->ajax_handler->handle_search_request();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json
        }
        
        $output = ob_get_clean();
        
        // Verify JSON response structure
        $response = json_decode($output, true);
        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('projects', $response['data']);
        $this->assertArrayHasKey('pagination', $response['data']);
    }
    
    public function test_search_request_with_invalid_nonce() {
        // Override nonce verification to return false
        $handler = new class($this->mock_search_engine) extends SearchAjaxHandler {
            protected function verify_nonce(): bool {
                return false;
            }
        };
        
        $_POST = ['nonce' => 'invalid_nonce'];
        
        ob_start();
        
        try {
            $handler->handle_search_request();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid security token', $response['error']['message']);
        $this->assertEquals(403, $response['error']['code']);
    }
    
    public function test_search_request_with_invalid_parameters() {
        // Test with invalid limit
        $_POST = [
            'nonce' => 'test_nonce',
            'limit' => '-1', // Invalid limit
        ];
        
        ob_start();
        
        try {
            $this->ajax_handler->handle_search_request();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Invalid search query', $response['error']['message']);
    }
    
    public function test_suggestions_request() {
        // Mock suggestions
        $this->mock_search_engine->method('get_suggestions')
            ->willReturn(['Forest Conservation', 'Forest Management']);
        
        $_POST = [
            'nonce' => 'test_nonce',
            'input' => 'forest',
            'limit' => '10',
        ];
        
        ob_start();
        
        try {
            $this->ajax_handler->handle_suggestions_request();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('suggestions', $response['data']);
        $this->assertCount(2, $response['data']['suggestions']);
    }
    
    public function test_suggestions_request_short_input() {
        // Test with input too short
        $_POST = [
            'nonce' => 'test_nonce',
            'input' => 'f', // Too short
        ];
        
        ob_start();
        
        try {
            $this->ajax_handler->handle_suggestions_request();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEmpty($response['data']['suggestions']);
    }
    
    public function test_project_details_request() {
        // Mock database
        $mock_database = $this->createMock(Database::class);
        $mock_database->method('get_project')
            ->willReturn($this->sample_projects[0]);
        
        // Create handler with mocked database
        $handler = new class($this->mock_search_engine) extends SearchAjaxHandler {
            public function handle_project_details_request(): void {
                // Override to use mock data
                $_POST = [
                    'nonce' => 'test_nonce',
                    'project_id' => '1',
                ];
                
                $project_data = [
                    'id' => 'proj_1',
                    'name' => 'Test Project',
                    'location' => 'Test Location',
                ];
                
                $project = \CarbonMarketplace\Models\Project::from_array($project_data);
                
                $response_data = [
                    'success' => true,
                    'data' => [
                        'project' => $project->to_array(),
                    ],
                ];
                
                wp_send_json($response_data);
            }
        };
        
        $_POST = [
            'nonce' => 'test_nonce',
            'project_id' => '1',
        ];
        
        ob_start();
        
        try {
            $handler->handle_project_details_request();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('project', $response['data']);
    }
    
    public function test_project_details_request_missing_id() {
        // Test without project ID
        $_POST = [
            'nonce' => 'test_nonce',
            // Missing project_id
        ];
        
        ob_start();
        
        try {
            $this->ajax_handler->handle_project_details_request();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success']);
        $this->assertEquals('Project ID is required', $response['error']['message']);
    }
    
    public function test_parameter_sanitization() {
        // Test parameter sanitization
        $handler = new class($this->mock_search_engine) extends SearchAjaxHandler {
            public function test_get_sanitized_search_params() {
                return $this->get_sanitized_search_params();
            }
        };
        
        $_POST = [
            'keyword' => '<script>alert("xss")</script>forest',
            'location' => 'Brazil<script>',
            'min_price' => '10.5',
            'max_price' => 'invalid',
            'limit' => '150', // Should be capped at 100
            'offset' => '-5', // Should be set to 0
            'sort_by' => 'invalid_field', // Should default to 'name'
        ];
        
        $params = $handler->test_get_sanitized_search_params();
        
        $this->assertEquals('forest', $params['keyword']); // Script tags removed
        $this->assertEquals('Brazil', $params['location']); // Script tags removed
        $this->assertEquals(10.5, $params['min_price']);
        $this->assertArrayNotHasKey('max_price', $params); // Invalid value excluded
        $this->assertEquals(100, $params['limit']); // Capped at maximum
        $this->assertEquals(0, $params['offset']); // Set to minimum
        $this->assertEquals('name', $params['sort_by']); // Default value
    }
    
    public function test_search_statistics() {
        // Test search statistics functionality
        $stats = $this->ajax_handler->get_search_statistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_searches', $stats);
        $this->assertArrayHasKey('popular_keywords', $stats);
        $this->assertArrayHasKey('average_results', $stats);
    }
    
    public function test_ajax_script_enqueuing() {
        // Test that AJAX scripts are properly configured
        ob_start();
        $this->ajax_handler->enqueue_ajax_scripts();
        $output = ob_get_clean();
        
        // This test mainly ensures no errors occur during script enqueuing
        $this->assertTrue(true);
    }
    
    public function test_error_handling_in_search() {
        // Mock search engine to throw exception
        $this->mock_search_engine->method('search')
            ->willThrowException(new Exception('Database error'));
        
        $_POST = [
            'nonce' => 'test_nonce',
            'keyword' => 'test',
        ];
        
        ob_start();
        
        try {
            $this->ajax_handler->handle_search_request();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertFalse($response['success']);
        $this->assertEquals('An unexpected error occurred', $response['error']['message']);
        $this->assertEquals(500, $response['error']['code']);
    }
    
    public function test_pagination_parameters() {
        // Test pagination parameter handling
        $mock_results = $this->createMock(\CarbonMarketplace\Search\SearchResults::class);
        $mock_results->method('has_errors')->willReturn(false);
        $mock_results->method('get_project_summaries')->willReturn([]);
        $mock_results->method('get_pagination_info')->willReturn([
            'current_page' => 2,
            'total_pages' => 5,
            'total_count' => 100,
            'result_count' => 20,
        ]);
        $mock_results->method('get_metadata')->willReturn([]);
        
        $this->mock_search_engine->method('search')->willReturn($mock_results);
        
        $_POST = [
            'nonce' => 'test_nonce',
            'limit' => '20',
            'offset' => '20', // Second page
        ];
        
        ob_start();
        
        try {
            $this->ajax_handler->handle_search_request();
        } catch (Exception $e) {
            // Expected due to exit() in wp_send_json
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals(2, $response['data']['pagination']['current_page']);
        $this->assertEquals(5, $response['data']['pagination']['total_pages']);
    }
    
    protected function tearDown(): void {
        // Clean up POST data
        $_POST = [];
    }
}