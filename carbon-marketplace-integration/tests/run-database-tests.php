<?php
/**
 * Database and Migration Test Runner
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

// Include test files
require_once dirname(__FILE__) . '/core/test-database.php';
require_once dirname(__FILE__) . '/core/test-migration.php';

// Mock WordPress constants if not available
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../../');
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

echo "=== Carbon Marketplace Database and Migration Tests ===\n\n";

// Run Database Tests
$database_test = new DatabaseTest();
$database_test->run_all_tests();

echo "\n" . str_repeat("=", 60) . "\n\n";

// Run Migration Tests
$migration_test = new MigrationTest();
$migration_test->run_all_tests();

echo "\n" . str_repeat("=", 60) . "\n";
echo "All tests completed!\n";