<?php
/**
 * SearchEngine Class
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

namespace CarbonMarketplace\Search;

use CarbonMarketplace\Api\ApiManager;
use CarbonMarketplace\Models\Project;
use CarbonMarketplace\Models\SearchQuery;
use CarbonMarketplace\Search\SearchResults;

/**
 * SearchEngine class for searching carbon projects via APIs
 */
class SearchEngine {
    
    /**
     * API Manager instance
     *
     * @var ApiManager
     */
    private $api_manager;
    
    /**
     * Constructor
     *
     * @param ApiManager $api_manager API Manager instance
     */
    public function __construct(ApiManager $api_manager = null) {
        $this->api_manager = $api_manager ?: new ApiManager();
    }
    
    /**
     * Search projects using CNaught API
     *
     * @param SearchQuery $query Search query
     * @return SearchResults Search results
     */
    public function search(SearchQuery $query): SearchResults {
        try {
            if (!$query->validate()) {
                return new SearchResults([], 0, $query->get_validation_errors());
            }

            // Get all portfolios from CNaught API
            $portfolios = $this->api_manager->fetch_all_portfolios();
            
            if (is_wp_error($portfolios)) {
                return new SearchResults([], 0, ['api_error' => $portfolios->get_error_message()]);
            }
            
            // Extract projects from portfolios and apply filters
            $all_projects = [];
            foreach ($portfolios as $portfolio) {
                $projects = $portfolio->get_projects();
                $all_projects = array_merge($all_projects, $projects);
            }
            
            // Apply search filters
            $filtered_projects = $this->apply_filters($all_projects, $query->get_active_filters());
            
            // Apply sorting and pagination
            $sorted_projects = $this->sort_projects($filtered_projects, $query->sort_by, $query->sort_order);
            $paginated_projects = array_slice($sorted_projects, $query->offset, $query->limit);
            
            return new SearchResults($paginated_projects, count($filtered_projects));
            
        } catch (\Exception $e) {
            error_log('SearchEngine::search error: ' . $e->getMessage());
            return new SearchResults([], 0, ['search_error' => 'An error occurred during search']);
        }
    }
    
    /**
     * Apply filters to projects
     *
     * @param array $projects Array of Project objects
     * @param array $filters Filter criteria
     * @return array Filtered projects
     */
    private function apply_filters(array $projects, array $filters): array {
        $filtered = $projects;

        // Keyword search
        if (!empty($filters['keyword'])) {
            $filtered = $this->filter_by_keyword($filtered, $filters['keyword']);
        }

        // Location filter
        if (!empty($filters['location'])) {
            $filtered = $this->filter_by_location($filtered, $filters['location']);
        }

        // Project type filter
        if (!empty($filters['project_type'])) {
            $filtered = $this->filter_by_project_type($filtered, $filters['project_type']);
        }

        // Price range filter
        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $filtered = $this->filter_by_price_range(
                $filtered, 
                $filters['min_price'] ?? null, 
                $filters['max_price'] ?? null
            );
        }

