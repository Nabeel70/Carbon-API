<?php
/**
 * Carbon marketplace project detail template
 *
 * @package CarbonMarketplace
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$project_id = isset($atts['project_id']) ? sanitize_text_field($atts['project_id']) : '';
$vendor = isset($atts['vendor']) ? sanitize_text_field($atts['vendor']) : '';

if (empty($project_id)) {
    echo '<div class="carbon-marketplace-error">' . __('Project ID is required.', 'carbon-marketplace') . '</div>';
    return;
}

// Get the main plugin instance
$carbon_marketplace = CarbonMarketplace\CarbonMarketplace::get_instance();
$api_manager = $carbon_marketplace->get_api_manager();

try {
    // Fetch project details
    $project = $api_manager->get_project_details($project_id, $vendor);
    
    if (!$project) {
        throw new Exception('Project not found');
    }
} catch (Exception $e) {
    echo '<div class="carbon-marketplace-error">' . 
         sprintf(__('Unable to load project details: %s', 'carbon-marketplace'), $e->getMessage()) . 
         '</div>';
    return;
}
?>

<div class="carbon-marketplace-project-detail" data-project-id="<?php echo esc_attr($project->id); ?>" data-vendor="<?php echo esc_attr($project->vendor); ?>">
    <div class="project-detail-header">
        <div class="project-hero">
            <?php if (!empty($project->images) && is_array($project->images)): ?>
                <div class="project-image-gallery">
                    <div class="main-image">
                        <img src="<?php echo esc_url($project->images[0]); ?>" 
                             alt="<?php echo esc_attr($project->name); ?>">
                    </div>
                    <?php if (count($project->images) > 1): ?>
                        <div class="image-thumbnails">
                            <?php foreach (array_slice($project->images, 1, 4) as $index => $image): ?>
                                <div class="thumbnail" data-index="<?php echo $index + 1; ?>">
                                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($project->name); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="project-image-placeholder-large">
                    <div class="project-type-icon-large">
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
                        <span class="project-icon-large project-icon-<?php echo $icon_class; ?>"></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="project-info">
            <div class="project-meta">
                <span class="project-vendor-badge-large"><?php echo esc_html(ucfirst($project->vendor)); ?></span>
                <span class="project-type-badge"><?php echo esc_html($project->project_type); ?></span>
            </div>

            <h1 class="project-title"><?php echo esc_html($project->name); ?></h1>

            <div class="project-location-large">
                <span class="location-icon">📍</span>
                <?php echo esc_html($project->location); ?>
                <?php if (!empty($project->country) && $project->country !== $project->location): ?>
                    <span class="country">, <?php echo esc_html($project->country); ?></span>
                <?php endif; ?>
            </div>

            <div class="project-pricing-large">
                <div class="price-per-kg-large">
                    <span class="price-amount">$<?php echo number_format($project->price_per_kg, 2); ?></span>
                    <span class="price-unit"><?php _e('per tCO2e', 'carbon-marketplace'); ?></span>
                </div>
                <?php if (!empty($project->available_quantity)): ?>
                    <div class="available-quantity-large">
                        <?php printf(__('%s tCO2e available', 'carbon-marketplace'), number_format($project->available_quantity)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="project-detail-content">
        <div class="project-main-content">
            <div class="project-description-section">
                <h2><?php _e('Project Description', 'carbon-marketplace'); ?></h2>
                <?php if (!empty($project->description)): ?>
                    <div class="project-description-full">
                        <?php echo wp_kses_post(wpautop($project->description)); ?>
                    </div>
                <?php else: ?>
                    <p class="no-description"><?php _e('No description available for this project.', 'carbon-marketplace'); ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($project->methodology)): ?>
                <div class="project-methodology-section">
                    <h2><?php _e('Methodology', 'carbon-marketplace'); ?></h2>
                    <p><?php echo esc_html($project->methodology); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($project->sdgs) && is_array($project->sdgs)): ?>
                <div class="project-sdgs-section">
                    <h2><?php _e('UN Sustainable Development Goals', 'carbon-marketplace'); ?></h2>
                    <div class="sdgs-grid">
                        <?php foreach ($project->sdgs as $sdg): ?>
                            <div class="sdg-item">
                                <span class="sdg-badge-large"><?php echo esc_html($sdg); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($project->registry_name) || !empty($project->registry_url)): ?>
                <div class="project-registry-section">
                    <h2><?php _e('Registry Information', 'carbon-marketplace'); ?></h2>
                    <?php if (!empty($project->registry_name)): ?>
                        <p><strong><?php _e('Registry:', 'carbon-marketplace'); ?></strong> <?php echo esc_html($project->registry_name); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($project->registry_url)): ?>
                        <p>
                            <a href="<?php echo esc_url($project->registry_url); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="registry-link">
                                <?php _e('View on Registry', 'carbon-marketplace'); ?> →
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="project-sidebar">
            <div class="project-purchase-section">
                <h3><?php _e('Purchase Carbon Credits', 'carbon-marketplace'); ?></h3>
                
                <div class="quantity-selector">
                    <label for="carbon-quantity"><?php _e('Quantity (tCO2e)', 'carbon-marketplace'); ?></label>
                    <input type="number" 
                           id="carbon-quantity" 
                           name="quantity" 
                           min="1" 
                           max="<?php echo esc_attr($project->available_quantity); ?>"
                           value="1" 
                           step="0.1"
                           class="quantity-input">
                </div>

                <div class="price-calculator">
                    <div class="price-breakdown">
                        <div class="price-line">
                            <span class="price-label"><?php _e('Price per tCO2e:', 'carbon-marketplace'); ?></span>
                            <span class="price-value">$<?php echo number_format($project->price_per_kg, 2); ?></span>
                        </div>
                        <div class="price-line">
                            <span class="price-label"><?php _e('Quantity:', 'carbon-marketplace'); ?></span>
                            <span class="price-value quantity-display">1 tCO2e</span>
                        </div>
                        <div class="price-line total-line">
                            <span class="price-label"><?php _e('Total:', 'carbon-marketplace'); ?></span>
                            <span class="price-value total-price">$<?php echo number_format($project->price_per_kg, 2); ?></span>
                        </div>
                    </div>
                </div>

                <div class="purchase-actions">
                    <button type="button" class="btn btn-primary btn-large get-quote-btn-detail" 
                            data-project-id="<?php echo esc_attr($project->id); ?>"
                            data-vendor="<?php echo esc_attr($project->vendor); ?>">
                        <?php _e('Get Quote', 'carbon-marketplace'); ?>
                    </button>
                    <button type="button" class="btn btn-secondary btn-large purchase-btn-detail"
                            data-project-id="<?php echo esc_attr($project->id); ?>"
                            data-vendor="<?php echo esc_attr($project->vendor); ?>">
                        <?php _e('Purchase Now', 'carbon-marketplace'); ?>
                    </button>
                </div>

                <div class="purchase-info">
                    <div class="info-item">
                        <span class="info-icon">✓</span>
                        <span class="info-text"><?php _e('Instant retirement certificate', 'carbon-marketplace'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-icon">✓</span>
                        <span class="info-text"><?php _e('Verified by international standards', 'carbon-marketplace'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-icon">✓</span>
                        <span class="info-text"><?php _e('Transparent allocation reporting', 'carbon-marketplace'); ?></span>
                    </div>
                </div>
            </div>

            <div class="project-details-section">
                <h3><?php _e('Project Details', 'carbon-marketplace'); ?></h3>
                
                <div class="detail-item">
                    <span class="detail-label"><?php _e('Project Type:', 'carbon-marketplace'); ?></span>
                    <span class="detail-value"><?php echo esc_html($project->project_type); ?></span>
                </div>

                <?php if (!empty($project->vintage_year)): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php _e('Vintage Year:', 'carbon-marketplace'); ?></span>
                        <span class="detail-value"><?php echo esc_html($project->vintage_year); ?></span>
                    </div>
                <?php endif; ?>

                <div class="detail-item">
                    <span class="detail-label"><?php _e('Vendor:', 'carbon-marketplace'); ?></span>
                    <span class="detail-value"><?php echo esc_html(ucfirst($project->vendor)); ?></span>
                </div>

                <div class="detail-item">
                    <span class="detail-label"><?php _e('Currency:', 'carbon-marketplace'); ?></span>
                    <span class="detail-value"><?php echo esc_html($project->currency); ?></span>
                </div>

                <?php if (!empty($project->metadata) && is_array($project->metadata)): ?>
                    <?php foreach ($project->metadata as $key => $value): ?>
                        <?php if (!empty($value) && is_string($value)): ?>
                            <div class="detail-item">
                                <span class="detail-label"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</span>
                                <span class="detail-value"><?php echo esc_html($value); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize project detail functionality
jQuery(document).ready(function($) {
    if (typeof CarbonMarketplaceProjectDetail !== 'undefined') {
        CarbonMarketplaceProjectDetail.init({
            container: '.carbon-marketplace-project-detail',
            quantityInput: '#carbon-quantity',
            getQuoteButton: '.get-quote-btn-detail',
            purchaseButton: '.purchase-btn-detail',
            pricePerKg: <?php echo $project->price_per_kg; ?>
        });
    }
});
</script>