/**
 * Carbon Marketplace Frontend JavaScript
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Main Carbon Marketplace Frontend Object
     */
    window.CarbonMarketplace = window.CarbonMarketplace || {};
    
    /**
     * Initialize all components
     */
    CarbonMarketplace.init = function() {
        this.initSearchForm();
        this.initProjectGrid();
        this.initProjectDetail();
        this.initQuoteModal();
        this.initPurchaseModal();
    };
    
    /**
     * Search Form Functionality
     */
    CarbonMarketplace.initSearchForm = function() {
        const $searchForm = $('.carbon-marketplace-search-form');
        if (!$searchForm.length) return;
        
        // Search form submission
        $searchForm.on('submit', function(e) {
            e.preventDefault();
            CarbonMarketplace.performSearch();
        });
        
        // Filter changes
        $searchForm.find('select, input').on('change', function() {
            CarbonMarketplace.performSearch();
        });
        
        // Clear filters
        $('#clear-filters').on('click', function() {
            $searchForm.find('select').val('');
            $searchForm.find('input[type="text"]').val('');
            CarbonMarketplace.performSearch();
        });
        
        // Layout toggle
        $('.layout-btn').on('click', function() {
            const layout = $(this).data('layout');
            $('.layout-btn').removeClass('active');
            $(this).addClass('active');
            $('.search-results').removeClass('grid-layout list-layout').addClass(layout + '-layout');
        });
    };
    
    /**
     * Perform search via AJAX
     */
    CarbonMarketplace.performSearch = function() {
        const $form = $('.carbon-marketplace-search-form');
        const $results = $('#search-results');
        const $loading = $('#search-loading');
        const $count = $('#results-count');
        
        // Show loading
        $loading.show();
        $results.hide();
        
        // Collect form data
        const formData = {
            action: 'carbon_marketplace_search',
            nonce: carbonMarketplace.nonce,
            search_term: $('#carbon-search-input').val(),
            location: $('#location-filter').val(),
            project_type: $('#project-type-filter').val(),
            price_range: $('#price-range-filter').val(),
            vendor: $('#vendor-filter').val(),
            page: 1,
            per_page: 20
        };
        
        // Perform AJAX request
        $.ajax({
            url: carbonMarketplace.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                $loading.hide();
                $results.show();
                
                if (response.success) {
                    $results.html(response.data.html);
                    $count.text(response.data.count_text);
                } else {
                    $results.html('<div class="search-error">' + (response.data || carbonMarketplace.strings.error) + '</div>');
                    $count.text('');
                }
            },
            error: function() {
                $loading.hide();
                $results.show().html('<div class="search-error">' + carbonMarketplace.strings.error + '</div>');
                $count.text('');
            }
        });
    };
    
    /**
     * Project Grid Functionality
     */
    CarbonMarketplace.initProjectGrid = function() {
        // Project detail links
        $(document).on('click', '.project-detail-link', function(e) {
            e.preventDefault();
            const projectId = $(this).data('project-id');
            const vendor = $(this).data('vendor') || $(this).closest('.project-card').data('vendor');
            CarbonMarketplace.showProjectDetail(projectId, vendor);
        });
        
        // Get quote buttons
        $(document).on('click', '.get-quote-btn', function(e) {
            e.preventDefault();
            const projectId = $(this).data('project-id');
            const vendor = $(this).data('vendor') || $(this).closest('.project-card').data('vendor');
            CarbonMarketplace.showQuoteModal(projectId, vendor);
        });
        
        // View details buttons
        $(document).on('click', '.view-details-btn', function(e) {
            e.preventDefault();
            const projectId = $(this).data('project-id');
            const vendor = $(this).data('vendor') || $(this).closest('.project-card').data('vendor');
            CarbonMarketplace.showProjectDetail(projectId, vendor);
        });
        
        // Load more button
        $(document).on('click', '.load-more-btn', function(e) {
            e.preventDefault();
            CarbonMarketplace.loadMoreProjects($(this));
        });
    };
    
    /**
     * Project Detail Functionality
     */
    CarbonMarketplace.initProjectDetail = function() {
        // Quantity calculator
        $(document).on('input', '#carbon-quantity', function() {
            CarbonMarketplace.updatePriceCalculator();
        });
        
        // Get quote from detail page
        $(document).on('click', '.get-quote-btn-detail', function(e) {
            e.preventDefault();
            const projectId = $(this).data('project-id');
            const vendor = $(this).data('vendor');
            const quantity = parseFloat($('#carbon-quantity').val()) || 1;
            CarbonMarketplace.showQuoteModal(projectId, vendor, quantity);
        });
        
        // Purchase from detail page
        $(document).on('click', '.purchase-btn-detail', function(e) {
            e.preventDefault();
            const projectId = $(this).data('project-id');
            const vendor = $(this).data('vendor');
            const quantity = parseFloat($('#carbon-quantity').val()) || 1;
            CarbonMarketplace.showPurchaseModal(projectId, vendor, quantity);
        });
        
        // Image gallery
        $(document).on('click', '.thumbnail', function() {
            const index = $(this).data('index');
            const $mainImage = $('.main-image img');
            const $thumbnail = $(this).find('img');
            
            // Swap images
            const mainSrc = $mainImage.attr('src');
            const thumbSrc = $thumbnail.attr('src');
            $mainImage.attr('src', thumbSrc);
            $thumbnail.attr('src', mainSrc);
        });
    };
    
    /**
     * Update price calculator
     */
    CarbonMarketplace.updatePriceCalculator = function() {
        const quantity = parseFloat($('#carbon-quantity').val()) || 1;
        const pricePerKg = parseFloat($('#carbon-quantity').data('price-per-kg')) || 0;
        const total = quantity * pricePerKg;
        
        $('.quantity-display').text(quantity + ' tCO2e');
        $('.total-price').text('$' + total.toFixed(2));
    };
    
    /**
     * Show project detail modal or page
     */
    CarbonMarketplace.showProjectDetail = function(projectId, vendor) {
        // This could open a modal or redirect to a detail page
        // For now, we'll create a simple modal
        const modalHtml = `
            <div class="carbon-modal" id="project-detail-modal">
                <div class="carbon-modal-content">
                    <span class="carbon-modal-close">&times;</span>
                    <div class="carbon-modal-body">
                        <div class="loading-container">
                            <div class="loading-spinner"></div>
                            <p>Loading project details...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        $('#project-detail-modal').show();
        
        // Load project details via AJAX
        $.ajax({
            url: carbonMarketplace.ajaxUrl,
            type: 'POST',
            data: {
                action: 'carbon_marketplace_get_project_detail',
                nonce: carbonMarketplace.nonce,
                project_id: projectId,
                vendor: vendor
            },
            success: function(response) {
                if (response.success) {
                    $('#project-detail-modal .carbon-modal-body').html(response.data.html);
                } else {
                    $('#project-detail-modal .carbon-modal-body').html('<div class="error">Failed to load project details.</div>');
                }
            },
            error: function() {
                $('#project-detail-modal .carbon-modal-body').html('<div class="error">Failed to load project details.</div>');
            }
        });
    };
    
    /**
     * Show quote modal
     */
    CarbonMarketplace.showQuoteModal = function(projectId, vendor, quantity = 1) {
        // Implementation for quote modal
        console.log('Show quote modal for project:', projectId, 'vendor:', vendor, 'quantity:', quantity);
    };
    
    /**
     * Show purchase modal
     */
    CarbonMarketplace.showPurchaseModal = function(projectId, vendor, quantity = 1) {
        // Implementation for purchase modal
        console.log('Show purchase modal for project:', projectId, 'vendor:', vendor, 'quantity:', quantity);
    };
    
    /**
     * Initialize quote modal functionality
     */
    CarbonMarketplace.initQuoteModal = function() {
        // Close modal functionality
        $(document).on('click', '.carbon-modal-close, .carbon-modal-backdrop', function() {
            $('.carbon-modal').remove();
        });
        
        // ESC key to close modal
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.carbon-modal').remove();
            }
        });
    };
    
    /**
     * Initialize purchase modal functionality
     */
    CarbonMarketplace.initPurchaseModal = function() {
        // Implementation for purchase modal initialization
    };
    
    /**
     * Load more projects
     */
    CarbonMarketplace.loadMoreProjects = function($button) {
        const offset = parseInt($button.data('offset')) || 0;
        const limit = parseInt($button.data('limit')) || 20;
        
        $button.prop('disabled', true).text('Loading...');
        
        // Get current search parameters
        const $form = $('.carbon-marketplace-search-form');
        const formData = {
            action: 'carbon_marketplace_search',
            nonce: carbonMarketplace.nonce,
            search_term: $('#carbon-search-input').val(),
            location: $('#location-filter').val(),
            project_type: $('#project-type-filter').val(),
            price_range: $('#price-range-filter').val(),
            vendor: $('#vendor-filter').val(),
            offset: offset,
            per_page: limit
        };
        
        $.ajax({
            url: carbonMarketplace.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success && response.data.html) {
                    // Append new projects
                    $('.projects-grid').append(response.data.html);
                    
                    // Update button or hide if no more results
                    if (response.data.has_more) {
                        $button.data('offset', offset + limit)
                               .prop('disabled', false)
                               .text('Load More Projects');
                    } else {
                        $button.remove();
                    }
                } else {
                    $button.prop('disabled', false).text('Load More Projects');
                }
            },
            error: function() {
                $button.prop('disabled', false).text('Load More Projects');
            }
        });
    };
    
    /**
     * Utility functions
     */
    CarbonMarketplace.utils = {
        formatPrice: function(price, currency = 'USD') {
            const formatter = new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            });
            return formatter.format(price);
        },
        
        formatNumber: function(number, decimals = 2) {
            return parseFloat(number).toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        },
        
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        CarbonMarketplace.init();
    });
    
})(jQuery);