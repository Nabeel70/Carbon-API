<?php
/**
 * Project Model Tests
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

use CarbonMarketplace\Models\Project;
use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase {
    
    /**
     * Test project creation with valid data
     */
    public function test_project_creation_with_valid_data() {
        $data = [
            'id' => 'proj_123',
            'vendor' => 'cnaught',
            'name' => 'Forest Conservation Project',
            'description' => 'A forest conservation project in Brazil',
            'location' => 'Brazil',
            'project_type' => 'forestry',
            'methodology' => 'VCS',
            'price_per_kg' => 15.50,
            'available_quantity' => 1000,
            'images' => ['image1.jpg', 'image2.jpg'],
            'sdgs' => [13, 15],
            'registry_url' => 'https://registry.verra.org/project/123',
        ];
        
        $project = new Project($data);
        
        $this->assertEquals('proj_123', $project->id);
        $this->assertEquals('cnaught', $project->vendor);
        $this->assertEquals('Forest Conservation Project', $project->name);
        $this->assertEquals(15.50, $project->price_per_kg);
        $this->assertEquals(1000, $project->available_quantity);
        $this->assertIsArray($project->images);
        $this->assertIsArray($project->sdgs);
    }
    
    /**
     * Test project validation with valid data
     */
    public function test_project_validation_valid() {
        $data = [
            'id' => 'proj_123',
            'vendor' => 'cnaught',
            'name' => 'Test Project',
            'price_per_kg' => 10.00,
            'available_quantity' => 500,
        ];
        
        $project = new Project($data);
        $this->assertTrue($project->validate());
        $this->assertEmpty($project->get_validation_errors());
    }
    
    /**
     * Test project validation with missing required fields
     */
    public function test_project_validation_missing_required() {
        $project = new Project();
        $this->assertFalse($project->validate());
        
        $errors = $project->get_validation_errors();
        $this->assertArrayHasKey('id', $errors);
        $this->assertArrayHasKey('vendor', $errors);
        $this->assertArrayHasKey('name', $errors);
    }
    
    /**
     * Test project validation with invalid price
     */
    public function test_project_validation_invalid_price() {
        $data = [
            'id' => 'proj_123',
            'vendor' => 'cnaught',
            'name' => 'Test Project',
            'price_per_kg' => -5.00,
        ];
        
        $project = new Project($data);
        $this->assertFalse($project->validate());
        
        $errors = $project->get_validation_errors();
        $this->assertArrayHasKey('price_per_kg', $errors);
    }
    
    /**
     * Test project validation with invalid URL
     */
    public function test_project_validation_invalid_url() {
        $data = [
            'id' => 'proj_123',
            'vendor' => 'cnaught',
            'name' => 'Test Project',
            'registry_url' => 'not-a-valid-url',
        ];
        
        $project = new Project($data);
        $this->assertFalse($project->validate());
        
        $errors = $project->get_validation_errors();
        $this->assertArrayHasKey('registry_url', $errors);
    }
    
    /**
     * Test project to_array method
     */
    public function test_project_to_array() {
        $data = [
            'id' => 'proj_123',
            'vendor' => 'cnaught',
            'name' => 'Test Project',
            'price_per_kg' => 10.00,
        ];
        
        $project = new Project($data);
        $array = $project->to_array();
        
        $this->assertIsArray($array);
        $this->assertEquals('proj_123', $array['id']);
        $this->assertEquals('cnaught', $array['vendor']);
        $this->assertEquals('Test Project', $array['name']);
        $this->assertEquals(10.00, $array['price_per_kg']);
    }
    
    /**
     * Test project from_array method
     */
    public function test_project_from_array() {
        $data = [
            'id' => 'proj_123',
            'vendor' => 'cnaught',
            'name' => 'Test Project',
        ];
        
        $project = Project::from_array($data);
        
        $this->assertInstanceOf(Project::class, $project);
        $this->assertEquals('proj_123', $project->id);
        $this->assertEquals('cnaught', $project->vendor);
    }
    
    /**
     * Test project JSON serialization
     */
    public function test_project_json_serialization() {
        $data = [
            'id' => 'proj_123',
            'vendor' => 'cnaught',
            'name' => 'Test Project',
        ];
        
        $project = new Project($data);
        $json = $project->to_json();
        
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals('proj_123', $decoded['id']);
        
        $project_from_json = Project::from_json($json);
        $this->assertInstanceOf(Project::class, $project_from_json);
        $this->assertEquals('proj_123', $project_from_json->id);
    }
    
    /**
     * Test project availability check
     */
    public function test_project_availability() {
        $available_project = new Project(['available_quantity' => 100]);
        $this->assertTrue($available_project->is_available());
        
        $unavailable_project = new Project(['available_quantity' => 0]);
        $this->assertFalse($unavailable_project->is_available());
    }
    
    /**
     * Test formatted price
     */
    public function test_formatted_price() {
        $project = new Project(['price_per_kg' => 15.50]);
        $this->assertEquals('$15.50/kg', $project->get_formatted_price());
        $this->assertEquals('€15.50/kg', $project->get_formatted_price('€'));
    }
    
    /**
     * Test project summary
     */
    public function test_project_summary() {
        $data = [
            'id' => 'proj_123',
            'name' => 'Test Project',
            'location' => 'Brazil',
            'project_type' => 'forestry',
            'price_per_kg' => 15.50,
            'available_quantity' => 100,
        ];
        
        $project = new Project($data);
        $summary = $project->get_summary();
        
        $this->assertIsArray($summary);
        $this->assertEquals('proj_123', $summary['id']);
        $this->assertEquals('Test Project', $summary['name']);
        $this->assertTrue($summary['available']);
        $this->assertEquals('$15.50/kg', $summary['formatted_price']);
    }
}