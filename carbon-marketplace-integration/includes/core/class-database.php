<?php
/**
 * Database Utility Class
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Core;

/**
 * Database utility class for table management and queries
 */
class Database {
    
    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private $wpdb;
    
    /**
     * Table prefix
     *
     * @var string
     */
    private $table_prefix;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'carbon_';
    }
    
    /**
     * Get projects table name
     *
     * @return string Full table name
     */
    public function get_projects_table(): string {
        return $this->table_prefix . 'projects';
    }
    
    /**
     * Get orders table name
     *
     * @return string Full table name
     */
    public function get_orders_table(): string {
        return $this->table_prefix . 'orders';
    }
    
    /**
     * Check if table exists
     *
     * @param string $table_name Table name
     * @return bool True if table exists
     */
    public function table_exists(string $table_name): bool {
        $query = $this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        );
        
        return $this->wpdb->get_var($query) === $table_name;
    }
    
    /**
     * Create projects table
     *
     * @return bool True on success, false on failure
     */
    public function create_projects_table(): bool {
        $table_name = $this->get_projects_table();
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id VARCHAR(255) NOT NULL,
            vendor VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            location VARCHAR(255),
            project_type VARCHAR(100),
            methodology VARCHAR(255),
            price_per_kg DECIMAL(10,4),
            available_quantity INT,
            images JSON,
            sdgs JSON,
            registry_url VARCHAR(500),
            data JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY vendor_project (vendor, vendor_id),
            KEY location (location),
            KEY project_type (project_type),
            KEY price_per_kg (price_per_kg),
            KEY vendor (vendor),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $result = dbDelta($sql);
        
        return $this->table_exists($table_name);
    }
    
    /**
     * Create orders table
     *
     * @return bool True on success, false on failure
     */
    public function create_orders_table(): bool {
        $table_name = $this->get_orders_table();
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_order_id VARCHAR(255) NOT NULL,
            vendor VARCHAR(50) NOT NULL,
            user_id BIGINT(20) UNSIGNED,
            amount_kg DECIMAL(10,4) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'USD',
            status VARCHAR(50) NOT NULL,
            project_allocations JSON,
            retirement_certificate TEXT,
            retirement_data JSON,
            commission_amount DECIMAL(10,2),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY vendor_order (vendor, vendor_order_id),
            KEY user_id (user_id),
            KEY vendor (vendor),
            KEY status (status),
            KEY created_at (created_at),
            KEY completed_at (completed_at),
            FOREIGN KEY (user_id) REFERENCES {$this->wpdb->users}(ID) ON DELETE SET NULL
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $result = dbDelta($sql);
        
        return $this->table_exists($table_name);
    }
    
    /**
     * Drop projects table
     *
     * @return bool True on success, false on failure
     */
    public function drop_projects_table(): bool {
        $table_name = $this->get_projects_table();
        
        $sql = "DROP TABLE IF EXISTS $table_name";
        $result = $this->wpdb->query($sql);
        
        return $result !== false;
    }
    
    /**
     * Drop orders table
     *
     * @return bool True on success, false on failure
     */
    public function drop_orders_table(): bool {
        $table_name = $this->get_orders_table();
        
        $sql = "DROP TABLE IF EXISTS $table_name";
        $result = $this->wpdb->query($sql);
        
        return $result !== false;
    }
    
    /**
     * Insert project into database
     *
     * @param array $project_data Project data
     * @return int|false Insert ID on success, false on failure
     */
    public function insert_project(array $project_data) {
        $table_name = $this->get_projects_table();
        
        // Prepare data for insertion
        $data = [
            'vendor_id' => $project_data['vendor_id'] ?? '',
            'vendor' => $project_data['vendor'] ?? '',
            'name' => $project_data['name'] ?? '',
            'description' => $project_data['description'] ?? '',
            'location' => $project_data['location'] ?? '',
            'project_type' => $project_data['project_type'] ?? '',
            'methodology' => $project_data['methodology'] ?? '',
            'price_per_kg' => $project_data['price_per_kg'] ?? 0,
            'available_quantity' => $project_data['available_quantity'] ?? 0,
            'images' => isset($project_data['images']) ? wp_json_encode($project_data['images']) : null,
            'sdgs' => isset($project_data['sdgs']) ? wp_json_encode($project_data['sdgs']) : null,
            'registry_url' => $project_data['registry_url'] ?? '',
            'data' => isset($project_data['data']) ? wp_json_encode($project_data['data']) : null,
        ];
        
        $formats = [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s'
        ];
        
        $result = $this->wpdb->insert($table_name, $data, $formats);
        
        return $result ? $this->wpdb->insert_id : false;
    }
    
    /**
     * Update project in database
     *
     * @param int $id Project ID
     * @param array $project_data Project data
     * @return bool True on success, false on failure
     */
    public function update_project(int $id, array $project_data): bool {
        $table_name = $this->get_projects_table();
        
        // Prepare data for update
        $data = [];
        $formats = [];
        
        $allowed_fields = [
            'vendor_id', 'vendor', 'name', 'description', 'location', 
            'project_type', 'methodology', 'price_per_kg', 'available_quantity', 
            'registry_url'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($project_data[$field])) {
                $data[$field] = $project_data[$field];
                $formats[] = in_array($field, ['price_per_kg']) ? '%f' : 
                           (in_array($field, ['available_quantity']) ? '%d' : '%s');
            }
        }
        
        // Handle JSON fields
        if (isset($project_data['images'])) {
            $data['images'] = wp_json_encode($project_data['images']);
            $formats[] = '%s';
        }
        
        if (isset($project_data['sdgs'])) {
            $data['sdgs'] = wp_json_encode($project_data['sdgs']);
            $formats[] = '%s';
        }
        
        if (isset($project_data['data'])) {
            $data['data'] = wp_json_encode($project_data['data']);
            $formats[] = '%s';
        }
        
        if (empty($data)) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $table_name,
            $data,
            ['id' => $id],
            $formats,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get project by ID
     *
     * @param int $id Project ID
     * @return array|null Project data or null if not found
     */
    public function get_project(int $id): ?array {
        $table_name = $this->get_projects_table();
        
        $query = $this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        );
        
        $result = $this->wpdb->get_row($query, ARRAY_A);
        
        if ($result) {
            // Decode JSON fields
            $result['images'] = json_decode($result['images'], true) ?? [];
            $result['sdgs'] = json_decode($result['sdgs'], true) ?? [];
            $result['data'] = json_decode($result['data'], true) ?? [];
        }
        
        return $result;
    }
    
    /**
     * Get project by vendor and vendor ID
     *
     * @param string $vendor Vendor name
     * @param string $vendor_id Vendor project ID
     * @return array|null Project data or null if not found
     */
    public function get_project_by_vendor_id(string $vendor, string $vendor_id): ?array {
        $table_name = $this->get_projects_table();
        
        $query = $this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE vendor = %s AND vendor_id = %s",
            $vendor,
            $vendor_id
        );
        
        $result = $this->wpdb->get_row($query, ARRAY_A);
        
        if ($result) {
            // Decode JSON fields
            $result['images'] = json_decode($result['images'], true) ?? [];
            $result['sdgs'] = json_decode($result['sdgs'], true) ?? [];
            $result['data'] = json_decode($result['data'], true) ?? [];
        }
        
        return $result;
    }
    
    /**
     * Search projects
     *
     * @param array $filters Search filters
     * @param int $limit Results limit
     * @param int $offset Results offset
     * @param string $order_by Order by field
     * @param string $order Order direction (ASC/DESC)
     * @return array Array of project data
     */
    public function search_projects(array $filters = [], int $limit = 20, int $offset = 0, string $order_by = 'name', string $order = 'ASC'): array {
        $table_name = $this->get_projects_table();
        
        $where_clauses = [];
        $where_values = [];
        
        // Build WHERE clauses
        if (!empty($filters['keyword'])) {
            $where_clauses[] = "(name LIKE %s OR description LIKE %s OR location LIKE %s)";
            $keyword = '%' . $this->wpdb->esc_like($filters['keyword']) . '%';
            $where_values[] = $keyword;
            $where_values[] = $keyword;
            $where_values[] = $keyword;
        }
        
        if (!empty($filters['location'])) {
            $where_clauses[] = "location LIKE %s";
            $where_values[] = '%' . $this->wpdb->esc_like($filters['location']) . '%';
        }
        
        if (!empty($filters['project_type'])) {
            $where_clauses[] = "project_type = %s";
            $where_values[] = $filters['project_type'];
        }
        
        if (!empty($filters['vendor'])) {
            $where_clauses[] = "vendor = %s";
            $where_values[] = $filters['vendor'];
        }
        
        if (isset($filters['min_price'])) {
            $where_clauses[] = "price_per_kg >= %f";
            $where_values[] = $filters['min_price'];
        }
        
        if (isset($filters['max_price'])) {
            $where_clauses[] = "price_per_kg <= %f";
            $where_values[] = $filters['max_price'];
        }
        
        // Build query
        $sql = "SELECT * FROM $table_name";
        
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        // Validate order by field
        $allowed_order_fields = ['name', 'price_per_kg', 'location', 'project_type', 'created_at'];
        if (!in_array($order_by, $allowed_order_fields)) {
            $order_by = 'name';
        }
        
        // Validate order direction
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        
        $sql .= " ORDER BY $order_by $order";
        $sql .= " LIMIT %d OFFSET %d";
        
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        if (!empty($where_values)) {
            $query = $this->wpdb->prepare($sql, $where_values);
        } else {
            $query = $sql;
        }
        
        $results = $this->wpdb->get_results($query, ARRAY_A);
        
        // Decode JSON fields for each result
        foreach ($results as &$result) {
            $result['images'] = json_decode($result['images'], true) ?? [];
            $result['sdgs'] = json_decode($result['sdgs'], true) ?? [];
            $result['data'] = json_decode($result['data'], true) ?? [];
        }
        
        return $results;
    }
    
    /**
     * Insert order into database
     *
     * @param array $order_data Order data
     * @return int|false Insert ID on success, false on failure
     */
    public function insert_order(array $order_data) {
        $table_name = $this->get_orders_table();
        
        // Prepare data for insertion
        $data = [
            'vendor_order_id' => $order_data['vendor_order_id'] ?? '',
            'vendor' => $order_data['vendor'] ?? '',
            'user_id' => $order_data['user_id'] ?? null,
            'amount_kg' => $order_data['amount_kg'] ?? 0,
            'total_price' => $order_data['total_price'] ?? 0,
            'currency' => $order_data['currency'] ?? 'USD',
            'status' => $order_data['status'] ?? 'pending',
            'project_allocations' => isset($order_data['project_allocations']) ? wp_json_encode($order_data['project_allocations']) : null,
            'retirement_certificate' => $order_data['retirement_certificate'] ?? null,
            'retirement_data' => isset($order_data['retirement_data']) ? wp_json_encode($order_data['retirement_data']) : null,
            'commission_amount' => $order_data['commission_amount'] ?? null,
        ];
        
        $formats = [
            '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%f'
        ];
        
        $result = $this->wpdb->insert($table_name, $data, $formats);
        
        return $result ? $this->wpdb->insert_id : false;
    }
    
    /**
     * Update order in database
     *
     * @param int $id Order ID
     * @param array $order_data Order data
     * @return bool True on success, false on failure
     */
    public function update_order(int $id, array $order_data): bool {
        $table_name = $this->get_orders_table();
        
        // Prepare data for update
        $data = [];
        $formats = [];
        
        $allowed_fields = [
            'vendor_order_id', 'vendor', 'user_id', 'amount_kg', 'total_price', 
            'currency', 'status', 'retirement_certificate', 'commission_amount', 'completed_at'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($order_data[$field])) {
                $data[$field] = $order_data[$field];
                
                if (in_array($field, ['amount_kg', 'total_price', 'commission_amount'])) {
                    $formats[] = '%f';
                } elseif (in_array($field, ['user_id'])) {
                    $formats[] = '%d';
                } else {
                    $formats[] = '%s';
                }
            }
        }
        
        // Handle JSON fields
        if (isset($order_data['project_allocations'])) {
            $data['project_allocations'] = wp_json_encode($order_data['project_allocations']);
            $formats[] = '%s';
        }
        
        if (isset($order_data['retirement_data'])) {
            $data['retirement_data'] = wp_json_encode($order_data['retirement_data']);
            $formats[] = '%s';
        }
        
        if (empty($data)) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $table_name,
            $data,
            ['id' => $id],
            $formats,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get order by ID
     *
     * @param int $id Order ID
     * @return array|null Order data or null if not found
     */
    public function get_order(int $id): ?array {
        $table_name = $this->get_orders_table();
        
        $query = $this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        );
        
        $result = $this->wpdb->get_row($query, ARRAY_A);
        
        if ($result) {
            // Decode JSON fields
            $result['project_allocations'] = json_decode($result['project_allocations'], true) ?? [];
            $result['retirement_data'] = json_decode($result['retirement_data'], true) ?? [];
        }
        
        return $result;
    }
    
    /**
     * Get order by vendor order ID
     *
     * @param string $vendor Vendor name
     * @param string $vendor_order_id Vendor order ID
     * @return array|null Order data or null if not found
     */
    public function get_order_by_vendor_id(string $vendor, string $vendor_order_id): ?array {
        $table_name = $this->get_orders_table();
        
        $query = $this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE vendor = %s AND vendor_order_id = %s",
            $vendor,
            $vendor_order_id
        );
        
        $result = $this->wpdb->get_row($query, ARRAY_A);
        
        if ($result) {
            // Decode JSON fields
            $result['project_allocations'] = json_decode($result['project_allocations'], true) ?? [];
            $result['retirement_data'] = json_decode($result['retirement_data'], true) ?? [];
        }
        
        return $result;
    }
    
    /**
     * Get orders by user ID
     *
     * @param int $user_id User ID
     * @param int $limit Results limit
     * @param int $offset Results offset
     * @return array Array of order data
     */
    public function get_orders_by_user(int $user_id, int $limit = 20, int $offset = 0): array {
        $table_name = $this->get_orders_table();
        
        $query = $this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        );
        
        $results = $this->wpdb->get_results($query, ARRAY_A);
        
        // Decode JSON fields for each result
        foreach ($results as &$result) {
            $result['project_allocations'] = json_decode($result['project_allocations'], true) ?? [];
            $result['retirement_data'] = json_decode($result['retirement_data'], true) ?? [];
        }
        
        return $results;
    }
}