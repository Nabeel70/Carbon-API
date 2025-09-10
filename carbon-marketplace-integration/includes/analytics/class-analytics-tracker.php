<?php
/**
 * Analytics Tracker for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Analytics;

use CarbonMarketplace\Core\Database;
use CarbonMarketplace\Models\Order;

/**
 * Tracks user interactions and conversions
 */
class AnalyticsTracker {
    
    private Database $database;
    
    public function __construct(Database $database) {
        $this->database = $database;
    }
    
    /**
     * Initialize analytics tracking
     */
    public function init(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_tracking_scripts']);
        add_action('wp_ajax_carbon_marketplace_track_event', [$this, 'handle_track_event']);
        add_action('wp_ajax_nopriv_carbon_marketplace_track_event', [$this, 'handle_track_event']);
        
        // Hook into order events
        add_action('carbon_marketplace_checkout_completed', [$this, 'track_conversion'], 10, 2);
        add_action('carbon_marketplace_order_fulfilled', [$this, 'track_fulfillment'], 10, 2);
        add_action('carbon_marketplace_order_retired', [$this, 'track_retirement'], 10, 2);
    }
    
    /**
     * Enqueue tracking scripts
     */
    public function enqueue_tracking_scripts(): void {
        wp_enqueue_script(
            'carbon-marketplace-analytics',
            CARBON_MARKETPLACE_URL . 'assets/js/analytics.js',
            ['jquery'],
            CARBON_MARKETPLACE_VERSION,
            true
        );
        
        wp_localize_script('carbon-marketplace-analytics', 'carbonAnalytics', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('carbon_marketplace_analytics'),
            'userId' => get_current_user_id(),
            'sessionId' => $this->get_session_id()
        ]);
    }
    
    /**
     * Handle AJAX event tracking
     */
    public function handle_track_event(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'carbon_marketplace_analytics')) {
            wp_die('Security check failed');
        }
        
        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $event_data = $_POST['event_data'] ?? [];
        
        if (empty($event_type)) {
            wp_send_json_error('Event type is required');
        }
        
        $success = $this->track_event($event_type, $event_data);
        
        if ($success) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to track event');
        }
    }
    
    /**
     * Track an event
     */
    public function track_event(string $event_type, array $event_data = []): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'carbon_marketplace_analytics';
        
        $data = [
            'event_type' => $event_type,
            'user_id' => get_current_user_id() ?: null,
            'session_id' => $this->get_session_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'page_url' => $_SERVER['REQUEST_URI'] ?? '',
            'event_data' => json_encode($event_data),
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table_name, $data, [
            '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ]);
        
        return $result !== false;
    }
    
    /**
     * Track conversion event
     */
    public function track_conversion($session, Order $order): void {
        $conversion_data = [
            'order_id' => $order->get_id(),
            'vendor_order_id' => $order->get_vendor_order_id(),
            'vendor' => $order->get_vendor(),
            'amount_kg' => $order->get_amount_kg(),
            'total_price' => $order->get_total_price(),
            'currency' => $order->get_currency(),
            'commission_amount' => $order->get_commission_amount()
        ];
        
        $this->track_event('conversion', $conversion_data);
        
        // Update conversion tracking table
        $this->record_conversion($order);
    }
    
    /**
     * Track fulfillment event
     */
    public function track_fulfillment(Order $order, array $payload): void {
        $fulfillment_data = [
            'order_id' => $order->get_id(),
            'vendor_order_id' => $order->get_vendor_order_id(),
            'vendor' => $order->get_vendor(),
            'project_allocations' => $order->get_project_allocations()
        ];
        
        $this->track_event('fulfillment', $fulfillment_data);
    }
    
    /**
     * Track retirement event
     */
    public function track_retirement(Order $order, array $payload): void {
        $retirement_data = [
            'order_id' => $order->get_id(),
            'vendor_order_id' => $order->get_vendor_order_id(),
            'vendor' => $order->get_vendor(),
            'amount_kg' => $order->get_amount_kg(),
            'retirement_data' => $order->get_retirement_data()
        ];
        
        $this->track_event('retirement', $retirement_data);
    }
    
    /**
     * Record conversion in dedicated table
     */
    private function record_conversion(Order $order): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'carbon_marketplace_conversions';
        
        $data = [
            'order_id' => $order->get_id(),
            'vendor_order_id' => $order->get_vendor_order_id(),
            'vendor' => $order->get_vendor(),
            'user_id' => $order->get_user_id(),
            'session_id' => $this->get_session_id(),
            'amount_kg' => $order->get_amount_kg(),
            'total_price' => $order->get_total_price(),
            'currency' => $order->get_currency(),
            'commission_amount' => $order->get_commission_amount(),
            'conversion_date' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table_name, $data, [
            '%d', '%s', '%s', '%d', '%s', '%f', '%f', '%s', '%f', '%s'
        ]);
        
        return $result !== false;
    }
    
    /**
     * Get analytics data
     */
    public function get_analytics_data(array $filters = []): array {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'carbon_marketplace_analytics';
        $conversions_table = $wpdb->prefix . 'carbon_marketplace_conversions';
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
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
        
        $data = [];
        
        // Total events
        $query = "SELECT COUNT(*) FROM {$analytics_table} WHERE {$where_clause}";
        $data['total_events'] = $wpdb->get_var(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        );
        
        // Events by type
        $query = "SELECT event_type, COUNT(*) as count FROM {$analytics_table} WHERE {$where_clause} GROUP BY event_type";
        $event_results = $wpdb->get_results(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        );
        
        $data['events_by_type'] = [];
        foreach ($event_results as $result) {
            $data['events_by_type'][$result->event_type] = $result->count;
        }
        
        // Conversion data
        $conversion_where = str_replace('created_at', 'conversion_date', $where_clause);
        
        $query = "SELECT COUNT(*) FROM {$conversions_table} WHERE {$conversion_where}";
        $data['total_conversions'] = $wpdb->get_var(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        );
        
        $query = "SELECT SUM(total_price) FROM {$conversions_table} WHERE {$conversion_where}";
        $data['total_revenue'] = $wpdb->get_var(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        ) ?: 0;
        
        $query = "SELECT SUM(commission_amount) FROM {$conversions_table} WHERE {$conversion_where}";
        $data['total_commission'] = $wpdb->get_var(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        ) ?: 0;
        
        $query = "SELECT SUM(amount_kg) FROM {$conversions_table} WHERE {$conversion_where}";
        $data['total_carbon_kg'] = $wpdb->get_var(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        ) ?: 0;
        
        // Average order value
        $data['average_order_value'] = $data['total_conversions'] > 0 
            ? $data['total_revenue'] / $data['total_conversions'] 
            : 0;
        
        // Conversions by vendor
        $query = "SELECT vendor, COUNT(*) as count, SUM(total_price) as revenue FROM {$conversions_table} WHERE {$conversion_where} GROUP BY vendor";
        $vendor_results = $wpdb->get_results(
            empty($where_values) ? $query : $wpdb->prepare($query, ...$where_values)
        );
        
        $data['conversions_by_vendor'] = [];
        foreach ($vendor_results as $result) {
            $data['conversions_by_vendor'][$result->vendor] = [
                'count' => $result->count,
                'revenue' => $result->revenue
            ];
        }
        
        return $data;
    }
    
    /**
     * Get conversion funnel data
     */
    public function get_conversion_funnel(array $filters = []): array {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'carbon_marketplace_analytics';
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $funnel_steps = [
            'page_view' => 'Page Views',
            'search' => 'Searches',
            'project_view' => 'Project Views',
            'quote_request' => 'Quote Requests',
            'checkout_start' => 'Checkout Started',
            'conversion' => 'Conversions'
        ];
        
        $funnel_data = [];
        
        foreach ($funnel_steps as $event_type => $label) {
            $query = "SELECT COUNT(DISTINCT session_id) FROM {$analytics_table} WHERE event_type = %s AND {$where_clause}";
            $values = array_merge([$event_type], $where_values);
            
            $count = $wpdb->get_var($wpdb->prepare($query, ...$values));
            
            $funnel_data[] = [
                'step' => $event_type,
                'label' => $label,
                'count' => $count ?: 0
            ];
        }
        
        // Calculate conversion rates
        $total_visitors = $funnel_data[0]['count'] ?? 1;
        
        foreach ($funnel_data as &$step) {
            $step['conversion_rate'] = $total_visitors > 0 
                ? ($step['count'] / $total_visitors) * 100 
                : 0;
        }
        
        return $funnel_data;
    }
    
    /**
     * Get popular search terms
     */
    public function get_popular_search_terms(int $limit = 10): array {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'carbon_marketplace_analytics';
        
        $query = "
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.keyword')) as search_term,
                COUNT(*) as search_count
            FROM {$analytics_table} 
            WHERE event_type = 'search' 
            AND JSON_EXTRACT(event_data, '$.keyword') IS NOT NULL
            AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.keyword')) != ''
            GROUP BY search_term 
            ORDER BY search_count DESC 
            LIMIT %d
        ";
        
        return $wpdb->get_results($wpdb->prepare($query, $limit));
    }
    
    /**
     * Get user journey data
     */
    public function get_user_journey(string $session_id): array {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'carbon_marketplace_analytics';
        
        $query = "
            SELECT event_type, event_data, created_at 
            FROM {$analytics_table} 
            WHERE session_id = %s 
            ORDER BY created_at ASC
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $session_id));
        
        $journey = [];
        foreach ($results as $result) {
            $journey[] = [
                'event_type' => $result->event_type,
                'event_data' => json_decode($result->event_data, true),
                'timestamp' => $result->created_at
            ];
        }
        
        return $journey;
    }
    
    /**
     * Get session ID
     */
    private function get_session_id(): string {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['carbon_marketplace_session_id'])) {
            $_SESSION['carbon_marketplace_session_id'] = wp_generate_uuid4();
        }
        
        return $_SESSION['carbon_marketplace_session_id'];
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip(): string {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Create analytics tables
     */
    public function create_analytics_tables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Analytics events table
        $analytics_table = $wpdb->prefix . 'carbon_marketplace_analytics';
        $analytics_sql = "CREATE TABLE {$analytics_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            user_id bigint(20) UNSIGNED NULL,
            session_id varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            referrer text,
            page_url text,
            event_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        // Conversions table
        $conversions_table = $wpdb->prefix . 'carbon_marketplace_conversions';
        $conversions_sql = "CREATE TABLE {$conversions_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            vendor_order_id varchar(255) NOT NULL,
            vendor varchar(50) NOT NULL,
            user_id bigint(20) UNSIGNED NULL,
            session_id varchar(255) NOT NULL,
            amount_kg decimal(10,4) NOT NULL,
            total_price decimal(10,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            commission_amount decimal(10,2) DEFAULT 0,
            conversion_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id),
            KEY vendor (vendor),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY conversion_date (conversion_date)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($analytics_sql);
        dbDelta($conversions_sql);
    }
}