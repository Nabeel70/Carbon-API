<?php
/**
 * Webhook Handler for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Webhooks;

use CarbonMarketplace\Checkout\CheckoutManager;
use CarbonMarketplace\Core\Database;
use CarbonMarketplace\Models\Order;
use WP_Error;

/**
 * Handles incoming webhooks from vendor APIs
 */
class WebhookHandler {
    
    private CheckoutManager $checkout_manager;
    private Database $database;
    
    public function __construct(CheckoutManager $checkout_manager, Database $database) {
        $this->checkout_manager = $checkout_manager;
        $this->database = $database;
    }
    
    /**
     * Initialize webhook endpoints
     */
    public function init(): void {
        add_action('rest_api_init', [$this, 'register_webhook_endpoints']);
    }
    
    /**
     * Register REST API endpoints for webhooks
     */
    public function register_webhook_endpoints(): void {
        register_rest_route('carbon-marketplace/v1', '/webhooks/cnaught', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_cnaught_webhook'],
            'permission_callback' => [$this, 'verify_webhook_signature']
        ]);
        
        register_rest_route('carbon-marketplace/v1', '/webhooks/toucan', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_toucan_webhook'],
            'permission_callback' => [$this, 'verify_webhook_signature']
        ]);
        
        register_rest_route('carbon-marketplace/v1', '/webhooks/generic', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_generic_webhook'],
            'permission_callback' => [$this, 'verify_webhook_signature']
        ]);
    }
    
    /**
     * Handle CNaught webhook
     */
    public function handle_cnaught_webhook(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $payload = $request->get_json_params();
            
            if (empty($payload)) {
                return new \WP_REST_Response(['error' => 'Empty payload'], 400);
            }
            
            $this->log_webhook('cnaught', $payload);
            
            switch ($payload['event_type'] ?? '') {
                case 'checkout.session.completed':
                    return $this->handle_checkout_completion($payload);
                    
                case 'order.fulfilled':
                    return $this->handle_order_fulfillment($payload);
                    
                case 'order.retired':
                    return $this->handle_order_retirement($payload);
                    
                default:
                    $this->log_webhook_error('cnaught', 'Unknown event type', $payload);
                    return new \WP_REST_Response(['error' => 'Unknown event type'], 400);
            }
            
        } catch (\Exception $e) {
            $this->log_webhook_error('cnaught', $e->getMessage(), $request->get_json_params());
            return new \WP_REST_Response(['error' => 'Webhook processing failed'], 500);
        }
    }
    
    /**
     * Handle Toucan webhook
     */
    public function handle_toucan_webhook(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $payload = $request->get_json_params();
            
            if (empty($payload)) {
                return new \WP_REST_Response(['error' => 'Empty payload'], 400);
            }
            
            $this->log_webhook('toucan', $payload);
            
            // Toucan webhooks might be different format
            switch ($payload['type'] ?? '') {
                case 'retirement.completed':
                    return $this->handle_toucan_retirement($payload);
                    
                case 'token.transferred':
                    return $this->handle_token_transfer($payload);
                    
                default:
                    $this->log_webhook_error('toucan', 'Unknown event type', $payload);
                    return new \WP_REST_Response(['error' => 'Unknown event type'], 400);
            }
            
        } catch (\Exception $e) {
            $this->log_webhook_error('toucan', $e->getMessage(), $request->get_json_params());
            return new \WP_REST_Response(['error' => 'Webhook processing failed'], 500);
        }
    }
    
    /**
     * Handle generic webhook for future vendors
     */
    public function handle_generic_webhook(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $payload = $request->get_json_params();
            $vendor = $request->get_header('X-Vendor-Name') ?? 'unknown';
            
            $this->log_webhook($vendor, $payload);
            
            // Allow plugins to handle custom webhooks
            $result = apply_filters('carbon_marketplace_handle_webhook', null, $vendor, $payload);
            
            if ($result !== null) {
                return new \WP_REST_Response($result);
            }
            
            return new \WP_REST_Response(['message' => 'Webhook received'], 200);
            
        } catch (\Exception $e) {
            $this->log_webhook_error('generic', $e->getMessage(), $request->get_json_params());
            return new \WP_REST_Response(['error' => 'Webhook processing failed'], 500);
        }
    }
    
    /**
     * Handle checkout completion webhook
     */
    private function handle_checkout_completion(array $payload): \WP_REST_Response {
        $session_id = $payload['data']['session_id'] ?? '';
        
        if (empty($session_id)) {
            return new \WP_REST_Response(['error' => 'Missing session ID'], 400);
        }
        
        $completion_data = [
            'order_id' => $payload['data']['order_id'] ?? '',
            'project_allocations' => $payload['data']['project_allocations'] ?? [],
            'commission_amount' => $payload['data']['commission_amount'] ?? 0
        ];
        
        $success = $this->checkout_manager->handle_checkout_completion($session_id, $completion_data);
        
        if (!$success) {
            return new \WP_REST_Response(['error' => 'Failed to process checkout completion'], 500);
        }
        
        return new \WP_REST_Response(['message' => 'Checkout completion processed'], 200);
    }
    
    /**
     * Handle order fulfillment webhook
     */
    private function handle_order_fulfillment(array $payload): \WP_REST_Response {
        $order_id = $payload['data']['order_id'] ?? '';
        
        if (empty($order_id)) {
            return new \WP_REST_Response(['error' => 'Missing order ID'], 400);
        }
        
        $order = $this->get_order_by_vendor_id($order_id);
        if (!$order) {
            return new \WP_REST_Response(['error' => 'Order not found'], 404);
        }
        
        // Update order status and project allocations
        $order->set_status('fulfilled');
        $order->set_project_allocations($payload['data']['project_allocations'] ?? []);
        
        $this->update_order($order);
        
        // Trigger fulfillment hooks
        do_action('carbon_marketplace_order_fulfilled', $order, $payload);
        
        return new \WP_REST_Response(['message' => 'Order fulfillment processed'], 200);
    }
    
    /**
     * Handle order retirement webhook
     */
    private function handle_order_retirement(array $payload): \WP_REST_Response {
        $order_id = $payload['data']['order_id'] ?? '';
        
        if (empty($order_id)) {
            return new \WP_REST_Response(['error' => 'Missing order ID'], 400);
        }
        
        $order = $this->get_order_by_vendor_id($order_id);
        if (!$order) {
            return new \WP_REST_Response(['error' => 'Order not found'], 404);
        }
        
        // Update order with retirement data
        $order->mark_completed();
        $order->set_retirement_data($payload['data']['retirement_data'] ?? []);
        
        $this->update_order($order);
        
        // Trigger retirement hooks
        do_action('carbon_marketplace_order_retired', $order, $payload);
        
        return new \WP_REST_Response(['message' => 'Order retirement processed'], 200);
    }
    
    /**
     * Handle Toucan retirement webhook
     */
    private function handle_toucan_retirement(array $payload): \WP_REST_Response {
        // Toucan-specific retirement handling
        $transaction_hash = $payload['data']['transaction_hash'] ?? '';
        $retirement_data = $payload['data'] ?? [];
        
        // Find order by transaction hash or other identifier
        $order = $this->find_order_by_retirement_data($retirement_data);
        
        if ($order) {
            $order->mark_completed();
            $order->set_retirement_data($retirement_data);
            $this->update_order($order);
            
            do_action('carbon_marketplace_toucan_retirement', $order, $payload);
        }
        
        return new \WP_REST_Response(['message' => 'Toucan retirement processed'], 200);
    }
    
    /**
     * Handle token transfer webhook
     */
    private function handle_token_transfer(array $payload): \WP_REST_Response {
        // Handle token transfer events from Toucan
        $transfer_data = $payload['data'] ?? [];
        
        // Log the transfer for tracking
        $this->log_token_transfer($transfer_data);
        
        // Trigger transfer hooks
        do_action('carbon_marketplace_token_transfer', $transfer_data, $payload);
        
        return new \WP_REST_Response(['message' => 'Token transfer processed'], 200);
    }
    
    /**
     * Verify webhook signature
     */
    public function verify_webhook_signature(\WP_REST_Request $request): bool {
        $signature = $request->get_header('X-Signature') ?? '';
        $vendor = $request->get_header('X-Vendor-Name') ?? '';
        
        if (empty($signature)) {
            return true; // Allow unsigned webhooks for development
        }
        
        $body = $request->get_body();
        $secret = $this->get_webhook_secret($vendor);
        
        if (empty($secret)) {
            return true; // No secret configured
        }
        
        $expected_signature = hash_hmac('sha256', $body, $secret);
        
        return hash_equals($signature, $expected_signature);
    }
    
    /**
     * Get webhook secret for vendor
     */
    private function get_webhook_secret(string $vendor): string {
        $secrets = get_option('carbon_marketplace_webhook_secrets', []);
        return $secrets[$vendor] ?? '';
    }
    
    /**
     * Get order by vendor order ID
     */
    private function get_order_by_vendor_id(string $vendor_order_id): ?Order {
        global $wpdb;
        
        $table_name = $this->database->get_table_name('orders');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE vendor_order_id = %s", $vendor_order_id)
        );
        
        if (!$row) {
            return null;
        }
        
        return Order::from_array((array) $row);
    }
    
    /**
     * Find order by retirement data
     */
    private function find_order_by_retirement_data(array $retirement_data): ?Order {
        // Implementation depends on how retirement data links to orders
        // This is a placeholder for Toucan-specific logic
        return null;
    }
    
    /**
     * Update order in database
     */
    private function update_order(Order $order): bool {
        return $this->database->update_order($order->get_id(), $order->to_array());
    }
    
    /**
     * Log webhook for debugging
     */
    private function log_webhook(string $vendor, array $payload): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Webhook received from {$vendor}: " . json_encode($payload));
        }
        
        // Store webhook log in database for audit
        $this->store_webhook_log($vendor, 'received', $payload);
    }
    
    /**
     * Log webhook error
     */
    private function log_webhook_error(string $vendor, string $error, array $payload): void {
        error_log("Webhook error from {$vendor}: {$error}");
        $this->store_webhook_log($vendor, 'error', $payload, $error);
    }
    
    /**
     * Log token transfer
     */
    private function log_token_transfer(array $transfer_data): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Token transfer: " . json_encode($transfer_data));
        }
    }
    
    /**
     * Store webhook log in database
     */
    private function store_webhook_log(string $vendor, string $status, array $payload, string $error = ''): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'carbon_marketplace_webhook_logs';
        
        $wpdb->insert(
            $table_name,
            [
                'vendor' => $vendor,
                'status' => $status,
                'payload' => json_encode($payload),
                'error_message' => $error,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Get webhook statistics
     */
    public function get_webhook_statistics(): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'carbon_marketplace_webhook_logs';
        
        $stats = [];
        
        // Total webhooks received
        $stats['total_webhooks'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Successful webhooks
        $stats['successful_webhooks'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'received'"
        );
        
        // Failed webhooks
        $stats['failed_webhooks'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'error'"
        );
        
        // Success rate
        $stats['success_rate'] = $stats['total_webhooks'] > 0 
            ? ($stats['successful_webhooks'] / $stats['total_webhooks']) * 100 
            : 0;
        
        // Webhooks by vendor
        $vendor_stats = $wpdb->get_results(
            "SELECT vendor, COUNT(*) as count FROM {$table_name} GROUP BY vendor"
        );
        
        $stats['by_vendor'] = [];
        foreach ($vendor_stats as $vendor_stat) {
            $stats['by_vendor'][$vendor_stat->vendor] = $vendor_stat->count;
        }
        
        return $stats;
    }
}