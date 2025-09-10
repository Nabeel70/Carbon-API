<?php
/**
 * Checkout Request Model Class
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Models;

/**
 * Checkout request model for creating checkout sessions
 */
class CheckoutRequest extends BaseModel {
    
    /**
     * Amount in kilograms
     *
     * @var float
     */
    public $amount_kg;
    
    /**
     * Portfolio ID (optional)
     *
     * @var string|null
     */
    public $portfolio_id;
    
    /**
     * Project ID (optional)
     *
     * @var string|null
     */
    public $project_id;
    
    /**
     * Currency code
     *
     * @var string
     */
    public $currency;
    
    /**
     * Success URL for redirect after successful checkout
     *
     * @var string
     */
    public $success_url;
    
    /**
     * Cancel URL for redirect after cancelled checkout
     *
     * @var string
     */
    public $cancel_url;
    
    /**
     * Customer email
     *
     * @var string|null
     */
    public $customer_email;
    
    /**
     * Customer name
     *
     * @var string|null
     */
    public $customer_name;
    
    /**
     * Metadata for tracking
     *
     * @var array
     */
    public $metadata;
    
    /**
     * Constructor
     *
     * @param array $data Checkout request data
     */
    public function __construct(array $data = []) {
        $this->amount_kg = isset($data['amount_kg']) ? (float) $data['amount_kg'] : 0.0;
        $this->portfolio_id = $data['portfolio_id'] ?? null;
        $this->project_id = $data['project_id'] ?? null;
        $this->currency = $data['currency'] ?? 'USD';
        $this->success_url = $data['success_url'] ?? '';
        $this->cancel_url = $data['cancel_url'] ?? '';
        $this->customer_email = $data['customer_email'] ?? null;
        $this->customer_name = $data['customer_name'] ?? null;
        $this->metadata = $data['metadata'] ?? [];
    }
    
    /**
     * Validate the checkout request data
     *
     * @return bool True if valid, false otherwise
     */
    public function validate(): bool {
        $this->clear_validation_errors();
        
        $is_valid = true;
        
        // Required fields
        if (!$this->validate_required($this->success_url, 'success_url')) {
            $is_valid = false;
        }
        
        if (!$this->validate_required($this->cancel_url, 'cancel_url')) {
            $is_valid = false;
        }
        
        // Amount must be positive
        if (!$this->validate_numeric($this->amount_kg, 'amount_kg', 0.01)) {
            $is_valid = false;
        }
        
        // URL validations
        if ($this->success_url && !$this->validate_url($this->success_url, 'success_url')) {
            $is_valid = false;
        }
        
        if ($this->cancel_url && !$this->validate_url($this->cancel_url, 'cancel_url')) {
            $is_valid = false;
        }
        
        // Email validation
        if ($this->customer_email && !$this->validate_email($this->customer_email, 'customer_email')) {
            $is_valid = false;
        }
        
        // Currency validation
        $valid_currencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];
        if (!in_array($this->currency, $valid_currencies, true)) {
            $this->add_validation_error('currency', 'Invalid currency code');
            $is_valid = false;
        }
        
        // Array validation
        if (!is_array($this->metadata)) {
            $this->add_validation_error('metadata', 'Metadata must be an array');
            $is_valid = false;
        }
        
        return $is_valid;
    }
    
    /**
     * Convert model to array
     *
     * @return array Model data as associative array
     */
    public function to_array(): array {
        return [
            'amount_kg' => $this->amount_kg,
            'portfolio_id' => $this->portfolio_id,
            'project_id' => $this->project_id,
            'currency' => $this->currency,
            'success_url' => $this->success_url,
            'cancel_url' => $this->cancel_url,
            'customer_email' => $this->customer_email,
            'customer_name' => $this->customer_name,
            'metadata' => $this->metadata,
        ];
    }
    
    /**
     * Create model from array data
     *
     * @param array $data Input data
     * @return static New model instance
     */
    public static function from_array(array $data): self {
        return new static($data);
    }
}