        return array_values($filtered);
    }
    
    /**
     * Filter projects by keyword
     *
     * @param array $projects Array of Project objects
     * @param string $keyword Search keyword
     * @return array Filtered projects
     */
    private function filter_by_keyword(array $projects, string $keyword): array {
        $keyword = strtolower(trim($keyword));
        
        if (empty($keyword)) {
            return $projects;
        }
        
        return array_filter($projects, function($project) use ($keyword) {
            $searchable_text = strtolower(
                $project->get_name() . ' ' . 
                $project->description . ' ' . 
                $project->get_location() . ' ' . 
                $project->get_project_type()
            );
            return strpos($searchable_text, $keyword) !== false;
        });
    }
    
    /**
     * Filter projects by location
     *
     * @param array $projects Array of Project objects
     * @param string $location Location filter
     * @return array Filtered projects
     */
    private function filter_by_location(array $projects, string $location): array {
        $location = strtolower(trim($location));
        
        if (empty($location)) {
            return $projects;
        }
        
        return array_filter($projects, function($project) use ($location) {
            $project_location = strtolower($project->get_location());
            return strpos($project_location, $location) !== false;
        });
    }
    
    /**
     * Filter projects by project type
     *
     * @param array $projects Array of Project objects
     * @param string $project_type Project type filter
     * @return array Filtered projects
     */
    private function filter_by_project_type(array $projects, string $project_type): array {
        return array_filter($projects, function($project) use ($project_type) {
            return strcasecmp($project->get_project_type(), $project_type) === 0;
        });
    }
    
    /**
     * Filter projects by price range
     *
     * @param array $projects Array of Project objects
     * @param float|null $min_price Minimum price
     * @param float|null $max_price Maximum price
     * @return array Filtered projects
     */
    private function filter_by_price_range(array $projects, ?float $min_price, ?float $max_price): array {
        return array_filter($projects, function($project) use ($min_price, $max_price) {
            $price = $project->price_per_kg;
            
            if ($min_price !== null && $price < $min_price) {
                return false;
            }
            
            if ($max_price !== null && $price > $max_price) {
                return false;
            }
            
            return true;
        });
    }
    
    /**
     * Sort projects based on criteria
     *
     * @param array $projects Array of Project objects
     * @param string $sort_by Sort criteria
     * @param string $sort_order Sort order (ASC/DESC)
     * @return array Sorted projects
     */
    private function sort_projects(array $projects, string $sort_by, string $sort_order): array {
        if (empty($projects)) {
            return $projects;
        }
        
        usort($projects, function($a, $b) use ($sort_by, $sort_order) {
            $result = 0;
            
            switch ($sort_by) {
                case 'price':
                    $result = $a->price_per_kg <=> $b->price_per_kg;
                    break;
                case 'location':
                    $result = strcasecmp($a->get_location(), $b->get_location());
                    break;
                case 'project_type':
                    $result = strcasecmp($a->get_project_type(), $b->get_project_type());
                    break;
                case 'available_quantity':
                    $result = $a->get_available_quantity() <=> $b->get_available_quantity();
                    break;
                case 'name':
                default:
                    $result = strcasecmp($a->get_name(), $b->get_name());
                    break;
            }
            
            return $sort_order === 'DESC' ? -$result : $result;
        });
        
        return $projects;
    }
    
    /**
     * Get search suggestions based on partial input
     *
     * @param string $partial_input Partial search input
     * @param int $limit Number of suggestions
     * @return array Array of suggestions
     */
    public function get_suggestions(string $partial_input, int $limit = 10): array {
        try {
            $partial_input = strtolower(trim($partial_input));
            
            if (strlen($partial_input) < 2) {
                return [];
            }
            
            // Get all portfolios for suggestions
            $portfolios = $this->api_manager->fetch_all_portfolios();
            
            if (is_wp_error($portfolios)) {
                return [];
            }
            
            $suggestions = [];
            
            foreach ($portfolios as $portfolio) {
                $projects = $portfolio->get_projects();
                
                foreach ($projects as $project) {
                    // Check project name
                    if (stripos($project->get_name(), $partial_input) !== false) {
                        $suggestions[] = $project->get_name();
                    }
                    
                    // Check location
                    if (stripos($project->get_location(), $partial_input) !== false) {
                        $suggestions[] = $project->get_location();
                    }
                    
                    // Check project type
                    if (stripos($project->get_project_type(), $partial_input) !== false) {
                        $suggestions[] = $project->get_project_type();
                    }
                }
            }
            
            // Remove duplicates and limit results
            $suggestions = array_unique($suggestions);
            return array_slice($suggestions, 0, $limit);
            
        } catch (\Exception $e) {
            error_log('SearchEngine::get_suggestions error: ' . $e->getMessage());
            return [];
        }
    }
}