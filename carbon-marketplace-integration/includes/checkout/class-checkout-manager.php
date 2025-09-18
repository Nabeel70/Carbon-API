<?php
/**
 * Checkout Manager for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Checkout;

use CarbonMarketplace\API\ApiManager;
use CarbonMarketplace\Models\CheckoutSession;
use CarbonMarketplace\Models\Order;
use CarbonMarketplace\Core\Database;
use WP_Error;

/**
 * Manages checkout sessions and purchase flow
 */
class CheckoutManager {
    
    private ApiManager $api_manager;
    private Database $database;
    
    public function __construct(ApiManager $api_manager, Database $database) {
        $this->api_manager = $api_manager;
        $this->database = $database;
    }
    
    /**
     * Create checkout session with vendor
     */
    public function create_checkout_session(array $request_data): CheckoutSession|WP_Error {
        try {
            // Validate request data
            $validation = $this->validate_checkout_request($request_data);
            if (is_wp_error($validation)) {
                return $validation;
            }
            
            // Get quote first
            $quote = $this->api_manager->get_quote(
                $request_data['amount_kg'],
                $request_data['portfolio_id'] ?? null
            );
            
            if (is_wp_error($quote)) {
                return new WP_Error('quote_failed', 'Failed to get pricing quote');
            }
            
            // Create checkout session with vendor
            $checkout_request = [
                'amount_kg' => $request_data['amount_kg'],
                'portfolio_id' => $request_data['portfolio_id'] ?? null,
                'success_url' => $request_data['success_url'],
                'cancel_url' => $request_data['cancel_url'],
                'webhook_url' => $request_data['webhook_url'] ?? null,
                'metadata' => $request_data['metadata'] ?? []
            ];
            
            $session = $this->api_manager->create_checkout_session($checkout_request);
            
            if (is_wp_error($session)) {
                return $session;
            }
            
            // Store session in database
            $this->store_checkout_session($session);
            
            return $session;
            
        } catch (\Exception $e) {
            error_log('Checkout session creation failed: ' . $e->getMessage());
            return new WP_Error('checkout_error', 'Failed to create checkout session');
        }
    }
    
    /**
     * Handle checkout completion
     */
    public function handle_checkout_completion(string $session_id, array $completion_data): bool {
        try {
            // Get session from database
            $session = $this->get_checkout_session($session_id);
            if (!$session) {
                error_log("Checkout session not found: {$session_id}");
                return false;
            }
            
            // Mark session as complete
            $session->mark_complete();
            $this->update_checkout_session($session);
            
            // Create order record
            $order = $this->create_order_from_session($session, $completion_data);
            if (is_wp_error($order)) {
                error_log('Failed to create order: ' . $order->get_error_message());
                return false;
            }
            
            // Trigger completion hooks
            do_action('carbon_marketplace_checkout_completed', $session, $order);
            
            return true;
            
        } catch (\Exception $e) {
            error_log('Checkout completion failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get checkout session by ID
     */
    public function get_checkout_session(string $session_id): ?CheckoutSession {
        global $wpdb;
        
        $table_name = $this->database->get_table_name('checkout_sessions');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE session_id = %s", $session_id)
        );
        
        if (!$row) {
            return null;
        }
        
        return CheckoutSession::from_array((array) $row);
    }
    
    /**
     * Validate checkout request data
     */
    private function validate_checkout_request(array $data): bool|WP_Error {
        $required_fields = ['amount_kg', 'success_url', 'cancel_url'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Required field missing: {$field}");
            }
        }
        
        if (!is_numeric($data['amount_kg']) || $data['amount_kg'] <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be a positive number');
        }
        
        if (!filter_var($data['success_url'], FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid success URL');
        }
        
        if (!filter_var($data['cancel_url'], FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid cancel URL');
        }
        
        return true;
    }
    
    /**
     * Store checkout session in database
     */
    private function store_checkout_session(CheckoutSession $session): bool {
        global $wpdb;
        
        $table_name = $this->database->get_table_name('checkout_sessions');
        
        $result = $wpdb->insert(
            $table_name,
            [
                'session_id' => $session->get_id(),
                'vendor' => $session->get_vendor(),
                'amount_kg' => $session->get_amount_kg(),
                'total_price' => $session->get_total_price(),
                'currency' => $session->get_currency(),
                'status' => $session->get_status(),
                'checkout_url' => $session->get_checkout_url(),
                'success_url' => $session->get_success_url(),
                'cancel_url' => $session->get_cancel_url(),
                'metadata' => json_encode($session->get_metadata()),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Update checkout session in database
     */
    private function update_checkout_session(CheckoutSession $session): bool {
        global $wpdb;
        
        $table_name = $this->database->get_table_name('checkout_sessions');
        
        $result = $wpdb->update(
            $table_name,
            [
                'status' => $session->get_status(),
                'metadata' => json_encode($session->get_metadata()),
                'updated_at' => current_time('mysql')
            ],
            ['session_id' => $session->get_id()],
            ['%s', '%s', '%s'],
            ['%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Create order from completed checkout session
     */
    private function create_order_from_session(CheckoutSession $session, array $completion_data): Order|WP_Error {
        try {
            $order_data = [
                'vendor_order_id' => $completion_data['order_id'] ?? $session->get_id(),
                'vendor' => $session->get_vendor(),
                'user_id' => get_current_user_id(),
                'amount_kg' => $session->get_amount_kg(),
                'total_price' => $session->get_total_price(),
                'currency' => $session->get_currency(),
                'status' => 'pending',
                'project_allocations' => $completion_data['project_allocations'] ?? [],
                'retirement_data' => $completion_data['retirement_data'] ?? [],
                'commission_amount' => $completion_data['commission_amount'] ?? 0
            ];
            
            $order = Order::from_array($order_data);
            
            // Store order in database
            $order_id = $this->database->insert_order($order->to_array());
            if (!$order_id) {
                return new WP_Error('order_creation_failed', 'Failed to store order in database');
            }
            
            $order->set_id($order_id);
            return $order;
            
        } catch (\Exception $e) {
            return new WP_Error('order_error', 'Failed to create order: ' . $e->getMessage());
        }
    }
    
    /**
     * Get checkout statistics
     */
    public function get_checkout_statistics(): array {
        global $wpdb;
        
        $sessions_table = $this->database->get_table_name('checkout_sessions');
        $orders_table = $this->database->get_table_name('orders');
        
        $stats = [];
        
        // Total sessions created
        $stats['total_sessions'] = $wpdb->get_var("SELECT COUNT(*) FROM {$sessions_table}");
        
        // Completed sessions
        $stats['completed_sessions'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$sessions_table} WHERE status = 'complete'"
        );
        
        // Conversion rate
        $stats['conversion_rate'] = $stats['total_sessions'] > 0 
            ? ($stats['completed_sessions'] / $stats['total_sessions']) * 100 
            : 0;
        
        // Total orders
        $stats['total_orders'] = $wpdb->get_var("SELECT COUNT(*) FROM {$orders_table}");
        
        // Total revenue
        $stats['total_revenue'] = $wpdb->get_var(
            "SELECT SUM(total_price) FROM {$orders_table} WHERE status IN ('completed', 'fulfilled')"
        ) ?: 0;
        
        // Average order value
        $stats['average_order_value'] = $stats['total_orders'] > 0 
            ? $stats['total_revenue'] / $stats['total_orders'] 
            : 0;
        
        return $stats;
    }
}