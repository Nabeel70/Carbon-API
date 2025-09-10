<?php
/**
 * Cache Manager
 *
 * Manages caching of API data using WordPress transients with TTL-based
 * invalidation and background refresh capabilities.
 *
 * @package CarbonMarketplace
 * @subpackage Cache
 */

namespace CarbonMarketplace\Cache;

use CarbonMarketplace\Models\Portfolio;
use CarbonMarketplace\Models\Project;
use WP_Error;

/**
 * Cache Manager class for handling API data caching
 */
class CacheManager {

    /**
     * Cache key prefix
     *
     * @var string
     */
    private $cache_prefix = 'carbon_marketplace_';

    /**
     * Default TTL values in seconds
     *
     * @var array
     */
    private $default_ttl = [
        'portfolios' => 900,    // 15 minutes
        'projects' => 3600,     // 1 hour
        'project_details' => 1800, // 30 minutes
        'quotes' => 300,        // 5 minutes
        'search_results' => 600, // 10 minutes
    ];

    /**
     * Configuration options
     *
     * @var array
     */
    private $config;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct($config = []) {
        $this->config = wp_parse_args($config, [
            'enable_cache' => true,
            'cache_prefix' => 'carbon_marketplace_',
            'default_ttl' => $this->default_ttl,
            'max_cache_size' => 1000, // Maximum number of cached items
            'background_refresh' => true,
            'compression' => true,
        ]);

        $this->cache_prefix = $this->config['cache_prefix'];
        $this->default_ttl = array_merge($this->default_ttl, $this->config['default_ttl']);

        // Schedule background refresh if enabled
        if ($this->config['background_refresh']) {
            $this->schedule_background_refresh();
        }
    }

    /**
     * Get cached portfolios
     *
     * @param string $vendor Optional vendor filter
     * @return array|null Cached portfolios or null if not found
     */
    public function get_cached_portfolios($vendor = '') {
        if (!$this->config['enable_cache']) {
            return null;
        }

        $cache_key = $this->build_cache_key('portfolios', $vendor);
        $cached_data = get_transient($cache_key);

        if ($cached_data === false) {
            return null;
        }

        return $this->decompress_data($cached_data);
    }

    /**
     * Cache portfolios data
     *
     * @param array $portfolios Portfolio data to cache
     * @param string $vendor Optional vendor identifier
     * @param int $ttl Time to live in seconds (optional)
     * @return bool True on success, false on failure
     */
    public function cache_portfolios($portfolios, $vendor = '', $ttl = null) {
        if (!$this->config['enable_cache'] || empty($portfolios)) {
            return false;
        }

        $cache_key = $this->build_cache_key('portfolios', $vendor);
        $ttl = $ttl ?? $this->default_ttl['portfolios'];
        
        $compressed_data = $this->compress_data($portfolios);
        
        $result = set_transient($cache_key, $compressed_data, $ttl);
        
        if ($result) {
            $this->update_cache_metadata($cache_key, [
                'type' => 'portfolios',
                'vendor' => $vendor,
                'count' => count($portfolios),
                'cached_at' => current_time('timestamp'),
                'expires_at' => current_time('timestamp') + $ttl,
            ]);
        }

        return $result;
    }

    /**
     * Get cached projects
     *
     * @param string $vendor Optional vendor filter
     * @param array $filters Optional additional filters
     * @return array|null Cached projects or null if not found
     */
    public function get_cached_projects($vendor = '', $filters = []) {
        if (!$this->config['enable_cache']) {
            return null;
        }

        $cache_key = $this->build_cache_key('projects', $vendor, $filters);
        $cached_data = get_transient($cache_key);

        if ($cached_data === false) {
            return null;
        }

        return $this->decompress_data($cached_data);
    }

