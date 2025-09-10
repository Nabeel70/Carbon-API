<?php
/**
 * Portfolio Model Class
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Models;

/**
 * Portfolio model representing a collection of carbon offset projects
 */
class Portfolio extends BaseModel {
    
    /**
     * Portfolio ID
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
     * Portfolio name
     *
     * @var string
     */
    public $name;
    
    /**
     * Portfolio description
     *
     * @var string
     */
    public $description;
    
    /**
     * Array of project IDs or Project objects
     *
     * @var array
     */
    public $projects;
    
    /**
     * Base price per kilogram
     *
     * @var float
     */
    public $base_price_per_kg;
    
    /**
     * Whether portfolio is active
     *
     * @var bool
     */
    public $is_active;
    
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
     * @param array $data Portfolio data
     */
    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? '';
        $this->vendor = $data['vendor'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->projects = $data['projects'] ?? [];
        $this->base_price_per_kg = isset($data['base_price_per_kg']) ? (float) $data['base_price_per_kg'] : 0.0;
        $this->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : true;
        $this->metadata = $data['metadata'] ?? [];
        
        // Handle DateTime objects
        $this->created_at = $this->parse_datetime($data['created_at'] ?? null);
        $this->updated_at = $this->parse_datetime($data['updated_at'] ?? null);
    }
    
    /**
     * Validate the portfolio data
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
        
        // Numeric validations
        if (!$this->validate_numeric($this->base_price_per_kg, 'base_price_per_kg', 0)) {
            $is_valid = false;
        }
        
        // Array validation
        if (!is_array($this->projects)) {
            $this->add_validation_error('projects', 'Projects must be an array');
            $is_valid = false;
        }
        
        // Boolean validation
        if (!is_bool($this->is_active)) {
            $this->add_validation_error('is_active', 'is_active must be a boolean');
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
        $projects_array = [];
        
        // Convert Project objects to arrays if needed
        foreach ($this->projects as $project) {
            if ($project instanceof Project) {
                $projects_array[] = $project->to_array();
            } else {
                $projects_array[] = $project;
            }
        }
        
        return [
            'id' => $this->id,
            'vendor' => $this->vendor,
            'name' => $this->name,
            'description' => $this->description,
            'projects' => $projects_array,
            'base_price_per_kg' => $this->base_price_per_kg,
            'is_active' => $this->is_active,
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
     * Add project to portfolio
     *
     * @param Project|string $project Project object or ID
     */
    public function add_project($project): void {
        $this->projects[] = $project;
    }
    
    /**
     * Remove project from portfolio
     *
     * @param string $project_id Project ID to remove
     */
    public function remove_project(string $project_id): void {
        $this->projects = array_filter($this->projects, function($project) use ($project_id) {
            if ($project instanceof Project) {
                return $project->id !== $project_id;
            }
            return $project !== $project_id;
        });
        
        // Re-index array
        $this->projects = array_values($this->projects);
    }
    
    /**
     * Get project count
     *
     * @return int Number of projects in portfolio
     */
    public function get_project_count(): int {
        return count($this->projects);
    }
    
    /**
     * Get formatted base price
     *
     * @param string $currency Currency symbol
     * @return string Formatted price string
     */
    public function get_formatted_base_price(string $currency = '$'): string {
        return sprintf('%s%.2f/kg', $currency, $this->base_price_per_kg);
    }
    
    /**
     * Check if portfolio has projects
     *
     * @return bool True if portfolio has projects
     */
    public function has_projects(): bool {
        return !empty($this->projects);
    }
    
    /**
     * Get portfolio ID
     *
     * @return string Portfolio ID
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
     * Get portfolio name
     *
     * @return string Portfolio name
     */
    public function get_name(): string {
        return $this->name;
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
     * Get portfolio summary for display
     *
     * @return array Summary data
     */
    public function get_summary(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'vendor' => $this->vendor,
            'project_count' => $this->get_project_count(),
            'base_price_per_kg' => $this->base_price_per_kg,
            'formatted_base_price' => $this->get_formatted_base_price(),
            'is_active' => $this->is_active,
        ];
    }
}