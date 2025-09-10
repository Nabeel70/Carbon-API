<?php
/**
 * Database Migration Class
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Core;

/**
 * Database migration class with version tracking
 */
class Migration {
    
    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private $wpdb;
    
    /**
     * Database utility instance
     *
     * @var Database
     */
    private $database;
    
    /**
     * Plugin version option name
     *
     * @var string
     */
    private $version_option = 'carbon_marketplace_db_version';
    
    /**
     * Current database version
     *
     * @var string
     */
    private $current_version = '1.0.0';
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Database();
    }
    
    /**
     * Run migrations
     *
     * @return bool True on success, false on failure
     */
    public function run_migrations(): bool {
        $installed_version = get_option($this->version_option, '0.0.0');
        
        // If already at current version, no migration needed
        if (version_compare($installed_version, $this->current_version, '>=')) {
            return true;
        }
        
        $success = true;
        
        // Run migrations based on version
        if (version_compare($installed_version, '1.0.0', '<')) {
            $success = $success && $this->migrate_to_1_0_0();
        }
        
        // Update version if all migrations successful
        if ($success) {
            update_option($this->version_option, $this->current_version);
        }
        
        return $success;
    }
    
    /**
     * Migrate to version 1.0.0
     *
     * @return bool True on success, false on failure
     */
    private function migrate_to_1_0_0(): bool {
        $success = true;
        
        // Create projects table
        if (!$this->database->table_exists($this->database->get_projects_table())) {
            $success = $success && $this->database->create_projects_table();
        }
        
        // Create orders table
        if (!$this->database->table_exists($this->database->get_orders_table())) {
            $success = $success && $this->database->create_orders_table();
        }
        
        return $success;
    }
    
    /**
     * Rollback migrations
     *
     * @return bool True on success, false on failure
     */
    public function rollback_migrations(): bool {
        $success = true;
        
        // Drop tables in reverse order (orders first due to foreign key)
        $success = $success && $this->database->drop_orders_table();
        $success = $success && $this->database->drop_projects_table();
        
        // Reset version
        if ($success) {
            delete_option($this->version_option);
        }
        
        return $success;
    }
    
    /**
     * Get installed database version
     *
     * @return string Installed version
     */
    public function get_installed_version(): string {
        return get_option($this->version_option, '0.0.0');
    }
    
    /**
     * Get current database version
     *
     * @return string Current version
     */
    public function get_current_version(): string {
        return $this->current_version;
    }
    
    /**
     * Check if migration is needed
     *
     * @return bool True if migration is needed
     */
    public function needs_migration(): bool {
        $installed_version = $this->get_installed_version();
        return version_compare($installed_version, $this->current_version, '<');
    }
    
    /**
     * Verify database integrity
     *
     * @return array Array of verification results
     */
    public function verify_database(): array {
        $results = [
            'projects_table' => $this->database->table_exists($this->database->get_projects_table()),
            'orders_table' => $this->database->table_exists($this->database->get_orders_table()),
            'version_match' => !$this->needs_migration(),
        ];
        
        $results['all_good'] = $results['projects_table'] && $results['orders_table'] && $results['version_match'];
        
        return $results;
    }
    
    /**
     * Get migration status
     *
     * @return array Migration status information
     */
    public function get_migration_status(): array {
        return [
            'installed_version' => $this->get_installed_version(),
            'current_version' => $this->get_current_version(),
            'needs_migration' => $this->needs_migration(),
            'database_integrity' => $this->verify_database(),
        ];
    }
    
    /**
     * Force migration (for development/testing)
     *
     * @return bool True on success, false on failure
     */
    public function force_migration(): bool {
        // Drop existing tables
        $this->rollback_migrations();
        
        // Run fresh migration
        return $this->run_migrations();
    }
    
    /**
     * Create backup of existing data (if tables exist)
     *
     * @return array Backup data
     */
    public function create_backup(): array {
        $backup = [
            'projects' => [],
            'orders' => [],
            'timestamp' => current_time('mysql'),
        ];
        
        // Backup projects if table exists
        if ($this->database->table_exists($this->database->get_projects_table())) {
            $projects_table = $this->database->get_projects_table();
            $backup['projects'] = $this->wpdb->get_results("SELECT * FROM $projects_table", ARRAY_A);
        }
        
        // Backup orders if table exists
        if ($this->database->table_exists($this->database->get_orders_table())) {
            $orders_table = $this->database->get_orders_table();
            $backup['orders'] = $this->wpdb->get_results("SELECT * FROM $orders_table", ARRAY_A);
        }
        
        return $backup;
    }
    
    /**
     * Restore from backup
     *
     * @param array $backup Backup data
     * @return bool True on success, false on failure
     */
    public function restore_from_backup(array $backup): bool {
        if (empty($backup) || !isset($backup['projects']) || !isset($backup['orders'])) {
            return false;
        }
        
        $success = true;
        
        // Restore projects
        foreach ($backup['projects'] as $project) {
            $project_id = $project['id'];
            unset($project['id']); // Remove ID to let auto-increment handle it
            
            $result = $this->database->insert_project($project);
            if (!$result) {
                $success = false;
            }
        }
        
        // Restore orders
        foreach ($backup['orders'] as $order) {
            $order_id = $order['id'];
            unset($order['id']); // Remove ID to let auto-increment handle it
            
            $result = $this->database->insert_order($order);
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }
}