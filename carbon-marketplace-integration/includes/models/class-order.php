<?php
/**
 * Order Model for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace\Models
 */

namespace CarbonMarketplace\Models;

/**
 * Order model class
 */
class Order {
    
    /**
     * Internal order ID
     *
     * @var string
     */
    public $id;
    
    /**
     * Vendor order ID
     *
     * @var string
     */
    public $vendor_order_id;
    
    /**
     * Vendor name
     *
     * @var string
     */
    public $vendor;
    
    /**
     * Amount in kg
     *
     * @var float
     */
    public $amount_kg;
    
    /**
     * Total price
     *
     * @var float
     */
    public $total_price;
    
    /**
     * Currency code
     *
     * @var string
     */
    public $currency;
    
    /**
     * Order status
     *
     * @var string
     */
    public $status;
    
    /**
     * Retirement certificate URL or data
     *
     * @var string|null
     */
    public $retirement_certificate;
    
    /**
     * Project allocations
     *
     * @var array
     */
    public $project_allocations;
    
    /**
     * Order creation time
     *
     * @var string|null
     */
    public $created_at;
    
    /**
     * Order completion time
     *
     * @var string|null
     */
    public $completed_at;
    
    /**
     * Additional metadata
     *
     * @var array
     */
    public $metadata;
    
    /**
     * Valid order statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    
    /**
     * Constructor
     *
     * @param array $data Order data
     */
    public function __construct($data = array()) {
        $this->id = $data['id'] ?? '';
        $this->vendor_order_id = $data['vendor_order_id'] ?? '';
        $this->vendor = $data['vendor'] ?? '';
        $this->amount_kg = (float) ($data['amount_kg'] ?? 0);
        $this->total_price = (float) ($data['total_price'] ?? 0);
        $this->currency = $data['currency'] ?? 'USD';
        $this->status = $data['status'] ?? self::STATUS_PENDING;
        $this->retirement_certificate = $data['retirement_certificate'] ?? null;
        $this->project_allocations = $data['project_allocations'] ?? array();
        $this->created_at = $data['created_at'] ?? null;
        $this->completed_at = $data['completed_at'] ?? null;
        $this->metadata = $data['metadata'] ?? array();
    }
    
    /**
     * Check if order is completed
     *
     * @return bool True if completed
     */
    public function is_completed() {
        return $this->status === self::STATUS_COMPLETED;
    }
    
    /**
     * Check if order is pending
     *
     * @return bool True if pending
     */
    public function is_pending() {
        return $this->status === self::STATUS_PENDING;
    }
    
    /**
     * Check if order is processing
     *
     * @return bool True if processing
     */
    public function is_processing() {
        return $this->status === self::STATUS_PROCESSING;
    }
    
    /**
     * Check if order is cancelled
     *
     * @return bool True if cancelled
     */
    public function is_cancelled() {
        return $this->status === self::STATUS_CANCELLED;
    }
    
    /**
     * Check if order failed
     *
     * @return bool True if failed
     */
    public function is_failed() {
        return $this->status === self::STATUS_FAILED;
    }
    
    /**
     * Check if order is refunded
     *
     * @return bool True if refunded
     */
    public function is_refunded() {
        return $this->status === self::STATUS_REFUNDED;
    }
    
    /**
     * Mark order as completed
     *
     * @param \DateTime|null $completed_at Completion time
     */
    public function mark_completed($completed_at = null) {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = $completed_at ? $completed_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    }
    
    /**
     * Mark order as processing
     */
    public function mark_processing() {
        $this->status = self::STATUS_PROCESSING;
    }
    
    /**
     * Mark order as cancelled
     */
    public function mark_cancelled() {
        $this->status = self::STATUS_CANCELLED;
    }
    
    /**
     * Mark order as failed
     */
    public function mark_failed() {
        $this->status = self::STATUS_FAILED;
    }
    
    /**
     * Mark order as refunded
     */
    public function mark_refunded() {
        $this->status = self::STATUS_REFUNDED;
    }
    
    /**
     * Get formatted price
     *
     * @return string Formatted price with currency
     */
    public function get_formatted_price() {
        return $this->format_currency($this->total_price);
    }
    
    /**
     * Format currency amount
     *
     * @param float $amount Amount to format
     * @return string Formatted currency
     */
    private function format_currency($amount) {
        $currency_symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        );
        
        $symbol = $currency_symbols[$this->currency] ?? $this->currency . ' ';
        
