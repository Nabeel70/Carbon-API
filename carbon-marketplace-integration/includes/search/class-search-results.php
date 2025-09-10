<?php
/**
 * SearchResults Class
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Search;

use CarbonMarketplace\Models\Project;

/**
 * SearchResults class for encapsulating search results and metadata
 */
class SearchResults {
    
    /**
     * Array of Project objects
     *
     * @var array
     */
    private $projects;
    
    /**
     * Total count of matching projects
     *
     * @var int
     */
    private $total_count;
    
    /**
     * Search errors
     *
     * @var array
     */
    private $errors;
    
    /**
     * Search metadata
     *
     * @var array
     */
    private $metadata;
    
    /**
     * Constructor
     *
     * @param array $projects Array of Project objects
     * @param int $total_count Total count of matching projects
     * @param array $errors Search errors
     * @param array $metadata Additional metadata
     */
    public function __construct(array $projects = [], int $total_count = 0, array $errors = [], array $metadata = []) {
        $this->projects = $projects;
        $this->total_count = $total_count;
        $this->errors = $errors;
        $this->metadata = $metadata;
    }
    
    /**
     * Get projects
     *
     * @return array Array of Project objects
     */
    public function get_projects(): array {
        return $this->projects;
    }
    
    /**
     * Get total count
     *
     * @return int Total count of matching projects
     */
    public function get_total_count(): int {
        return $this->total_count;
    }
    
    /**
     * Get result count (current page)
     *
     * @return int Number of projects in current results
     */
    public function get_result_count(): int {
        return count($this->projects);
    }
    
    /**
     * Check if search has errors
     *
     * @return bool True if there are errors
     */
    public function has_errors(): bool {
        return !empty($this->errors);
    }
    
    /**
     * Get errors
     *
     * @return array Search errors
     */
    public function get_errors(): array {
        return $this->errors;
    }
    
    /**
     * Check if results are empty
     *
     * @return bool True if no results
     */
    public function is_empty(): bool {
        return empty($this->projects);
    }
    
    /**
     * Get metadata
     *
     * @return array Search metadata
     */
    public function get_metadata(): array {
        return $this->metadata;
    }
    
    /**
     * Set metadata
     *
     * @param array $metadata Metadata to set
     */
    public function set_metadata(array $metadata): void {
        $this->metadata = $metadata;
    }
    
    /**
     * Add metadata
     *
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     */
    public function add_metadata(string $key, $value): void {
        $this->metadata[$key] = $value;
    }
    
    /**
     * Get projects as array data
     *
     * @return array Array of project data arrays
     */
    public function get_projects_as_array(): array {
        return array_map(function($project) {
            return $project instanceof Project ? $project->to_array() : $project;
        }, $this->projects);
    }
    
    /**
     * Get pagination info
     *
     * @param int $limit Results per page
     * @param int $offset Current offset
     * @return array Pagination information
     */
    public function get_pagination_info(int $limit, int $offset): array {
        $current_page = floor($offset / $limit) + 1;
        $total_pages = $limit > 0 ? ceil($this->total_count / $limit) : 1;
        
        return [
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'total_count' => $this->total_count,
            'result_count' => $this->get_result_count(),
            'has_next_page' => $current_page < $total_pages,
            'has_previous_page' => $current_page > 1,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }
    
    /**
     * Convert to array for JSON response
     *
     * @param int $limit Results per page
     * @param int $offset Current offset
     * @return array Array representation
     */
    public function to_array(int $limit = 20, int $offset = 0): array {
        return [
            'projects' => $this->get_projects_as_array(),
            'pagination' => $this->get_pagination_info($limit, $offset),
            'errors' => $this->errors,
            'metadata' => $this->metadata,
        ];
    }
    
    /**
     * Get project summaries for quick display
     *
     * @return array Array of project summaries
     */
    public function get_project_summaries(): array {
        return array_map(function($project) {
            return $project instanceof Project ? $project->get_summary() : $project;
        }, $this->projects);
    }
    
    /**
     * Filter results by availability
     *
     * @return SearchResults New SearchResults with only available projects
     */
    public function filter_available(): SearchResults {
        $available_projects = array_filter($this->projects, function($project) {
            return $project instanceof Project ? $project->is_available() : ($project['available_quantity'] ?? 0) > 0;
        });
        
        return new SearchResults(
            array_values($available_projects),
            count($available_projects),
            $this->errors,
            $this->metadata
        );
    }
    
    /**
     * Sort results by field
     *
     * @param string $field Field to sort by
     * @param string $direction Sort direction (asc/desc)
     * @return SearchResults New SearchResults with sorted projects
     */
    public function sort_by(string $field, string $direction = 'asc'): SearchResults {
        $sorted_projects = $this->projects;
        
        usort($sorted_projects, function($a, $b) use ($field, $direction) {
            $value_a = $this->get_sort_value($a, $field);
            $value_b = $this->get_sort_value($b, $field);
            
            $comparison = $value_a <=> $value_b;
            
            return $direction === 'desc' ? -$comparison : $comparison;
        });
        
        return new SearchResults(
            $sorted_projects,
            $this->total_count,
            $this->errors,
            $this->metadata
        );
    }
    
    /**
     * Get sort value for a project
     *
     * @param Project|array $project Project object or array
     * @param string $field Field name
     * @return mixed Sort value
     */
    private function get_sort_value($project, string $field) {
        if ($project instanceof Project) {
            switch ($field) {
                case 'name':
                    return $project->get_name();
                case 'location':
                    return $project->get_location();
                case 'project_type':
                    return $project->get_project_type();
                case 'price_per_kg':
                    return $project->price_per_kg;
                case 'available_quantity':
                    return $project->get_available_quantity();
                case 'created_at':
                    return $project->created_at ? $project->created_at->getTimestamp() : 0;
                default:
                    return '';
            }
        } else {
            return $project[$field] ?? '';
        }
    }
    
    /**
     * Slice results for pagination
     *
     * @param int $offset Starting offset
     * @param int $limit Number of results
     * @return SearchResults New SearchResults with sliced projects
     */
    public function slice(int $offset, int $limit): SearchResults {
        $sliced_projects = array_slice($this->projects, $offset, $limit);
        
        return new SearchResults(
            $sliced_projects,
            $this->total_count,
            $this->errors,
            $this->metadata
        );
    }
}