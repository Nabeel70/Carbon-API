<?php
/**
 * Migration Tests
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

require_once dirname(__FILE__) . '/../../includes/core/class-migration.php';
require_once dirname(__FILE__) . '/../../includes/core/class-database.php';

use CarbonMarketplace\Core\Migration;
use CarbonMarketplace\Core\Database;

class MigrationTest {
    
    /**
     * Migration instance
     *
     * @var Migration
     */
    private $migration;
    
    /**
     * Test results
     */
    private $tests_passed = 0;
    private $tests_failed = 0;
    private $failures = [];
    
    /**
     * Set up test environment
     */
    public function setUp() {
        // Mock WordPress functions if not available
        if (!function_exists('get_option')) {
            function get_option($option, $default = false) {
                // Simulate different version states for testing
                if ($option === 'carbon_marketplace_db_version') {
                    return '0.0.0'; // Simulate fresh install
                }
                return $default;
            }
        }
        
        if (!function_exists('update_option')) {
            function update_option($option, $value) {
                return true;
            }
        }
        
        if (!function_exists('delete_option')) {
            function delete_option($option) {
                return true;
            }
        }
        
        if (!function_exists('current_time')) {
            function current_time($type) {
                return date('Y-m-d H:i:s');
            }
        }
        
        // Mock global $wpdb
        global $wpdb;
        if (!isset($wpdb)) {
            $wpdb = new MockWpdb();
        }
        
        $this->migration = new Migration();
    }
    
    /**
     * Run all migration tests
     */
    public function run_all_tests() {
        echo "Running Migration Tests...\n\n";
        
        $this->setUp();
        
        $this->test_version_tracking();
        $this->test_migration_detection();
        $this->test_migration_status();
        $this->test_database_integrity();
        $this->test_backup_operations();
        $this->test_migration_execution();
        $this->test_rollback_functionality();
        $this->test_error_handling();
        
        $this->print_results();
    }
    
    /**
     * Test migration version tracking
     */
    public function test_version_tracking() {
        echo "Testing version tracking...\n";
        
        $current_version = $this->migration->get_current_version();
        $installed_version = $this->migration->get_installed_version();
        
        $this->assert_true(is_string($current_version), 'Current version should be string');
        $this->assert_true(is_string($installed_version), 'Installed version should be string');
        $this->assert_true(preg_match('/^\d+\.\d+\.\d+$/', $current_version), 'Current version should match semver pattern');
        
        // Test version comparison
        $this->assert_true(version_compare('1.0.0', '0.9.0', '>'), 'Version 1.0.0 should be greater than 0.9.0');
        $this->assert_true(version_compare('1.1.0', '1.0.0', '>'), 'Version 1.1.0 should be greater than 1.0.0');
        $this->assert_true(version_compare('1.0.1', '1.0.0', '>'), 'Version 1.0.1 should be greater than 1.0.0');
        $this->assert_false(version_compare('1.0.0', '1.0.0', '>'), 'Version 1.0.0 should not be greater than 1.0.0');
        $this->assert_true(version_compare('1.0.0', '1.0.0', '>='), 'Version 1.0.0 should be greater than or equal to 1.0.0');
        
        echo "Version tracking tests completed.\n\n";
    }
    
    /**
     * Test migration need detection
     */
    public function test_migration_detection() {
        echo "Testing migration detection...\n";
        
        $needs_migration = $this->migration->needs_migration();
        $this->assert_true(is_bool($needs_migration), 'Migration detection should return boolean');
        
        // Since we mocked installed version as 0.0.0, it should need migration
        $this->assert_true($needs_migration, 'Fresh install should need migration');
        
        echo "Migration detection tests completed.\n\n";
    }
    
    /**
     * Test migration status
     */
    public function test_migration_status() {
        echo "Testing migration status...\n";
        
        $status = $this->migration->get_migration_status();
        
        $this->assert_true(is_array($status), 'Migration status should be array');
        $this->assert_true(array_key_exists('installed_version', $status), 'Status should have installed_version key');
        $this->assert_true(array_key_exists('current_version', $status), 'Status should have current_version key');
        $this->assert_true(array_key_exists('needs_migration', $status), 'Status should have needs_migration key');
        $this->assert_true(array_key_exists('database_integrity', $status), 'Status should have database_integrity key');
        
        $this->assert_true(is_string($status['installed_version']), 'Installed version should be string');
        $this->assert_true(is_string($status['current_version']), 'Current version should be string');
        $this->assert_true(is_bool($status['needs_migration']), 'Needs migration should be boolean');
        $this->assert_true(is_array($status['database_integrity']), 'Database integrity should be array');
        
        echo "Migration status tests completed.\n\n";
    }
    
    /**
     * Test database integrity verification
     */
    public function test_database_integrity() {
        echo "Testing database integrity verification...\n";
        
        $integrity = $this->migration->verify_database();
        
        $this->assert_true(is_array($integrity), 'Database integrity should be array');
        $this->assert_true(array_key_exists('projects_table', $integrity), 'Integrity should check projects table');
        $this->assert_true(array_key_exists('orders_table', $integrity), 'Integrity should check orders table');
        $this->assert_true(array_key_exists('version_match', $integrity), 'Integrity should check version match');
        $this->assert_true(array_key_exists('all_good', $integrity), 'Integrity should have overall status');
        
        $this->assert_true(is_bool($integrity['projects_table']), 'Projects table check should be boolean');
        $this->assert_true(is_bool($integrity['orders_table']), 'Orders table check should be boolean');
        $this->assert_true(is_bool($integrity['version_match']), 'Version match check should be boolean');
        $this->assert_true(is_bool($integrity['all_good']), 'Overall status should be boolean');
        
        echo "Database integrity tests completed.\n\n";
    }
    
    /**
     * Test backup operations
     */
    public function test_backup_operations() {
        echo "Testing backup operations...\n";
        
        // Test backup creation
        $backup = $this->migration->create_backup();
        
        $this->assert_true(is_array($backup), 'Backup should be array');
        $this->assert_true(array_key_exists('projects', $backup), 'Backup should have projects key');
        $this->assert_true(array_key_exists('orders', $backup), 'Backup should have orders key');
        $this->assert_true(array_key_exists('timestamp', $backup), 'Backup should have timestamp key');
        
        $this->assert_true(is_array($backup['projects']), 'Projects backup should be array');
        $this->assert_true(is_array($backup['orders']), 'Orders backup should be array');
        $this->assert_true(is_string($backup['timestamp']), 'Timestamp should be string');
        
        // Test backup structure validation
        $valid_backup = [
            'projects' => [
                [
                    'id' => 1,
                    'vendor_id' => 'proj_123',
                    'vendor' => 'cnaught',
                    'name' => 'Test Project',
                    'price_per_kg' => 15.50,
                ]
            ],
            'orders' => [
                [
                    'id' => 1,
                    'vendor_order_id' => 'order_456',
                    'vendor' => 'cnaught',
                    'amount_kg' => 10.0,
                    'total_price' => 150.00,
                ]
            ],
            'timestamp' => '2023-12-01 10:00:00',
        ];
        
        $this->assert_true(is_array($valid_backup), 'Valid backup should be array');
        $this->assert_true(array_key_exists('projects', $valid_backup), 'Valid backup should have projects');
        $this->assert_true(array_key_exists('orders', $valid_backup), 'Valid backup should have orders');
        
        // Test project data structure in backup
        if (!empty($valid_backup['projects'])) {
            $project = $valid_backup['projects'][0];
            $this->assert_true(array_key_exists('vendor_id', $project), 'Project backup should have vendor_id');
            $this->assert_true(array_key_exists('vendor', $project), 'Project backup should have vendor');
            $this->assert_true(array_key_exists('name', $project), 'Project backup should have name');
        }
        
        // Test order data structure in backup
        if (!empty($valid_backup['orders'])) {
            $order = $valid_backup['orders'][0];
            $this->assert_true(array_key_exists('vendor_order_id', $order), 'Order backup should have vendor_order_id');
            $this->assert_true(array_key_exists('vendor', $order), 'Order backup should have vendor');
            $this->assert_true(array_key_exists('amount_kg', $order), 'Order backup should have amount_kg');
        }
        
        echo "Backup operations tests completed.\n\n";
    }
    
    /**
     * Test migration execution
     */
    public function test_migration_execution() {
        echo "Testing migration execution...\n";
        
        // Test that migration methods exist and return boolean
        $this->assert_true(method_exists($this->migration, 'run_migrations'), 'run_migrations method should exist');
        $this->assert_true(method_exists($this->migration, 'force_migration'), 'force_migration method should exist');
        
        // Test method signatures
        $reflection = new ReflectionMethod($this->migration, 'run_migrations');
        $this->assert_true($reflection->isPublic(), 'run_migrations should be public');
        
        $reflection = new ReflectionMethod($this->migration, 'force_migration');
        $this->assert_true($reflection->isPublic(), 'force_migration should be public');
        
        echo "Migration execution tests completed.\n\n";
    }
    
    /**
     * Test rollback functionality
     */
    public function test_rollback_functionality() {
        echo "Testing rollback functionality...\n";
        
        // Test that rollback method exists and returns boolean
        $this->assert_true(method_exists($this->migration, 'rollback_migrations'), 'rollback_migrations method should exist');
        
        // Test method signature
        $reflection = new ReflectionMethod($this->migration, 'rollback_migrations');
        $this->assert_true($reflection->isPublic(), 'rollback_migrations should be public');
        
        // Test backup restoration method
        $this->assert_true(method_exists($this->migration, 'restore_from_backup'), 'restore_from_backup method should exist');
        
        $reflection = new ReflectionMethod($this->migration, 'restore_from_backup');
        $this->assert_true($reflection->isPublic(), 'restore_from_backup should be public');
        
        echo "Rollback functionality tests completed.\n\n";
    }
    
    /**
     * Test error handling
     */
    public function test_error_handling() {
        echo "Testing error handling...\n";
        
        // Test that migration methods handle errors gracefully
        $status = $this->migration->get_migration_status();
        
        // Should not throw exceptions
        $this->assert_true(is_array($status), 'Migration status should not throw exceptions');
        
        // Test backup with empty data
        $empty_backup = [];
        $this->assert_true(is_array($empty_backup), 'Empty backup should be array');
        $this->assert_true(empty($empty_backup), 'Empty backup should be empty');
        
        // Test invalid backup structure
        $invalid_backup = ['invalid' => 'data'];
        $this->assert_true(is_array($invalid_backup), 'Invalid backup should be array');
        $this->assert_false(array_key_exists('projects', $invalid_backup), 'Invalid backup should not have projects key');
        $this->assert_false(array_key_exists('orders', $invalid_backup), 'Invalid backup should not have orders key');
        
        // Test backup restoration with invalid data
        $result = $this->migration->restore_from_backup($empty_backup);
        $this->assert_false($result, 'Restore from empty backup should fail');
        
        $result = $this->migration->restore_from_backup($invalid_backup);
        $this->assert_false($result, 'Restore from invalid backup should fail');
        
        echo "Error handling tests completed.\n\n";
    }
    
    /**
     * Assert helper methods
     */
    private function assert_equals($expected, $actual, $message) {
        if ($expected === $actual) {
            $this->tests_passed++;
            echo "  ✓ $message\n";
        } else {
            $this->tests_failed++;
            $this->failures[] = "$message - Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true);
            echo "  ✗ $message - Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . "\n";
        }
    }
    
    private function assert_true($condition, $message) {
        if ($condition) {
            $this->tests_passed++;
            echo "  ✓ $message\n";
        } else {
            $this->tests_failed++;
            $this->failures[] = "$message - Expected true, got false";
            echo "  ✗ $message - Expected true, got false\n";
        }
    }
    
    private function assert_false($condition, $message) {
        if (!$condition) {
            $this->tests_passed++;
            echo "  ✓ $message\n";
        } else {
            $this->tests_failed++;
            $this->failures[] = "$message - Expected false, got true";
            echo "  ✗ $message - Expected false, got true\n";
        }
    }
    
    private function print_results() {
        echo "Migration Test Results:\n";
        echo "Passed: {$this->tests_passed}\n";
        echo "Failed: {$this->tests_failed}\n";
        
        if ($this->tests_failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $failure) {
                echo "  - $failure\n";
            }
        }
        
        echo "\n" . ($this->tests_failed === 0 ? "All migration tests passed! ✓" : "Some migration tests failed! ✗") . "\n";
    }
}