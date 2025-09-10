<?php
/**
 * Project Detail Page Component
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Frontend;

use CarbonMarketplace\Models\Project;
use CarbonMarketplace\Api\ApiManager;
use CarbonMarketplace\Models\Quote;

/**
 * Project Detail Page class for comprehensive project information display
 */
class ProjectDetailPage {
    
    /**
     * API Manager instance
     *
     * @var ApiManager
     */
    private $api_manager;
    
    /**
     * Page configuration
     *
     * @var array
     */
    private $config;
    
    /**
     * Constructor
     *
     * @param ApiManager $api_manager API Manager instance
     * @param array $config Page configuration
     */
    public function __construct(ApiManager $api_manager, $config = []) {
        $this->api_manager = $api_manager;
        $this->config = wp_parse_args($config, [
            'show_gallery' => true,
            'show_map' => true,
            'show_certificates' => true,
            'show_related_projects' => true,
            'enable_real_time_pricing' => true,
            'default_quantity' => 1,
            'max_quantity' => 1000,
        ]);
    }
    
    /**
     * Render the complete project detail page
     *
     * @param string $project_id Project ID
     * @param string $vendor Vendor name
     * @return string HTML output
     */
    public function render($project_id, $vendor) {
        $project = $this->get_project_details($project_id, $vendor);
        
        if (is_wp_error($project)) {
            return $this->render_error_page($project);
        }
        
        $html = '<div class="carbon-project-detail-page" data-project-id="' . esc_attr($project_id) . '" data-vendor="' . esc_attr($vendor) . '">';
        
        // Project header
        $html .= $this->render_project_header($project);
        
        // Main content area
        $html .= '<div class="project-detail-content">';
        
        // Left column - Project information
        $html .= '<div class="project-info-column">';
        $html .= $this->render_project_overview($project);
        $html .= $this->render_project_details($project);
        $html .= $this->render_project_methodology($project);
        $html .= $this->render_project_certifications($project);
        $html .= '</div>';
        
        // Right column - Purchase options
        $html .= '<div class="project-purchase-column">';
        $html .= $this->render_purchase_widget($project);
        $html .= $this->render_project_stats($project);
        $html .= '</div>';
        
        $html .= '</div>'; // Close project-detail-content
        
        // Additional sections
        if ($this->config['show_gallery']) {
            $html .= $this->render_project_gallery($project);
        }
        
        if ($this->config['show_map']) {
            $html .= $this->render_project_map($project);
        }
        
        if ($this->config['show_related_projects']) {
            $html .= $this->render_related_projects($project);
        }
        
        $html .= '</div>'; // Close carbon-project-detail-page
        
        return $html;
    }
    
    /**
     * Get project details from API
     *
     * @param string $project_id Project ID
     * @param string $vendor Vendor name
     * @return Project|WP_Error
     */
    private function get_project_details($project_id, $vendor) {
        try {
            return $this->api_manager->get_project_details($project_id, $vendor);
        } catch (Exception $e) {
            return new \WP_Error('project_fetch_error', $e->getMessage());
        }
    }
    
    /**
     * Render project header section
     *
     * @param Project $project Project instance
     * @return string HTML output
     */
    private function render_project_header($project) {
        $project_data = $project->to_array();
        
        $html = '<div class="project-header">';
        
        // Breadcrumb navigation
        $html .= '<nav class="project-breadcrumb">';
        $html .= '<a href="#" class="breadcrumb-link back-to-search">← Back to Search</a>';
        $html .= '<span class="breadcrumb-separator">/</span>';
        $html .= '<span class="breadcrumb-current">Project Details</span>';
        $html .= '</nav>';
        
        // Project title and basic info
        $html .= '<div class="project-title-section">';
        $html .= '<h1 class="project-title">' . esc_html($project_data['name']) . '</h1>';
        
        $html .= '<div class="project-meta-header">';
        
        if (!empty($project_data['location'])) {
            $html .= '<span class="meta-item location">';
            $html .= '<i class="icon-location"></i>';
            $html .= esc_html($project_data['location']);
            $html .= '</span>';
        }
        
        if (!empty($project_data['project_type'])) {
            $html .= '<span class="meta-item project-type">';
            $html .= '<i class="icon-type"></i>';
            $html .= esc_html(ucwords(str_replace('_', ' ', $project_data['project_type'])));
            $html .= '</span>';
        }
        
        $html .= '<span class="meta-item vendor">';
        $html .= '<i class="icon-vendor"></i>';
        $html .= 'Provided by ' . esc_html(ucfirst($project_data['vendor']));
        $html .= '</span>';
        
        $html .= '</div>'; // Close project-meta-header
        
        $html .= '</div>'; // Close project-title-section
        
        $html .= '</div>'; // Close project-header
        
        return $html;
    }
    
