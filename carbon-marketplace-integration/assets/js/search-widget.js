/**
 * Search Widget JavaScript for Carbon Marketplace Integration
 */

(function($) {
    'use strict';
    
    var CarbonSearchWidget = {
        
        /**
         * Initialize search widgets
         */
        init: function() {
            this.bindEvents();
            this.initializeWidgets();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Form submission
            $(document).on('submit', '.carbon-search-form', this.handleFormSubmit);
            
            // Clear filters
            $(document).on('click', '.carbon-clear-button', this.handleClearFilters);
            
            // Instant search (if enabled)
            $(document).on('input', '.carbon-search-form input', this.handleInstantSearch);
            $(document).on('change', '.carbon-search-form select', this.handleInstantSearch);
            
            // Load more button
            $(document).on('click', '.load-more-btn', this.handleLoadMore);
            
            // Pagination
            $(document).on('click', '.pagination-btn', this.handlePagination);
            
            // Project actions
            $(document).on('click', '.project-view-btn', this.handleProjectView);
            $(document).on('click', '.project-buy-btn', this.handleProjectBuy);
        },
        
        /**
         * Initialize all search widgets on the page
         */
        initializeWidgets: function() {
            $('.carbon-marketplace-search-widget').each(function() {
                var $widget = $(this);
                var widgetId = $widget.attr('id');
                
                if (widgetId) {
                    CarbonSearchWidget.initWidget(widgetId);
                }
            });
        },
        
        /**
         * Initialize individual widget
         */
        initWidget: function(widgetId) {
            var $widget = $('#' + widgetId);
            var $results = $widget.find('.carbon-search-results');
            
            // Store widget configuration
            var config = {
                layout: $results.data('layout') || 'grid',
                columns: $results.data('columns') || '3',
                perPage: $results.data('per-page') || 12,
                showImages: $results.data('show-images') === 'yes',
                showPrice: $results.data('show-price') === 'yes',
                showLocation: $results.data('show-location') === 'yes',
                showPagination: $results.data('show-pagination') === 'yes',
                instantSearch: $results.data('instant-search') === 'yes'
            };
            
            $widget.data('config', config);
            
            // Load initial results
            this.performSearch(widgetId, {}, true);
        },
        
        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $widget = $form.closest('.carbon-marketplace-search-widget');
            var widgetId = $widget.attr('id');
            var searchParams = CarbonSearchWidget.getSearchParams($form);
            
            CarbonSearchWidget.performSearch(widgetId, searchParams);
        },
        
        /**
         * Handle clear filters
         */
        handleClearFilters: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $form = $button.closest('.carbon-search-form');
            var $widget = $form.closest('.carbon-marketplace-search-widget');
            var widgetId = $widget.attr('id');
            
            // Clear form fields
            $form[0].reset();
            
            // Perform search with empty parameters
            CarbonSearchWidget.performSearch(widgetId, {});
        },
        
        /**
         * Handle instant search
         */
        handleInstantSearch: function(e) {
            var $input = $(this);
            var $form = $input.closest('.carbon-search-form');
            var $widget = $form.closest('.carbon-marketplace-search-widget');
            var config = $widget.data('config');
            
            if (!config || !config.instantSearch) {
                return;
            }
            
            var widgetId = $widget.attr('id');
            
            // Debounce the search
            clearTimeout($widget.data('searchTimeout'));
            
            var timeout = setTimeout(function() {
                var searchParams = CarbonSearchWidget.getSearchParams($form);
                CarbonSearchWidget.performSearch(widgetId, searchParams);
            }, 500);
            
            $widget.data('searchTimeout', timeout);
        },
        
        /**
         * Handle load more button
         */
        handleLoadMore: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $widget = $button.closest('.carbon-marketplace-search-widget');
            var widgetId = $widget.attr('id');
            var currentPage = parseInt($widget.data('currentPage') || 1);
            var $form = $widget.find('.carbon-search-form');
            var searchParams = CarbonSearchWidget.getSearchParams($form);
            
            searchParams.page = currentPage + 1;
            
            CarbonSearchWidget.performSearch(widgetId, searchParams, false, true);
        },
        
        /**
         * Handle pagination
         */
        handlePagination: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var page = parseInt($button.data('page'));
            var $widget = $button.closest('.carbon-marketplace-search-widget');
            var widgetId = $widget.attr('id');
            var $form = $widget.find('.carbon-search-form');
            var searchParams = CarbonSearchWidget.getSearchParams($form);
            
            if (page && !$button.hasClass('current')) {
                searchParams.page = page;
                CarbonSearchWidget.performSearch(widgetId, searchParams);
            }
        },
        
        /**
         * Handle project view button
         */
        handleProjectView: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var projectId = $button.data('project-id');
            
            if (projectId) {
                // Open project detail modal or navigate to project page
                CarbonSearchWidget.showProjectDetails(projectId);
            }
        },
        
        /**
         * Handle project buy button
         */
        handleProjectBuy: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var projectId = $button.data('project-id');
            var vendor = $button.data('vendor');
            
            if (projectId && vendor) {
                // Redirect to purchase flow
                CarbonSearchWidget.initiatePurchase(projectId, vendor);
            }
        },
        
        /**
         * Get search parameters from form
         */
        getSearchParams: function($form) {
            var params = {};
            
            $form.find('input, select').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                
                if (name && value) {
                    params[name] = value;
                }
            });
            
            return params;
        },
        
        /**
         * Perform search via AJAX
         */
        performSearch: function(widgetId, searchParams, isInitial, isLoadMore) {
            var $widget = $('#' + widgetId);
            var $results = $widget.find('.carbon-search-results');
            var $loading = $results.find('.search-loading');
            var $container = $results.find('.search-results-container');
            var $noResults = $results.find('.search-no-results');
            var config = $widget.data('config');
            
            isInitial = isInitial || false;
            isLoadMore = isLoadMore || false;
            
            // Show loading state
            if (!isLoadMore) {
                $loading.show();
                $container.hide();
                $noResults.hide();
            }
            
            // Prepare AJAX data
            var ajaxData = {
                action: 'carbon_marketplace_search_projects',
                nonce: carbonMarketplaceSearch.nonce,
                widget_id: widgetId,
                search_params: searchParams,
                config: config,
                page: searchParams.page || 1,
                per_page: config.perPage
            };
            
            // Make AJAX request
            $.ajax({
                url: carbonMarketplaceSearch.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    CarbonSearchWidget.handleSearchSuccess(widgetId, response, isLoadMore);
                },
                error: function() {
                    CarbonSearchWidget.handleSearchError(widgetId);
                },
                complete: function() {
                    $loading.hide();
                }
            });
        },
        
        /**
         * Handle successful search response
         */
        handleSearchSuccess: function(widgetId, response, isLoadMore) {
            var $widget = $('#' + widgetId);
            var $results = $widget.find('.carbon-search-results');
            var $container = $results.find('.search-results-container');
            var $noResults = $results.find('.search-no-results');
            var config = $widget.data('config');
            
            if (response.success && response.data) {
                var data = response.data;
                
                if (data.projects && data.projects.length > 0) {
                    // Render projects
                    var projectsHtml = CarbonSearchWidget.renderProjects(data.projects, config);
                    
                    if (isLoadMore) {
                        $container.append(projectsHtml);
                    } else {
                        $container.html(projectsHtml);
                    }
                    
                    // Update pagination
                    if (config.showPagination && data.pagination) {
                        var paginationHtml = CarbonSearchWidget.renderPagination(data.pagination);
                        
                        // Remove existing pagination
                        $results.find('.search-pagination').remove();
                        
                        // Add new pagination
                        if (paginationHtml) {
                            $results.append(paginationHtml);
                        }
                    }
                    
                    // Update load more button
                    CarbonSearchWidget.updateLoadMoreButton($widget, data.pagination);
                    
                    // Store current page
                    $widget.data('currentPage', data.pagination.current_page);
                    
                    $container.show();
                    $noResults.hide();
                } else {
                    // No results found
                    if (!isLoadMore) {
                        $container.hide();
                        $noResults.show();
                    }
                }
            } else {
                CarbonSearchWidget.handleSearchError(widgetId, response.data);
            }
        },
        
        /**
         * Handle search error
         */
        handleSearchError: function(widgetId, errorMessage) {
            var $widget = $('#' + widgetId);
            var $results = $widget.find('.carbon-search-results');
            var $container = $results.find('.search-results-container');
            
            var errorHtml = '<div class="search-error">' +
                '<p>' + (errorMessage || carbonMarketplaceSearch.strings.error) + '</p>' +
                '</div>';
            
            $container.html(errorHtml).show();
        },
        
        /**
         * Render projects HTML
         */
        renderProjects: function(projects, config) {
            var html = '';
            
            projects.forEach(function(project) {
                html += CarbonSearchWidget.renderProjectCard(project, config);
            });
            
            return html;
        },
        
        /**
         * Render individual project card
         */
        renderProjectCard: function(project, config) {
            var html = '<div class="project-card">';
            
            // Project image
            if (config.showImages && project.image) {
                html += '<img src="' + project.image + '" alt="' + project.name + '" class="project-image">';
            }
            
            html += '<div class="project-content">';
            
            // Project title
            html += '<h3 class="project-title">' + project.name + '</h3>';
            
            // Project description
            if (project.description) {
                html += '<p class="project-description">' + project.description + '</p>';
            }
            
            // Project meta
            html += '<div class="project-meta">';
            
            if (config.showLocation && project.location) {
                html += '<span class="project-location">' + project.location + '</span>';
            }
            
            if (project.project_type) {
                html += '<span class="project-type">' + project.project_type + '</span>';
            }
            
            html += '</div>';
            
            // Project price
            if (config.showPrice && project.price_per_kg) {
                html += '<div class="project-price">$' + project.price_per_kg + ' per tonne</div>';
            }
            
            // Project actions
            html += '<div class="project-actions">';
            html += '<button class="project-view-btn" data-project-id="' + project.id + '">View Details</button>';
            html += '<button class="project-buy-btn" data-project-id="' + project.id + '" data-vendor="' + project.vendor + '">Buy Credits</button>';
            html += '</div>';
            
            html += '</div>'; // project-content
            html += '</div>'; // project-card
            
            return html;
        },
        
        /**
         * Render pagination HTML
         */
        renderPagination: function(pagination) {
            if (!pagination || pagination.total_pages <= 1) {
                return '';
            }
            
            var html = '<div class="search-pagination">';
            
            // Previous button
            if (pagination.current_page > 1) {
                html += '<button class="pagination-btn" data-page="' + (pagination.current_page - 1) + '">Previous</button>';
            }
            
            // Page numbers
            var startPage = Math.max(1, pagination.current_page - 2);
            var endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            for (var i = startPage; i <= endPage; i++) {
                var activeClass = i === pagination.current_page ? ' current' : '';
                html += '<button class="pagination-btn' + activeClass + '" data-page="' + i + '">' + i + '</button>';
            }
            
            // Next button
            if (pagination.current_page < pagination.total_pages) {
                html += '<button class="pagination-btn" data-page="' + (pagination.current_page + 1) + '">Next</button>';
            }
            
            // Results info
            var start = ((pagination.current_page - 1) * pagination.per_page) + 1;
            var end = Math.min(pagination.current_page * pagination.per_page, pagination.total_count);
            
            html += '<span class="pagination-info">';
            html += carbonMarketplaceSearch.strings.showingResults
                .replace('{start}', start)
                .replace('{end}', end)
                .replace('{total}', pagination.total_count);
            html += '</span>';
            
            html += '</div>';
            
            return html;
        },
        
        /**
         * Update load more button
         */
        updateLoadMoreButton: function($widget, pagination) {
            var $loadMore = $widget.find('.load-more-btn');
            
            if (pagination && pagination.current_page < pagination.total_pages) {
                if ($loadMore.length === 0) {
                    var $results = $widget.find('.carbon-search-results');
                    $results.append('<button class="load-more-btn">' + carbonMarketplaceSearch.strings.loadMore + '</button>');
                } else {
                    $loadMore.show();
                }
            } else {
                $loadMore.hide();
            }
        },
        
        /**
         * Show project details
         */
        showProjectDetails: function(projectId) {
            // This would typically open a modal or navigate to a detail page
            // For now, we'll just log the action
            console.log('Show project details for:', projectId);
            
            // You could implement a modal here or redirect to a detail page
            // window.location.href = '/project/' + projectId;
        },
        
        /**
         * Initiate purchase flow
         */
        initiatePurchase: function(projectId, vendor) {
            // This would typically redirect to the purchase flow
            console.log('Initiate purchase for project:', projectId, 'vendor:', vendor);
            
            // You could redirect to a purchase page or open a purchase modal
            // window.location.href = '/purchase/' + vendor + '/' + projectId;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        CarbonSearchWidget.init();
    });
    
    // Re-initialize when new content is loaded (for AJAX page loads)
    $(document).on('elementor/popup/show', function() {
        CarbonSearchWidget.initializeWidgets();
    });
    
})(jQuery);