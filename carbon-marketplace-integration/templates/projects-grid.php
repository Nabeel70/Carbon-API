<?php
/**
 * Carbon marketplace projects grid template
 *
 * @package CarbonMarketplace
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$limit = isset($atts['limit']) ? intval($atts['limit']) : 20;
$vendor = isset($atts['vendor']) ? sanitize_text_field($atts['vendor']) : '';
$project_type = isset($atts['project_type']) ? sanitize_text_field($atts['project_type']) : '';
$location = isset($atts['location']) ? sanitize_text_field($atts['location']) : '';

// Get the main plugin instance
$carbon_marketplace = CarbonMarketplace\CarbonMarketplace::get_instance();
$search_engine = $carbon_marketplace->get_search_engine();

// Build search query
$search_params = array(
    'limit' => $limit,
    'vendor' => $vendor,
    'project_type' => $project_type,
    'location' => $location,
);

// Remove empty parameters
$search_params = array_filter($search_params, function($value) {
    return !empty($value);
});

try {
    // Perform search
    $results = $search_engine->search($search_params);
    $projects = $results->get_projects();
    $total_count = $results->get_total_count();
} catch (Exception $e) {
    $projects = array();
    $total_count = 0;
    error_log('Carbon Marketplace: Error fetching projects - ' . $e->getMessage());
}
?>

<div class="carbon-marketplace-projects-grid">
    <?php if (!empty($projects)): ?>
        <div class="projects-header">
            <div class="projects-count">
                <?php printf(_n('%d project found', '%d projects found', $total_count, 'carbon-marketplace'), $total_count); ?>
            </div>
        </div>

        <div class="projects-grid">
            <?php foreach ($projects as $project): ?>
                <div class="project-card" data-project-id="<?php echo esc_attr($project->id); ?>" data-vendor="<?php echo esc_attr($project->vendor); ?>">
                    <div class="project-card-header">
                        <?php if (!empty($project->images) && is_array($project->images)): ?>
                            <div class="project-image">
                                <img src="<?php echo esc_url($project->images[0]); ?>" 
                                     alt="<?php echo esc_attr($project->name); ?>"
                                     loading="lazy">
                            </div>
                        <?php else: ?>
                            <div class="project-image-placeholder">
                                <div class="project-type-icon">
                                    <?php 
                                    $icon_class = 'default';
                                    switch (strtolower($project->project_type)) {
                                        case 'forestry':
                                            $icon_class = 'tree';
                                            break;
                                        case 'renewable-energy':
                                            $icon_class = 'solar';
                                            break;
                                        case 'energy-efficiency':
                                            $icon_class = 'lightbulb';
                                            break;
                                        case 'waste-management':
                                            $icon_class = 'recycle';
                                            break;
                                        case 'agriculture':
                                            $icon_class = 'leaf';
                                            break;
                                        default:
                                            $icon_class = 'eco';
                                    }
                                    ?>
                                    <span class="project-icon project-icon-<?php echo $icon_class; ?>"></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="project-vendor-badge">
                            <?php echo esc_html(ucfirst($project->vendor)); ?>
                        </div>
                    </div>

                    <div class="project-card-content">
                        <h3 class="project-title">
                            <a href="#" class="project-detail-link" data-project-id="<?php echo esc_attr($project->id); ?>">
                                <?php echo esc_html($project->name); ?>
                            </a>
                        </h3>

                        <div class="project-location">
                            <span class="location-icon">📍</span>
                            <?php echo esc_html($project->location); ?>
                        </div>

                        <div class="project-type">
                            <span class="type-label"><?php _e('Type:', 'carbon-marketplace'); ?></span>
                            <?php echo esc_html($project->project_type); ?>
                        </div>

                        <?php if (!empty($project->description)): ?>
                            <div class="project-description">
                                <?php echo esc_html(wp_trim_words($project->description, 20)); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($project->sdgs) && is_array($project->sdgs)): ?>
                            <div class="project-sdgs">
                                <span class="sdgs-label"><?php _e('UN SDGs:', 'carbon-marketplace'); ?></span>
                                <div class="sdgs-list">
                                    <?php foreach (array_slice($project->sdgs, 0, 3) as $sdg): ?>
                                        <span class="sdg-badge"><?php echo esc_html($sdg); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($project->sdgs) > 3): ?>
                                        <span class="sdg-more">+<?php echo count($project->sdgs) - 3; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="project-card-footer">
                        <div class="project-pricing">
                            <div class="price-per-kg">
                                <span class="price-amount">$<?php echo number_format($project->price_per_kg, 2); ?></span>
                                <span class="price-unit"><?php _e('per tCO2e', 'carbon-marketplace'); ?></span>
                            </div>
                            <?php if (!empty($project->available_quantity)): ?>
                                <div class="available-quantity">
                                    <?php printf(__('%s tCO2e available', 'carbon-marketplace'), number_format($project->available_quantity)); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="project-actions">
                            <button type="button" class="btn btn-primary get-quote-btn" 
                                    data-project-id="<?php echo esc_attr($project->id); ?>"
                                    data-vendor="<?php echo esc_attr($project->vendor); ?>">
                                <?php _e('Get Quote', 'carbon-marketplace'); ?>
                            </button>
                            <button type="button" class="btn btn-secondary view-details-btn"
                                    data-project-id="<?php echo esc_attr($project->id); ?>"
                                    data-vendor="<?php echo esc_attr($project->vendor); ?>">
                                <?php _e('Details', 'carbon-marketplace'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_count > $limit): ?>
            <div class="projects-pagination">
                <button type="button" class="btn btn-outline load-more-btn" 
                        data-offset="<?php echo $limit; ?>"
                        data-limit="<?php echo $limit; ?>">
                    <?php _e('Load More Projects', 'carbon-marketplace'); ?>
                </button>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="no-projects-found">
            <div class="no-projects-icon">🌱</div>
            <h3><?php _e('No Projects Found', 'carbon-marketplace'); ?></h3>
            <p><?php _e('We couldn\'t find any carbon projects matching your criteria. Try adjusting your filters or search terms.', 'carbon-marketplace'); ?></p>
            <div class="no-projects-suggestions">
                <h4><?php _e('Suggestions:', 'carbon-marketplace'); ?></h4>
                <ul>
                    <li><?php _e('Try searching for "forestry" or "renewable energy"', 'carbon-marketplace'); ?></li>
                    <li><?php _e('Check different locations like "Brazil" or "Indonesia"', 'carbon-marketplace'); ?></li>
                    <li><?php _e('Clear all filters to see all available projects', 'carbon-marketplace'); ?></li>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Initialize project grid functionality
jQuery(document).ready(function($) {
    if (typeof CarbonMarketplaceProjects !== 'undefined') {
        CarbonMarketplaceProjects.init({
            container: '.carbon-marketplace-projects-grid',
            getQuoteButtons: '.get-quote-btn',
            viewDetailsButtons: '.view-details-btn',
            loadMoreButton: '.load-more-btn'
        });
    }
});
</script>