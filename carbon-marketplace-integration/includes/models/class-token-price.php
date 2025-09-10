<?php
/**
 * Token Price Model
 *
 * Represents pricing information for carbon tokens from DEX data.
 *
 * @package CarbonMarketplace
 * @subpackage Models
 */

namespace CarbonMarketplace\Models;

/**
 * Token Price class
 */
class TokenPrice {

    /**
     * Token contract address
     *
     * @var string
     */
    protected $token_address;

    /**
     * Price in USD
     *
     * @var float
     */
    protected $price_usd;

    /**
     * Currency code
     *
     * @var string
     */
    protected $currency;

    /**
     * Last updated timestamp
     *
     * @var string
     */
    protected $last_updated;

    /**
     * Data source identifier
     *
     * @var string
     */
    protected $data_source;

    /**
     * Additional metadata
     *
     * @var array
     */
    protected $metadata;

    /**
     * Constructor
     *
     * @param array $data Token price data
     */
    public function __construct($data = array()) {
        $this->token_address = $data['token_address'] ?? '';
        $this->price_usd = floatval($data['price_usd'] ?? 0);
        $this->currency = $data['currency'] ?? 'USD';
        $this->last_updated = $data['last_updated'] ?? current_time('mysql');
        $this->data_source = $data['data_source'] ?? '';
        $this->metadata = $data['metadata'] ?? array();
    }

    /**
     * Get token address
     *
     * @return string Token address
     */
    public function get_token_address() {
        return $this->token_address;
    }

    /**
     * Get price in USD
     *
     * @return float Price in USD
     */
    public function get_price_usd() {
        return $this->price_usd;
    }

    /**
     * Get currency
     *
     * @return string Currency code
     */
    public function get_currency() {
        return $this->currency;
    }

    /**
     * Get last updated timestamp
     *
     * @return string Last updated timestamp
     */
    public function get_last_updated() {
        return $this->last_updated;
    }

    /**
     * Get data source
     *
     * @return string Data source
     */
    public function get_data_source() {
        return $this->data_source;
    }

    /**
     * Get metadata
     *
     * @return array Metadata
     */
    public function get_metadata() {
        return $this->metadata;
    }

    /**
     * Check if price data is fresh (within specified minutes)
     *
     * @param int $max_age_minutes Maximum age in minutes
     * @return bool True if fresh
     */
    public function is_fresh($max_age_minutes = 15) {
        $last_updated_timestamp = strtotime($this->last_updated);
        $max_age_seconds = $max_age_minutes * 60;
        
        return (time() - $last_updated_timestamp) <= $max_age_seconds;
    }

    /**
     * Convert to array
     *
     * @return array Token price data as array
     */
    public function to_array() {
        return array(
            'token_address' => $this->token_address,
            'price_usd' => $this->price_usd,
            'currency' => $this->currency,
            'last_updated' => $this->last_updated,
            'data_source' => $this->data_source,
            'metadata' => $this->metadata,
            'is_fresh' => $this->is_fresh()
        );
    }

    /**
     * Format price for display
     *
     * @param int $decimals Number of decimal places
     * @return string Formatted price
     */
    public function format_price($decimals = 2) {
        return '$' . number_format($this->price_usd, $decimals);
    }
}