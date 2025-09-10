<?php
/**
 * SearchQuery Model Tests
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

use CarbonMarketplace\Models\SearchQuery;
use PHPUnit\Framework\TestCase;

class SearchQueryTest extends TestCase {
    
    /**
     * Test search query creation with valid data
     */
    public function test_search_query_creation_with_valid_data() {
        $data = [
            'keyword' => 'forest',
            'location' => 'Brazil',
            'project_type' => 'forestry',
            'min_price' => 10.00,
            'max_price' => 20.00,
            'vendor' => 'cnaught',
            'limit' => 50,
            'offset' => 10,
            'sort_by' => 'price_per_kg',
            'sort_order' => 'desc',
        ];
        
        $query = new SearchQuery($data);
        
        $this->assertEquals('forest', $query->keyword);
        $this->assertEquals('Brazil', $query->location);
        $this->assertEquals('forestry', $query->project_type);
        $this->assertEquals(10.00, $query->min_price);
        $this->assertEquals(20.00, $query->max_price);
        $this->assertEquals('cnaught', $query->vendor);
        $this->assertEquals(50, $query->limit);
        $this->assertEquals(10, $query->offset);
        $this->assertEquals('price_per_kg', $query->sort_by);
        $this->assertEquals('desc', $query->sort_order);
    }
    
    /**
     * Test search query with default values
     */
    public function test_search_query_defaults() {
        $query = new SearchQuery();
        
        $this->assertNull($query->keyword);
        $this->assertNull($query->location);
        $this->assertEquals(20, $query->limit);
        $this->assertEquals(0, $query->offset);
        $this->assertEquals('name', $query->sort_by);
        $this->assertEquals('asc', $query->sort_order);
    }
    
    /**
     * Test search query validation with valid data
     */
    public function test_search_query_validation_valid() {
        $data = [
            'keyword' => 'forest',
            'limit' => 25,
            'offset' => 0,
            'sort_by' => 'name',
            'sort_order' => 'asc',
        ];
        
        $query = new SearchQuery($data);
        $this->assertTrue($query->validate());
        $this->assertEmpty($query->get_validation_errors());
    }
    
    /**
     * Test search query validation with invalid limit
     */
    public function test_search_query_validation_invalid_limit() {
        $query = new SearchQuery(['limit' => 0]);
        $this->assertFalse($query->validate());
        
        $errors = $query->get_validation_errors();
        $this->assertArrayHasKey('limit', $errors);
        
        $query2 = new SearchQuery(['limit' => 150]);
        $this->assertFalse($query2->validate());
        
        $errors2 = $query2->get_validation_errors();
        $this->assertArrayHasKey('limit', $errors2);
    }
    
    /**
     * Test search query validation with invalid price range
     */
    public function test_search_query_validation_invalid_price_range() {
        $data = [
            'min_price' => 20.00,
            'max_price' => 10.00,
        ];
        
        $query = new SearchQuery($data);
        $this->assertFalse($query->validate());
        
        $errors = $query->get_validation_errors();
        $this->assertArrayHasKey('price_range', $errors);
    }
    
    /**
     * Test search query validation with invalid sort field
     */
    public function test_search_query_validation_invalid_sort_field() {
        $query = new SearchQuery(['sort_by' => 'invalid_field']);
        $this->assertFalse($query->validate());
        
        $errors = $query->get_validation_errors();
        $this->assertArrayHasKey('sort_by', $errors);
    }
    
    /**
     * Test search query validation with invalid sort order
     */
    public function test_search_query_validation_invalid_sort_order() {
        $query = new SearchQuery(['sort_order' => 'invalid_order']);
        $this->assertFalse($query->validate());
        
        $errors = $query->get_validation_errors();
        $this->assertArrayHasKey('sort_order', $errors);
    }
    
    /**
     * Test search query to_array method
     */
    public function test_search_query_to_array() {
        $data = [
            'keyword' => 'forest',
            'location' => 'Brazil',
            'limit' => 25,
        ];
        
        $query = new SearchQuery($data);
        $array = $query->to_array();
        
        $this->assertIsArray($array);
        $this->assertEquals('forest', $array['keyword']);
        $this->assertEquals('Brazil', $array['location']);
        $this->assertEquals(25, $array['limit']);
    }
    
    /**
     * Test search query from_array method
     */
    public function test_search_query_from_array() {
        $data = [
            'keyword' => 'forest',
            'location' => 'Brazil',
        ];
        
        $query = SearchQuery::from_array($data);
        
        $this->assertInstanceOf(SearchQuery::class, $query);
        $this->assertEquals('forest', $query->keyword);
        $this->assertEquals('Brazil', $query->location);
    }
    
    /**
     * Test search query JSON serialization
     */
    public function test_search_query_json_serialization() {
        $data = [
            'keyword' => 'forest',
            'location' => 'Brazil',
        ];
        
        $query = new SearchQuery($data);
        $json = $query->to_json();
        
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals('forest', $decoded['keyword']);
        
        $query_from_json = SearchQuery::from_json($json);
        $this->assertInstanceOf(SearchQuery::class, $query_from_json);
        $this->assertEquals('forest', $query_from_json->keyword);
    }
    
    /**
     * Test has filters method
     */
    public function test_has_filters() {
        $empty_query = new SearchQuery();
        $this->assertFalse($empty_query->has_filters());
        
        $query_with_keyword = new SearchQuery(['keyword' => 'forest']);
        $this->assertTrue($query_with_keyword->has_filters());
        
        $query_with_location = new SearchQuery(['location' => 'Brazil']);
        $this->assertTrue($query_with_location->has_filters());
        
        $query_with_price = new SearchQuery(['min_price' => 10.00]);
        $this->assertTrue($query_with_price->has_filters());
    }
    
    /**
     * Test get active filters method
     */
    public function test_get_active_filters() {
        $data = [
            'keyword' => 'forest',
            'location' => 'Brazil',
            'min_price' => 10.00,
        ];
        
        $query = new SearchQuery($data);
        $filters = $query->get_active_filters();
        
        $this->assertIsArray($filters);
        $this->assertEquals('forest', $filters['keyword']);
        $this->assertEquals('Brazil', $filters['location']);
        $this->assertEquals(10.00, $filters['min_price']);
        $this->assertArrayNotHasKey('max_price', $filters);
    }
    
    /**
     * Test pagination methods
     */
    public function test_pagination() {
        $query = new SearchQuery(['limit' => 20, 'offset' => 40]);
        
        $pagination = $query->get_pagination();
        $this->assertEquals(20, $pagination['limit']);
        $this->assertEquals(40, $pagination['offset']);
        $this->assertEquals(3, $pagination['page']); // (40 / 20) + 1
        
        $next_page = $query->get_next_page();
        $this->assertEquals(60, $next_page->offset);
        
        $prev_page = $query->get_previous_page();
        $this->assertEquals(20, $prev_page->offset);
    }
    
    /**
     * Test sort methods
     */
    public function test_sort() {
        $query = new SearchQuery(['sort_by' => 'price_per_kg', 'sort_order' => 'desc']);
        
        $sort = $query->get_sort();
        $this->assertEquals('price_per_kg', $sort['sort_by']);
        $this->assertEquals('desc', $sort['sort_order']);
    }
}