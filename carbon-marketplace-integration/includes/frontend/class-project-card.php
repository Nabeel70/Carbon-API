<?php
/**
 * Project Card Component
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Frontend;

use CarbonMarketplace\Models\Project;

/**
 * Project Card class for individual project display
 */
class ProjectCard {
    
    /**
     * Project instance
     *
     * @var Project
     */
    private $project;
    
    /**
     * Card configuration
     *
     * @var array
     */
    private $config;
    
    /**
     * Constructor
     *
     * @param Project $project Project instance
     * @param array $config Card configuration
     */
    public function __construct(Project $project, $config = []) {
        $this->project = $project;
        $this->config = wp_parse_args($config, [
            'show_image' => true,
            'show_price' => true,
            'show_location' => true,
            'show_type' => true,
            'show_availability' => true,
            'show_description' => true,
            'description_length' => 150,
            'lazy_load_images' => true,
        ]);
    }
    
    /**
     * Render the project card
     *
     * @return string HTML output
     */
    public function render() {
        $project_data = $this->project->to_array();
        
        $html = '<div class="carbon-project-card" data-project-id="' . esc_attr($project_data['id']) . '">';
        
        // Card header with image
        if ($this->config['show_image']) {
            $html .= $this->render_card_image();
        }
        
        // Card content
        $html .= '<div class="card-content">';
        
        // Project title
        $html .= '<h3 class="project-title">' . esc_html($project_data['name']) . '</h3>';
        
        // Project metadata
        $html .= '<div class="project-meta">';
        
        if ($this->config['show_location'] && !empty($project_data['location'])) {
            $html .= '<span class="project-location">';
            $html .= '<i class="icon-location"></i>';
            $html .= esc_html($project_data['location']);
            $html .= '</span>';
        }
        
        if ($this->config['show_type'] && !empty($project_data['project_type'])) {
            $html .= '<span class="project-type">';
            $html .= '<i class="icon-type"></i>';
            $html .= esc_html(ucwords(str_replace('_', ' ', $project_data['project_type'])));
            $html .= '</span>';
        }
        
        $html .= '</div>'; // Close project-meta
        
        // Project description
        if ($this->config['show_description'] && !empty($project_data['description'])) {
            $html .= $this->render_description($project_data['description']);
        }
        
        // Project details
        $html .= '<div class="project-details">';
        
        // Methodology
        if (!empty($project_data['methodology'])) {
            $html .= '<div class="detail-item">';
            $html .= '<span class="detail-label">Methodology:</span>';
            $html .= '<span class="detail-value">' . esc_html($project_data['methodology']) . '</span>';
            $html .= '</div>';
        }
        
        // Vendor
        $html .= '<div class="detail-item">';
        $html .= '<span class="detail-label">Vendor:</span>';
        $html .= '<span class="detail-value vendor-' . esc_attr($project_data['vendor']) . '">';
        $html .= esc_html(ucfirst($project_data['vendor']));
        $html .= '</span>';
        $html .= '</div>';
        
        $html .= '</div>'; // Close project-details
        
        // Card footer
        $html .= '<div class="card-footer">';
        
        // Price and availability
        if ($this->config['show_price'] || $this->config['show_availability']) {
            $html .= '<div class="price-availability">';
            
            if ($this->config['show_price'] && isset($project_data['price_per_kg'])) {
                $html .= '<div class="project-price">';
                $html .= '<span class="price-label">Price:</span>';
                $html .= '<span class="price-value">$' . number_format($project_data['price_per_kg'], 2) . '/tCO₂</span>';
                $html .= '</div>';
            }
            
            if ($this->config['show_availability']) {
                $html .= $this->render_availability_status();
            }
            
            $html .= '</div>'; // Close price-availability
        }
        
        // Action buttons
        $html .= '<div class="card-actions">';
        $html .= '<button class="btn btn-primary view-details" data-project-id="' . esc_attr($project_data['id']) . '">';
        $html .= 'View Details';
        $html .= '</button>';
        
        if ($this->is_purchasable()) {
            $html .= '<button class="btn btn-secondary get-quote" data-project-id="' . esc_attr($project_data['id']) . '">';
            $html .= 'Get Quote';
            $html .= '</button>';
        }
        
        $html .= '</div>'; // Close card-actions
        
        $html .= '</div>'; // Close card-footer
        
        $html .= '</div>'; // Close card-content
        
        $html .= '</div>'; // Close carbon-project-card
        
        return $html;
    }
    
