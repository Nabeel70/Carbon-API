<?php
/**
 * Project Model Class
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Models;

/**
 * Project model representing a carbon offset project
 */
class Project extends BaseModel {
    
    /**
     * Project ID
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
     * Project name
     *
     * @var string
     */
    public $name;
    
    /**
     * Project description
     *
     * @var string
     */
    public $description;
    
    /**
     * Project location
     *
     * @var string
     */
    public $location;
    
    /**
     * Project type
     *
     * @var string
     */
    public $project_type;
    
    /**
     * Methodology used
     *
     * @var string
     */
    public $methodology;
    
    /**
     * Price per kilogram
     *
     * @var float
     */
    public $price_per_kg;
    
    /**
     * Available quantity
     *
     * @var int
     */
    public $available_quantity;
    
    /**
     * Project images
     *
     * @var array
     */
    public $images;
    
    /**
     * Sustainable Development Goals
     *
     * @var array
     */
    public $sdgs;
    
    /**
     * Registry URL
     *
     * @var string
     */
    public $registry_url;
    
    /**
     * Created timestamp
     *
     * @var \DateTime
     */
    public $created_at;
    
    /**
     * Updated timestamp
     *
     * @var \DateTime
     */
    public $updated_at;
    
    /**
     * Additional metadata
     *
     * @var array
     */
    public $metadata;
    
    /**
     * Constructor
     *
     * @param array $data Project data
     */
    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? '';
        $this->vendor = $data['vendor'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->location = $data['location'] ?? '';
        $this->project_type = $data['project_type'] ?? '';
        $this->methodology = $data['methodology'] ?? '';
        $this->price_per_kg = isset($data['price_per_kg']) ? (float) $data['price_per_kg'] : 0.0;
        $this->available_quantity = isset($data['available_quantity']) ? (int) $data['available_quantity'] : 0;
        $this->images = $data['images'] ?? [];
        $this->sdgs = $data['sdgs'] ?? [];
        $this->registry_url = $data['registry_url'] ?? '';
        $this->metadata = $data['metadata'] ?? [];
        
        // Handle DateTime objects
        $this->created_at = $this->parse_datetime($data['created_at'] ?? null);
        $this->updated_at = $this->parse_datetime($data['updated_at'] ?? null);
    }
    
    /**
     * Validate the project data
     *
     * @return bool True if valid, false otherwise
     */
    public function validate(): bool {
        $this->clear_validation_errors();
        
        $is_valid = true;
        
        // Required fields
        if (!$this->validate_required($this->id, 'id')) {
            $is_valid = false;
        }
        
        if (!$this->validate_required($this->vendor, 'vendor')) {
            $is_valid = false;
        }
        
        if (!$this->validate_required($this->name, 'name')) {
            $is_valid = false;
        }
        
        // String validations
        if ($this->id && !$this->validate_string($this->id, 'id', 255)) {
            $is_valid = false;
        }
        
        if ($this->vendor && !$this->validate_string($this->vendor, 'vendor', 50)) {
            $is_valid = false;
        }
        
        if ($this->name && !$this->validate_string($this->name, 'name', 255)) {
            $is_valid = false;
        }
        
        if ($this->location && !$this->validate_string($this->location, 'location', 255)) {
            $is_valid = false;
        }
        
        if ($this->project_type && !$this->validate_string($this->project_type, 'project_type', 100)) {
            $is_valid = false;
        }
        
        if ($this->methodology && !$this->validate_string($this->methodology, 'methodology', 255)) {
            $is_valid = false;
        }
        
        // Numeric validations
        if (!$this->validate_numeric($this->price_per_kg, 'price_per_kg', 0)) {
            $is_valid = false;
        }
        
        if (!$this->validate_numeric($this->available_quantity, 'available_quantity', 0)) {
            $is_valid = false;
        }
        
        // URL validation
        if ($this->registry_url && !$this->validate_url($this->registry_url, 'registry_url')) {
            $is_valid = false;
        }
        
        // Array validations
        if (!is_array($this->images)) {
            $this->add_validation_error('images', 'Images must be an array');
            $is_valid = false;
        }
        
        if (!is_array($this->sdgs)) {
            $this->add_validation_error('sdgs', 'SDGs must be an array');
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
            'id' => $this->id,
            'vendor' => $this->vendor,
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'project_type' => $this->project_type,
            'methodology' => $this->methodology,
            'price_per_kg' => $this->price_per_kg,
            'available_quantity' => $this->available_quantity,
            'images' => $this->images,
            'sdgs' => $this->sdgs,
            'registry_url' => $this->registry_url,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
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
    
    /**
     * Parse datetime from various formats
     *
     * @param mixed $datetime DateTime input
     * @return \DateTime|null Parsed DateTime or null
     */
    private function parse_datetime($datetime): ?\DateTime {
        if ($datetime instanceof \DateTime) {
            return $datetime;
        }
        
        if (is_string($datetime)) {
            try {
                return new \DateTime($datetime);
            } catch (\Exception $e) {
                return null;
            }
        }
        
        return null;
    }
    
    /**
     * Get formatted price
     *
     * @param string $currency Currency symbol
     * @return string Formatted price string
     */
    public function get_formatted_price(string $currency = '$'): string {
        return sprintf('%s%.2f/kg', $currency, $this->price_per_kg);
    }
    
    /**
     * Check if project is available
     *
     * @return bool True if available quantity > 0
     */
    public function is_available(): bool {
        return $this->available_quantity > 0;
    }
    
    /**
     * Get project ID
     *
     * @return string Project ID
     */
    public function get_id(): string {
        return $this->id;
    }
    
    /**
     * Get vendor name
     *
     * @return string Vendor name
     */
    public function get_vendor(): string {
        return $this->vendor;
    }
    
    /**
     * Get project name
     *
     * @return string Project name
     */
    public function get_name(): string {
        return $this->name;
    }
    
    /**
     * Get project location
     *
     * @return string Project location
     */
    public function get_location(): string {
        return $this->location;
    }
    
    /**
     * Get project type
     *
     * @return string Project type
     */
    public function get_project_type(): string {
        return $this->project_type;
    }
    
    /**
     * Get available quantity
     *
     * @return int Available quantity
     */
    public function get_available_quantity(): int {
        return $this->available_quantity;
    }
    
    /**
     * Get metadata
     *
     * @return array Metadata array
     */
    public function get_metadata(): array {
        return $this->metadata ?? [];
    }
    
    /**
     * Set metadata
     *
     * @param array $metadata Metadata array
     */
    public function set_metadata(array $metadata): void {
        $this->metadata = $metadata;
    }

    /**
     * Get project summary for display
     *
     * @return array Summary data
     */
    public function get_summary(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'project_type' => $this->project_type,
            'price_per_kg' => $this->price_per_kg,
            'available' => $this->is_available(),
            'formatted_price' => $this->get_formatted_price(),
        ];
    }
}