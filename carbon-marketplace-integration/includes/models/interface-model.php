<?php
/**
 * Base Model Interface
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Models;

/**
 * Interface for all model classes to ensure consistent data handling
 */
interface ModelInterface {
    
    /**
     * Validate the model data
     *
     * @return bool True if valid, false otherwise
     */
    public function validate(): bool;
    
    /**
     * Get validation errors
     *
     * @return array Array of validation error messages
     */
    public function get_validation_errors(): array;
    
    /**
     * Convert model to array
     *
     * @return array Model data as associative array
     */
    public function to_array(): array;
    
    /**
     * Create model from array data
     *
     * @param array $data Input data
     * @return static New model instance
     */
    public static function from_array(array $data): self;
    
    /**
     * Convert model to JSON string
     *
     * @return string JSON representation
     */
    public function to_json(): string;
    
    /**
     * Create model from JSON string
     *
     * @param string $json JSON string
     * @return static New model instance
     */
    public static function from_json(string $json): self;
}