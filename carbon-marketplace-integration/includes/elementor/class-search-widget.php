<?php
/**
 * Elementor Search Widget for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace\Elementor
 */

namespace CarbonMarketplace\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Core\Schemes\Typography;
use Elementor\Core\Schemes\Color;

/**
 * Carbon Marketplace Search Widget
 */
class SearchWidget extends Widget_Base {
    
    /**
     * Get widget name
     */
    public function get_name() {
        return 'carbon-marketplace-search';
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return __('Carbon Project Search', 'carbon-marketplace');
    }
    
    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-search';
    }
    
    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['carbon-marketplace'];
    }
    
    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['carbon', 'search', 'projects', 'offset', 'marketplace'];
    }
    
    /**
     * Register widget controls
     */
    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }
    
    /**
     * Register content controls
     */
    private function register_content_controls() {
        // Search Form Section
        $this->start_controls_section(
            'search_form_section',
            [
                'label' => __('Search Form', 'carbon-marketplace'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'search_title',
            [
                'label' => __('Search Title', 'carbon-marketplace'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Find Carbon Offset Projects', 'carbon-marketplace'),
                'placeholder' => __('Enter search title', 'carbon-marketplace'),
            ]
        );
        
        $this->add_control(
            'search_description',
            [
                'label' => __('Search Description', 'carbon-marketplace'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Search and filter carbon offset projects from verified vendors.', 'carbon-marketplace'),
                'placeholder' => __('Enter search description', 'carbon-marketplace'),
            ]
        );
        
        $this->add_control(
            'show_keyword_search',
            [
                'label' => __('Show Keyword Search', 'carbon-marketplace'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'carbon-marketplace'),
                'label_off' => __('Hide', 'carbon-marketplace'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'keyword_placeholder',
            [
                'label' => __('Keyword Placeholder', 'carbon-marketplace'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Search projects...', 'carbon-marketplace'),
                'condition' => [
                    'show_keyword_search' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'show_location_filter',
            [
                'label' => __('Show Location Filter', 'carbon-marketplace'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'carbon-marketplace'),
                'label_off' => __('Hide', 'carbon-marketplace'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'show_project_type_filter',
            [
                'label' => __('Show Project Type Filter', 'carbon-marketplace'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'carbon-marketplace'),
                'label_off' => __('Hide', 'carbon-marketplace'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'show_price_filter',
            [
                'label' => __('Show Price Filter', 'carbon-marketplace'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'carbon-marketplace'),
                'label_off' => __('Hide', 'carbon-marketplace'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'results_per_page',
            [
                'label' => __('Results Per Page', 'carbon-marketplace'),
                'type' => Controls_Manager::NUMBER,
                'default' => 12,
                'min' => 1,
                'max' => 50,
            ]
        );
        
        $this->add_control(
            'enable_instant_search',
            [
                'label' => __('Enable Instant Search', 'carbon-marketplace'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Enable', 'carbon-marketplace'),
                'label_off' => __('Disable', 'carbon-marketplace'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Search as user types (requires AJAX)', 'carbon-marketplace'),
            ]
        );
        
        $this->end_controls_section();
        
        // Results Display Section
        $this->start_controls_section(
            'results_display_section',
            [
                'label' => __('Results Display', 'carbon-marketplace'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'results_layout',
            [
                'label' => __('Results Layout', 'carbon-marketplace'),
                'type' => Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid' => __('Grid', 'carbon-marketplace'),
                    'list' => __('List', 'carbon-marketplace'),
                ],
            ]
        );
        
        $this->add_control(
            'grid_columns',
            [
                'label' => __('Grid Columns', 'carbon-marketplace'),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '1' => __('1 Column', 'carbon-marketplace'),
                    '2' => __('2 Columns', 'carbon-marketplace'),
                    '3' => __('3 Columns', 'carbon-marketplace'),
                    '4' => __('4 Columns', 'carbon-marketplace'),
                ],
                'condition' => [
                    'results_layout' => 'grid',
                ],
            ]
        );
        
        $this->add_control(
            'show_project_images',
            [
                'label' => __('Show Project Images', 'carbon-marketplace'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'carbon-marketplace'),
                'label_off' => __('Hide', 'carbon-marketplace'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'show_project_price',
            [
                'label' => __('Show Project Price', 'carbon-marketplace'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'carbon-marketplace'),
                'label_off' => __('Hide', 'carbon-marketplace'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'show_project_location',
            [
                'label' => __('Show Project Location', 'carbon-marketplace'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'carbon-marketplace'),
                'label_off' => __('Hide', 'carbon-marketplace'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'show_pagination',
            [
                'label' => __('Show Pagination', 'carbon-marketplace'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'carbon-marketplace'),
                'label_off' => __('Hide', 'carbon-marketplace'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Register style controls
     */
    private function register_style_controls() {
        // Search Form Styles
        $this->start_controls_section(
            'search_form_style',
            [
                'label' => __('Search Form', 'carbon-marketplace'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'label' => __('Title Typography', 'carbon-marketplace'),
                'selector' => '{{WRAPPER}} .carbon-search-title',
            ]
        );
        
        $this->add_control(
            'title_color',
            [
                'label' => __('Title Color', 'carbon-marketplace'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-title' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'label' => __('Description Typography', 'carbon-marketplace'),
                'selector' => '{{WRAPPER}} .carbon-search-description',
            ]
        );
        
        $this->add_control(
            'description_color',
            [
                'label' => __('Description Color', 'carbon-marketplace'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-description' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'form_background_color',
            [
                'label' => __('Form Background', 'carbon-marketplace'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-form' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'form_border',
                'label' => __('Form Border', 'carbon-marketplace'),
                'selector' => '{{WRAPPER}} .carbon-search-form',
            ]
        );
        
        $this->add_control(
            'form_border_radius',
            [
                'label' => __('Form Border Radius', 'carbon-marketplace'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'form_box_shadow',
                'label' => __('Form Box Shadow', 'carbon-marketplace'),
                'selector' => '{{WRAPPER}} .carbon-search-form',
            ]
        );
        
        $this->add_responsive_control(
            'form_padding',
            [
                'label' => __('Form Padding', 'carbon-marketplace'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Input Fields Styles
        $this->start_controls_section(
            'input_fields_style',
            [
                'label' => __('Input Fields', 'carbon-marketplace'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'input_typography',
                'label' => __('Input Typography', 'carbon-marketplace'),
                'selector' => '{{WRAPPER}} .carbon-search-form input, {{WRAPPER}} .carbon-search-form select',
            ]
        );
        
        $this->add_control(
            'input_text_color',
            [
                'label' => __('Input Text Color', 'carbon-marketplace'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-form input' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .carbon-search-form select' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'input_background_color',
            [
                'label' => __('Input Background', 'carbon-marketplace'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-form input' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .carbon-search-form select' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'input_border',
                'label' => __('Input Border', 'carbon-marketplace'),
                'selector' => '{{WRAPPER}} .carbon-search-form input, {{WRAPPER}} .carbon-search-form select',
            ]
        );
        
        $this->add_control(
            'input_border_radius',
            [
                'label' => __('Input Border Radius', 'carbon-marketplace'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-form input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .carbon-search-form select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'input_padding',
            [
                'label' => __('Input Padding', 'carbon-marketplace'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-form input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .carbon-search-form select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Button Styles
        $this->start_controls_section(
            'button_style',
            [
                'label' => __('Search Button', 'carbon-marketplace'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'label' => __('Button Typography', 'carbon-marketplace'),
                'selector' => '{{WRAPPER}} .carbon-search-button',
            ]
        );
        
        $this->start_controls_tabs('button_tabs');
        
        $this->start_controls_tab(
            'button_normal',
            [
                'label' => __('Normal', 'carbon-marketplace'),
            ]
        );
        
        $this->add_control(
            'button_text_color',
            [
                'label' => __('Text Color', 'carbon-marketplace'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-button' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_background_color',
            [
                'label' => __('Background Color', 'carbon-marketplace'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_tab();
        
        $this->start_controls_tab(
            'button_hover',
            [
                'label' => __('Hover', 'carbon-marketplace'),
            ]
        );
        
        $this->add_control(
            'button_hover_text_color',
            [
                'label' => __('Text Color', 'carbon-marketplace'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_hover_background_color',
            [
                'label' => __('Background Color', 'carbon-marketplace'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_tab();
        
        $this->end_controls_tabs();
        
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'label' => __('Button Border', 'carbon-marketplace'),
                'selector' => '{{WRAPPER}} .carbon-search-button',
            ]
        );
        
        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Button Border Radius', 'carbon-marketplace'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Button Padding', 'carbon-marketplace'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Results Styles
        $this->start_controls_section(
            'results_style',
            [
                'label' => __('Search Results', 'carbon-marketplace'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'results_background_color',
            [
                'label' => __('Results Background', 'carbon-marketplace'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-results' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'results_padding',
            [
                'label' => __('Results Padding', 'carbon-marketplace'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-results' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'results_margin',
            [
                'label' => __('Results Margin', 'carbon-marketplace'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .carbon-search-results' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Generate unique widget ID
        $widget_id = 'carbon-search-' . $this->get_id();
        
        ?>
        <div class="carbon-marketplace-search-widget" id="<?php echo esc_attr($widget_id); ?>">
            <?php if (!empty($settings['search_title'])): ?>
                <h2 class="carbon-search-title"><?php echo esc_html($settings['search_title']); ?></h2>
            <?php endif; ?>
            
            <?php if (!empty($settings['search_description'])): ?>
                <p class="carbon-search-description"><?php echo esc_html($settings['search_description']); ?></p>
            <?php endif; ?>
            
            <form class="carbon-search-form" data-widget-id="<?php echo esc_attr($widget_id); ?>">
                <div class="search-fields">
                    <?php if ($settings['show_keyword_search'] === 'yes'): ?>
                        <div class="search-field keyword-field">
                            <label for="<?php echo esc_attr($widget_id); ?>-keyword" class="screen-reader-text">
                                <?php _e('Search Keywords', 'carbon-marketplace'); ?>
                            </label>
                            <input type="text" 
                                   id="<?php echo esc_attr($widget_id); ?>-keyword"
                                   name="keyword" 
                                   placeholder="<?php echo esc_attr($settings['keyword_placeholder']); ?>"
                                   class="search-input keyword-input">
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($settings['show_location_filter'] === 'yes'): ?>
                        <div class="search-field location-field">
                            <label for="<?php echo esc_attr($widget_id); ?>-location">
                                <?php _e('Location', 'carbon-marketplace'); ?>
                            </label>
                            <select id="<?php echo esc_attr($widget_id); ?>-location" name="location" class="search-select location-select">
                                <option value=""><?php _e('All Locations', 'carbon-marketplace'); ?></option>
                                <?php echo $this->get_location_options(); ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($settings['show_project_type_filter'] === 'yes'): ?>
                        <div class="search-field project-type-field">
                            <label for="<?php echo esc_attr($widget_id); ?>-project-type">
                                <?php _e('Project Type', 'carbon-marketplace'); ?>
                            </label>
                            <select id="<?php echo esc_attr($widget_id); ?>-project-type" name="project_type" class="search-select project-type-select">
                                <option value=""><?php _e('All Project Types', 'carbon-marketplace'); ?></option>
                                <?php echo $this->get_project_type_options(); ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($settings['show_price_filter'] === 'yes'): ?>
                        <div class="search-field price-field">
                            <div class="price-range">
                                <div class="price-min">
                                    <label for="<?php echo esc_attr($widget_id); ?>-min-price">
                                        <?php _e('Min Price', 'carbon-marketplace'); ?>
                                    </label>
                                    <input type="number" 
                                           id="<?php echo esc_attr($widget_id); ?>-min-price"
                                           name="min_price" 
                                           placeholder="0"
                                           min="0"
                                           step="0.01"
                                           class="search-input price-input">
                                </div>
                                <div class="price-max">
                                    <label for="<?php echo esc_attr($widget_id); ?>-max-price">
                                        <?php _e('Max Price', 'carbon-marketplace'); ?>
                                    </label>
                                    <input type="number" 
                                           id="<?php echo esc_attr($widget_id); ?>-max-price"
                                           name="max_price" 
                                           placeholder="1000"
                                           min="0"
                                           step="0.01"
                                           class="search-input price-input">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="search-actions">
                    <button type="submit" class="carbon-search-button">
                        <?php _e('Search Projects', 'carbon-marketplace'); ?>
                    </button>
                    <button type="button" class="carbon-clear-button">
                        <?php _e('Clear Filters', 'carbon-marketplace'); ?>
                    </button>
                </div>
            </form>
            
            <div class="carbon-search-results" 
                 data-layout="<?php echo esc_attr($settings['results_layout']); ?>"
                 data-columns="<?php echo esc_attr($settings['grid_columns']); ?>"
                 data-per-page="<?php echo esc_attr($settings['results_per_page']); ?>"
                 data-show-images="<?php echo esc_attr($settings['show_project_images']); ?>"
                 data-show-price="<?php echo esc_attr($settings['show_project_price']); ?>"
                 data-show-location="<?php echo esc_attr($settings['show_project_location']); ?>"
                 data-show-pagination="<?php echo esc_attr($settings['show_pagination']); ?>"
                 data-instant-search="<?php echo esc_attr($settings['enable_instant_search']); ?>">
                
                <div class="search-loading" style="display: none;">
                    <div class="loading-spinner"></div>
                    <p><?php _e('Searching projects...', 'carbon-marketplace'); ?></p>
                </div>
                
                <div class="search-results-container">
                    <!-- Results will be loaded here via AJAX -->
                </div>
                
                <div class="search-no-results" style="display: none;">
                    <p><?php _e('No projects found matching your criteria.', 'carbon-marketplace'); ?></p>
                    <p><?php _e('Try adjusting your search filters or keywords.', 'carbon-marketplace'); ?></p>
                </div>
            </div>
        </div>
        
        <?php
        // Enqueue widget scripts and styles
        $this->enqueue_widget_assets($widget_id, $settings);
    }
    
    /**
     * Get location options for select field
     */
    private function get_location_options() {
        $locations = [
            'africa' => __('Africa', 'carbon-marketplace'),
            'asia' => __('Asia', 'carbon-marketplace'),
            'europe' => __('Europe', 'carbon-marketplace'),
            'north-america' => __('North America', 'carbon-marketplace'),
            'south-america' => __('South America', 'carbon-marketplace'),
            'oceania' => __('Oceania', 'carbon-marketplace'),
        ];
        
        $options = '';
        foreach ($locations as $value => $label) {
            $options .= '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        
        return $options;
    }
    
    /**
     * Get project type options for select field
     */
    private function get_project_type_options() {
        $project_types = [
            'forestry' => __('Forestry & Land Use', 'carbon-marketplace'),
            'renewable-energy' => __('Renewable Energy', 'carbon-marketplace'),
            'energy-efficiency' => __('Energy Efficiency', 'carbon-marketplace'),
            'methane-capture' => __('Methane Capture', 'carbon-marketplace'),
            'direct-air-capture' => __('Direct Air Capture', 'carbon-marketplace'),
            'blue-carbon' => __('Blue Carbon', 'carbon-marketplace'),
            'agriculture' => __('Agriculture', 'carbon-marketplace'),
            'waste-management' => __('Waste Management', 'carbon-marketplace'),
        ];
        
        $options = '';
        foreach ($project_types as $value => $label) {
            $options .= '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        
        return $options;
    }
    
    /**
     * Enqueue widget assets
     */
    private function enqueue_widget_assets($widget_id, $settings) {
        // Enqueue search widget CSS
        wp_enqueue_style(
            'carbon-marketplace-search-widget',
            CARBON_MARKETPLACE_URL . 'assets/css/search-widget.css',
            [],
            CARBON_MARKETPLACE_VERSION
        );
        
        // Enqueue search widget JS
        wp_enqueue_script(
            'carbon-marketplace-search-widget',
            CARBON_MARKETPLACE_URL . 'assets/js/search-widget.js',
            ['jquery'],
            CARBON_MARKETPLACE_VERSION,
            true
        );
        
        // Localize script with widget settings and AJAX data
        wp_localize_script('carbon-marketplace-search-widget', 'carbonMarketplaceSearch', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('carbon_marketplace_search'),
            'widgetId' => $widget_id,
            'settings' => $settings,
            'strings' => [
                'searching' => __('Searching...', 'carbon-marketplace'),
                'noResults' => __('No results found', 'carbon-marketplace'),
                'error' => __('Search error occurred', 'carbon-marketplace'),
                'loadMore' => __('Load More', 'carbon-marketplace'),
                'showingResults' => __('Showing {start} - {end} of {total} results', 'carbon-marketplace'),
            ]
        ]);
    }
    
    /**
     * Render widget output in the editor
     */
    protected function content_template() {
        ?>
        <#
        var widgetId = 'carbon-search-' + view.getID();
        #>
        
        <div class="carbon-marketplace-search-widget" id="{{ widgetId }}">
            <# if (settings.search_title) { #>
                <h2 class="carbon-search-title">{{{ settings.search_title }}}</h2>
            <# } #>
            
            <# if (settings.search_description) { #>
                <p class="carbon-search-description">{{{ settings.search_description }}}</p>
            <# } #>
            
            <form class="carbon-search-form">
                <div class="search-fields">
                    <# if (settings.show_keyword_search === 'yes') { #>
                        <div class="search-field keyword-field">
                            <input type="text" 
                                   name="keyword" 
                                   placeholder="{{ settings.keyword_placeholder }}"
                                   class="search-input keyword-input">
                        </div>
                    <# } #>
                    
                    <# if (settings.show_location_filter === 'yes') { #>
                        <div class="search-field location-field">
                            <label><?php _e('Location', 'carbon-marketplace'); ?></label>
                            <select name="location" class="search-select location-select">
                                <option value=""><?php _e('All Locations', 'carbon-marketplace'); ?></option>
                            </select>
                        </div>
                    <# } #>
                    
                    <# if (settings.show_project_type_filter === 'yes') { #>
                        <div class="search-field project-type-field">
                            <label><?php _e('Project Type', 'carbon-marketplace'); ?></label>
                            <select name="project_type" class="search-select project-type-select">
                                <option value=""><?php _e('All Project Types', 'carbon-marketplace'); ?></option>
                            </select>
                        </div>
                    <# } #>
                    
                    <# if (settings.show_price_filter === 'yes') { #>
                        <div class="search-field price-field">
                            <div class="price-range">
                                <div class="price-min">
                                    <label><?php _e('Min Price', 'carbon-marketplace'); ?></label>
                                    <input type="number" name="min_price" placeholder="0" class="search-input price-input">
                                </div>
                                <div class="price-max">
                                    <label><?php _e('Max Price', 'carbon-marketplace'); ?></label>
                                    <input type="number" name="max_price" placeholder="1000" class="search-input price-input">
                                </div>
                            </div>
                        </div>
                    <# } #>
                </div>
                
                <div class="search-actions">
                    <button type="submit" class="carbon-search-button">
                        <?php _e('Search Projects', 'carbon-marketplace'); ?>
                    </button>
                    <button type="button" class="carbon-clear-button">
                        <?php _e('Clear Filters', 'carbon-marketplace'); ?>
                    </button>
                </div>
            </form>
            
            <div class="carbon-search-results">
                <div class="search-results-container">
                    <p style="text-align: center; color: #666; font-style: italic;">
                        <?php _e('Search results will appear here', 'carbon-marketplace'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
}