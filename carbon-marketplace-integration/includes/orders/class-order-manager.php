<?php
/**
 * Order Manager for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Orders;

use CarbonMarketplace\Core\Database;
use CarbonMarketplace\Models\Order;
use WP_Error;

/**
 * Manages order tracking and lifecycle
 */
class OrderManager {
    
    private Database $database;
    
    public function __construct(Database $database) {
        $this->database = $database;
    }
    
    /**
     * Get order by ID
     */
    public function get_order(int $order_id): ?Order {
        $order_data = $this->database->get_order($order_id);
        
        if (!$order_data) {
            return null;
        }
        
        return Order::from_array($order_data);
    }
    
    /**
     * Get order by vendor order ID
     */
    public function get_order_by_vendor_id(string $vendor_order_id): ?Order {
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
     * Get orders for user
     */
    public function get_user_orders(int $user_id, array $filters = []): array {
        global $wpdb;
        
        $table_name = $this->database->get_table_name('orders');
        
        $where_conditions = ['user_id = %d'];
        $where_values = [$user_id];
        
        // Add status filter
        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $filters['status'];
        }
        
        // Add vendor filter
        if (!empty($filters['vendor'])) {
            $where_conditions[] = 'vendor = %s';
            $where_values[] = $filters['vendor'];
        }
        
        // Add date range filter
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;
        
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        $results = $wpdb->get_results(
            $wpdb->prepare($query, ...$where_values)
        );
        
        $orders = [];
        foreach ($results as $row) {
            $orders[] = Order::from_array((array) $row);
        }
        
        return $orders;
    }
    
    /**
     * Update order status
     */
    public function update_order_status(int $order_id, string $status): bool {
        $order = $this->get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $order->set_status($status);
        
        if ($status === 'completed') {
            $order->mark_completed();
        }
        
        return $this->update_order($order);
    }
    
    /**
     * Update order
     */
    public function update_order(Order $order): bool {
        return $this->database->update_order($order->get_id(), $order->to_array());
    }
    
    /**
     * Add retirement data to order
     */
    public function add_retirement_data(int $order_id, array $retirement_data): bool {
        $order = $this->get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $order->set_retirement_data($retirement_data);
        $order->mark_completed();
        
        $success = $this->update_order($order);
        
        if ($success) {
            // Trigger retirement hooks
            do_action('carbon_marketplace_retirement_added', $order, $retirement_data);
        }
        
        return $success;
    }
    
    /**
     * Get order statistics
     */
    public function get_order_statistics(array $filters = []): array {
        global $wpdb;
        
        $table_name = $this->database->get_table_name('orders');
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        // Add user filter
        if (!empty($filters['user_id'])) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $filters['user_id'];
        }
        
        // Add vendor filter
        if (!empty($filters['vendor'])) {
            $where_conditions[] = 'vendor = %s';
            $where_values[] = $filters['vendor'];
        }
        
        // Add date range filter
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $stats = [];
        
        // Total orders
        $query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
        $stats['total_orders'] = $wpdb->get_var(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        );
        
        // Completed orders
        $completed_conditions = $where_conditions;
        $completed_conditions[] = "status IN ('completed', 'fulfilled')";
        $completed_where = implode(' AND ', $completed_conditions);
        
        $query = "SELECT COUNT(*) FROM {$table_name} WHERE {$completed_where}";
        $stats['completed_orders'] = $wpdb->get_var(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        );
        
        // Total carbon offset
        $query = "SELECT SUM(amount_kg) FROM {$table_name} WHERE {$completed_where}";
        $stats['total_carbon_kg'] = $wpdb->get_var(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        ) ?: 0;
        
        // Total revenue
        $query = "SELECT SUM(total_price) FROM {$table_name} WHERE {$completed_where}";
        $stats['total_revenue'] = $wpdb->get_var(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        ) ?: 0;
        
        // Average order value
        $stats['average_order_value'] = $stats['completed_orders'] > 0 
            ? $stats['total_revenue'] / $stats['completed_orders'] 
            : 0;
        
        // Orders by status
        $query = "SELECT status, COUNT(*) as count FROM {$table_name} WHERE {$where_clause} GROUP BY status";
        $status_results = $wpdb->get_results(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        );
        