    /**
     * Render card image
     *
     * @return string HTML output
     */
    private function render_card_image() {
        $project_data = $this->project->to_array();
        $images = $project_data['images'] ?? [];
        
        $html = '<div class="card-image">';
        
        if (!empty($images) && is_array($images)) {
            $image_url = $images[0]; // Use first image
            $lazy_class = $this->config['lazy_load_images'] ? ' lazy-load' : '';
            
            if ($this->config['lazy_load_images']) {
                $html .= '<img class="project-image' . $lazy_class . '" ';
                $html .= 'data-src="' . esc_url($image_url) . '" ';
                $html .= 'src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 300 200\'%3E%3C/svg%3E" ';
                $html .= 'alt="' . esc_attr($project_data['name']) . '">';
            } else {
                $html .= '<img class="project-image" ';
                $html .= 'src="' . esc_url($image_url) . '" ';
                $html .= 'alt="' . esc_attr($project_data['name']) . '">';
            }
        } else {
            // Default placeholder image
            $html .= '<div class="project-image-placeholder">';
            $html .= '<i class="icon-image"></i>';
            $html .= '<span>No Image Available</span>';
            $html .= '</div>';
        }
        
        // Project type badge
        if (!empty($project_data['project_type'])) {
            $html .= '<div class="project-type-badge">';
            $html .= esc_html(ucwords(str_replace('_', ' ', $project_data['project_type'])));
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render project description
     *
     * @param string $description Full description
     * @return string HTML output
     */
    private function render_description($description) {
        $truncated = $this->truncate_text($description, $this->config['description_length']);
        
        $html = '<div class="project-description">';
        $html .= '<p>' . esc_html($truncated) . '</p>';
        
        if (strlen($description) > $this->config['description_length']) {
            $html .= '<button class="read-more-btn" data-full-text="' . esc_attr($description) . '">';
            $html .= 'Read More';
            $html .= '</button>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render availability status
     *
     * @return string HTML output
     */
    private function render_availability_status() {
        $project_data = $this->project->to_array();
        $available_quantity = $project_data['available_quantity'] ?? 0;
        
        $html = '<div class="availability-status">';
        
        if ($available_quantity > 0) {
            $html .= '<span class="status-indicator available"></span>';
            $html .= '<span class="status-text">Available</span>';
            
            if ($available_quantity < 1000) {
                $html .= '<span class="quantity-warning">Limited Stock</span>';
            }
        } else {
            $html .= '<span class="status-indicator unavailable"></span>';
            $html .= '<span class="status-text">Sold Out</span>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Check if project is purchasable
     *
     * @return bool
     */
    private function is_purchasable() {
        $project_data = $this->project->to_array();
        $available_quantity = $project_data['available_quantity'] ?? 0;
        $price = $project_data['price_per_kg'] ?? 0;
        
        return $available_quantity > 0 && $price > 0;
    }
    
    /**
     * Truncate text to specified length
     *
     * @param string $text Text to truncate
     * @param int $length Maximum length
     * @return string Truncated text
     */
    private function truncate_text($text, $length) {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        $truncated = substr($text, 0, $length);
        $last_space = strrpos($truncated, ' ');
        
        if ($last_space !== false) {
            $truncated = substr($truncated, 0, $last_space);
        }
        
        return $truncated . '...';
    }
    
    /**
     * Get project data for JavaScript
     *
     * @return array
     */
    public function get_js_data() {
        $project_data = $this->project->to_array();
        
        return [
            'id' => $project_data['id'],
            'name' => $project_data['name'],
            'vendor' => $project_data['vendor'],
            'price_per_kg' => $project_data['price_per_kg'] ?? 0,
            'available_quantity' => $project_data['available_quantity'] ?? 0,
            'is_purchasable' => $this->is_purchasable(),
        ];
    }
}