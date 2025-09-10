<?php
/**
 * SearchEngine Unit Tests
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

use PHPUnit\Framework\TestCase;
use CarbonMarketplace\Search\SearchEngine;
use CarbonMarketplace\Search\SearchResults;
use CarbonMarketplace\Models\Project;
use CarbonMarketplace\Models\SearchQuery;
use CarbonMarketplace\Core\Database;

class SearchEngineTest extends TestCase {
    
    private $search_engine;
    private $mock_database;
    private $sample_projects;
    
    protected function setUp(): void {
        // Create mock database
        $this->mock_database = $this->createMock(Database::class);
        $this->search_engine = new SearchEngine($this->mock_database);
        
        // Create sample projects for testing
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
            [
                'id' => 'proj_3',
                'vendor' => 'cnaught',
                'name' => 'Wind Farm Texas',
                'description' => 'Wind energy generation in Texas',
                'location' => 'Texas, USA',
                'project_type' => 'Renewable Energy',
                'methodology' => 'VCS',
                'price_per_kg' => 18.75,
                'available_quantity' => 0, // Not available
                'images' => [],
                'sdgs' => [7, 13],
                'registry_url' => 'https://registry.example.com/proj_3',
                'created_at' => '2024-01-03 00:00:00',
                'updated_at' => '2024-01-03 00:00:00',
            ],
        ];
    }
    
    public function test_index_projects_success() {
        // Test successful project indexing
        $result = $this->search_engine->index_projects($this->sample_projects);
        
        $this->assertTrue($result);
    }
    
    public function test_index_projects_with_project_objects() {
        // Test indexing with Project objects
        $project_objects = array_map(function($data) {
            return Project::from_array($data);
        }, $this->sample_projects);
        
        $result = $this->search_engine->index_projects($project_objects);
        
        $this->assertTrue($result);
    }
    
    public function test_search_basic_functionality() {
        // Mock database search method
        $this->mock_database->method('search_projects')
            ->willReturn($this->sample_projects);
        
        // Mock get_total_count method
        global $wpdb;
        $wpdb = $this->createMock(wpdb::class);
        $wpdb->method('get_var')->willReturn(3);
        $wpdb->method('esc_like')->willReturnArgument(0);
        
        $this->mock_database->method('get_projects_table')
            ->willReturn('wp_carbon_projects');
        
        $query = new SearchQuery(['keyword' => 'energy']);
        $results = $this->search_engine->search($query);
        
        $this->assertInstanceOf(SearchResults::class, $results);
        $this->assertGreaterThan(0, count($results->get_projects()));
    }
    
    public function test_search_with_invalid_query() {
        // Test search with invalid query
        $query = new SearchQuery(['limit' => -1]); // Invalid limit
        $results = $this->search_engine->search($query);
        
        $this->assertInstanceOf(SearchResults::class, $results);
        $this->assertTrue($results->has_errors());
    }
    
    public function test_apply_filters_keyword() {
        // Convert sample data to Project objects
        $projects = array_map(function($data) {
            return Project::from_array($data);
        }, $this->sample_projects);
        
        $filters = ['keyword' => 'energy'];
        $filtered = $this->search_engine->apply_filters($projects, $filters);
        
        // Should return projects with 'energy' in name, description, etc.
        $this->assertCount(2, $filtered); // Solar Energy India and Wind Farm Texas
    }
    
    public function test_apply_filters_location() {
        // Convert sample data to Project objects
        $projects = array_map(function($data) {
            return Project::from_array($data);
        }, $this->sample_projects);
        
        $filters = ['location' => 'brazil'];
        $filtered = $this->search_engine->apply_filters($projects, $filters);
        
        // Should return projects in Brazil
        $this->assertCount(1, $filtered);
        $this->assertEquals('Forest Conservation Brazil', $filtered[0]->get_name());
    }
    
    public function test_apply_filters_project_type() {
        // Convert sample data to Project objects
        $projects = array_map(function($data) {
            return Project::from_array($data);
        }, $this->sample_projects);
        
        $filters = ['project_type' => 'Renewable Energy'];
        $filtered = $this->search_engine->apply_filters($projects, $filters);
        
        // Should return renewable energy projects
        $this->assertCount(2, $filtered);
    }
    
    public function test_apply_filters_price_range() {
        // Convert sample data to Project objects
        $projects = array_map(function($data) {
            return Project::from_array($data);
        }, $this->sample_projects);
        
        $filters = ['min_price' => 15.0, 'max_price' => 20.0];
        $filtered = $this->search_engine->apply_filters($projects, $filters);
        
        // Should return projects within price range
        $this->assertCount(2, $filtered); // Forest Conservation Brazil and Wind Farm Texas
    }
    
    public function test_apply_filters_vendor() {
        // Convert sample data to Project objects
        $projects = array_map(function($data) {
            return Project::from_array($data);
        }, $this->sample_projects);
        
        $filters = ['vendor' => 'cnaught'];
        $filtered = $this->search_engine->apply_filters($projects, $filters);
        
        // Should return CNaught projects
        $this->assertCount(2, $filtered);
    }
    
    public function test_apply_filters_availability() {
        // Convert sample data to Project objects
        $projects = array_map(function($data) {
            return Project::from_array($data);
        }, $this->sample_projects);
        
        $filters = []; // No specific filters, but should filter out unavailable
        $filtered = $this->search_engine->apply_filters($projects, $filters);
        
        // Should exclude unavailable projects (Wind Farm Texas has 0 quantity)
        $this->assertCount(2, $filtered);
        
        foreach ($filtered as $project) {
            $this->assertTrue($project->is_available());
        }
    }
    
    public function test_rank_results_with_keyword() {
        // Convert sample data to Project objects
        $projects = array_map(function($data) {
            return Project::from_array($data);
        }, $this->sample_projects);
        
        $query = new SearchQuery(['keyword' => 'energy']);
        $ranked = $this->search_engine->rank_results($projects, $query);
        
        // Should return projects ranked by relevance
        $this->assertCount(3, $ranked);
        
        // Projects with 'energy' in name should rank higher
        $first_project = $ranked[0];
        $this->assertStringContainsStringIgnoringCase('energy', $first_project->get_name());
    }
    
    public function test_rank_results_without_keyword() {
        // Convert sample data to Project objects
        $projects = array_map(function($data) {
            return Project::from_array($data);
        }, $this->sample_projects);
        
        $query = new SearchQuery(); // No keyword
        $ranked = $this->search_engine->rank_results($projects, $query);
        
        // Should return projects in original order
        $this->assertEquals($projects, $ranked);
    }
    
    public function test_get_suggestions() {
        // Mock database for suggestions
        global $wpdb;
        $wpdb = $this->createMock(wpdb::class);
        
        $mock_results = [
            ['name' => 'Forest Conservation Brazil', 'location' => 'Brazil', 'project_type' => 'Forest Conservation'],
            ['name' => 'Solar Energy India', 'location' => 'India', 'project_type' => 'Renewable Energy'],
        ];
        
        $wpdb->method('get_results')->willReturn($mock_results);
        $wpdb->method('prepare')->willReturnArgument(0);
        $wpdb->method('esc_like')->willReturnArgument(0);
        
        $this->mock_database->method('get_projects_table')
            ->willReturn('wp_carbon_projects');
        
        $suggestions = $this->search_engine->get_suggestions('forest');
        
        $this->assertIsArray($suggestions);
    }
    
    public function test_get_suggestions_short_input() {
        // Test with input too short
        $suggestions = $this->search_engine->get_suggestions('f');
        
        $this->assertEmpty($suggestions);
    }
    
    public function test_clear_index() {
        // Test clearing search index
        $result = $this->search_engine->clear_index();
        
        // Should return boolean (true/false depending on transient existence)
        $this->assertIsBool($result);
    }
    
    public function test_multiple_filters_combination() {
        // Convert sample data to Project objects
        $projects = array_map(function($data) {
            return Project::from_array($data);
        }, $this->sample_projects);
        
        $filters = [
            'keyword' => 'energy',
            'project_type' => 'Renewable Energy',
            'min_price' => 10.0,
            'max_price' => 15.0
        ];
        
        $filtered = $this->search_engine->apply_filters($projects, $filters);
        
        // Should return Solar Energy India (matches all criteria)
        $this->assertCount(1, $filtered);
        $this->assertEquals('Solar Energy India', $filtered[0]->get_name());
    }
    
    public function test_search_error_handling() {
        // Mock database to throw exception
        $this->mock_database->method('search_projects')
            ->willThrowException(new Exception('Database error'));
        
        $query = new SearchQuery(['keyword' => 'test']);
        $results = $this->search_engine->search($query);
        
        $this->assertInstanceOf(SearchResults::class, $results);
        $this->assertTrue($results->has_errors());
        $this->assertEmpty($results->get_projects());
    }
    
    public function test_relevance_scoring() {
        // Test relevance scoring algorithm
        $project = Project::from_array($this->sample_projects[0]);
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->search_engine);
        $method = $reflection->getMethod('calculate_relevance_score');
        $method->setAccessible(true);
        
        $score = $method->invoke($this->search_engine, $project, 'forest');
        
        $this->assertGreaterThan(0, $score);
    }
    
    public function test_searchable_text_creation() {
        // Test searchable text creation
        $project = Project::from_array($this->sample_projects[0]);
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->search_engine);
        $method = $reflection->getMethod('create_searchable_text');
        $method->setAccessible(true);
        
        $searchable_text = $method->invoke($this->search_engine, $project);
        
        $this->assertIsString($searchable_text);
        $this->assertStringContainsStringIgnoringCase('forest', $searchable_text);
        $this->assertStringContainsStringIgnoringCase('brazil', $searchable_text);
    }
}