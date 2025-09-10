<?php
/**
 * Simple syntax validation for API Manager
 */

// Include the API Manager file to check for syntax errors
try {
    include_once __DIR__ . '/includes/api/class-api-manager.php';
    echo "✓ API Manager syntax is valid\n";
} catch (ParseError $e) {
    echo "✗ Syntax error in API Manager: " . $e->getMessage() . "\n";
    exit(1);
} catch (Error $e) {
    echo "✓ API Manager syntax is valid (runtime error expected without dependencies)\n";
} catch (Exception $e) {
    echo "✓ API Manager syntax is valid (exception expected without dependencies)\n";
}

echo "Validation complete.\n";