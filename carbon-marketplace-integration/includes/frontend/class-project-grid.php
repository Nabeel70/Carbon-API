<?php
/**
 * Project Grid Component
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Frontend;

use CarbonMarketplace\Models\Project;

/**
 * Project Grid class for displaying search results
 */
class ProjectGrid {
    
    /**
     * Grid configuration
     *
     * @var array
     */
    private $config;
    
    /**
     * Constructor
     *
     * @param array $config Grid configuration
     */
    public function __construct($config = []) {
        $this->config = wp_parse_args($config, [
            'columns' => 3,
            'show_pagination' => true,
            'items_per_page' => 12,
            'show_filters' => true,
            'lazy_load' => true,
            'infinite_scroll' => false,
        ]);
    }
    
    /**
     * Render the project grid
     *
     * @param array $projects Array of Project objects
     * @param array $pagination Pagination info
     * @return string HTML output
     */
    public function render($projects, $pagination = []) {
        if (empty($projects)) {
            return $this->render_empty_state();
        }
        
        $html = '<div class="carbon-project-grid" data-columns="' . esc_attr($this->config['columns']) . '">';
        
        // Add filters if enabled
        if ($this->config['show_filters']) {
            $html .= $this->render_filters();
        }
        
        // Add grid container
        $html .= '<div class="project-grid-container">';
        
        foreach ($projects as $project) {
            $html .= $this->render_project_card($project);
        }
        
        $html .= '</div>'; // Close grid container
        
        // Add pagination if enabled
        if ($this->config['show_pagination'] && !empty($pagination)) {
            $html .= $this->render_pagination($pagination);
        }
        
        // Add infinite scroll trigger if enabled
        if ($this->config['infinite_scroll']) {
            $html .= '<div class="infinite-scroll-trigger" style="height: 1px;"></div>';
        }
        
        $html .= '</div>'; // Close main container
        
        return $html;
    }
    
    /**
     * Render a single project card
     *
     * @param Project $project Project object
     * @return string HTML output
     */
    private function render_project_card($project) {
        $card = new ProjectCard($project);
        return $card->render();
    }
    
    /**
     * Render filter controls
     *
     * @return string HTML output
     */
    private function render_filters() {
        $html = '<div class="project-grid-filters">';
        $html .= '<div class="filter-group">';
        
        // Location filter
        $html .= '<select name="location" class="filter-select" data-filter="location">';
        $html .= '<option value="">All Locations</option>';
        $html .= '<option value="brazil">Brazil</option>';
        $html .= '<option value="indonesia">Indonesia</option>';
        $html .= '<option value="peru">Peru</option>';
        $html .= '<option value="colombia">Colombia</option>';
        $html .= '</select>';
        
        // Project type filter
        $html .= '<select name="project_type" class="filter-select" data-filter="project_type">';
        $html .= '<option value="">All Project Types</option>';
        $html .= '<option value="forestry">Forestry</option>';
        $html .= '<option value="renewable_energy">Renewable Energy</option>';
        $html .= '<option value="agriculture">Agriculture</option>';
        $html .= '<option value="waste_management">Waste Management</option>';
        $html .= '</select>';
        
        // Price range filter
        $html .= '<div class="price-range-filter">';
        $html .= '<label>Price Range ($/tCO2)</label>';
        $html .= '<input type="range" name="min_price" min="0" max="100" value="0" class="price-slider" data-filter="min_price">';
        $html .= '<input type="range" name="max_price" min="0" max="100" value="100" class="price-slider" data-filter="max_price">';
        $html .= '<div class="price-display">$<span id="min-price-display">0</span> - $<span id="max-price-display">100</span></div>';
        $html .= '</div>';
        
        // Sort options
        $html .= '<select name="sort_by" class="filter-select" data-filter="sort_by">';
        $html .= '<option value="name">Sort by Name</option>';
        $html .= '<option value="price_asc">Price: Low to High</option>';
        $html .= '<option value="price_desc">Price: High to Low</option>';
        $html .= '<option value="location">Location</option>';
        $html .= '</select>';
        
        $html .= '</div>'; // Close filter-group
        $html .= '</div>'; // Close project-grid-filters
        
        return $html;
    }
    
    /**
     * Render pagination controls
     *
     * @param array $pagination Pagination info
     * @return string HTML output
     */
    private function render_pagination($pagination) {
        $current_page = $pagination['current_page'] ?? 1;
        $total_pages = $pagination['total_pages'] ?? 1;
        $total_count = $pagination['total_count'] ?? 0;
        
        if ($total_pages <= 1) {
            return '';
        }
        
        $html = '<div class="project-grid-pagination">';
        $html .= '<div class="pagination-info">';
        $html .= sprintf('Showing page %d of %d (%d total projects)', $current_page, $total_pages, $total_count);
        $html .= '</div>';
        
        $html .= '<div class="pagination-controls">';
        
        // Previous button
        if ($current_page > 1) {
            $html .= '<button class="pagination-btn prev-btn" data-page="' . ($current_page - 1) . '">Previous</button>';
        }
        
        // Page numbers
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        if ($start_page > 1) {
            $html .= '<button class="pagination-btn page-btn" data-page="1">1</button>';
            if ($start_page > 2) {
                $html .= '<span class="pagination-ellipsis">...</span>';
            }
        }
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active_class = ($i === $current_page) ? ' active' : '';
            $html .= '<button class="pagination-btn page-btn' . $active_class . '" data-page="' . $i . '">' . $i . '</button>';
        }
        
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $html .= '<span class="pagination-ellipsis">...</span>';
            }
            $html .= '<button class="pagination-btn page-btn" data-page="' . $total_pages . '">' . $total_pages . '</button>';
        }
        
        // Next button
        if ($current_page < $total_pages) {
            $html .= '<button class="pagination-btn next-btn" data-page="' . ($current_page + 1) . '">Next</button>';
        }
        
        $html .= '</div>'; // Close pagination-controls
        $html .= '</div>'; // Close project-grid-pagination
        
        return $html;
    }
    
    /**
     * Render empty state when no projects found
     *
     * @return string HTML output
     */
    private function render_empty_state() {
        $html = '<div class="project-grid-empty">';
        $html .= '<div class="empty-state-content">';
        $html .= '<h3>No Projects Found</h3>';
        $html .= '<p>We couldn\'t find any carbon offset projects matching your criteria.</p>';
        $html .= '<div class="empty-state-suggestions">';
        $html .= '<h4>Try:</h4>';
        $html .= '<ul>';
        $html .= '<li>Adjusting your search filters</li>';
        $html .= '<li>Expanding your location criteria</li>';
        $html .= '<li>Increasing your price range</li>';
        $html .= '<li>Browsing all available projects</li>';
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '<button class="btn-clear-filters">Clear All Filters</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get grid configuration
     *
     * @return array
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Update grid configuration
     *
     * @param array $config New configuration
     */
    public function set_config($config) {
        $this->config = wp_parse_args($config, $this->config);
    }
    
    /**
     * Enqueue required scripts and styles
     */
    public static function enqueue_assets() {
        wp_enqueue_style(
            'carbon-project-grid',
            plugin_dir_url(__FILE__) . '../../assets/css/project-grid.css',
            [],
            '1.0.0'
        );
        
        wp_enqueue_script(
            'carbon-project-grid',
            plugin_dir_url(__FILE__) . '../../assets/js/project-grid.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('carbon-project-grid', 'carbonProjectGrid', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('carbon_marketplace_search'),
        ]);
    }
}