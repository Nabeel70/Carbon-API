<?php
/**
 * Data Synchronizer for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Sync;

use CarbonMarketplace\Api\ApiManager;
use CarbonMarketplace\Cache\CacheManager;
use CarbonMarketplace\Core\Database;
use CarbonMarketplace\Search\SearchEngine;
use WP_Error;

/**
 * Handles scheduled data synchronization from vendor APIs
 */
class DataSynchronizer {
    
    private ApiManager $api_manager;
    private CacheManager $cache_manager;
    private Database $database;
    private SearchEngine $search_engine;
    
    public function __construct(
        ApiManager $api_manager,
        CacheManager $cache_manager,
        Database $database,
        SearchEngine $search_engine
    ) {
        $this->api_manager = $api_manager;
        $this->cache_manager = $cache_manager;
        $this->database = $database;
        $this->search_engine = $search_engine;
    }
    
    /**
     * Initialize synchronization hooks
     */
    public function init(): void {
        // Register cron hooks
        add_action('carbon_marketplace_sync_portfolios', [$this, 'sync_portfolios']);
        add_action('carbon_marketplace_sync_projects', [$this, 'sync_projects']);
        add_action('carbon_marketplace_sync_pricing', [$this, 'sync_pricing']);
        add_action('carbon_marketplace_full_sync', [$this, 'full_sync']);
        add_action('carbon_marketplace_cleanup_data', [$this, 'cleanup_old_data']);
        
        // Schedule cron jobs on activation
        add_action('carbon_marketplace_activated', [$this, 'schedule_sync_jobs']);
        add_action('carbon_marketplace_deactivated', [$this, 'unschedule_sync_jobs']);
        
        // Admin hooks for manual sync
        add_action('wp_ajax_carbon_marketplace_manual_sync', [$this, 'handle_manual_sync']);
    }
    
    /**
     * Schedule all synchronization jobs
     */
    public function schedule_sync_jobs(): void {
        // Portfolio sync - every 30 minutes
        if (!wp_next_scheduled('carbon_marketplace_sync_portfolios')) {
            wp_schedule_event(time(), 'carbon_marketplace_30min', 'carbon_marketplace_sync_portfolios');
        }
        
        // Project sync - every hour
        if (!wp_next_scheduled('carbon_marketplace_sync_projects')) {
            wp_schedule_event(time(), 'hourly', 'carbon_marketplace_sync_projects');
        }
        
        // Pricing sync - every 15 minutes
        if (!wp_next_scheduled('carbon_marketplace_sync_pricing')) {
            wp_schedule_event(time(), 'carbon_marketplace_15min', 'carbon_marketplace_sync_pricing');
        }
        
        // Full sync - daily
        if (!wp_next_scheduled('carbon_marketplace_full_sync')) {
            wp_schedule_event(time(), 'daily', 'carbon_marketplace_full_sync');
        }
        
        // Cleanup - weekly
        if (!wp_next_scheduled('carbon_marketplace_cleanup_data')) {
            wp_schedule_event(time(), 'weekly', 'carbon_marketplace_cleanup_data');
        }
        
        // Add custom cron schedules
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedules']);
    }
    
