<?php
/**
 * Quote Request Model Class
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Models;

/**
 * Quote request model for requesting pricing information
 */
class QuoteRequest extends BaseModel {
    
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
     * Constructor
     *
     * @param array $data Quote request data
     */
    public function __construct(array $data = []) {
        $this->amount_kg = isset($data['amount_kg']) ? (float) $data['amount_kg'] : 0.0;
        $this->portfolio_id = $data['portfolio_id'] ?? null;
        $this->project_id = $data['project_id'] ?? null;
        $this->currency = $data['currency'] ?? 'USD';
    }
    
    /**
     * Validate the quote request data
     *
     * @return bool True if valid, false otherwise
     */
    public function validate(): bool {
        $this->clear_validation_errors();
        
        $is_valid = true;
        
        // Amount must be positive
        if (!$this->validate_numeric($this->amount_kg, 'amount_kg', 0.01)) {
            $is_valid = false;
        }
        
        // Currency validation
        $valid_currencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];
        if (!in_array($this->currency, $valid_currencies, true)) {
            $this->add_validation_error('currency', 'Invalid currency code');
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