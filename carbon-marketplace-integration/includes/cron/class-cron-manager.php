<?php
/**
 * Cron Manager for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Cron;

use CarbonMarketplace\Sync\DataSynchronizer;
use CarbonMarketplace\Cache\CacheManager;
use CarbonMarketplace\Core\Database;

/**
 * Manages WordPress cron jobs for data synchronization
 */
class CronManager {
    
    private DataSynchronizer $data_synchronizer;
    private CacheManager $cache_manager;
    private Database $database;
    
    public function __construct(
        DataSynchronizer $data_synchronizer,
        CacheManager $cache_manager,
        Database $database
    ) {
        $this->data_synchronizer = $data_synchronizer;
        $this->cache_manager = $cache_manager;
        $this->database = $database;
    }
    
    /**
     * Initialize cron jobs
     */
    public function init(): void {
        // Register custom cron schedules
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedules']);
        
        // Register cron hooks
        add_action('carbon_marketplace_sync_portfolios', [$this, 'sync_portfolios_job']);
        add_action('carbon_marketplace_sync_projects', [$this, 'sync_projects_job']);
        add_action('carbon_marketplace_sync_pricing', [$this, 'sync_pricing_job']);
        add_action('carbon_marketplace_cache_cleanup', [$this, 'cache_cleanup_job']);
        add_action('carbon_marketplace_data_maintenance', [$this, 'data_maintenance_job']);
        
        // Schedule jobs on plugin activation
        add_action('carbon_marketplace_activate', [$this, 'schedule_cron_jobs']);
        add_action('carbon_marketplace_deactivate', [$this, 'unschedule_cron_jobs']);
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_custom_cron_schedules(array $schedules): array {
        $schedules['every_5_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every 5 Minutes', 'carbon-marketplace')
        ];
        
        $schedules['every_15_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 Minutes', 'carbon-marketplace')
        ];
        
        $schedules['every_30_minutes'] = [
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 Minutes', 'carbon-marketplace')
        ];
        
        $schedules['every_2_hours'] = [
            'interval' => 2 * HOUR_IN_SECONDS,
            'display' => __('Every 2 Hours', 'carbon-marketplace')
        ];
        