        $stats['by_status'] = [];
        foreach ($status_results as $result) {
            $stats['by_status'][$result->status] = $result->count;
        }
        
        // Orders by vendor
        $query = "SELECT vendor, COUNT(*) as count FROM {$table_name} WHERE {$where_clause} GROUP BY vendor";
        $vendor_results = $wpdb->get_results(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        );
        
        $stats['by_vendor'] = [];
        foreach ($vendor_results as $result) {
            $stats['by_vendor'][$result->vendor] = $result->count;
        }
        
        return $stats;
    }
    
    /**
     * Get retirement certificate data
     */
    public function get_retirement_certificate(int $order_id): ?array {
        $order = $this->get_order($order_id);
        
        if (!$order || !$order->is_completed()) {
            return null;
        }
        
        $retirement_data = $order->get_retirement_data();
        
        if (empty($retirement_data)) {
            return null;
        }
        
        return [
            'order_id' => $order->get_id(),
            'vendor_order_id' => $order->get_vendor_order_id(),
            'vendor' => $order->get_vendor(),
            'amount_kg' => $order->get_amount_kg(),
            'total_price' => $order->get_total_price(),
            'currency' => $order->get_currency(),
            'completed_at' => $order->get_completed_at(),
            'project_allocations' => $order->get_project_allocations(),
            'retirement_data' => $retirement_data,
            'certificate_url' => $this->generate_certificate_url($order)
        ];
    }
    
    /**
     * Generate certificate URL
     */
    private function generate_certificate_url(Order $order): string {
        $retirement_data = $order->get_retirement_data();
        
        // Check for registry URLs in retirement data
        if (!empty($retirement_data['registry_url'])) {
            return $retirement_data['registry_url'];
        }
        
        if (!empty($retirement_data['certificate_url'])) {
            return $retirement_data['certificate_url'];
        }
        
        // Generate internal certificate page URL
        return add_query_arg([
            'action' => 'view_certificate',
            'order_id' => $order->get_id(),
            'token' => wp_create_nonce('certificate_' . $order->get_id())
        ], home_url('/carbon-certificate/'));
    }
    
    /**
     * Search orders
     */
    public function search_orders(string $search_term, array $filters = []): array {
        global $wpdb;
        
        $table_name = $this->database->get_table_name('orders');
        
        $where_conditions = [];
        $where_values = [];
        
        // Search in vendor_order_id and project allocations
        if (!empty($search_term)) {
            $where_conditions[] = "(vendor_order_id LIKE %s OR project_allocations LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search_term) . '%';
            $where_values[] = $search_like;
            $where_values[] = $search_like;
        }
        
        // Add additional filters
        if (!empty($filters['user_id'])) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $filters['user_id'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['vendor'])) {
            $where_conditions[] = 'vendor = %s';
            $where_values[] = $filters['vendor'];
        }
        
        $where_clause = empty($where_conditions) ? '1=1' : implode(' AND ', $where_conditions);
        
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 20;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;
        
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        $results = $wpdb->get_results(
            $wpdb->prepare($query, ...$where_values)
        );
        
        $orders = [];
        foreach ($results as $row) {
            $orders[] = Order::from_array((array) $row);
        }
        
        return $orders;
    }
    
    /**
     * Export orders to CSV
     */
    public function export_orders_csv(array $filters = []): string {
        $orders = $this->get_user_orders($filters['user_id'] ?? 0, $filters);
        
        $csv_data = [];
        $csv_data[] = [
            'Order ID',
            'Vendor Order ID',
            'Vendor',
            'Amount (kg)',
            'Total Price',
            'Currency',
            'Status',
            'Created At',
            'Completed At'
        ];
        
        foreach ($orders as $order) {
            $csv_data[] = [
                $order->get_id(),
                $order->get_vendor_order_id(),
                $order->get_vendor(),
                $order->get_amount_kg(),
                $order->get_total_price(),
                $order->get_currency(),
                $order->get_status(),
                $order->get_created_at() ? $order->get_created_at()->format('Y-m-d H:i:s') : '',
                $order->get_completed_at() ? $order->get_completed_at()->format('Y-m-d H:i:s') : ''
            ];
        }
        
        // Generate CSV content
        $output = fopen('php://temp', 'r+');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return $csv_content;
    }
}