<?php
/**
 * Maintenance Manager for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Maintenance;

use CarbonMarketplace\Cache\CacheManager;
use CarbonMarketplace\Core\Database;
use CarbonMarketplace\Api\ApiManager;

/**
 * Handles cache warming and system maintenance tasks
 */
class MaintenanceManager {
    
    private CacheManager $cache_manager;
    private Database $database;
    private ApiManager $api_manager;
    
    public function __construct(
        CacheManager $cache_manager,
        Database $database,
        ApiManager $api_manager
    ) {
        $this->cache_manager = $cache_manager;
        $this->database = $database;
        $this->api_manager = $api_manager;
    }
    
    /**
     * Initialize maintenance hooks
     */
    public function init(): void {
        // Register maintenance cron hooks
        add_action('carbon_marketplace_cache_warming', [$this, 'warm_all_caches']);
        add_action('carbon_marketplace_database_maintenance', [$this, 'perform_database_maintenance']);
        add_action('carbon_marketplace_system_health_check', [$this, 'perform_health_check']);
        
        // Schedule maintenance jobs
        add_action('carbon_marketplace_activated', [$this, 'schedule_maintenance_jobs']);
        add_action('carbon_marketplace_deactivated', [$this, 'unschedule_maintenance_jobs']);
        
        // Admin hooks
        add_action('wp_ajax_carbon_marketplace_manual_maintenance', [$this, 'handle_manual_maintenance']);
        add_action('wp_ajax_carbon_marketplace_health_check', [$this, 'handle_health_check_request']);
    }
    
    /**
     * Schedule maintenance jobs
     */
    public function schedule_maintenance_jobs(): void {
        // Cache warming - every 2 hours
        if (!wp_next_scheduled('carbon_marketplace_cache_warming')) {
            wp_schedule_event(time(), 'carbon_marketplace_2hours', 'carbon_marketplace_cache_warming');
        }
        
        // Database maintenance - daily at 3 AM
        if (!wp_next_scheduled('carbon_marketplace_database_maintenance')) {
            $next_3am = strtotime('tomorrow 3:00 AM');
            wp_schedule_event($next_3am, 'daily', 'carbon_marketplace_database_maintenance');
        }
        
        // Health check - every 6 hours
        if (!wp_next_scheduled('carbon_marketplace_system_health_check')) {
            wp_schedule_event(time(), 'carbon_marketplace_6hours', 'carbon_marketplace_system_health_check');
        }
        
        // Add custom schedules
        add_filter('cron_schedules', [$this, 'add_maintenance_cron_schedules']);
    }
    
    /**
     * Unschedule maintenance jobs
     */
    public function unschedule_maintenance_jobs(): void {
        wp_clear_scheduled_hook('carbon_marketplace_cache_warming');
        wp_clear_scheduled_hook('carbon_marketplace_database_maintenance');
        wp_clear_scheduled_hook('carbon_marketplace_system_health_check');
    }
    
    /**
     * Add custom cron schedules for maintenance
     */
    public function add_maintenance_cron_schedules($schedules): array {
        $schedules['carbon_marketplace_2hours'] = [
            'interval' => 2 * HOUR_IN_SECONDS,
            'display' => __('Every 2 Hours', 'carbon-marketplace')
        ];
        
        $schedules['carbon_marketplace_6hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Every 6 Hours', 'carbon-marketplace')
        ];
        