    /**
     * Render project overview section
     *
     * @param Project $project Project instance
     * @return string HTML output
     */
    private function render_project_overview($project) {
        $project_data = $project->to_array();
        
        $html = '<div class="project-section project-overview">';
        $html .= '<h2 class="section-title">Project Overview</h2>';
        
        if (!empty($project_data['description'])) {
            $html .= '<div class="project-description">';
            $html .= '<p>' . wp_kses_post($project_data['description']) . '</p>';
            $html .= '</div>';
        }
        
        // Key highlights
        $html .= '<div class="project-highlights">';
        $html .= '<h3>Key Highlights</h3>';
        $html .= '<ul class="highlights-list">';
        
        if (!empty($project_data['methodology'])) {
            $html .= '<li><strong>Methodology:</strong> ' . esc_html($project_data['methodology']) . '</li>';
        }
        
        if (isset($project_data['available_quantity']) && $project_data['available_quantity'] > 0) {
            $html .= '<li><strong>Available Credits:</strong> ' . number_format($project_data['available_quantity']) . ' tCO₂</li>';
        }
        
        if (isset($project_data['price_per_kg'])) {
            $html .= '<li><strong>Price:</strong> $' . number_format($project_data['price_per_kg'], 2) . ' per tCO₂</li>';
        }
        
        // Add SDGs if available
        if (!empty($project_data['sdgs']) && is_array($project_data['sdgs'])) {
            $html .= '<li><strong>UN SDGs:</strong> ' . implode(', ', array_map('esc_html', $project_data['sdgs'])) . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>'; // Close project-highlights
        
        $html .= '</div>'; // Close project-overview
        
        return $html;
    }
    
    /**
     * Render detailed project information
     *
     * @param Project $project Project instance
     * @return string HTML output
     */
    private function render_project_details($project) {
        $project_data = $project->to_array();
        
        $html = '<div class="project-section project-details">';
        $html .= '<h2 class="section-title">Project Details</h2>';
        
        $html .= '<div class="details-grid">';
        
        $details = [
            'Project ID' => $project_data['id'] ?? '',
            'Location' => $project_data['location'] ?? '',
            'Project Type' => !empty($project_data['project_type']) ? ucwords(str_replace('_', ' ', $project_data['project_type'])) : '',
            'Methodology' => $project_data['methodology'] ?? '',
            'Vendor' => !empty($project_data['vendor']) ? ucfirst($project_data['vendor']) : '',
        ];
        
        // Add metadata if available
        $metadata = $project->get_metadata();
        if (!empty($metadata)) {
            if (isset($metadata['standard'])) {
                $details['Standard'] = $metadata['standard'];
            }
            if (isset($metadata['vintage'])) {
                $details['Vintage'] = $metadata['vintage'];
            }
            if (isset($metadata['emission_type'])) {
                $details['Emission Type'] = $metadata['emission_type'];
            }
        }
        
        foreach ($details as $label => $value) {
            if (!empty($value)) {
                $html .= '<div class="detail-row">';
                $html .= '<span class="detail-label">' . esc_html($label) . ':</span>';
                $html .= '<span class="detail-value">' . esc_html($value) . '</span>';
                $html .= '</div>';
            }
        }
        
        $html .= '</div>'; // Close details-grid
        
        $html .= '</div>'; // Close project-details
        
        return $html;
    }
    
    /**
     * Render project methodology section
     *
     * @param Project $project Project instance
     * @return string HTML output
     */
    private function render_project_methodology($project) {
        $project_data = $project->to_array();
        
        if (empty($project_data['methodology'])) {
            return '';
        }
        
        $html = '<div class="project-section project-methodology">';
        $html .= '<h2 class="section-title">Methodology</h2>';
        
        $html .= '<div class="methodology-content">';
        $html .= '<p><strong>' . esc_html($project_data['methodology']) . '</strong></p>';
        
        // Add methodology description if available in metadata
        $metadata = $project->get_metadata();
        if (!empty($metadata['methodology_description'])) {
            $html .= '<p>' . wp_kses_post($metadata['methodology_description']) . '</p>';
        }
        
        // Registry link if available
        if (!empty($project_data['registry_url'])) {
            $html .= '<p>';
            $html .= '<a href="' . esc_url($project_data['registry_url']) . '" target="_blank" rel="noopener" class="registry-link">';
            $html .= 'View on Registry <i class="icon-external-link"></i>';
            $html .= '</a>';
            $html .= '</p>';
        }
        
        $html .= '</div>'; // Close methodology-content
        
        $html .= '</div>'; // Close project-methodology
        
        return $html;
    }
    
    /**
     * Render project certifications section
     *
     * @param Project $project Project instance
     * @return string HTML output
     */
    private function render_project_certifications($project) {
        $metadata = $project->get_metadata();
        
        if (empty($metadata['certifications']) && empty($metadata['standards'])) {
            return '';
        }
        
        $html = '<div class="project-section project-certifications">';
        $html .= '<h2 class="section-title">Certifications & Standards</h2>';
        
        $html .= '<div class="certifications-grid">';
        
        // Standards
        if (!empty($metadata['standards'])) {
            $standards = is_array($metadata['standards']) ? $metadata['standards'] : [$metadata['standards']];
            foreach ($standards as $standard) {
                $html .= '<div class="certification-item">';
                $html .= '<div class="cert-icon"><i class="icon-certificate"></i></div>';
                $html .= '<div class="cert-info">';
                $html .= '<h4>' . esc_html($standard) . '</h4>';
                $html .= '<p>Verified Standard</p>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }
        
        // Additional certifications
        if (!empty($metadata['certifications'])) {
            $certifications = is_array($metadata['certifications']) ? $metadata['certifications'] : [$metadata['certifications']];
            foreach ($certifications as $cert) {
                $html .= '<div class="certification-item">';
                $html .= '<div class="cert-icon"><i class="icon-award"></i></div>';
                $html .= '<div class="cert-info">';
                $html .= '<h4>' . esc_html($cert) . '</h4>';
                $html .= '<p>Certification</p>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }
        
        $html .= '</div>'; // Close certifications-grid
        
        $html .= '</div>'; // Close project-certifications
        
        return $html;
    }
    
    /**
     * Render purchase widget
     *
     * @param Project $project Project instance
     * @return string HTML output
     */
    private function render_purchase_widget($project) {
        $project_data = $project->to_array();
        
        $html = '<div class="project-section purchase-widget">';
        $html .= '<h2 class="section-title">Purchase Carbon Credits</h2>';
        
        $html .= '<div class="purchase-form">';
        
        // Quantity selector
        $html .= '<div class="quantity-selector">';
        $html .= '<label for="credit-quantity">Quantity (tCO₂)</label>';
        $html .= '<div class="quantity-input-group">';
        $html .= '<button type="button" class="quantity-btn decrease">-</button>';
        $html .= '<input type="number" id="credit-quantity" class="quantity-input" ';
        $html .= 'value="' . $this->config['default_quantity'] . '" ';
        $html .= 'min="1" max="' . $this->config['max_quantity'] . '" step="1">';
        $html .= '<button type="button" class="quantity-btn increase">+</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Price display
        if (isset($project_data['price_per_kg'])) {
            $html .= '<div class="price-display">';
            $html .= '<div class="price-row">';
            $html .= '<span class="price-label">Price per tCO₂:</span>';
            $html .= '<span class="price-value">$' . number_format($project_data['price_per_kg'], 2) . '</span>';
            $html .= '</div>';
            
            $html .= '<div class="price-row total-price">';
            $html .= '<span class="price-label">Total:</span>';
            $html .= '<span class="price-value total-amount">$' . number_format($project_data['price_per_kg'] * $this->config['default_quantity'], 2) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Real-time pricing button
        if ($this->config['enable_real_time_pricing']) {
            $html .= '<button type="button" class="btn btn-secondary get-live-quote" data-project-id="' . esc_attr($project_data['id']) . '">';
            $html .= '<i class="icon-refresh"></i> Get Live Quote';
            $html .= '</button>';
        }
        
        // Purchase button
        $is_available = isset($project_data['available_quantity']) && $project_data['available_quantity'] > 0;
        
        if ($is_available) {
            $html .= '<button type="button" class="btn btn-primary btn-purchase" ';
            $html .= 'data-project-id="' . esc_attr($project_data['id']) . '" ';
            $html .= 'data-vendor="' . esc_attr($project_data['vendor']) . '">';
            $html .= '<i class="icon-cart"></i> Purchase Credits';
            $html .= '</button>';
        } else {
            $html .= '<button type="button" class="btn btn-disabled" disabled>';
            $html .= 'Currently Unavailable';
            $html .= '</button>';
        }
        
        // Additional info
        $html .= '<div class="purchase-info">';
        $html .= '<p class="info-text">';
        $html .= '<i class="icon-info"></i> ';
        $html .= 'You will be redirected to our secure checkout to complete your purchase.';
        $html .= '</p>';
        
        if ($is_available && isset($project_data['available_quantity'])) {
            $html .= '<p class="availability-info">';
            $html .= number_format($project_data['available_quantity']) . ' tCO₂ available';
            $html .= '</p>';
        }
        $html .= '</div>';
        
        $html .= '</div>'; // Close purchase-form
        
        $html .= '</div>'; // Close purchase-widget
        
        return $html;
    }
    
    /**
     * Render project statistics
     *
     * @param Project $project Project instance
     * @return string HTML output
     */
    private function render_project_stats($project) {
        $project_data = $project->to_array();
        $metadata = $project->get_metadata();
        
        $html = '<div class="project-section project-stats">';
        $html .= '<h2 class="section-title">Project Statistics</h2>';
        
        $html .= '<div class="stats-grid">';
        
        // Available credits
        if (isset($project_data['available_quantity'])) {
            $html .= '<div class="stat-item">';
            $html .= '<div class="stat-value">' . number_format($project_data['available_quantity']) . '</div>';
            $html .= '<div class="stat-label">tCO₂ Available</div>';
            $html .= '</div>';
        }
        
        // Price
        if (isset($project_data['price_per_kg'])) {
            $html .= '<div class="stat-item">';
            $html .= '<div class="stat-value">$' . number_format($project_data['price_per_kg'], 2) . '</div>';
            $html .= '<div class="stat-label">Price per tCO₂</div>';
            $html .= '</div>';
        }
        
        // Vintage year if available
        if (!empty($metadata['vintage'])) {
            $html .= '<div class="stat-item">';
            $html .= '<div class="stat-value">' . esc_html($metadata['vintage']) . '</div>';
            $html .= '<div class="stat-label">Vintage Year</div>';
            $html .= '</div>';
        }
        
        // Project size if available
        if (!empty($metadata['project_size'])) {
            $html .= '<div class="stat-item">';
            $html .= '<div class="stat-value">' . esc_html($metadata['project_size']) . '</div>';
            $html .= '<div class="stat-label">Project Size</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>'; // Close stats-grid
        
        $html .= '</div>'; // Close project-stats
        
        return $html;
    }
    
    /**
     * Render project gallery
     *
     * @param Project $project Project instance
     * @return string HTML output
     */
    private function render_project_gallery($project) {
        $project_data = $project->to_array();
        $images = $project_data['images'] ?? [];
        
        if (empty($images) || !is_array($images)) {
            return '';
        }
        
        $html = '<div class="project-section project-gallery">';
        $html .= '<h2 class="section-title">Project Gallery</h2>';
        
        $html .= '<div class="gallery-container">';
        
        foreach ($images as $index => $image_url) {
            $html .= '<div class="gallery-item" data-index="' . $index . '">';
            $html .= '<img src="' . esc_url($image_url) . '" alt="Project Image ' . ($index + 1) . '" class="gallery-image">';
            $html .= '</div>';
        }
        
        $html .= '</div>'; // Close gallery-container
        
        $html .= '</div>'; // Close project-gallery
        
        return $html;
    }
    
    /**
     * Render project map
     *
     * @param Project $project Project instance
     * @return string HTML output
     */
    private function render_project_map($project) {
        $project_data = $project->to_array();
        
        if (empty($project_data['location'])) {
            return '';
        }
        
        $html = '<div class="project-section project-map">';
        $html .= '<h2 class="section-title">Project Location</h2>';
        
        $html .= '<div class="map-container">';
        $html .= '<div id="project-map" class="project-map-element" ';
        $html .= 'data-location="' . esc_attr($project_data['location']) . '">';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>'; // Close project-map
        
        return $html;
    }
    
    /**
     * Render related projects
     *
     * @param Project $project Project instance
     * @return string HTML output
     */
    private function render_related_projects($project) {
        $project_data = $project->to_array();
        
        // Get related projects (same type or location)
        $related_projects = $this->get_related_projects($project);
        
        if (empty($related_projects)) {
            return '';
        }
        
        $html = '<div class="project-section related-projects">';
        $html .= '<h2 class="section-title">Related Projects</h2>';
        
        $html .= '<div class="related-projects-grid">';
        
        foreach ($related_projects as $related_project) {
            $html .= $this->render_related_project_card($related_project);
        }
        
        $html .= '</div>'; // Close related-projects-grid
        
        $html .= '</div>'; // Close related-projects
        
        return $html;
    }
    
    /**
     * Render related project card
     *
     * @param Project $project Project instance
     * @return string HTML output
     */
    private function render_related_project_card($project) {
        $project_data = $project->to_array();
        
        $html = '<div class="related-project-card">';
        
        // Image
        $images = $project_data['images'] ?? [];
        if (!empty($images) && is_array($images)) {
            $html .= '<div class="related-project-image">';
            $html .= '<img src="' . esc_url($images[0]) . '" alt="' . esc_attr($project_data['name']) . '">';
            $html .= '</div>';
        }
        
        // Content
        $html .= '<div class="related-project-content">';
        $html .= '<h4 class="related-project-title">' . esc_html($project_data['name']) . '</h4>';
        
        if (!empty($project_data['location'])) {
            $html .= '<p class="related-project-location">' . esc_html($project_data['location']) . '</p>';
        }
        
        if (isset($project_data['price_per_kg'])) {
            $html .= '<p class="related-project-price">$' . number_format($project_data['price_per_kg'], 2) . '/tCO₂</p>';
        }
        
        $html .= '<a href="#" class="btn btn-sm btn-outline view-related-project" ';
        $html .= 'data-project-id="' . esc_attr($project_data['id']) . '" ';
        $html .= 'data-vendor="' . esc_attr($project_data['vendor']) . '">';
        $html .= 'View Details';
        $html .= '</a>';
        
        $html .= '</div>'; // Close related-project-content
        
        $html .= '</div>'; // Close related-project-card
        
        return $html;
    }
    
    /**
     * Get related projects
     *
     * @param Project $project Current project
     * @return array Array of related projects
     */
    private function get_related_projects($project) {
        $project_data = $project->to_array();
        
        try {
            // Get projects with same type or location
            $filters = [];
            
            if (!empty($project_data['project_type'])) {
                $filters['project_type'] = $project_data['project_type'];
            }
            
            $related_projects = $this->api_manager->fetch_all_projects($filters);
            
            // Remove current project and limit to 3
            $related_projects = array_filter($related_projects, function($p) use ($project_data) {
                $p_data = $p->to_array();
                return $p_data['id'] !== $project_data['id'];
            });
            
            return array_slice($related_projects, 0, 3);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Render error page
     *
     * @param WP_Error $error Error object
     * @return string HTML output
     */
    private function render_error_page($error) {
        $html = '<div class="project-detail-error">';
        $html .= '<div class="error-content">';
        $html .= '<h2>Project Not Found</h2>';
        $html .= '<p>' . esc_html($error->get_error_message()) . '</p>';
        $html .= '<a href="#" class="btn btn-primary back-to-search">Back to Search</a>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Enqueue required scripts and styles
     */
    public static function enqueue_assets() {
        wp_enqueue_style(
            'carbon-project-detail',
            plugin_dir_url(__FILE__) . '../../assets/css/project-detail.css',
            [],
            '1.0.0'
        );
        
        wp_enqueue_script(
            'carbon-project-detail',
            plugin_dir_url(__FILE__) . '../../assets/js/project-detail.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('carbon-project-detail', 'carbonProjectDetail', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('carbon_marketplace_detail'),
        ]);
    }
}