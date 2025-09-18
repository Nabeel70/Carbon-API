<?php
/**
 * Template for search form shortcode
 *
 * @package CarbonMarketplace
 * @var array $atts Shortcode attributes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="carbon-marketplace-search-wrapper">
    <div id="carbon-marketplace-search" class="carbon-search-form">
        <form id="carbon-search-form" method="get">
            <div class="search-row">
                <div class="search-field">
                    <label for="search-keywords"><?php _e('Keywords', 'carbon-marketplace'); ?></label>
                    <input type="text" id="search-keywords" name="keywords" placeholder="<?php _e('Search for carbon projects...', 'carbon-marketplace'); ?>">
                </div>
                <div class="search-field">
                    <button type="submit" class="search-button"><?php _e('Search', 'carbon-marketplace'); ?></button>
                </div>
            </div>

            <?php if ($atts['show_filters'] === 'true'): ?>
            <div class="search-filters">
                <div class="filter-row">
                    <div class="filter-field">
                        <label for="location-filter"><?php _e('Location', 'carbon-marketplace'); ?></label>
                        <select id="location-filter" name="location">
                            <option value=""><?php _e('All Locations', 'carbon-marketplace'); ?></option>
                            <option value="africa"><?php _e('Africa', 'carbon-marketplace'); ?></option>
                            <option value="asia"><?php _e('Asia', 'carbon-marketplace'); ?></option>
                            <option value="europe"><?php _e('Europe', 'carbon-marketplace'); ?></option>
                            <option value="north-america"><?php _e('North America', 'carbon-marketplace'); ?></option>
                            <option value="south-america"><?php _e('South America', 'carbon-marketplace'); ?></option>
                            <option value="oceania"><?php _e('Oceania', 'carbon-marketplace'); ?></option>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="project-type-filter"><?php _e('Project Type', 'carbon-marketplace'); ?></label>
                        <select id="project-type-filter" name="project_type">
                            <option value=""><?php _e('All Types', 'carbon-marketplace'); ?></option>
                            <option value="renewable-energy"><?php _e('Renewable Energy', 'carbon-marketplace'); ?></option>
                            <option value="forest-conservation"><?php _e('Forest Conservation', 'carbon-marketplace'); ?></option>
                            <option value="biochar"><?php _e('Biochar', 'carbon-marketplace'); ?></option>
                            <option value="methane-capture"><?php _e('Methane Capture', 'carbon-marketplace'); ?></option>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="vendor-filter"><?php _e('Vendor', 'carbon-marketplace'); ?></label>
                        <select id="vendor-filter" name="vendor">
                            <option value=""><?php _e('All Vendors', 'carbon-marketplace'); ?></option>
                            <option value="cnaught"><?php _e('CNaught', 'carbon-marketplace'); ?></option>
                            <option value="toucan"><?php _e('Toucan Protocol', 'carbon-marketplace'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <div id="carbon-search-results" class="carbon-search-results <?php echo esc_attr($atts['layout']); ?>">
        <!-- Search results will be loaded here via AJAX -->
    </div>

    <div id="carbon-search-loading" class="carbon-loading" style="display: none;">
        <p><?php _e('Loading...', 'carbon-marketplace'); ?></p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var searchForm = $('#carbon-search-form');
    var resultsContainer = $('#carbon-search-results');
    var loadingElement = $('#carbon-search-loading');
    
    // Handle form submission
    searchForm.on('submit', function(e) {
        e.preventDefault();
        performSearch();
    });
    
    // Handle filter changes
    searchForm.find('select, input').on('change', function() {
        performSearch();
    });
    
    // Perform search function
    function performSearch() {
        var formData = searchForm.serialize();
        formData += '&action=carbon_marketplace_search';
        formData += '&nonce=' + carbonMarketplace.nonce;
        formData += '&results_per_page=' + <?php echo (int)$atts['results_per_page']; ?>;
        formData += '&layout=' + '<?php echo esc_js($atts['layout']); ?>';
        
        loadingElement.show();
        resultsContainer.html('');
        
        $.ajax({
            url: carbonMarketplace.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                loadingElement.hide();
                
                if (response.success && response.data) {
                    resultsContainer.html(response.data.html);
                } else {
                    resultsContainer.html('<div class="no-results">' + carbonMarketplace.strings.noResults + '</div>');
                }
            },
            error: function() {
                loadingElement.hide();
                resultsContainer.html('<div class="error-message">' + carbonMarketplace.strings.error + '</div>');
            }
        });
    }
    
    // Load initial results
    performSearch();
});
</script>

<style>
.carbon-marketplace-search-wrapper {
    margin: 20px 0;
}

.carbon-search-form {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.search-row, .filter-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.search-field, .filter-field {
    flex: 1;
}

.search-field label, .filter-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.search-field input, .filter-field select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.search-button {
    background: #0073aa;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 3px;
    cursor: pointer;
}

.search-button:hover {
    background: #005a87;
}

.carbon-search-results {
    min-height: 200px;
}

.carbon-search-results.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.carbon-loading {
    text-align: center;
    padding: 20px;
}

.no-results, .error-message {
    text-align: center;
    padding: 40px;
    background: #f0f0f0;
    border-radius: 5px;
    color: #666;
}

.error-message {
    background: #ffeaea;
    color: #d63638;
}

@media (max-width: 768px) {
    .search-row, .filter-row {
        flex-direction: column;
    }
}
</style>