        $schedules['every_6_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Every 6 Hours', 'carbon-marketplace')
        ];
        
        return $schedules;
    }
    
    /**
     * Schedule all cron jobs
     */
    public function schedule_cron_jobs(): void {
        // Portfolio sync - every 30 minutes
        if (!wp_next_scheduled('carbon_marketplace_sync_portfolios')) {
            wp_schedule_event(time(), 'every_30_minutes', 'carbon_marketplace_sync_portfolios');
        }
        
        // Project sync - every 15 minutes
        if (!wp_next_scheduled('carbon_marketplace_sync_projects')) {
            wp_schedule_event(time(), 'every_15_minutes', 'carbon_marketplace_sync_projects');
        }
        
        // Pricing sync - every 5 minutes
        if (!wp_next_scheduled('carbon_marketplace_sync_pricing')) {
            wp_schedule_event(time(), 'every_5_minutes', 'carbon_marketplace_sync_pricing');
        }
        
        // Cache cleanup - every 2 hours
        if (!wp_next_scheduled('carbon_marketplace_cache_cleanup')) {
            wp_schedule_event(time(), 'every_2_hours', 'carbon_marketplace_cache_cleanup');
        }
        
        // Data maintenance - every 6 hours
        if (!wp_next_scheduled('carbon_marketplace_data_maintenance')) {
            wp_schedule_event(time(), 'every_6_hours', 'carbon_marketplace_data_maintenance');
        }
    }
    
    /**
     * Unschedule all cron jobs
     */
    public function unschedule_cron_jobs(): void {
        $hooks = [
            'carbon_marketplace_sync_portfolios',
            'carbon_marketplace_sync_projects',
            'carbon_marketplace_sync_pricing',
            'carbon_marketplace_cache_cleanup',
            'carbon_marketplace_data_maintenance'
        ];
        
        foreach ($hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }
    
    /**
     * Portfolio synchronization job
     */
    public function sync_portfolios_job(): void {
        try {
            $this->log_cron_start('sync_portfolios');
            
            $result = $this->data_synchronizer->sync_portfolios();
            
            if (is_wp_error($result)) {
                $this->log_cron_error('sync_portfolios', $result->get_error_message());
            } else {
                $this->log_cron_success('sync_portfolios', $result);
            }
            
        } catch (\Exception $e) {
            $this->log_cron_error('sync_portfolios', $e->getMessage());
        }
    }
    
    /**
     * Project synchronization job
     */
    public function sync_projects_job(): void {
        try {
            $this->log_cron_start('sync_projects');
            
            $result = $this->data_synchronizer->sync_projects();
            
            if (is_wp_error($result)) {
                $this->log_cron_error('sync_projects', $result->get_error_message());
            } else {
                $this->log_cron_success('sync_projects', $result);
            }
            
        } catch (\Exception $e) {
            $this->log_cron_error('sync_projects', $e->getMessage());
        }
    }
    
    /**
     * Pricing synchronization job
     */
    public function sync_pricing_job(): void {
        try {
            $this->log_cron_start('sync_pricing');
            
            $result = $this->data_synchronizer->sync_pricing();
            
            if (is_wp_error($result)) {
                $this->log_cron_error('sync_pricing', $result->get_error_message());
            } else {
                $this->log_cron_success('sync_pricing', $result);
            }
            
        } catch (\Exception $e) {
            $this->log_cron_error('sync_pricing', $e->getMessage());
        }
    }
    
    /**
     * Cache cleanup job
     */
    public function cache_cleanup_job(): void {
        try {
            $this->log_cron_start('cache_cleanup');
            
            $cleaned = $this->cache_manager->cleanup_expired_cache();
            
            $this->log_cron_success('cache_cleanup', ['cleaned_entries' => $cleaned]);
            
        } catch (\Exception $e) {
            $this->log_cron_error('cache_cleanup', $e->getMessage());
        }
    }
    
    /**
     * Data maintenance job
     */
    public function data_maintenance_job(): void {
        try {
            $this->log_cron_start('data_maintenance');
            
            // Clean old analytics data (older than 90 days)
            $this->cleanup_old_analytics_data(90);
            
            // Clean old webhook logs (older than 30 days)
            $this->cleanup_old_webhook_logs(30);
            
            // Optimize database tables
            $this->optimize_database_tables();
            
            $this->log_cron_success('data_maintenance', ['status' => 'completed']);
            
        } catch (\Exception $e) {
            $this->log_cron_error('data_maintenance', $e->getMessage());
        }
    }
    
    /**
     * Clean old analytics data
     */
    private function cleanup_old_analytics_data(int $days): int {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'carbon_marketplace_analytics';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < %s",
                $cutoff_date
            )
        );
        
        return $deleted ?: 0;
    }
    
    /**
     * Clean old webhook logs
     */
    private function cleanup_old_webhook_logs(int $days): int {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'carbon_marketplace_webhook_logs';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < %s",
                $cutoff_date
            )
        );
        
        return $deleted ?: 0;
    }
    
    /**
     * Optimize database tables
     */
    private function optimize_database_tables(): void {
        global $wpdb;
        
        $tables = [
            $this->database->get_table_name('projects'),
            $this->database->get_table_name('orders'),
            $wpdb->prefix . 'carbon_marketplace_analytics',
            $wpdb->prefix . 'carbon_marketplace_webhook_logs'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table}");
        }
    }
    
    /**
     * Get cron job status
     */
    public function get_cron_status(): array {
        $jobs = [
            'sync_portfolios' => 'carbon_marketplace_sync_portfolios',
            'sync_projects' => 'carbon_marketplace_sync_projects',
            'sync_pricing' => 'carbon_marketplace_sync_pricing',
            'cache_cleanup' => 'carbon_marketplace_cache_cleanup',
            'data_maintenance' => 'carbon_marketplace_data_maintenance'
        ];
        
        $status = [];
        
        foreach ($jobs as $job_name => $hook) {
            $next_run = wp_next_scheduled($hook);
            $status[$job_name] = [
                'scheduled' => $next_run !== false,
                'next_run' => $next_run ? date('Y-m-d H:i:s', $next_run) : null,
                'next_run_relative' => $next_run ? human_time_diff($next_run) : null
            ];
        }
        
        return $status;
    }
    
    /**
     * Manually trigger a cron job
     */
    public function trigger_job(string $job_name): bool {
        $job_methods = [
            'sync_portfolios' => 'sync_portfolios_job',
            'sync_projects' => 'sync_projects_job',
            'sync_pricing' => 'sync_pricing_job',
            'cache_cleanup' => 'cache_cleanup_job',
            'data_maintenance' => 'data_maintenance_job'
        ];
        
        if (!isset($job_methods[$job_name])) {
            return false;
        }
        
        $method = $job_methods[$job_name];
        
        try {
            $this->$method();
            return true;
        } catch (\Exception $e) {
            error_log("Manual cron job trigger failed for {$job_name}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log cron job start
     */
    private function log_cron_start(string $job_name): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Carbon Marketplace cron job started: {$job_name}");
        }
        
        update_option("carbon_marketplace_cron_{$job_name}_last_start", current_time('mysql'));
    }
    
    /**
     * Log cron job success
     */
    private function log_cron_success(string $job_name, array $result = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Carbon Marketplace cron job completed: {$job_name} - " . json_encode($result));
        }
        
        update_option("carbon_marketplace_cron_{$job_name}_last_success", current_time('mysql'));
        update_option("carbon_marketplace_cron_{$job_name}_last_result", $result);
    }
    
    /**
     * Log cron job error
     */
    private function log_cron_error(string $job_name, string $error): void {
        error_log("Carbon Marketplace cron job failed: {$job_name} - {$error}");
        
        update_option("carbon_marketplace_cron_{$job_name}_last_error", current_time('mysql'));
        update_option("carbon_marketplace_cron_{$job_name}_last_error_message", $error);
    }
    
    /**
     * Get cron job logs
     */
    public function get_cron_logs(): array {
        $jobs = ['sync_portfolios', 'sync_projects', 'sync_pricing', 'cache_cleanup', 'data_maintenance'];
        $logs = [];
        
        foreach ($jobs as $job) {
            $logs[$job] = [
                'last_start' => get_option("carbon_marketplace_cron_{$job}_last_start"),
                'last_success' => get_option("carbon_marketplace_cron_{$job}_last_success"),
                'last_error' => get_option("carbon_marketplace_cron_{$job}_last_error"),
                'last_error_message' => get_option("carbon_marketplace_cron_{$job}_last_error_message"),
                'last_result' => get_option("carbon_marketplace_cron_{$job}_last_result", [])
            ];
        }
        
        return $logs;
    }
}