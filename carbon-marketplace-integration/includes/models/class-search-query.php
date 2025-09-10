<?php
/**
 * SearchQuery Model Class
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Models;

/**
 * SearchQuery model representing search parameters for carbon offset projects
 */
class SearchQuery extends BaseModel {
    
    /**
     * Search keyword
     *
     * @var string|null
     */
    public $keyword;
    
    /**
     * Location filter
     *
     * @var string|null
     */
    public $location;
    
    /**
     * Project type filter
     *
     * @var string|null
     */
    public $project_type;
    
    /**
     * Minimum price filter
     *
     * @var float|null
     */
    public $min_price;
    
    /**
     * Maximum price filter
     *
     * @var float|null
     */
    public $max_price;
    
    /**
     * Vendor filter
     *
     * @var string|null
     */
    public $vendor;
    
    /**
     * Results limit
     *
     * @var int
     */
    public $limit;
    
    /**
     * Results offset
     *
     * @var int
     */
    public $offset;
    
    /**
     * Sort field
     *
     * @var string
     */
    public $sort_by;
    
    /**
     * Sort direction
     *
     * @var string
     */
    public $sort_order;
    
    /**
     * Valid sort fields
     *
     * @var array
     */
    const VALID_SORT_FIELDS = ['name', 'price_per_kg', 'location', 'project_type', 'created_at'];
    
    /**
     * Valid sort orders
     *
     * @var array
     */
    const VALID_SORT_ORDERS = ['asc', 'desc'];
    
    /**
     * Constructor
     *
     * @param array $data Search query data
     */
    public function __construct(array $data = []) {
        $this->keyword = $data['keyword'] ?? null;
        $this->location = $data['location'] ?? null;
        $this->project_type = $data['project_type'] ?? null;
        $this->min_price = isset($data['min_price']) ? (float) $data['min_price'] : null;
        $this->max_price = isset($data['max_price']) ? (float) $data['max_price'] : null;
        $this->vendor = $data['vendor'] ?? null;
        $this->limit = isset($data['limit']) ? (int) $data['limit'] : 20;
        $this->offset = isset($data['offset']) ? (int) $data['offset'] : 0;
        $this->sort_by = $data['sort_by'] ?? 'name';
        $this->sort_order = $data['sort_order'] ?? 'asc';
    }
    
    /**
     * Validate the search query data
     *
     * @return bool True if valid, false otherwise
     */
    public function validate(): bool {
        $this->clear_validation_errors();
        
        $is_valid = true;
        
        // Validate limit
        if (!$this->validate_numeric($this->limit, 'limit', 1, 100)) {
            $is_valid = false;
        }
        
        // Validate offset
        if (!$this->validate_numeric($this->offset, 'offset', 0)) {
            $is_valid = false;
        }
        
        // Validate price range
        if ($this->min_price !== null && !$this->validate_numeric($this->min_price, 'min_price', 0)) {
            $is_valid = false;
        }
        
        if ($this->max_price !== null && !$this->validate_numeric($this->max_price, 'max_price', 0)) {
            $is_valid = false;
        }
        
        // Validate price range logic
        if ($this->min_price !== null && $this->max_price !== null && $this->min_price > $this->max_price) {
            $this->add_validation_error('price_range', 'Minimum price cannot be greater than maximum price');
            $is_valid = false;
        }
        
        // Validate sort field
        if (!in_array($this->sort_by, self::VALID_SORT_FIELDS, true)) {
            $this->add_validation_error('sort_by', 'Invalid sort field');
            $is_valid = false;
        }
        
        // Validate sort order
        if (!in_array($this->sort_order, self::VALID_SORT_ORDERS, true)) {
            $this->add_validation_error('sort_order', 'Invalid sort order');
            $is_valid = false;
        }
        
        // Validate string fields if provided
        if ($this->keyword !== null && !$this->validate_string($this->keyword, 'keyword', 255)) {
            $is_valid = false;
        }
        
        if ($this->location !== null && !$this->validate_string($this->location, 'location', 255)) {
            $is_valid = false;
        }
        
        if ($this->project_type !== null && !$this->validate_string($this->project_type, 'project_type', 100)) {
            $is_valid = false;
        }
        
        if ($this->vendor !== null && !$this->validate_string($this->vendor, 'vendor', 50)) {
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
            'keyword' => $this->keyword,
            'location' => $this->location,
            'project_type' => $this->project_type,
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
            'vendor' => $this->vendor,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'sort_by' => $this->sort_by,
            'sort_order' => $this->sort_order,
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
     * Check if query has filters
     *
     * @return bool True if any filters are set
     */
    public function has_filters(): bool {
        return !empty($this->keyword) || 
               !empty($this->location) || 
               !empty($this->project_type) || 
               $this->min_price !== null || 
               $this->max_price !== null ||
               !empty($this->vendor);
    }
    
    /**
     * Get active filters
     *
     * @return array Array of active filters
     */
    public function get_active_filters(): array {
        $filters = [];
        
        if (!empty($this->keyword)) {
            $filters['keyword'] = $this->keyword;
        }
        
        if (!empty($this->location)) {
            $filters['location'] = $this->location;
        }
        
        if (!empty($this->project_type)) {
            $filters['project_type'] = $this->project_type;
        }
        
        if ($this->min_price !== null) {
            $filters['min_price'] = $this->min_price;
        }
        
        if ($this->max_price !== null) {
            $filters['max_price'] = $this->max_price;
        }
        
        if (!empty($this->vendor)) {
            $filters['vendor'] = $this->vendor;
        }
        
        return $filters;
    }
    
    /**
     * Get pagination info
     *
     * @return array Pagination data
     */
    public function get_pagination(): array {
        return [
            'limit' => $this->limit,
            'offset' => $this->offset,
            'page' => floor($this->offset / $this->limit) + 1,
        ];
    }
    
    /**
     * Get sort info
     *
     * @return array Sort data
     */
    public function get_sort(): array {
        return [
            'sort_by' => $this->sort_by,
            'sort_order' => $this->sort_order,
        ];
    }
    
    /**
     * Create query for next page
     *
     * @return static New query for next page
     */
    public function get_next_page(): self {
        $data = $this->to_array();
        $data['offset'] = $this->offset + $this->limit;
        return static::from_array($data);
    }
    
    /**
     * Create query for previous page
     *
     * @return static New query for previous page
     */
    public function get_previous_page(): self {
        $data = $this->to_array();
        $data['offset'] = max(0, $this->offset - $this->limit);
        return static::from_array($data);
    }
}