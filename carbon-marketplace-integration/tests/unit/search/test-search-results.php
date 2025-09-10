<?php
/**
 * SearchResults Unit Tests
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

use PHPUnit\Framework\TestCase;
use CarbonMarketplace\Search\SearchResults;
use CarbonMarketplace\Models\Project;

class SearchResultsTest extends TestCase {
    
    private $sample_projects;
    private $sample_project_objects;
    
    protected function setUp(): void {
        // Create sample project data
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
        
        // Convert to Project objects
        $this->sample_project_objects = array_map(function($data) {
            return Project::from_array($data);
        }, $this->sample_projects);
    }
    
    public function test_constructor_default() {
        // Test default constructor
        $results = new SearchResults();
        
        $this->assertEmpty($results->get_projects());
        $this->assertEquals(0, $results->get_total_count());
        $this->assertFalse($results->has_errors());
        $this->assertEmpty($results->get_errors());
        $this->assertEmpty($results->get_metadata());
    }
    
    public function test_constructor_with_data() {
        // Test constructor with data
        $errors = ['test_error' => 'Test error message'];
        $metadata = ['search_time' => 0.5];
        
        $results = new SearchResults($this->sample_project_objects, 10, $errors, $metadata);
        
        $this->assertCount(3, $results->get_projects());
        $this->assertEquals(10, $results->get_total_count());
        $this->assertTrue($results->has_errors());
        $this->assertEquals($errors, $results->get_errors());
        $this->assertEquals($metadata, $results->get_metadata());
    }
    
    public function test_get_result_count() {
        // Test result count
        $results = new SearchResults($this->sample_project_objects, 10);
        
        $this->assertEquals(3, $results->get_result_count());
        $this->assertEquals(10, $results->get_total_count());
    }
    
    public function test_is_empty() {
        // Test empty results
        $empty_results = new SearchResults();
        $this->assertTrue($empty_results->is_empty());
        
        // Test non-empty results
        $results = new SearchResults($this->sample_project_objects);
        $this->assertFalse($results->is_empty());
    }
    
    public function test_metadata_operations() {
        // Test metadata operations
        $results = new SearchResults();
        
        // Test setting metadata
        $metadata = ['search_time' => 0.5, 'cache_hit' => true];
        $results->set_metadata($metadata);
        $this->assertEquals($metadata, $results->get_metadata());
        
        // Test adding metadata
        $results->add_metadata('total_api_calls', 3);
        $expected = array_merge($metadata, ['total_api_calls' => 3]);
        $this->assertEquals($expected, $results->get_metadata());
    }
    
    public function test_get_projects_as_array() {
        // Test converting projects to array
        $results = new SearchResults($this->sample_project_objects);
        $projects_array = $results->get_projects_as_array();
        
        $this->assertIsArray($projects_array);
        $this->assertCount(3, $projects_array);
        
        // Check that each item is an array
        foreach ($projects_array as $project_data) {
            $this->assertIsArray($project_data);
            $this->assertArrayHasKey('id', $project_data);
            $this->assertArrayHasKey('name', $project_data);
        }
    }
    
    public function test_get_pagination_info() {
        // Test pagination info
        $results = new SearchResults($this->sample_project_objects, 25);
        
        $pagination = $results->get_pagination_info(10, 0);
        
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(3, $pagination['total_pages']);
        $this->assertEquals(25, $pagination['total_count']);
        $this->assertEquals(3, $pagination['result_count']);
        $this->assertTrue($pagination['has_next_page']);
        $this->assertFalse($pagination['has_previous_page']);
        $this->assertEquals(10, $pagination['limit']);
        $this->assertEquals(0, $pagination['offset']);
    }
    
    public function test_get_pagination_info_second_page() {
        // Test pagination info for second page
        $results = new SearchResults($this->sample_project_objects, 25);
        
        $pagination = $results->get_pagination_info(10, 10);
        
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(3, $pagination['total_pages']);
        $this->assertTrue($pagination['has_next_page']);
        $this->assertTrue($pagination['has_previous_page']);
    }
    
    public function test_to_array() {
        // Test converting results to array
        $metadata = ['search_time' => 0.5];
        $results = new SearchResults($this->sample_project_objects, 25, [], $metadata);
        
        $array = $results->to_array(10, 0);
        
        $this->assertArrayHasKey('projects', $array);
        $this->assertArrayHasKey('pagination', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('metadata', $array);
        
        $this->assertCount(3, $array['projects']);
        $this->assertEquals($metadata, $array['metadata']);
    }
    
    public function test_get_project_summaries() {
        // Test getting project summaries
        $results = new SearchResults($this->sample_project_objects);
        $summaries = $results->get_project_summaries();
        
        $this->assertIsArray($summaries);
        $this->assertCount(3, $summaries);
        
        // Check summary structure
        foreach ($summaries as $summary) {
            $this->assertArrayHasKey('id', $summary);
            $this->assertArrayHasKey('name', $summary);
            $this->assertArrayHasKey('location', $summary);
            $this->assertArrayHasKey('project_type', $summary);
            $this->assertArrayHasKey('price_per_kg', $summary);
            $this->assertArrayHasKey('available', $summary);
        }
    }
    
    public function test_filter_available() {
        // Test filtering available projects
        $results = new SearchResults($this->sample_project_objects, 3);
        $available_results = $results->filter_available();
        
        // Should exclude Wind Farm Texas (available_quantity = 0)
        $this->assertEquals(2, $available_results->get_result_count());
        
        foreach ($available_results->get_projects() as $project) {
            $this->assertTrue($project->is_available());
        }
    }
    
    public function test_sort_by_name_asc() {
        // Test sorting by name ascending
        $results = new SearchResults($this->sample_project_objects);
        $sorted_results = $results->sort_by('name', 'asc');
        
        $projects = $sorted_results->get_projects();
        $names = array_map(function($project) {
            return $project->get_name();
        }, $projects);
        
        $expected_order = ['Forest Conservation Brazil', 'Solar Energy India', 'Wind Farm Texas'];
        $this->assertEquals($expected_order, $names);
    }
    
    public function test_sort_by_name_desc() {
        // Test sorting by name descending
        $results = new SearchResults($this->sample_project_objects);
        $sorted_results = $results->sort_by('name', 'desc');
        
        $projects = $sorted_results->get_projects();
        $names = array_map(function($project) {
            return $project->get_name();
        }, $projects);
        
        $expected_order = ['Wind Farm Texas', 'Solar Energy India', 'Forest Conservation Brazil'];
        $this->assertEquals($expected_order, $names);
    }
    
    public function test_sort_by_price() {
        // Test sorting by price
        $results = new SearchResults($this->sample_project_objects);
        $sorted_results = $results->sort_by('price_per_kg', 'asc');
        
        $projects = $sorted_results->get_projects();
        $prices = array_map(function($project) {
            return $project->price_per_kg;
        }, $projects);
        
        $expected_order = [12.25, 15.50, 18.75];
        $this->assertEquals($expected_order, $prices);
    }
    
    public function test_slice() {
        // Test slicing results
        $results = new SearchResults($this->sample_project_objects, 10);
        $sliced_results = $results->slice(1, 2);
        
        $this->assertEquals(2, $sliced_results->get_result_count());
        $this->assertEquals(10, $sliced_results->get_total_count()); // Total count preserved
        
        $projects = $sliced_results->get_projects();
        $this->assertEquals('Solar Energy India', $projects[0]->get_name());
        $this->assertEquals('Wind Farm Texas', $projects[1]->get_name());
    }
    
    public function test_slice_out_of_bounds() {
        // Test slicing with out of bounds parameters
        $results = new SearchResults($this->sample_project_objects);
        $sliced_results = $results->slice(10, 5); // Start beyond available results
        
        $this->assertEquals(0, $sliced_results->get_result_count());
        $this->assertEmpty($sliced_results->get_projects());
    }
    
    public function test_with_array_data() {
        // Test SearchResults with array data instead of Project objects
        $results = new SearchResults($this->sample_projects, 3);
        
        $this->assertCount(3, $results->get_projects());
        $this->assertEquals(3, $results->get_total_count());
        
        // Test get_projects_as_array with array data
        $projects_array = $results->get_projects_as_array();
        $this->assertEquals($this->sample_projects, $projects_array);
    }
    
    public function test_filter_available_with_array_data() {
        // Test filtering available projects with array data
        $results = new SearchResults($this->sample_projects, 3);
        $available_results = $results->filter_available();
        
        // Should exclude Wind Farm Texas (available_quantity = 0)
        $this->assertEquals(2, $available_results->get_result_count());
    }
    
    public function test_error_handling() {
        // Test error handling
        $errors = [
            'validation_error' => 'Invalid search parameters',
            'api_error' => 'API timeout'
        ];
        
        $results = new SearchResults([], 0, $errors);
        
        $this->assertTrue($results->has_errors());
        $this->assertEquals($errors, $results->get_errors());
        $this->assertTrue($results->is_empty());
    }
}