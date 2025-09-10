<?php
/**
 * Abstract Base Model Class
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Models;

/**
 * Abstract base class providing common model functionality
 */
abstract class BaseModel implements ModelInterface {
    
    /**
     * Validation errors
     *
     * @var array
     */
    protected $validation_errors = [];
    
    /**
     * Get validation errors
     *
     * @return array Array of validation error messages
     */
    public function get_validation_errors(): array {
        return $this->validation_errors;
    }
    
    /**
     * Add validation error
     *
     * @param string $field Field name
     * @param string $message Error message
     */
    protected function add_validation_error(string $field, string $message): void {
        $this->validation_errors[$field] = $message;
    }
    
    /**
     * Clear validation errors
     */
    protected function clear_validation_errors(): void {
        $this->validation_errors = [];
    }
    
    /**
     * Convert model to JSON string
     *
     * @return string JSON representation
     */
    public function to_json(): string {
        return wp_json_encode($this->to_array());
    }
    
    /**
     * Create model from JSON string
     *
     * @param string $json JSON string
     * @return static New model instance
     * @throws \InvalidArgumentException If JSON is invalid
     */
    public static function from_json(string $json): self {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        
        return static::from_array($data);
    }
    
    /**
     * Validate required field
     *
     * @param mixed $value Field value
     * @param string $field_name Field name for error messages
     * @return bool True if valid
     */
    protected function validate_required($value, string $field_name): bool {
        if (empty($value) && $value !== 0 && $value !== '0') {
            $this->add_validation_error($field_name, sprintf('%s is required', $field_name));
            return false;
        }
        return true;
    }
    
    /**
     * Validate string field
     *
     * @param mixed $value Field value
     * @param string $field_name Field name for error messages
     * @param int $max_length Maximum length (optional)
     * @return bool True if valid
     */
    protected function validate_string($value, string $field_name, int $max_length = null): bool {
        if (!is_string($value)) {
            $this->add_validation_error($field_name, sprintf('%s must be a string', $field_name));
            return false;
        }
        
        if ($max_length && strlen($value) > $max_length) {
            $this->add_validation_error($field_name, sprintf('%s must be %d characters or less', $field_name, $max_length));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate numeric field
     *
     * @param mixed $value Field value
     * @param string $field_name Field name for error messages
     * @param float $min_value Minimum value (optional)
     * @param float $max_value Maximum value (optional)
     * @return bool True if valid
     */
    protected function validate_numeric($value, string $field_name, float $min_value = null, float $max_value = null): bool {
        if (!is_numeric($value)) {
            $this->add_validation_error($field_name, sprintf('%s must be numeric', $field_name));
            return false;
        }
        
        $numeric_value = (float) $value;
        
        if ($min_value !== null && $numeric_value < $min_value) {
            $this->add_validation_error($field_name, sprintf('%s must be at least %s', $field_name, $min_value));
            return false;
        }
        
        if ($max_value !== null && $numeric_value > $max_value) {
            $this->add_validation_error($field_name, sprintf('%s must be at most %s', $field_name, $max_value));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate email field
     *
     * @param mixed $value Field value
     * @param string $field_name Field name for error messages
     * @return bool True if valid
     */
    protected function validate_email($value, string $field_name): bool {
        if (!is_email($value)) {
            $this->add_validation_error($field_name, sprintf('%s must be a valid email address', $field_name));
            return false;
        }
        return true;
    }
    
    /**
     * Validate URL field
     *
     * @param mixed $value Field value
     * @param string $field_name Field name for error messages
     * @return bool True if valid
     */
    protected function validate_url($value, string $field_name): bool {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->add_validation_error($field_name, sprintf('%s must be a valid URL', $field_name));
            return false;
        }
        return true;
    }
}