    /**
     * Cache projects data
     *
     * @param array $projects Project data to cache
     * @param string $vendor Optional vendor identifier
     * @param array $filters Optional filters used
     * @param int $ttl Time to live in seconds (optional)
     * @return bool True on success, false on failure
     */
    public function cache_projects($projects, $vendor = '', $filters = [], $ttl = null) {
        if (!$this->config['enable_cache'] || empty($projects)) {
            return false;
        }

        $cache_key = $this->build_cache_key('projects', $vendor, $filters);
        $ttl = $ttl ?? $this->default_ttl['projects'];
        
        $compressed_data = $this->compress_data($projects);
        
        $result = set_transient($cache_key, $compressed_data, $ttl);
        
        if ($result) {
            $this->update_cache_metadata($cache_key, [
                'type' => 'projects',
                'vendor' => $vendor,
                'filters' => $filters,
                'count' => count($projects),
                'cached_at' => current_time('timestamp'),
                'expires_at' => current_time('timestamp') + $ttl,
            ]);
        }

        return $result;
    }

    /**
     * Get cached project details
     *
     * @param string $project_id Project ID
     * @param string $vendor Vendor name
     * @return Project|null Cached project or null if not found
     */
    public function get_cached_project($project_id, $vendor) {
        if (!$this->config['enable_cache'] || empty($project_id) || empty($vendor)) {
            return null;
        }

        $cache_key = $this->build_cache_key('project_details', $vendor, $project_id);
        $cached_data = get_transient($cache_key);

        if ($cached_data === false) {
            return null;
        }

        $data = $this->decompress_data($cached_data);
        
        // Convert back to Project object if it's an array
        if (is_array($data)) {
            return new Project($data);
        }

        return $data instanceof Project ? $data : null;
    }

    /**
     * Cache project details
     *
     * @param Project $project Project object to cache
     * @param int $ttl Time to live in seconds (optional)
     * @return bool True on success, false on failure
     */
    public function cache_project(Project $project, $ttl = null) {
        if (!$this->config['enable_cache'] || !$project->validate()) {
            return false;
        }

        $cache_key = $this->build_cache_key('project_details', $project->vendor, $project->id);
        $ttl = $ttl ?? $this->default_ttl['project_details'];
        
        // Store as array to avoid serialization issues
        $compressed_data = $this->compress_data($project->to_array());
        
        $result = set_transient($cache_key, $compressed_data, $ttl);
        
        if ($result) {
            $this->update_cache_metadata($cache_key, [
                'type' => 'project_details',
                'vendor' => $project->vendor,
                'project_id' => $project->id,
                'cached_at' => current_time('timestamp'),
                'expires_at' => current_time('timestamp') + $ttl,
            ]);
        }

        return $result;
    }

    /**
     * Get cached search results
     *
     * @param array $search_params Search parameters
     * @return array|null Cached search results or null if not found
     */
    public function get_cached_search_results($search_params) {
        if (!$this->config['enable_cache'] || empty($search_params)) {
            return null;
        }

        $cache_key = $this->build_cache_key('search_results', '', $search_params);
        $cached_data = get_transient($cache_key);

        if ($cached_data === false) {
            return null;
        }

        return $this->decompress_data($cached_data);
    }

    /**
     * Cache search results
     *
     * @param array $results Search results to cache
     * @param array $search_params Search parameters used
     * @param int $ttl Time to live in seconds (optional)
     * @return bool True on success, false on failure
     */
    public function cache_search_results($results, $search_params, $ttl = null) {
        if (!$this->config['enable_cache'] || empty($results)) {
            return false;
        }

        $cache_key = $this->build_cache_key('search_results', '', $search_params);
        $ttl = $ttl ?? $this->default_ttl['search_results'];
        
        $compressed_data = $this->compress_data($results);
        
        $result = set_transient($cache_key, $compressed_data, $ttl);
        
        if ($result) {
            $this->update_cache_metadata($cache_key, [
                'type' => 'search_results',
                'search_params' => $search_params,
                'count' => count($results),
                'cached_at' => current_time('timestamp'),
                'expires_at' => current_time('timestamp') + $ttl,
            ]);
        }

        return $result;
    }