        return $schedules;
    }
    
    /**
     * Warm all caches
     */
    public function warm_all_caches(): void {
        $this->log_maintenance_start('cache_warming');
        
        try {
            $warming_stats = [
                'portfolios' => 0,
                'projects' => 0,
                'search_results' => 0,
                'popular_searches' => 0,
                'errors' => 0
            ];
            
            // Warm portfolio cache
            try {
                $portfolios = $this->api_manager->fetch_all_portfolios();
                if (!is_wp_error($portfolios)) {
                    $this->cache_manager->cache_portfolios($portfolios);
                    $warming_stats['portfolios'] = count($portfolios);
                }
            } catch (\Exception $e) {
                $warming_stats['errors']++;
                error_log("Portfolio cache warming error: " . $e->getMessage());
            }
            
            // Warm project cache
            try {
                $projects = $this->api_manager->fetch_all_projects();
                if (!is_wp_error($projects)) {
                    foreach ($projects as $project) {
                        $this->cache_manager->cache_project($project);
                    }
                    $warming_stats['projects'] = count($projects);
                }
            } catch (\Exception $e) {
                $warming_stats['errors']++;
                error_log("Project cache warming error: " . $e->getMessage());
            }
            
            // Warm popular search results
            try {
                $popular_searches = $this->get_popular_search_terms();
                foreach ($popular_searches as $search_term) {
                    $cache_key = "search_results_" . md5($search_term);
                    if (!$this->cache_manager->get_cached_data($cache_key)) {
                        // Perform search and cache results
                        $search_results = $this->perform_search_for_warming($search_term);
                        if ($search_results) {
                            $this->cache_manager->cache_data($cache_key, $search_results, 'search_results');
                            $warming_stats['search_results']++;
                        }
                    }
                }
                $warming_stats['popular_searches'] = count($popular_searches);
            } catch (\Exception $e) {
                $warming_stats['errors']++;
                error_log("Search cache warming error: " . $e->getMessage());
            }
            
            $this->log_maintenance_complete('cache_warming', $warming_stats);
            
        } catch (\Exception $e) {
            $this->log_maintenance_error('cache_warming', $e->getMessage());
        }
    }
    
    /**
     * Perform database maintenance
     */
    public function perform_database_maintenance(): void {
        $this->log_maintenance_start('database_maintenance');
        
        try {
            global $wpdb;
            
            $maintenance_stats = [
                'optimized_tables' => 0,
                'cleaned_transients' => 0,
                'updated_indexes' => 0,
                'errors' => 0
            ];
            
            // Optimize database tables
            $tables = [
                $this->database->get_table_name('projects'),
                $this->database->get_table_name('orders'),
                $wpdb->prefix . 'carbon_marketplace_analytics',
                $wpdb->prefix . 'carbon_marketplace_webhook_logs'
            ];
            
            foreach ($tables as $table) {
                try {
                    $wpdb->query("OPTIMIZE TABLE {$table}");
                    $maintenance_stats['optimized_tables']++;
                } catch (\Exception $e) {
                    $maintenance_stats['errors']++;
                    error_log("Table optimization error for {$table}: " . $e->getMessage());
                }
            }
            
            // Clean expired transients
            try {
                $cleaned = $this->clean_expired_transients();
                $maintenance_stats['cleaned_transients'] = $cleaned;
            } catch (\Exception $e) {
                $maintenance_stats['errors']++;
                error_log("Transient cleanup error: " . $e->getMessage());
            }
            
            // Update search indexes
            try {
                $this->update_search_indexes();
                $maintenance_stats['updated_indexes'] = 1;
            } catch (\Exception $e) {
                $maintenance_stats['errors']++;
                error_log("Search index update error: " . $e->getMessage());
            }
            
            // Clean up old log entries
            try {
                $this->cleanup_old_logs();
            } catch (\Exception $e) {
                $maintenance_stats['errors']++;
                error_log("Log cleanup error: " . $e->getMessage());
            }
            
            $this->log_maintenance_complete('database_maintenance', $maintenance_stats);
            
        } catch (\Exception $e) {
            $this->log_maintenance_error('database_maintenance', $e->getMessage());
        }
    }
    
    /**
     * Perform system health check
     */
    public function perform_health_check(): void {
        $this->log_maintenance_start('health_check');
        
        try {
            $health_status = [
                'database' => $this->check_database_health(),
                'api_connections' => $this->check_api_connections(),
                'cache_system' => $this->check_cache_system(),
                'cron_jobs' => $this->check_cron_jobs(),
                'disk_space' => $this->check_disk_space(),
                'memory_usage' => $this->check_memory_usage()
            ];
            
            $overall_health = $this->calculate_overall_health($health_status);
            
            // Store health check results
            update_option('carbon_marketplace_health_status', [
                'overall' => $overall_health,
                'details' => $health_status,
                'checked_at' => current_time('mysql')
            ]);
            
            // Send alerts if critical issues found
            if ($overall_health < 70) {
                $this->send_health_alert($health_status);
            }
            
            $this->log_maintenance_complete('health_check', [
                'overall_health' => $overall_health,
                'issues_found' => array_filter($health_status, function($status) {
                    return $status['status'] !== 'healthy';
                })
            ]);
            
        } catch (\Exception $e) {
            $this->log_maintenance_error('health_check', $e->getMessage());
        }
    }
    
    /**
     * Handle manual maintenance request
     */
    public function handle_manual_maintenance(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'carbon_marketplace_maintenance')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $maintenance_type = sanitize_text_field($_POST['maintenance_type'] ?? '');
        
        switch ($maintenance_type) {
            case 'cache_warming':
                $this->warm_all_caches();
                break;
            case 'database_maintenance':
                $this->perform_database_maintenance();
                break;
            case 'health_check':
                $this->perform_health_check();
                break;
            case 'cleanup_cache':
                $this->cache_manager->invalidate_all_cache();
                break;
            default:
                wp_send_json_error('Invalid maintenance type');
        }
        
        wp_send_json_success('Maintenance task completed');
    }
    
    /**
     * Handle health check request
     */
    public function handle_health_check_request(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'carbon_marketplace_health')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $health_status = get_option('carbon_marketplace_health_status', []);
        wp_send_json_success($health_status);
    }
    
    /**
     * Get popular search terms for cache warming
     */
    private function get_popular_search_terms(): array {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'carbon_marketplace_analytics';
        
        $results = $wpdb->get_results("
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.keyword')) as search_term,
                COUNT(*) as search_count
            FROM {$analytics_table} 
            WHERE event_type = 'search' 
            AND JSON_EXTRACT(event_data, '$.keyword') IS NOT NULL
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY search_term 
            ORDER BY search_count DESC 
            LIMIT 10
        ");
        
        return array_column($results, 'search_term');
    }
    
    /**
     * Perform search for cache warming
     */
    private function perform_search_for_warming(string $search_term): ?array {
        // This would integrate with the search engine
        // For now, return mock data structure
        return [
            'keyword' => $search_term,
            'results' => [],
            'total_count' => 0,
            'cached_at' => current_time('mysql')
        ];
    }
    
    /**
     * Clean expired transients
     */
    private function clean_expired_transients(): int {
        global $wpdb;
        
        $count = $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_timeout_%' 
            AND option_value < UNIX_TIMESTAMP()
        ");
        
        // Clean up the corresponding transient data
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_%' 
            AND option_name NOT LIKE '_transient_timeout_%'
            AND option_name NOT IN (
                SELECT CONCAT('_transient_', SUBSTRING(option_name, 19))
                FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_timeout_%'
            )
        ");
        
        return $count ?: 0;
    }
    
    /**
     * Update search indexes
     */
    private function update_search_indexes(): void {
        global $wpdb;
        
        $projects_table = $this->database->get_table_name('projects');
        
        // Analyze table for better query performance
        $wpdb->query("ANALYZE TABLE {$projects_table}");
        
        // Update statistics
        $wpdb->query("UPDATE {$projects_table} SET updated_at = updated_at WHERE 1=1 LIMIT 1");
    }
    
    /**
     * Clean up old logs
     */
    private function cleanup_old_logs(): void {
        global $wpdb;
        
        // Clean webhook logs older than 3 months
        $webhook_logs_table = $wpdb->prefix . 'carbon_marketplace_webhook_logs';
        $wpdb->query("
            DELETE FROM {$webhook_logs_table} 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
        ");
        
        // Clean analytics data older than 1 year
        $analytics_table = $wpdb->prefix . 'carbon_marketplace_analytics';
        $wpdb->query("
            DELETE FROM {$analytics_table} 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
        ");
    }
    
    /**
     * Check database health
     */
    private function check_database_health(): array {
        global $wpdb;
        
        try {
            // Check database connection
            $wpdb->get_var("SELECT 1");
            
            // Check table integrity
            $tables = [
                $this->database->get_table_name('projects'),
                $this->database->get_table_name('orders')
            ];
            
            $issues = [];
            foreach ($tables as $table) {
                $result = $wpdb->get_row("CHECK TABLE {$table}");
                if ($result && $result->Msg_text !== 'OK') {
                    $issues[] = "Table {$table}: {$result->Msg_text}";
                }
            }
            
            return [
                'status' => empty($issues) ? 'healthy' : 'warning',
                'message' => empty($issues) ? 'Database is healthy' : 'Database issues detected',
                'issues' => $issues
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check API connections
     */
    private function check_api_connections(): array {
        try {
            $api_status = $this->api_manager->validate_all_clients();
            
            $healthy_apis = array_filter($api_status, function($status) {
                return !is_wp_error($status);
            });
            
            $health_percentage = count($healthy_apis) / count($api_status) * 100;
            
            if ($health_percentage >= 100) {
                $status = 'healthy';
                $message = 'All API connections are working';
            } elseif ($health_percentage >= 50) {
                $status = 'warning';
                $message = 'Some API connections have issues';
            } else {
                $status = 'critical';
                $message = 'Most API connections are failing';
            }
            
            return [
                'status' => $status,
                'message' => $message,
                'api_status' => $api_status,
                'health_percentage' => $health_percentage
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'API connection check failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check cache system
     */
    private function check_cache_system(): array {
        try {
            // Test cache write/read
            $test_key = 'health_check_' . time();
            $test_data = ['test' => true, 'timestamp' => time()];
            
            $this->cache_manager->cache_data($test_key, $test_data, 'test');
            $retrieved = $this->cache_manager->get_cached_data($test_key);
            
            if ($retrieved && $retrieved['test'] === true) {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache system is working properly'
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => 'Cache system may have issues'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Cache system is not working',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check cron jobs
     */
    private function check_cron_jobs(): array {
        $cron_jobs = [
            'carbon_marketplace_sync_portfolios',
            'carbon_marketplace_sync_projects',
            'carbon_marketplace_cache_warming'
        ];
        
        $issues = [];
        foreach ($cron_jobs as $job) {
            if (!wp_next_scheduled($job)) {
                $issues[] = "Cron job '{$job}' is not scheduled";
            }
        }
        
        return [
            'status' => empty($issues) ? 'healthy' : 'warning',
            'message' => empty($issues) ? 'All cron jobs are scheduled' : 'Some cron jobs are missing',
            'issues' => $issues
        ];
    }
    
    /**
     * Check disk space
     */
    private function check_disk_space(): array {
        $free_bytes = disk_free_space(ABSPATH);
        $total_bytes = disk_total_space(ABSPATH);
        
        if ($free_bytes === false || $total_bytes === false) {
            return [
                'status' => 'unknown',
                'message' => 'Unable to check disk space'
            ];
        }
        
        $free_percentage = ($free_bytes / $total_bytes) * 100;
        
        if ($free_percentage >= 20) {
            $status = 'healthy';
            $message = 'Sufficient disk space available';
        } elseif ($free_percentage >= 10) {
            $status = 'warning';
            $message = 'Disk space is getting low';
        } else {
            $status = 'critical';
            $message = 'Critically low disk space';
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'free_space' => $this->format_bytes($free_bytes),
            'total_space' => $this->format_bytes($total_bytes),
            'free_percentage' => round($free_percentage, 2)
        ];
    }
    
    /**
     * Check memory usage
     */
    private function check_memory_usage(): array {
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        $limit_bytes = $this->parse_size($memory_limit);
        $usage_percentage = ($memory_usage / $limit_bytes) * 100;
        
        if ($usage_percentage < 70) {
            $status = 'healthy';
            $message = 'Memory usage is normal';
        } elseif ($usage_percentage < 85) {
            $status = 'warning';
            $message = 'Memory usage is elevated';
        } else {
            $status = 'critical';
            $message = 'Memory usage is critically high';
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'memory_limit' => $memory_limit,
            'current_usage' => $this->format_bytes($memory_usage),
            'peak_usage' => $this->format_bytes($memory_peak),
            'usage_percentage' => round($usage_percentage, 2)
        ];
    }
    
    /**
     * Calculate overall health score
     */
    private function calculate_overall_health(array $health_status): int {
        $scores = [];
        
        foreach ($health_status as $check) {
            switch ($check['status']) {
                case 'healthy':
                    $scores[] = 100;
                    break;
                case 'warning':
                    $scores[] = 60;
                    break;
                case 'critical':
                    $scores[] = 20;
                    break;
                default:
                    $scores[] = 50;
            }
        }
        
        return empty($scores) ? 0 : (int) (array_sum($scores) / count($scores));
    }
    
    /**
     * Send health alert
     */
    private function send_health_alert(array $health_status): void {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $critical_issues = array_filter($health_status, function($status) {
            return $status['status'] === 'critical';
        });
        
        if (!empty($critical_issues)) {
            $subject = "[{$site_name}] Carbon Marketplace Health Alert";
            $message = "Critical issues detected in Carbon Marketplace:\n\n";
            
            foreach ($critical_issues as $component => $issue) {
                $message .= "- {$component}: {$issue['message']}\n";
            }
            
            $message .= "\nPlease check the admin dashboard for more details.";
            
            wp_mail($admin_email, $subject, $message);
        }
    }
    
    /**
     * Format bytes to human readable
     */
    private function format_bytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Parse size string to bytes
     */
    private function parse_size(string $size): int {
        $unit = strtolower(substr($size, -1));
        $value = (int) $size;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Log maintenance start
     */
    private function log_maintenance_start(string $task): void {
        update_option("carbon_marketplace_maintenance_{$task}_status", [
            'status' => 'running',
            'started_at' => current_time('mysql'),
            'message' => "Starting {$task} maintenance"
        ]);
    }
    
    /**
     * Log maintenance completion
     */
    private function log_maintenance_complete(string $task, array $stats): void {
        update_option("carbon_marketplace_maintenance_{$task}_status", [
            'status' => 'completed',
            'started_at' => get_option("carbon_marketplace_maintenance_{$task}_status")['started_at'] ?? current_time('mysql'),
            'completed_at' => current_time('mysql'),
            'stats' => $stats,
            'message' => "Completed {$task} maintenance"
        ]);
    }
    
    /**
     * Log maintenance error
     */
    private function log_maintenance_error(string $task, string $error): void {
        update_option("carbon_marketplace_maintenance_{$task}_status", [
            'status' => 'error',
            'started_at' => get_option("carbon_marketplace_maintenance_{$task}_status")['started_at'] ?? current_time('mysql'),
            'error_at' => current_time('mysql'),
            'error' => $error,
            'message' => "Error in {$task} maintenance: {$error}"
        ]);
        
        error_log("Carbon Marketplace: {$task} maintenance error - {$error}");
    }
    
    /**
     * Get maintenance status
     */
    public function get_maintenance_status(): array {
        $tasks = ['cache_warming', 'database_maintenance', 'health_check'];
        $status = [];
        
        foreach ($tasks as $task) {
            $status[$task] = get_option("carbon_marketplace_maintenance_{$task}_status", [
                'status' => 'never_run',
                'message' => 'Never executed'
            ]);
        }
        
        return $status;
    }
}