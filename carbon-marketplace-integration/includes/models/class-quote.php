<?php
/**
 * Quote Model for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace\Models
 */

namespace CarbonMarketplace\Models;

/**
 * Quote model class
 */
class Quote {
    
    /**
     * Quote ID
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
     * Amount in kg
     *
     * @var float
     */
    public $amount_kg;
    
    /**
     * Price per kg
     *
     * @var float
     */
    public $price_per_kg;
    
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
     * Quote expiration time
     *
     * @var string|null
     */
    public $expires_at;
    
    /**
     * Portfolio ID (if applicable)
     *
     * @var string|null
     */
    public $portfolio_id;
    
    /**
     * Additional metadata
     *
     * @var array
     */
    public $metadata;
    
    /**
     * Constructor
     *
     * @param array $data Quote data
     */
    public function __construct($data = array()) {
        $this->id = $data['id'] ?? '';
        $this->vendor = $data['vendor'] ?? '';
        $this->amount_kg = (float) ($data['amount_kg'] ?? 0);
        $this->price_per_kg = (float) ($data['price_per_kg'] ?? 0);
        $this->total_price = (float) ($data['total_price'] ?? 0);
        $this->currency = $data['currency'] ?? 'USD';
        $this->expires_at = $data['expires_at'] ?? null;
        $this->portfolio_id = $data['portfolio_id'] ?? null;
        $this->metadata = $data['metadata'] ?? array();
    }
    
    /**
     * Check if quote is expired
     *
     * @return bool True if expired
     */
    public function is_expired() {
        if (empty($this->expires_at)) {
            return false;
        }
        
        $expiry_time = strtotime($this->expires_at);
        return $expiry_time !== false && $expiry_time < time();
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
     * Get formatted price per kg
     *
     * @return string Formatted price per kg with currency
     */
    public function get_formatted_price_per_kg() {
        return $this->format_currency($this->price_per_kg) . '/kg';
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
     * Convert to array
     *
     * @return array Quote data as array
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'vendor' => $this->vendor,
            'amount_kg' => $this->amount_kg,
            'price_per_kg' => $this->price_per_kg,
            'total_price' => $this->total_price,
            'currency' => $this->currency,
            'expires_at' => $this->expires_at,
            'portfolio_id' => $this->portfolio_id,
            'metadata' => $this->metadata,
            'is_expired' => $this->is_expired(),
            'time_to_expiry' => $this->get_time_to_expiry(),
            'formatted_price' => $this->get_formatted_price(),
            'formatted_price_per_kg' => $this->get_formatted_price_per_kg(),
        );
    }
    
    /**
     * Create from array
     *
     * @param array $data Quote data
     * @return Quote Quote object
     */
    public static function from_array($data) {
        return new self($data);
    }
    
    /**
     * Validate quote data
     *
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate() {
        $errors = array();
        
        if (empty($this->id)) {
            $errors[] = 'Quote ID is required';
        }
        
        if (empty($this->vendor)) {
            $errors[] = 'Vendor is required';
        }
        
        if ($this->amount_kg <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }
        
        if ($this->price_per_kg < 0) {
            $errors[] = 'Price per kg cannot be negative';
        }
        
        if ($this->total_price < 0) {
            $errors[] = 'Total price cannot be negative';
        }
        
        if (empty($this->currency)) {
            $errors[] = 'Currency is required';
        }
        
        if (!empty($errors)) {
            return new \WP_Error('validation_failed', 'Quote validation failed', $errors);
        }
        
        return true;
    }
}