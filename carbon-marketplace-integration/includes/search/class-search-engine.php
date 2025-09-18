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
        
        // SDG filter
        if (!empty($filters['sdgs'])) {
            $filtered = $this->filter_by_sdgs($filtered, $filters['sdgs']);
        }
        
        return $filtered;
    }
    
    /**
     * Filter projects by keyword
     *
     * @param array $projects Array of projects
     * @param string $keyword Search keyword
     * @return array Filtered projects
     */
    private function filter_by_keyword(array $projects, string $keyword): array {
        $keyword = strtolower(trim($keyword));
        
        return array_filter($projects, function($project) use ($keyword) {
            $searchable_text = strtolower(
                $project->get_name() . ' ' . 
                $project->get_description() . ' ' . 
                $project->get_methodology()
            );
            
            return strpos($searchable_text, $keyword) !== false;
        });
    }
    
    /**
     * Filter projects by location
     *
     * @param array $projects Array of projects
     * @param string $location Location filter
     * @return array Filtered projects
     */
    private function filter_by_location(array $projects, string $location): array {
        $location = strtolower(trim($location));
        
        return array_filter($projects, function($project) use ($location) {
            $project_location = strtolower($project->get_location());
            return strpos($project_location, $location) !== false;
        });
    }
    
    /**
     * Filter projects by project type
     *
     * @param array $projects Array of projects
     * @param string $project_type Project type filter
     * @return array Filtered projects
     */
    private function filter_by_project_type(array $projects, string $project_type): array {
        $project_type = strtolower(trim($project_type));
        
        return array_filter($projects, function($project) use ($project_type) {
            $type = strtolower($project->get_project_type());
            return strpos($type, $project_type) !== false;
        });
    }
    
    /**
     * Filter projects by price range
     *
     * @param array $projects Array of projects
     * @param float|null $min_price Minimum price
     * @param float|null $max_price Maximum price
     * @return array Filtered projects
     */
    private function filter_by_price_range(array $projects, ?float $min_price, ?float $max_price): array {
        return array_filter($projects, function($project) use ($min_price, $max_price) {
            $price = $project->get_price_per_kg();
            
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
     * Filter projects by SDGs
     *
     * @param array $projects Array of projects
     * @param array $sdgs SDG filter
     * @return array Filtered projects
     */
    private function filter_by_sdgs(array $projects, array $sdgs): array {
        return array_filter($projects, function($project) use ($sdgs) {
            $project_sdgs = $project->get_sdgs();
            return !empty(array_intersect($sdgs, $project_sdgs));
        });
    }
    
    /**
     * Sort projects
     *
     * @param array $projects Array of projects
     * @param string $sort_by Sort field
     * @param string $sort_order Sort order (ASC|DESC)
     * @return array Sorted projects
     */
    private function sort_projects(array $projects, string $sort_by = 'name', string $sort_order = 'ASC'): array {
        usort($projects, function($a, $b) use ($sort_by, $sort_order) {
            $result = 0;
            
            switch ($sort_by) {
                case 'price':
                    $result = $a->get_price_per_kg() <=> $b->get_price_per_kg();
                    break;
                case 'location':
                    $result = strcasecmp($a->get_location(), $b->get_location());
                    break;
                case 'project_type':
                    $result = strcasecmp($a->get_project_type(), $b->get_project_type());
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
}
     * Index projects for search
     *
     * @param array $projects Array of project data
     * @return bool True on success, false on failure
     */
    public function index_projects(array $projects): bool {
        try {
            $search_index = [];
            
            foreach ($projects as $project_data) {
                $project = $project_data instanceof Project ? $project_data : Project::from_array($project_data);
                
                if (!$project->validate()) {
                    continue;
                }
                
                $search_index[$project->get_id()] = $this->create_search_document($project);
            }
            
            // Store index in WordPress transients
            return set_transient($this->index_cache_key, $search_index, $this->index_cache_ttl);
            
        } catch (\Exception $e) {
            error_log('SearchEngine::index_projects error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Search projects based on query
     *
     * @param SearchQuery $query Search query
     * @return SearchResults Search results
     */
    public function search(SearchQuery $query): SearchResults {
        try {
            if (!$query->validate()) {
                return new SearchResults([], 0, $query->get_validation_errors());
            }
            
            // Get projects from database with basic filtering
            $filters = $query->get_active_filters();
            $projects_data = $this->database->search_projects(
                $filters,
                $query->limit,
                $query->offset,
                $query->sort_by,
                strtoupper($query->sort_order)
            );
            
            // Convert to Project objects
            $projects = array_map(function($data) {
                return Project::from_array($data);
            }, $projects_data);
            
            // Apply additional filtering and ranking
            $filtered_projects = $this->apply_filters($projects, $filters);
            $ranked_projects = $this->rank_results($filtered_projects, $query);
            
            // Get total count for pagination
            $total_count = $this->get_total_count($filters);
            
            return new SearchResults($ranked_projects, $total_count);
            
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
    public function apply_filters(array $projects, array $filters): array {
        $filtered = $projects;
        
        // Keyword search (additional to database search)
        if (!empty($filters['keyword'])) {
            $filtered = $this->filter_by_keyword($filtered, $filters['keyword']);
        }
        
        // Location filter (additional fuzzy matching)
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
        
        // Vendor filter
        if (!empty($filters['vendor'])) {
            $filtered = $this->filter_by_vendor($filtered, $filters['vendor']);
        }
        
        // Only show available projects
        $filtered = array_filter($filtered, function($project) {
            return $project->is_available();
        });
        
        return array_values($filtered);
    }
    
    /**
     * Rank search results
     *
     * @param array $projects Array of Project objects
     * @param SearchQuery $query Search query
     * @return array Ranked projects
     */
    public function rank_results(array $projects, SearchQuery $query): array {
        if (empty($projects) || empty($query->keyword)) {
            return $projects;
        }
        
        $keyword = strtolower($query->keyword);
        
        // Calculate relevance scores
        $scored_projects = array_map(function($project) use ($keyword) {
            $score = $this->calculate_relevance_score($project, $keyword);
            return ['project' => $project, 'score' => $score];
        }, $projects);
        
        // Sort by relevance score (descending)
        usort($scored_projects, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Extract projects from scored results
        return array_map(function($item) {
            return $item['project'];
        }, $scored_projects);
    }
    
    /**
     * Get total count of projects matching filters
     *
     * @param array $filters Filter criteria
     * @return int Total count
     */
    public function get_total_count(array $filters): int {
        try {
            // Use database to get count efficiently
            global $wpdb;
            $table_name = $this->database->get_projects_table();
            
            $where_clauses = [];
            $where_values = [];
            
            // Build WHERE clauses (same logic as database search)
            if (!empty($filters['keyword'])) {
                $where_clauses[] = "(name LIKE %s OR description LIKE %s OR location LIKE %s)";
                $keyword = '%' . $wpdb->esc_like($filters['keyword']) . '%';
                $where_values[] = $keyword;
                $where_values[] = $keyword;
                $where_values[] = $keyword;
            }
            
            if (!empty($filters['location'])) {
                $where_clauses[] = "location LIKE %s";
                $where_values[] = '%' . $wpdb->esc_like($filters['location']) . '%';
            }
            
            if (!empty($filters['project_type'])) {
                $where_clauses[] = "project_type = %s";
                $where_values[] = $filters['project_type'];
            }
            
            if (!empty($filters['vendor'])) {
                $where_clauses[] = "vendor = %s";
                $where_values[] = $filters['vendor'];
            }
            
            if (isset($filters['min_price'])) {
                $where_clauses[] = "price_per_kg >= %f";
                $where_values[] = $filters['min_price'];
            }
            
            if (isset($filters['max_price'])) {
                $where_clauses[] = "price_per_kg <= %f";
                $where_values[] = $filters['max_price'];
            }
            
            // Add availability filter
            $where_clauses[] = "available_quantity > 0";
            
            $sql = "SELECT COUNT(*) FROM $table_name";
            
            if (!empty($where_clauses)) {
                $sql .= " WHERE " . implode(' AND ', $where_clauses);
            }
            
            if (!empty($where_values)) {
                $query = $wpdb->prepare($sql, $where_values);
            } else {
                $query = $sql;
            }
            
            return (int) $wpdb->get_var($query);
            
        } catch (\Exception $e) {
            error_log('SearchEngine::get_total_count error: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Create search document for a project
     *
     * @param Project $project Project object
     * @return array Search document
     */
    private function create_search_document(Project $project): array {
        return [
            'id' => $project->get_id(),
            'vendor' => $project->get_vendor(),
            'name' => $project->get_name(),
            'description' => $project->description,
            'location' => $project->get_location(),
            'project_type' => $project->get_project_type(),
            'methodology' => $project->methodology,
            'price_per_kg' => $project->price_per_kg,
            'available_quantity' => $project->get_available_quantity(),
            'searchable_text' => $this->create_searchable_text($project),
        ];
    }
    
    /**
     * Create searchable text for a project
     *
     * @param Project $project Project object
     * @return string Searchable text
     */
    private function create_searchable_text(Project $project): string {
        $text_parts = [
            $project->get_name(),
            $project->description,
            $project->get_location(),
            $project->get_project_type(),
            $project->methodology,
        ];
        
        return strtolower(implode(' ', array_filter($text_parts)));
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
            $searchable_text = $this->create_searchable_text($project);
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
     * Filter projects by vendor
     *
     * @param array $projects Array of Project objects
     * @param string $vendor Vendor filter
     * @return array Filtered projects
     */
    private function filter_by_vendor(array $projects, string $vendor): array {
        return array_filter($projects, function($project) use ($vendor) {
            return strcasecmp($project->get_vendor(), $vendor) === 0;
        });
    }
    
    /**
     * Calculate relevance score for a project
     *
     * @param Project $project Project object
     * @param string $keyword Search keyword
     * @return float Relevance score
     */
    private function calculate_relevance_score(Project $project, string $keyword): float {
        $score = 0.0;
        
        // Name match (highest weight)
        if (stripos($project->get_name(), $keyword) !== false) {
            $score += 10.0;
            // Exact match bonus
            if (strcasecmp($project->get_name(), $keyword) === 0) {
                $score += 5.0;
            }
        }
        
        // Location match
        if (stripos($project->get_location(), $keyword) !== false) {
            $score += 5.0;
        }
        
        // Project type match
        if (stripos($project->get_project_type(), $keyword) !== false) {
            $score += 3.0;
        }
        
        // Description match
        if (stripos($project->description, $keyword) !== false) {
            $score += 2.0;
        }
        
        // Methodology match
        if (stripos($project->methodology, $keyword) !== false) {
            $score += 1.0;
        }
        
        // Availability bonus
        if ($project->is_available()) {
            $score += 1.0;
        }
        
        return $score;
    }
    
    /**
     * Clear search index cache
     *
     * @return bool True on success
     */
    public function clear_index(): bool {
        return delete_transient($this->index_cache_key);
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
            
            global $wpdb;
            $table_name = $this->database->get_projects_table();
            
            $query = $wpdb->prepare(
                "SELECT DISTINCT name, location, project_type 
                 FROM $table_name 
                 WHERE (name LIKE %s OR location LIKE %s OR project_type LIKE %s)
                 AND available_quantity > 0
                 LIMIT %d",
                '%' . $wpdb->esc_like($partial_input) . '%',
                '%' . $wpdb->esc_like($partial_input) . '%',
                '%' . $wpdb->esc_like($partial_input) . '%',
                $limit
            );
            
            $results = $wpdb->get_results($query, ARRAY_A);
            
            $suggestions = [];
            foreach ($results as $result) {
                if (stripos($result['name'], $partial_input) !== false) {
                    $suggestions[] = $result['name'];
                }
                if (stripos($result['location'], $partial_input) !== false) {
                    $suggestions[] = $result['location'];
                }
                if (stripos($result['project_type'], $partial_input) !== false) {
                    $suggestions[] = $result['project_type'];
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