        return $symbol . number_format($amount, 2);
    }
    
    /**
     * Get customer email from metadata
     *
     * @return string|null Customer email
     */
    public function get_customer_email() {
        return $this->metadata['customer_email'] ?? null;
    }
    
    /**
     * Get customer name from metadata
     *
     * @return string|null Customer name
     */
    public function get_customer_name() {
        return $this->metadata['customer_name'] ?? null;
    }
    
    /**
     * Get portfolio ID from metadata
     *
     * @return string|null Portfolio ID
     */
    public function get_portfolio_id() {
        return $this->metadata['portfolio_id'] ?? null;
    }
    
    /**
     * Get checkout session ID from metadata
     *
     * @return string|null Checkout session ID
     */
    public function get_checkout_session_id() {
        return $this->metadata['checkout_session_id'] ?? null;
    }
    
    /**
     * Get retirement serials from metadata
     *
     * @return array Retirement serial numbers
     */
    public function get_retirement_serials() {
        return $this->metadata['retirement_serials'] ?? array();
    }
    
    /**
     * Check if retirement certificate is available
     *
     * @return bool True if certificate is available
     */
    public function has_retirement_certificate() {
        return !empty($this->retirement_certificate);
    }
    
    /**
     * Get total carbon offset amount
     *
     * @return float Total kg of carbon offset
     */
    public function get_total_offset_kg() {
        return $this->amount_kg;
    }
    
    /**
     * Get project allocation summary
     *
     * @return array Summary of project allocations
     */
    public function get_allocation_summary() {
        $summary = array();
        
        foreach ($this->project_allocations as $allocation) {
            $project_id = $allocation['project_id'] ?? 'unknown';
            $amount = (float) ($allocation['amount_kg'] ?? 0);
            
            if (isset($summary[$project_id])) {
                $summary[$project_id]['amount_kg'] += $amount;
            } else {
                $summary[$project_id] = array(
                    'project_id' => $project_id,
                    'project_name' => $allocation['project_name'] ?? '',
                    'amount_kg' => $amount,
                    'percentage' => 0, // Will be calculated below
                );
            }
        }
        
        // Calculate percentages
        $total_amount = $this->amount_kg;
        if ($total_amount > 0) {
            foreach ($summary as &$allocation) {
                $allocation['percentage'] = ($allocation['amount_kg'] / $total_amount) * 100;
            }
        }
        
        return array_values($summary);
    }
    
    /**
     * Get order age in days
     *
     * @return int|null Age in days, null if no creation date
     */
    public function get_age_in_days() {
        if (empty($this->created_at)) {
            return null;
        }
        
        $created_time = strtotime($this->created_at);
        if ($created_time === false) {
            return null;
        }
        
        return floor((time() - $created_time) / (24 * 60 * 60));
    }
    
    /**
     * Convert to array
     *
     * @return array Order data as array
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'vendor_order_id' => $this->vendor_order_id,
            'vendor' => $this->vendor,
            'amount_kg' => $this->amount_kg,
            'total_price' => $this->total_price,
            'currency' => $this->currency,
            'status' => $this->status,
            'retirement_certificate' => $this->retirement_certificate,
            'project_allocations' => $this->project_allocations,
            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
            'metadata' => $this->metadata,
            'is_completed' => $this->is_completed(),
            'is_pending' => $this->is_pending(),
            'is_processing' => $this->is_processing(),
            'is_cancelled' => $this->is_cancelled(),
            'is_failed' => $this->is_failed(),
            'is_refunded' => $this->is_refunded(),
            'formatted_price' => $this->get_formatted_price(),
            'has_retirement_certificate' => $this->has_retirement_certificate(),
            'allocation_summary' => $this->get_allocation_summary(),
            'age_in_days' => $this->get_age_in_days(),
        );
    }
    
    /**
     * Create from array
     *
     * @param array $data Order data
     * @return Order Order object
     */
    public static function from_array($data) {
        return new self($data);
    }
    
    /**
     * Validate order data
     *
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate() {
        $errors = array();
        
        if (empty($this->vendor_order_id)) {
            $errors[] = 'Vendor order ID is required';
        }
        
        if (empty($this->vendor)) {
            $errors[] = 'Vendor is required';
        }
        
        if ($this->amount_kg <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }
        
        if ($this->total_price < 0) {
            $errors[] = 'Total price cannot be negative';
        }
        
        if (empty($this->currency)) {
            $errors[] = 'Currency is required';
        }
        
        $valid_statuses = array(
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_FAILED,
            self::STATUS_REFUNDED,
        );
        
        if (!in_array($this->status, $valid_statuses)) {
            $errors[] = 'Invalid status: ' . $this->status;
        }
        
        if (!empty($errors)) {
            return new \WP_Error('validation_failed', 'Order validation failed', $errors);
        }
        
        return true;
    }
    
    /**
     * Set order status
     *
     * @param string $status New status
     */
    public function set_status($status) {
        $this->status = $status;
    }
    
    /**
     * Set project allocations
     *
     * @param array $allocations Project allocations
     */
    public function set_project_allocations($allocations) {
        $this->project_allocations = $allocations;
    }
    
    /**
     * Set retirement data
     *
     * @param array $retirement_data Retirement data
     */
    public function set_retirement_data($retirement_data) {
        $this->metadata['retirement_data'] = $retirement_data;
    }
    
    /**
     * Get order ID
     *
     * @return string Order ID
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get formatted total price
     *
     * @return string Formatted total price with currency symbol
     */
    public function get_formatted_total() {
        $currency_symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
        );
        
        $symbol = isset($currency_symbols[$this->currency]) ? $currency_symbols[$this->currency] : $this->currency . ' ';
        return $symbol . number_format($this->total_price, 2);
    }
}