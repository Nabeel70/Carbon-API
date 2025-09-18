<?php
/**
 * Simple test to check CNaught API integration
 */

// Define missing constants
define('CARBON_MARKETPLACE_VERSION', '1.0.0');

// Add the plugin path to include path
$plugin_path = '/workspaces/Carbon-API/carbon-marketplace-integration';
set_include_path(get_include_path() . PATH_SEPARATOR . $plugin_path);

// Mock WordPress functions for testing
function wp_remote_get($url, $args = array()) {
    // For testing, return a mock response
    return array(
        'response' => array('code' => 200, 'message' => 'OK'),
        'body' => json_encode(array(
            'data' => array(
                array(
                    'id' => 'port_test_123',
                    'name' => 'Test Portfolio',
                    'description' => 'A test portfolio for development',
                    'base_price_per_kg' => 15.50,
                    'is_active' => true,
                    'projects' => array(
                        array(
                            'id' => 'proj_test_456',
                            'name' => 'Forest Conservation Project',
                            'location' => 'Brazil',
                            'project_type' => 'Forestry',
                            'price_per_kg' => 18.00
                        )
                    )
                )
            )
        ))
    );
}

function wp_remote_request($url, $args = array()) {
    return wp_remote_get($url, $args);
}

function wp_remote_retrieve_response_code($response) {
    return $response['response']['code'];
}

function wp_remote_retrieve_body($response) {
    return $response['body'];
}

function get_option($option_name, $default = false) {
    return $default;
}

if (!function_exists('error_log')) {
    function error_log($message) {
        echo "[LOG] $message\n";
    }
}

function is_wp_error($thing) {
    return $thing instanceof WP_Error;
}

class WP_Error {
    private $errors = array();
    private $error_data = array();
    
    public function __construct($code = '', $message = '', $data = '') {
        if (!empty($code)) {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }
    }
    
    public function get_error_code() {
        $codes = array_keys($this->errors);
        return empty($codes) ? '' : $codes[0];
    }
    
    public function get_error_message() {
        $code = $this->get_error_code();
        return empty($this->errors[$code]) ? '' : $this->errors[$code][0];
    }
}

// Load the classes
require_once $plugin_path . '/includes/models/interface-model.php';
require_once $plugin_path . '/includes/models/abstract-base-model.php';
require_once $plugin_path . '/includes/models/class-portfolio.php';
require_once $plugin_path . '/includes/models/class-project.php';
require_once $plugin_path . '/includes/api/class-api-exception.php';
require_once $plugin_path . '/includes/api/class-base-api-client.php';
require_once $plugin_path . '/includes/api/class-cnaught-client.php';

use CarbonMarketplace\Api\CNaughtClient;

echo "Testing CNaught API Client...\n\n";

// Test 1: Initialize client
echo "1. Initializing CNaught client...\n";
$client = new CNaughtClient(array(
    'credentials' => array(
        'api_key' => 'test_api_key_for_development'
    )
));
echo "   ✓ Client initialized successfully\n\n";

// Test 2: Get portfolios
echo "2. Testing get_portfolios() method...\n";
try {
    $portfolios = $client->get_portfolios();
    
    if (is_wp_error($portfolios)) {
        echo "   ✗ Error: " . $portfolios->get_error_message() . "\n";
    } else {
        echo "   ✓ Successfully fetched " . count($portfolios) . " portfolio(s)\n";
        
        if (!empty($portfolios)) {
            $portfolio = $portfolios[0];
            echo "   ✓ First portfolio: " . $portfolio->name . " (ID: " . $portfolio->id . ")\n";
            echo "   ✓ Base price: $" . $portfolio->base_price_per_kg . "/kg\n";
            echo "   ✓ Projects: " . count($portfolio->projects) . "\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n";