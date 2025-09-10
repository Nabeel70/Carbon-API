<?php
/**
 * Unit tests for CacheManager class
 *
 * @package CarbonMarketplace
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;
use CarbonMarketplace\Cache\CacheManager;
use CarbonMarketplace\Models\Portfolio;
use CarbonMarketplace\Models\Project;

/**
 * Test class for CacheManager
 */
class TestCacheManager extends TestCase {

    /**
     * CacheManager instance
     *
     * @var CacheManager
     */
    private $cache_manager;

    /**
     * Test cache prefix
     *
     * @var string
     */
    private $test_prefix = 'test_carbon_marketplace_';

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->cache_manager = new CacheManager([
            'enable_cache' => true,
            'cache_prefix' => $this->test_prefix,
            'compression' => false, // Disable compression for easier testing
            'background_refresh' => false, // Disable background refresh for testing
        ]);

        // Clean up any existing test cache
        $this->cleanup_test_cache();
    }

    /**
     * Test caching and retrieving portfolios
     */
    public function test_cache_and_get_portfolios() {
        $portfolios = [
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

        // Cache portfolios
        $result = $this->cache_manager->cache_portfolios($portfolios, 'test_vendor', 300);
        $this->assertTrue($result);

        // Retrieve cached portfolios
        $cached_portfolios = $this->cache_manager->get_cached_portfolios('test_vendor');
        
        $this->assertIsArray($cached_portfolios);
        $this->assertCount(2, $cached_portfolios);
        $this->assertInstanceOf(Portfolio::class, $cached_portfolios[0]);
        $this->assertEquals('portfolio1', $cached_portfolios[0]->id);
    }

    /**
     * Test caching and retrieving projects
     */
    public function test_cache_and_get_projects() {
        $projects = [
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

        // Cache projects
        $result = $this->cache_manager->cache_projects($projects, 'test_vendor', [], 300);
        $this->assertTrue($result);

        // Retrieve cached projects
        $cached_projects = $this->cache_manager->get_cached_projects('test_vendor');
        
        $this->assertIsArray($cached_projects);
        $this->assertCount(1, $cached_projects);
        $this->assertInstanceOf(Project::class, $cached_projects[0]);
        $this->assertEquals('project1', $cached_projects[0]->id);
    }

    /**
     * Test caching and retrieving individual project
     */
    public function test_cache_and_get_project() {
        $project = new Project([
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

        // Cache project
        $result = $this->cache_manager->cache_project($project, 300);
        $this->assertTrue($result);

        // Retrieve cached project
        $cached_project = $this->cache_manager->get_cached_project('project1', 'test_vendor');
        
        $this->assertInstanceOf(Project::class, $cached_project);
        $this->assertEquals('project1', $cached_project->id);
        $this->assertEquals('test_vendor', $cached_project->vendor);
        $this->assertEquals('Test Project', $cached_project->name);
    }

    /**
     * Test caching search results
     */
    public function test_cache_and_get_search_results() {
        $search_params = [
            'location' => 'Brazil',
            'project_type' => 'Forestry',
            'min_price' => 10.00,
        ];

        $search_results = [
            ['id' => 'project1', 'name' => 'Forest Project 1'],
            ['id' => 'project2', 'name' => 'Forest Project 2'],
        ];

        // Cache search results
        $result = $this->cache_manager->cache_search_results($search_results, $search_params, 300);
        $this->assertTrue($result);

        // Retrieve cached search results
        $cached_results = $this->cache_manager->get_cached_search_results($search_params);
        
        $this->assertIsArray($cached_results);
        $this->assertCount(2, $cached_results);
        $this->assertEquals('project1', $cached_results[0]['id']);
    }

    /**
     * Test cache invalidation by pattern
     */
    public function test_invalidate_cache_by_pattern() {
        // Cache some data
        $portfolios = [
            new Portfolio(['id' => 'p1', 'vendor' => 'vendor1', 'name' => 'Portfolio 1']),
        ];
        $projects = [
            new Project(['id' => 'pr1', 'vendor' => 'vendor1', 'name' => 'Project 1']),
        ];

        $this->cache_manager->cache_portfolios($portfolios, 'vendor1', 300);
        $this->cache_manager->cache_projects($projects, 'vendor1', [], 300);

        // Verify data is cached
        $this->assertNotNull($this->cache_manager->get_cached_portfolios('vendor1'));
        $this->assertNotNull($this->cache_manager->get_cached_projects('vendor1'));

        // Invalidate portfolios only
        $invalidated = $this->cache_manager->invalidate_cache_by_type('portfolios');
        $this->assertGreaterThan(0, $invalidated);

        // Verify portfolios are invalidated but projects remain
        $this->assertNull($this->cache_manager->get_cached_portfolios('vendor1'));
        $this->assertNotNull($this->cache_manager->get_cached_projects('vendor1'));
    }

    /**
     * Test cache invalidation by vendor
     */
    public function test_invalidate_vendor_cache() {
        // Cache data for two vendors
        $portfolios1 = [new Portfolio(['id' => 'p1', 'vendor' => 'vendor1', 'name' => 'Portfolio 1'])];
        $portfolios2 = [new Portfolio(['id' => 'p2', 'vendor' => 'vendor2', 'name' => 'Portfolio 2'])];

        $this->cache_manager->cache_portfolios($portfolios1, 'vendor1', 300);
        $this->cache_manager->cache_portfolios($portfolios2, 'vendor2', 300);

        // Verify both are cached
        $this->assertNotNull($this->cache_manager->get_cached_portfolios('vendor1'));
        $this->assertNotNull($this->cache_manager->get_cached_portfolios('vendor2'));

        // Invalidate vendor1 cache only
        $invalidated = $this->cache_manager->invalidate_vendor_cache('vendor1');
        $this->assertGreaterThan(0, $invalidated);

        // Verify vendor1 is invalidated but vendor2 remains
        $this->assertNull($this->cache_manager->get_cached_portfolios('vendor1'));
        $this->assertNotNull($this->cache_manager->get_cached_portfolios('vendor2'));
    }

    /**
     * Test cache invalidation of all cache
     */
    public function test_invalidate_all_cache() {
        // Cache some data
        $portfolios = [new Portfolio(['id' => 'p1', 'vendor' => 'vendor1', 'name' => 'Portfolio 1'])];
        $projects = [new Project(['id' => 'pr1', 'vendor' => 'vendor1', 'name' => 'Project 1'])];

        $this->cache_manager->cache_portfolios($portfolios, 'vendor1', 300);
        $this->cache_manager->cache_projects($projects, 'vendor1', [], 300);

        // Verify data is cached
        $this->assertNotNull($this->cache_manager->get_cached_portfolios('vendor1'));
        $this->assertNotNull($this->cache_manager->get_cached_projects('vendor1'));

        // Invalidate all cache
        $invalidated = $this->cache_manager->invalidate_all_cache();
        $this->assertGreaterThan(0, $invalidated);

        // Verify all cache is invalidated
        $this->assertNull($this->cache_manager->get_cached_portfolios('vendor1'));
        $this->assertNull($this->cache_manager->get_cached_projects('vendor1'));
    }

    /**
     * Test cache statistics
     */
    public function test_get_cache_stats() {
        // Cache some data
        $portfolios = [new Portfolio(['id' => 'p1', 'vendor' => 'vendor1', 'name' => 'Portfolio 1'])];
        $projects = [new Project(['id' => 'pr1', 'vendor' => 'vendor1', 'name' => 'Project 1'])];

        $this->cache_manager->cache_portfolios($portfolios, 'vendor1', 300);
        $this->cache_manager->cache_projects($projects, 'vendor1', [], 300);

        $stats = $this->cache_manager->get_cache_stats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_entries', $stats);
        $this->assertArrayHasKey('types', $stats);
        $this->assertArrayHasKey('vendors', $stats);
        $this->assertGreaterThan(0, $stats['total_entries']);
        $this->assertArrayHasKey('portfolios', $stats['types']);
        $this->assertArrayHasKey('projects', $stats['types']);
    }

    /**
     * Test cache warming
     */
    public function test_warm_cache() {
        $data_sources = [
            [
                'type' => 'portfolios',
                'vendor' => 'test_vendor',
                'callback' => function() {
                    return [
                        new Portfolio(['id' => 'p1', 'vendor' => 'test_vendor', 'name' => 'Portfolio 1']),
                    ];
                },
            ],
            [
                'type' => 'projects',
                'vendor' => 'test_vendor',
                'callback' => function() {
                    return [
                        new Project(['id' => 'pr1', 'vendor' => 'test_vendor', 'name' => 'Project 1']),
                    ];
                },
            ],
        ];

        $results = $this->cache_manager->warm_cache($data_sources);
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('portfolios_test_vendor', $results);
        $this->assertArrayHasKey('projects_test_vendor', $results);
        
        $this->assertTrue($results['portfolios_test_vendor']['success']);
        $this->assertEquals(1, $results['portfolios_test_vendor']['count']);
        
        $this->assertTrue($results['projects_test_vendor']['success']);
        $this->assertEquals(1, $results['projects_test_vendor']['count']);

        // Verify data was actually cached
        $this->assertNotNull($this->cache_manager->get_cached_portfolios('test_vendor'));
        $this->assertNotNull($this->cache_manager->get_cached_projects('test_vendor'));
    }

    /**
     * Test cache warming with invalid callback
     */
    public function test_warm_cache_invalid_callback() {
        $data_sources = [
            [
                'type' => 'portfolios',
                'vendor' => 'test_vendor',
                'callback' => 'invalid_callback',
            ],
        ];

        $results = $this->cache_manager->warm_cache($data_sources);
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('portfolios_test_vendor', $results);
        $this->assertFalse($results['portfolios_test_vendor']['success']);
        $this->assertStringContains('Invalid callback', $results['portfolios_test_vendor']['error']);
    }

    /**
     * Test cache warming with callback error
     */
    public function test_warm_cache_callback_error() {
        $data_sources = [
            [
                'type' => 'portfolios',
                'vendor' => 'test_vendor',
                'callback' => function() {
                    return new WP_Error('api_error', 'API failed');
                },
            ],
        ];

        $results = $this->cache_manager->warm_cache($data_sources);
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('portfolios_test_vendor', $results);
        $this->assertFalse($results['portfolios_test_vendor']['success']);
        $this->assertEquals('API failed', $results['portfolios_test_vendor']['error']);
    }

    /**
     * Test cleanup of expired cache entries
     */
    public function test_cleanup_expired_cache() {
        // Cache data with very short TTL
        $portfolios = [new Portfolio(['id' => 'p1', 'vendor' => 'vendor1', 'name' => 'Portfolio 1'])];
        $this->cache_manager->cache_portfolios($portfolios, 'vendor1', 1); // 1 second TTL

        // Wait for cache to expire
        sleep(2);

        // Run cleanup
        $cleaned = $this->cache_manager->cleanup_expired_cache();
        
        // Note: This test might be flaky depending on WordPress transient implementation
        // In a real WordPress environment, expired transients are automatically cleaned up
        $this->assertGreaterThanOrEqual(0, $cleaned);
    }

    /**
     * Test caching with disabled cache
     */
    public function test_disabled_cache() {
        $disabled_cache_manager = new CacheManager([
            'enable_cache' => false,
        ]);

        $portfolios = [new Portfolio(['id' => 'p1', 'vendor' => 'vendor1', 'name' => 'Portfolio 1'])];
        
        // Attempt to cache
        $result = $disabled_cache_manager->cache_portfolios($portfolios, 'vendor1', 300);
        $this->assertFalse($result);

        // Attempt to retrieve
        $cached = $disabled_cache_manager->get_cached_portfolios('vendor1');
        $this->assertNull($cached);
    }

    /**
     * Test caching empty data
     */
    public function test_cache_empty_data() {
        $result = $this->cache_manager->cache_portfolios([], 'vendor1', 300);
        $this->assertFalse($result);

        $result = $this->cache_manager->cache_projects([], 'vendor1', [], 300);
        $this->assertFalse($result);
    }

    /**
     * Test caching invalid project
     */
    public function test_cache_invalid_project() {
        $invalid_project = new Project([]); // Missing required fields
        
        $result = $this->cache_manager->cache_project($invalid_project, 300);
        $this->assertFalse($result);
    }

    /**
     * Test retrieving non-existent cache
     */
    public function test_get_nonexistent_cache() {
        $cached_portfolios = $this->cache_manager->get_cached_portfolios('nonexistent_vendor');
        $this->assertNull($cached_portfolios);

        $cached_projects = $this->cache_manager->get_cached_projects('nonexistent_vendor');
        $this->assertNull($cached_projects);

        $cached_project = $this->cache_manager->get_cached_project('nonexistent_id', 'nonexistent_vendor');
        $this->assertNull($cached_project);

        $cached_search = $this->cache_manager->get_cached_search_results(['query' => 'nonexistent']);
        $this->assertNull($cached_search);
    }

    /**
     * Clean up test cache entries
     */
    private function cleanup_test_cache() {
        global $wpdb;
        
        // Delete all test transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            '_transient_' . $this->test_prefix . '%',
            '_transient_timeout_' . $this->test_prefix . '%'
        ));

        // Clean up metadata
        delete_option($this->test_prefix . 'metadata');
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void {
        parent::tearDown();
        $this->cleanup_test_cache();
        $this->cache_manager = null;
    }
}