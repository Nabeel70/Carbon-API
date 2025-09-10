<?php
/**
 * Checkout Session Model for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace\Models
 */

namespace CarbonMarketplace\Models;

/**
 * Checkout Session model class
 */
class CheckoutSession {
    
    /**
     * Session ID
     *
     * @var string
     */
    public $id;
    
    /**
     * Vendor name
     *
     * @var string
     */
    public $vendor;
    
    /**
     * Checkout URL
     *
     * @var string
     */
    public $checkout_url;
    
    /**
     * Session status
     *
     * @var string
     */
    public $status;
    
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
     * Success URL
     *
     * @var string
     */
    public $success_url;
    
    /**
     * Cancel URL
     *
     * @var string|null
     */
    public $cancel_url;
    
    /**
     * Session expiration time
     *
     * @var string|null
     */
    public $expires_at;
    
    /**
     * Additional metadata
     *
     * @var array
     */
    public $metadata;
    
    /**
     * Valid session statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETE = 'complete';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Constructor
     *
     * @param array $data Session data
     */
    public function __construct($data = array()) {
        $this->id = $data['id'] ?? '';
        $this->vendor = $data['vendor'] ?? '';
        $this->checkout_url = $data['checkout_url'] ?? '';
        $this->status = $data['status'] ?? self::STATUS_PENDING;
        $this->amount_kg = (float) ($data['amount_kg'] ?? 0);
        $this->total_price = (float) ($data['total_price'] ?? 0);
        $this->currency = $data['currency'] ?? 'USD';
        $this->success_url = $data['success_url'] ?? '';
        $this->cancel_url = $data['cancel_url'] ?? null;
        $this->expires_at = $data['expires_at'] ?? null;
        $this->metadata = $data['metadata'] ?? array();
    }
    
    /**
     * Check if session is complete
     *
     * @return bool True if complete
     */
    public function is_complete() {
        return $this->status === self::STATUS_COMPLETE;
    }
    
    /**
     * Check if session is expired
     *
     * @return bool True if expired
     */
    public function is_expired() {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }
        
        if (empty($this->expires_at)) {
            return false;
        }
        
        $expiry_time = strtotime($this->expires_at);
        return $expiry_time !== false && $expiry_time < time();
    }
    
    /**
     * Check if session is cancelled
     *
     * @return bool True if cancelled
     */
    public function is_cancelled() {
        return $this->status === self::STATUS_CANCELLED;
    }
    
    /**
     * Check if session is pending
     *
     * @return bool True if pending
     */
    public function is_pending() {
        return $this->status === self::STATUS_PENDING && !$this->is_expired();
    }
    
    /**
     * Mark session as complete
     */
    public function mark_complete() {
        $this->status = self::STATUS_COMPLETE;
    }
    
    /**
     * Mark session as expired
     */
    public function mark_expired() {
        $this->status = self::STATUS_EXPIRED;
    }
    
    /**
     * Mark session as cancelled
     */
    public function mark_cancelled() {
        $this->status = self::STATUS_CANCELLED;
    }
    
    /**
     * Get time until expiration in seconds
     *
     * @return int|null Seconds until expiration, null if no expiry
     */
    public function get_time_to_expiry() {
        if (empty($this->expires_at)) {
            return null;
        }
        
        $expiry_time = strtotime($this->expires_at);
        if ($expiry_time === false) {
            return null;
        }
        
        return max(0, $expiry_time - time());
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
     * Get order ID from metadata
     *
     * @return string|null Order ID
     */
    public function get_order_id() {
        return $this->metadata['order_id'] ?? null;
    }
    
    /**
     * Convert to array
     *
     * @return array Session data as array
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'vendor' => $this->vendor,
            'checkout_url' => $this->checkout_url,
            'status' => $this->status,
            'amount_kg' => $this->amount_kg,
            'total_price' => $this->total_price,
            'currency' => $this->currency,
            'success_url' => $this->success_url,
            'cancel_url' => $this->cancel_url,
            'expires_at' => $this->expires_at,
            'metadata' => $this->metadata,
            'is_complete' => $this->is_complete(),
            'is_expired' => $this->is_expired(),
            'is_cancelled' => $this->is_cancelled(),
            'is_pending' => $this->is_pending(),
            'time_to_expiry' => $this->get_time_to_expiry(),
            'formatted_price' => $this->get_formatted_price(),
        );
    }
    
    /**
     * Create from array
     *
     * @param array $data Session data
     * @return CheckoutSession Session object
     */
    public static function from_array($data) {
        return new self($data);
    }
    
    /**
     * Validate session data
     *
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate() {
        $errors = array();
        
        if (empty($this->id)) {
            $errors[] = 'Session ID is required';
        }
        
        if (empty($this->vendor)) {
            $errors[] = 'Vendor is required';
        }
        
        if (empty($this->checkout_url)) {
            $errors[] = 'Checkout URL is required';
        } elseif (!filter_var($this->checkout_url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Checkout URL must be a valid URL';
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
        
        if (empty($this->success_url)) {
            $errors[] = 'Success URL is required';
        } elseif (!filter_var($this->success_url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Success URL must be a valid URL';
        }
        
        if (!empty($this->cancel_url) && !filter_var($this->cancel_url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Cancel URL must be a valid URL';
        }
        
        $valid_statuses = array(
            self::STATUS_PENDING,
            self::STATUS_COMPLETE,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        );
        
        if (!in_array($this->status, $valid_statuses)) {
            $errors[] = 'Invalid status: ' . $this->status;
        }
        
        if (!empty($errors)) {
            return new \WP_Error('validation_failed', 'Checkout session validation failed', $errors);
        }
        
        return true;
    }
}