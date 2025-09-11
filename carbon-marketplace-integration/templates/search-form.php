<?php
/**
 * Carbon marketplace search form template
 *
 * @package CarbonMarketplace
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$show_filters = isset($atts['show_filters']) ? $atts['show_filters'] === 'true' : true;
$results_per_page = isset($atts['results_per_page']) ? intval($atts['results_per_page']) : 20;
$layout = isset($atts['layout']) ? $atts['layout'] : 'grid';
?>

<div class="carbon-marketplace-search-container">
    <form class="carbon-marketplace-search-form" id="carbon-search-form">
        <div class="search-input-wrapper">
            <input type="text" 
                   id="carbon-search-input" 
                   name="search_term" 
                   placeholder="<?php _e('Search carbon projects...', 'carbon-marketplace'); ?>"
                   class="carbon-search-input">
            <button type="submit" class="carbon-search-submit">
                <?php _e('Search', 'carbon-marketplace'); ?>
            </button>
        </div>

        <?php if ($show_filters): ?>
        <div class="carbon-search-filters" id="carbon-search-filters">
            <div class="filter-group">
                <label for="location-filter"><?php _e('Location', 'carbon-marketplace'); ?></label>
                <select id="location-filter" name="location">
                    <option value=""><?php _e('All Locations', 'carbon-marketplace'); ?></option>
                    <option value="brazil"><?php _e('Brazil', 'carbon-marketplace'); ?></option>
                    <option value="usa"><?php _e('United States', 'carbon-marketplace'); ?></option>
                    <option value="canada"><?php _e('Canada', 'carbon-marketplace'); ?></option>
                    <option value="indonesia"><?php _e('Indonesia', 'carbon-marketplace'); ?></option>
                    <option value="peru"><?php _e('Peru', 'carbon-marketplace'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <label for="project-type-filter"><?php _e('Project Type', 'carbon-marketplace'); ?></label>
                <select id="project-type-filter" name="project_type">
                    <option value=""><?php _e('All Types', 'carbon-marketplace'); ?></option>
                    <option value="forestry"><?php _e('Forestry', 'carbon-marketplace'); ?></option>
                    <option value="renewable-energy"><?php _e('Renewable Energy', 'carbon-marketplace'); ?></option>
                    <option value="energy-efficiency"><?php _e('Energy Efficiency', 'carbon-marketplace'); ?></option>
                    <option value="waste-management"><?php _e('Waste Management', 'carbon-marketplace'); ?></option>
                    <option value="agriculture"><?php _e('Agriculture', 'carbon-marketplace'); ?></option>
                    <option value="blue-carbon"><?php _e('Blue Carbon', 'carbon-marketplace'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <label for="price-range-filter"><?php _e('Price Range (per tCO2e)', 'carbon-marketplace'); ?></label>
                <select id="price-range-filter" name="price_range">
                    <option value=""><?php _e('Any Price', 'carbon-marketplace'); ?></option>
                    <option value="0-10"><?php _e('$0 - $10', 'carbon-marketplace'); ?></option>
                    <option value="10-25"><?php _e('$10 - $25', 'carbon-marketplace'); ?></option>
                    <option value="25-50"><?php _e('$25 - $50', 'carbon-marketplace'); ?></option>
                    <option value="50-100"><?php _e('$50 - $100', 'carbon-marketplace'); ?></option>
                    <option value="100+"><?php _e('$100+', 'carbon-marketplace'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <label for="vendor-filter"><?php _e('Vendor', 'carbon-marketplace'); ?></label>
                <select id="vendor-filter" name="vendor">
                    <option value=""><?php _e('All Vendors', 'carbon-marketplace'); ?></option>
                    <option value="cnaught"><?php _e('CNaught', 'carbon-marketplace'); ?></option>
                    <option value="toucan"><?php _e('Toucan Protocol', 'carbon-marketplace'); ?></option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="button" id="clear-filters" class="clear-filters-btn">
                    <?php _e('Clear Filters', 'carbon-marketplace'); ?>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </form>

    <div class="search-results-container">
        <div class="search-results-header">
            <div class="results-count" id="results-count">
                <?php _e('Search for carbon projects to see results', 'carbon-marketplace'); ?>
            </div>
            <div class="results-layout-toggle">
                <button type="button" class="layout-btn <?php echo $layout === 'grid' ? 'active' : ''; ?>" data-layout="grid">
                    <?php _e('Grid', 'carbon-marketplace'); ?>
                </button>
                <button type="button" class="layout-btn <?php echo $layout === 'list' ? 'active' : ''; ?>" data-layout="list">
                    <?php _e('List', 'carbon-marketplace'); ?>
                </button>
            </div>
        </div>

        <div class="search-results" id="search-results">
            <!-- Results will be loaded here via AJAX -->
        </div>

        <div class="search-pagination" id="search-pagination">
            <!-- Pagination will be loaded here via AJAX -->
        </div>
    </div>

    <div class="search-loading" id="search-loading" style="display: none;">
        <div class="loading-spinner"></div>
        <p><?php _e('Searching carbon projects...', 'carbon-marketplace'); ?></p>
    </div>
</div>

<script>
// Initialize search functionality
jQuery(document).ready(function($) {
    if (typeof CarbonMarketplaceSearch !== 'undefined') {
        CarbonMarketplaceSearch.init({
            form: '#carbon-search-form',
            resultsContainer: '#search-results',
            loadingElement: '#search-loading',
            resultsPerPage: <?php echo $results_per_page; ?>,
            defaultLayout: '<?php echo $layout; ?>'
        });
    }
});
</script>