    /**
     * Unschedule all synchronization jobs
     */
    public function unschedule_sync_jobs(): void {
        wp_clear_scheduled_hook('carbon_marketplace_sync_portfolios');
        wp_clear_scheduled_hook('carbon_marketplace_sync_projects');
        wp_clear_scheduled_hook('carbon_marketplace_sync_pricing');
        wp_clear_scheduled_hook('carbon_marketplace_full_sync');
        wp_clear_scheduled_hook('carbon_marketplace_cleanup_data');
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_custom_cron_schedules($schedules): array {
        $schedules['carbon_marketplace_15min'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 Minutes', 'carbon-marketplace')
        ];
        
        $schedules['carbon_marketplace_30min'] = [
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 Minutes', 'carbon-marketplace')
        ];
        
        return $schedules;
    }
    
    /**
     * Sync portfolios from all vendors
     */
    public function sync_portfolios(): void {
        $this->log_sync_start('portfolios');
        
        try {
            $portfolios = $this->api_manager->fetch_all_portfolios();
            
            if (is_wp_error($portfolios)) {
                $this->log_sync_error('portfolios', $portfolios->get_error_message());
                return;
            }
            
            $sync_stats = [
                'total' => count($portfolios),
                'updated' => 0,
                'new' => 0,
                'errors' => 0
            ];
            
            foreach ($portfolios as $portfolio) {
                try {
                    $existing = $this->get_existing_portfolio($portfolio->get_id(), $portfolio->get_vendor());
                    
                    if ($existing) {
                        $this->update_portfolio($portfolio);
                        $sync_stats['updated']++;
                    } else {
                        $this->insert_portfolio($portfolio);
                        $sync_stats['new']++;
                    }
                } catch (\Exception $e) {
                    $sync_stats['errors']++;
                    error_log("Portfolio sync error: " . $e->getMessage());
                }
            }
            
            // Update cache
            $this->cache_manager->cache_portfolios($portfolios);
            
            $this->log_sync_complete('portfolios', $sync_stats);
            
        } catch (\Exception $e) {
            $this->log_sync_error('portfolios', $e->getMessage());
        }
    }
    
    /**
     * Sync projects from all vendors
     */
    public function sync_projects(): void {
        $this->log_sync_start('projects');
        
        try {
            $projects = $this->api_manager->fetch_all_projects();
            
            if (is_wp_error($projects)) {
                $this->log_sync_error('projects', $projects->get_error_message());
                return;
            }
            
            $sync_stats = [
                'total' => count($projects),
                'updated' => 0,
                'new' => 0,
                'errors' => 0
            ];
            
            foreach ($projects as $project) {
                try {
                    $existing = $this->database->get_project($project->get_id());
                    
                    if ($existing) {
                        $this->database->update_project($project->get_id(), $project->to_array());
                        $sync_stats['updated']++;
                    } else {
                        $this->database->insert_project($project->to_array());
                        $sync_stats['new']++;
                    }
                } catch (\Exception $e) {
                    $sync_stats['errors']++;
                    error_log("Project sync error: " . $e->getMessage());
                }
            }
            
            // Update search index
            $this->search_engine->index_projects($projects);
            
            $this->log_sync_complete('projects', $sync_stats);
            
        } catch (\Exception $e) {
            $this->log_sync_error('projects', $e->getMessage());
        }
    }
    
    /**
     * Sync pricing data
     */
    public function sync_pricing(): void {
        $this->log_sync_start('pricing');
        
        try {
            // Get all active projects
            $projects = $this->database->search_projects(['status' => 'active']);
            
            $sync_stats = [
                'total' => count($projects),
                'updated' => 0,
                'errors' => 0
            ];
            
            foreach ($projects as $project_data) {
                try {
                    // Get updated pricing
                    $quote = $this->api_manager->get_quote(1.0, null, $project_data['vendor']);
                    
                    if (!is_wp_error($quote)) {
                        // Update project pricing
                        $this->database->update_project($project_data['id'], [
                            'price_per_kg' => $quote->get_price_per_kg(),
                            'updated_at' => current_time('mysql')
                        ]);
                        $sync_stats['updated']++;
                    }
                } catch (\Exception $e) {
                    $sync_stats['errors']++;
                    error_log("Pricing sync error for project {$project_data['id']}: " . $e->getMessage());
                }
            }
            
            // Invalidate pricing cache
            $this->cache_manager->invalidate_cache_by_type('quotes');
            
            $this->log_sync_complete('pricing', $sync_stats);
            
        } catch (\Exception $e) {
            $this->log_sync_error('pricing', $e->getMessage());
        }
    }
    
    /**
     * Perform full synchronization
     */
    public function full_sync(): void {
        $this->log_sync_start('full_sync');
        
        // Clear all caches first
        $this->cache_manager->invalidate_all_cache();
        
        // Sync in order
        $this->sync_portfolios();
        $this->sync_projects();
        $this->sync_pricing();
        
        // Warm up caches
        $this->cache_manager->warm_cache([
            'portfolios' => [$this->api_manager, 'fetch_all_portfolios'],
            'projects' => [$this->api_manager, 'fetch_all_projects']
        ]);
        
        $this->log_sync_complete('full_sync', ['message' => 'Full synchronization completed']);
    }
    
    /**
     * Clean up old data
     */
    public function cleanup_old_data(): void {
        global $wpdb;
        
        $this->log_sync_start('cleanup');
        
        try {
            $cleanup_stats = [
                'old_projects' => 0,
                'old_orders' => 0,
                'old_logs' => 0,
                'old_analytics' => 0
            ];
            
            // Clean up projects not updated in 30 days
            $projects_table = $this->database->get_table_name('projects');
            $old_projects = $wpdb->query(
                "DELETE FROM {$projects_table} WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            $cleanup_stats['old_projects'] = $old_projects ?: 0;
            
            // Clean up completed orders older than 1 year
            $orders_table = $this->database->get_table_name('orders');
            $old_orders = $wpdb->query(
                "DELETE FROM {$orders_table} WHERE status = 'completed' AND completed_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)"
            );
            $cleanup_stats['old_orders'] = $old_orders ?: 0;
            
            // Clean up webhook logs older than 3 months
            $logs_table = $wpdb->prefix . 'carbon_marketplace_webhook_logs';
            $old_logs = $wpdb->query(
                "DELETE FROM {$logs_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)"
            );
            $cleanup_stats['old_logs'] = $old_logs ?: 0;
            
            // Clean up analytics data older than 6 months
            $analytics_table = $wpdb->prefix . 'carbon_marketplace_analytics';
            $old_analytics = $wpdb->query(
                "DELETE FROM {$analytics_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)"
            );
            $cleanup_stats['old_analytics'] = $old_analytics ?: 0;
            
            // Clean up expired cache entries
            $this->cache_manager->cleanup_expired_cache();
            
            $this->log_sync_complete('cleanup', $cleanup_stats);
            
        } catch (\Exception $e) {
            $this->log_sync_error('cleanup', $e->getMessage());
        }
    }
    
    /**
     * Handle manual sync request from admin
     */
    public function handle_manual_sync(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'carbon_marketplace_sync')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $sync_type = sanitize_text_field($_POST['sync_type'] ?? '');
        
        switch ($sync_type) {
            case 'portfolios':
                $this->sync_portfolios();
                break;
            case 'projects':
                $this->sync_projects();
                break;
            case 'pricing':
                $this->sync_pricing();
                break;
            case 'full':
                $this->full_sync();
                break;
            default:
                wp_send_json_error('Invalid sync type');
        }
        
        wp_send_json_success('Synchronization completed');
    }
    
    /**
     * Get existing portfolio from database
     */
    private function get_existing_portfolio(string $portfolio_id, string $vendor): ?array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'carbon_marketplace_portfolios';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE vendor_id = %s AND vendor = %s",
                $portfolio_id,
                $vendor
            ),
            ARRAY_A
        );
    }
    
    /**
     * Insert new portfolio
     */
    private function insert_portfolio($portfolio): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'carbon_marketplace_portfolios';
        
        return $wpdb->insert(
            $table_name,
            [
                'vendor_id' => $portfolio->get_id(),
                'vendor' => $portfolio->get_vendor(),
                'name' => $portfolio->get_name(),
                'description' => $portfolio->get_description(),
                'base_price_per_kg' => $portfolio->get_base_price_per_kg(),
                'is_active' => $portfolio->is_active() ? 1 : 0,
                'data' => json_encode($portfolio->to_array()),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s']
        ) !== false;
    }
    
    /**
     * Update existing portfolio
     */
    private function update_portfolio($portfolio): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'carbon_marketplace_portfolios';
        
        return $wpdb->update(
            $table_name,
            [
                'name' => $portfolio->get_name(),
                'description' => $portfolio->get_description(),
                'base_price_per_kg' => $portfolio->get_base_price_per_kg(),
                'is_active' => $portfolio->is_active() ? 1 : 0,
                'data' => json_encode($portfolio->to_array()),
                'updated_at' => current_time('mysql')
            ],
            [
                'vendor_id' => $portfolio->get_id(),
                'vendor' => $portfolio->get_vendor()
            ],
            ['%s', '%s', '%f', '%d', '%s', '%s'],
            ['%s', '%s']
        ) !== false;
    }
    
    /**
     * Log sync start
     */
    private function log_sync_start(string $sync_type): void {
        update_option("carbon_marketplace_sync_{$sync_type}_status", [
            'status' => 'running',
            'started_at' => current_time('mysql'),
            'message' => "Starting {$sync_type} synchronization"
        ]);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Carbon Marketplace: Starting {$sync_type} sync");
        }
    }
    
    /**
     * Log sync completion
     */
    private function log_sync_complete(string $sync_type, array $stats): void {
        update_option("carbon_marketplace_sync_{$sync_type}_status", [
            'status' => 'completed',
            'started_at' => get_option("carbon_marketplace_sync_{$sync_type}_status")['started_at'] ?? current_time('mysql'),
            'completed_at' => current_time('mysql'),
            'stats' => $stats,
            'message' => "Completed {$sync_type} synchronization"
        ]);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Carbon Marketplace: Completed {$sync_type} sync - " . json_encode($stats));
        }
    }
    
    /**
     * Log sync error
     */
    private function log_sync_error(string $sync_type, string $error): void {
        update_option("carbon_marketplace_sync_{$sync_type}_status", [
            'status' => 'error',
            'started_at' => get_option("carbon_marketplace_sync_{$sync_type}_status")['started_at'] ?? current_time('mysql'),
            'error_at' => current_time('mysql'),
            'error' => $error,
            'message' => "Error in {$sync_type} synchronization: {$error}"
        ]);
        
        error_log("Carbon Marketplace: {$sync_type} sync error - {$error}");
    }
    
    /**
     * Get synchronization status
     */
    public function get_sync_status(): array {
        $sync_types = ['portfolios', 'projects', 'pricing', 'full_sync', 'cleanup'];
        $status = [];
        
        foreach ($sync_types as $type) {
            $status[$type] = get_option("carbon_marketplace_sync_{$type}_status", [
                'status' => 'never_run',
                'message' => 'Never executed'
            ]);
        }
        
        return $status;
    }
    
    /**
     * Get next scheduled sync times
     */
    public function get_next_sync_times(): array {
        return [
            'portfolios' => wp_next_scheduled('carbon_marketplace_sync_portfolios'),
            'projects' => wp_next_scheduled('carbon_marketplace_sync_projects'),
            'pricing' => wp_next_scheduled('carbon_marketplace_sync_pricing'),
            'full_sync' => wp_next_scheduled('carbon_marketplace_full_sync'),
            'cleanup' => wp_next_scheduled('carbon_marketplace_cleanup_data')
        ];
    }
}