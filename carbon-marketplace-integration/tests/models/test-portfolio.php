<?php
/**
 * Portfolio Model Tests
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

use CarbonMarketplace\Models\Portfolio;
use CarbonMarketplace\Models\Project;
use PHPUnit\Framework\TestCase;

class PortfolioTest extends TestCase {
    
    /**
     * Test portfolio creation with valid data
     */
    public function test_portfolio_creation_with_valid_data() {
        $data = [
            'id' => 'port_123',
            'vendor' => 'cnaught',
            'name' => 'Mixed Portfolio',
            'description' => 'A diverse portfolio of projects',
            'projects' => ['proj_1', 'proj_2'],
            'base_price_per_kg' => 12.00,
            'is_active' => true,
        ];
        
        $portfolio = new Portfolio($data);
        
        $this->assertEquals('port_123', $portfolio->id);
        $this->assertEquals('cnaught', $portfolio->vendor);
        $this->assertEquals('Mixed Portfolio', $portfolio->name);
        $this->assertEquals(12.00, $portfolio->base_price_per_kg);
        $this->assertTrue($portfolio->is_active);
        $this->assertIsArray($portfolio->projects);
    }
    
    /**
     * Test portfolio validation with valid data
     */
    public function test_portfolio_validation_valid() {
        $data = [
            'id' => 'port_123',
            'vendor' => 'cnaught',
            'name' => 'Test Portfolio',
            'base_price_per_kg' => 10.00,
            'is_active' => true,
        ];
        
        $portfolio = new Portfolio($data);
        $this->assertTrue($portfolio->validate());
        $this->assertEmpty($portfolio->get_validation_errors());
    }
    
    /**
     * Test portfolio validation with missing required fields
     */
    public function test_portfolio_validation_missing_required() {
        $portfolio = new Portfolio();
        $this->assertFalse($portfolio->validate());
        
        $errors = $portfolio->get_validation_errors();
        $this->assertArrayHasKey('id', $errors);
        $this->assertArrayHasKey('vendor', $errors);
        $this->assertArrayHasKey('name', $errors);
    }
    
    /**
     * Test portfolio validation with invalid price
     */
    public function test_portfolio_validation_invalid_price() {
        $data = [
            'id' => 'port_123',
            'vendor' => 'cnaught',
            'name' => 'Test Portfolio',
            'base_price_per_kg' => -5.00,
        ];
        
        $portfolio = new Portfolio($data);
        $this->assertFalse($portfolio->validate());
        
        $errors = $portfolio->get_validation_errors();
        $this->assertArrayHasKey('base_price_per_kg', $errors);
    }
    
    /**
     * Test portfolio to_array method
     */
    public function test_portfolio_to_array() {
        $data = [
            'id' => 'port_123',
            'vendor' => 'cnaught',
            'name' => 'Test Portfolio',
            'base_price_per_kg' => 10.00,
        ];
        
        $portfolio = new Portfolio($data);
        $array = $portfolio->to_array();
        
        $this->assertIsArray($array);
        $this->assertEquals('port_123', $array['id']);
        $this->assertEquals('cnaught', $array['vendor']);
        $this->assertEquals('Test Portfolio', $array['name']);
        $this->assertEquals(10.00, $array['base_price_per_kg']);
    }
    
    /**
     * Test portfolio from_array method
     */
    public function test_portfolio_from_array() {
        $data = [
            'id' => 'port_123',
            'vendor' => 'cnaught',
            'name' => 'Test Portfolio',
        ];
        
        $portfolio = Portfolio::from_array($data);
        
        $this->assertInstanceOf(Portfolio::class, $portfolio);
        $this->assertEquals('port_123', $portfolio->id);
        $this->assertEquals('cnaught', $portfolio->vendor);
    }
    
    /**
     * Test portfolio JSON serialization
     */
    public function test_portfolio_json_serialization() {
        $data = [
            'id' => 'port_123',
            'vendor' => 'cnaught',
            'name' => 'Test Portfolio',
        ];
        
        $portfolio = new Portfolio($data);
        $json = $portfolio->to_json();
        
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals('port_123', $decoded['id']);
        
        $portfolio_from_json = Portfolio::from_json($json);
        $this->assertInstanceOf(Portfolio::class, $portfolio_from_json);
        $this->assertEquals('port_123', $portfolio_from_json->id);
    }
    
    /**
     * Test adding projects to portfolio
     */
    public function test_add_project() {
        $portfolio = new Portfolio(['id' => 'port_123', 'vendor' => 'test', 'name' => 'Test']);
        
        $portfolio->add_project('proj_1');
        $this->assertEquals(1, $portfolio->get_project_count());
        
        $project = new Project(['id' => 'proj_2', 'vendor' => 'test', 'name' => 'Test Project']);
        $portfolio->add_project($project);
        $this->assertEquals(2, $portfolio->get_project_count());
    }
    
    /**
     * Test removing projects from portfolio
     */
    public function test_remove_project() {
        $portfolio = new Portfolio([
            'id' => 'port_123',
            'vendor' => 'test',
            'name' => 'Test',
            'projects' => ['proj_1', 'proj_2', 'proj_3']
        ]);
        
        $this->assertEquals(3, $portfolio->get_project_count());
        
        $portfolio->remove_project('proj_2');
        $this->assertEquals(2, $portfolio->get_project_count());
        $this->assertNotContains('proj_2', $portfolio->projects);
    }
    
    /**
     * Test portfolio has projects
     */
    public function test_has_projects() {
        $empty_portfolio = new Portfolio(['id' => 'port_1', 'vendor' => 'test', 'name' => 'Empty']);
        $this->assertFalse($empty_portfolio->has_projects());
        
        $portfolio_with_projects = new Portfolio([
            'id' => 'port_2',
            'vendor' => 'test',
            'name' => 'With Projects',
            'projects' => ['proj_1']
        ]);
        $this->assertTrue($portfolio_with_projects->has_projects());
    }
    
    /**
     * Test formatted base price
     */
    public function test_formatted_base_price() {
        $portfolio = new Portfolio(['base_price_per_kg' => 15.50]);
        $this->assertEquals('$15.50/kg', $portfolio->get_formatted_base_price());
        $this->assertEquals('€15.50/kg', $portfolio->get_formatted_base_price('€'));
    }
    
    /**
     * Test portfolio summary
     */
    public function test_portfolio_summary() {
        $data = [
            'id' => 'port_123',
            'name' => 'Test Portfolio',
            'vendor' => 'cnaught',
            'projects' => ['proj_1', 'proj_2'],
            'base_price_per_kg' => 15.50,
            'is_active' => true,
        ];
        
        $portfolio = new Portfolio($data);
        $summary = $portfolio->get_summary();
        
        $this->assertIsArray($summary);
        $this->assertEquals('port_123', $summary['id']);
        $this->assertEquals('Test Portfolio', $summary['name']);
        $this->assertEquals(2, $summary['project_count']);
        $this->assertEquals('$15.50/kg', $summary['formatted_base_price']);
        $this->assertTrue($summary['is_active']);
    }
}