    /**
     * Invalidate cache by key pattern
     *
     * @param string $pattern Cache key pattern (supports wildcards)
     * @return int Number of cache entries invalidated
     */
    public function invalidate_cache($pattern) {
        global $wpdb;

        // Convert pattern to SQL LIKE pattern
        $sql_pattern = str_replace('*', '%', $this->cache_prefix . $pattern);
        
        // Get matching transient keys
        $transient_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE '_transient_%'",
            '_transient_' . $sql_pattern
        ));

        $invalidated = 0;
        
        foreach ($transient_keys as $transient_key) {
            // Remove the '_transient_' prefix to get the actual transient name
            $transient_name = str_replace('_transient_', '', $transient_key);
            
            if (delete_transient($transient_name)) {
                $invalidated++;
            }
        }

        // Also clean up metadata
        $this->cleanup_cache_metadata($pattern);

        return $invalidated;
    }

    /**
     * Invalidate all cache
     *
     * @return int Number of cache entries invalidated
     */
    public function invalidate_all_cache() {
        return $this->invalidate_cache('*');
    }

    /**
     * Invalidate cache by vendor
     *
     * @param string $vendor Vendor name
     * @return int Number of cache entries invalidated
     */
    public function invalidate_vendor_cache($vendor) {
        return $this->invalidate_cache("*_{$vendor}_*");
    }

    /**
     * Invalidate cache by type
     *
     * @param string $type Cache type (portfolios, projects, etc.)
     * @return int Number of cache entries invalidated
     */
    public function invalidate_cache_by_type($type) {
        return $this->invalidate_cache("{$type}_*");
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function get_cache_stats() {
        $metadata = get_option($this->cache_prefix . 'metadata', []);
        
        $stats = [
            'total_entries' => count($metadata),
            'types' => [],
            'vendors' => [],
            'total_size' => 0,
            'expired_entries' => 0,
        ];

        $current_time = current_time('timestamp');

        foreach ($metadata as $cache_key => $meta) {
            // Count by type
            $type = $meta['type'] ?? 'unknown';
            if (!isset($stats['types'][$type])) {
                $stats['types'][$type] = 0;
            }
            $stats['types'][$type]++;

            // Count by vendor
            $vendor = $meta['vendor'] ?? 'unknown';
            if (!isset($stats['vendors'][$vendor])) {
                $stats['vendors'][$vendor] = 0;
            }
            $stats['vendors'][$vendor]++;

            // Check if expired
            if (isset($meta['expires_at']) && $meta['expires_at'] < $current_time) {
                $stats['expired_entries']++;
            }

            // Estimate size (rough calculation)
            $stats['total_size'] += strlen(serialize($meta));
        }

        return $stats;
    }

    /**
     * Warm cache with fresh data
     *
     * @param array $data_sources Array of data source configurations
     * @return array Results of cache warming operations
     */
    public function warm_cache($data_sources = []) {
        $results = [];

        foreach ($data_sources as $source) {
            $type = $source['type'] ?? '';
            $vendor = $source['vendor'] ?? '';
            $callback = $source['callback'] ?? null;

            if (!$callback || !is_callable($callback)) {
                $results[$type . '_' . $vendor] = [
                    'success' => false,
                    'error' => 'Invalid callback provided',
                ];
                continue;
            }

            try {
                $data = call_user_func($callback);
                
                if (is_wp_error($data)) {
                    $results[$type . '_' . $vendor] = [
                        'success' => false,
                        'error' => $data->get_error_message(),
                    ];
                    continue;
                }

                $cache_result = false;
                
                switch ($type) {
                    case 'portfolios':
                        $cache_result = $this->cache_portfolios($data, $vendor);
                        break;
                    case 'projects':
                        $cache_result = $this->cache_projects($data, $vendor);
                        break;
                }

                $results[$type . '_' . $vendor] = [
                    'success' => $cache_result,
                    'count' => is_array($data) ? count($data) : 0,
                ];

            } catch (\Exception $e) {
                $results[$type . '_' . $vendor] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Clean up expired cache entries
     *
     * @return int Number of entries cleaned up
     */
    public function cleanup_expired_cache() {
        $metadata = get_option($this->cache_prefix . 'metadata', []);
        $current_time = current_time('timestamp');
        $cleaned = 0;

        foreach ($metadata as $cache_key => $meta) {
            if (isset($meta['expires_at']) && $meta['expires_at'] < $current_time) {
                // Check if transient actually exists and delete it
                if (get_transient($cache_key) !== false) {
                    delete_transient($cache_key);
                }
                
                unset($metadata[$cache_key]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            update_option($this->cache_prefix . 'metadata', $metadata);
        }

        return $cleaned;
    }

    /**
     * Build cache key from components
     *
     * @param string $type Cache type
     * @param string $vendor Vendor name
     * @param mixed $params Additional parameters
     * @return string Cache key
     */
    private function build_cache_key($type, $vendor = '', $params = null) {
        $key_parts = [$this->cache_prefix, $type];

        if (!empty($vendor)) {
            $key_parts[] = $vendor;
        }

        if ($params !== null) {
            if (is_array($params)) {
                $key_parts[] = md5(serialize($params));
            } else {
                $key_parts[] = $params;
            }
        }

        return implode('_', $key_parts);
    }

    /**
     * Compress data for storage
     *
     * @param mixed $data Data to compress
     * @return mixed Compressed data or original if compression disabled
     */
    private function compress_data($data) {
        if (!$this->config['compression']) {
            return $data;
        }

        $serialized = serialize($data);
        
        if (function_exists('gzcompress')) {
            return [
                'compressed' => true,
                'data' => gzcompress($serialized),
            ];
        }

        return $data;
    }

    /**
     * Decompress data from storage
     *
     * @param mixed $data Data to decompress
     * @return mixed Decompressed data
     */
    private function decompress_data($data) {
        if (!is_array($data) || !isset($data['compressed']) || !$data['compressed']) {
            return $data;
        }

        if (function_exists('gzuncompress')) {
            $decompressed = gzuncompress($data['data']);
            return unserialize($decompressed);
        }

        return $data;
    }

    /**
     * Update cache metadata
     *
     * @param string $cache_key Cache key
     * @param array $metadata Metadata to store
     */
    private function update_cache_metadata($cache_key, $metadata) {
        $all_metadata = get_option($this->cache_prefix . 'metadata', []);
        $all_metadata[$cache_key] = $metadata;
        
        // Limit metadata size
        if (count($all_metadata) > $this->config['max_cache_size']) {
            // Remove oldest entries
            $sorted = $all_metadata;
            uasort($sorted, function($a, $b) {
                return ($a['cached_at'] ?? 0) <=> ($b['cached_at'] ?? 0);
            });
            
            $all_metadata = array_slice($sorted, -$this->config['max_cache_size'], null, true);
        }
        
        update_option($this->cache_prefix . 'metadata', $all_metadata);
    }

    /**
     * Clean up cache metadata
     *
     * @param string $pattern Pattern to match for cleanup
     */
    private function cleanup_cache_metadata($pattern) {
        $all_metadata = get_option($this->cache_prefix . 'metadata', []);
        $pattern_regex = '/^' . str_replace('*', '.*', preg_quote($this->cache_prefix . $pattern, '/')) . '/';
        
        foreach ($all_metadata as $cache_key => $metadata) {
            if (preg_match($pattern_regex, $cache_key)) {
                unset($all_metadata[$cache_key]);
            }
        }
        
        update_option($this->cache_prefix . 'metadata', $all_metadata);
    }

    /**
     * Schedule background refresh
     */
    private function schedule_background_refresh() {
        if (!wp_next_scheduled('carbon_marketplace_cache_refresh')) {
            wp_schedule_event(time(), 'hourly', 'carbon_marketplace_cache_refresh');
        }
    }

    /**
     * Background cache refresh callback
     */
    public function background_refresh_callback() {
        // Clean up expired entries
        $this->cleanup_expired_cache();

        // This method can be extended to implement automatic cache warming
        // based on usage patterns or predefined schedules
        
        do_action('carbon_marketplace_cache_refresh', $this);
    }
}