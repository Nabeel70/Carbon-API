/**
 * Carbon Marketplace Search AJAX JavaScript
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Carbon Marketplace Search Handler
     */
    const CarbonMarketplaceSearch = {
        
        /**
         * Configuration
         */
        config: {
            searchFormSelector: '.carbon-marketplace-search-form',
            resultsContainerSelector: '.carbon-marketplace-results',
            loadingSelector: '.carbon-marketplace-loading',
            paginationSelector: '.carbon-marketplace-pagination',
            suggestionsSelector: '.carbon-marketplace-suggestions',
            debounceDelay: 300,
            suggestionsMinLength: 2,
        },
        
        /**
         * Current search request (for cancellation)
         */
        currentSearchRequest: null,
        
        /**
         * Current suggestions request (for cancellation)
         */
        currentSuggestionsRequest: null,
        
        /**
         * Debounce timer
         */
        debounceTimer: null,
        
        /**
         * Initialize the search functionality
         */
        init: function() {
            this.bindEvents();
            this.initializeExistingForms();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;
            
            // Search form submission
            $(document).on('submit', this.config.searchFormSelector, function(e) {
                e.preventDefault();
                self.performSearch($(this));
            });
            
            // Real-time search on input
            $(document).on('input', this.config.searchFormSelector + ' input[name="keyword"]', function() {
                const form = $(this).closest(self.config.searchFormSelector);
                self.debounceSearch(form);
            });
            
            // Filter changes
            $(document).on('change', this.config.searchFormSelector + ' select, ' + this.config.searchFormSelector + ' input[type="range"]', function() {
                const form = $(this).closest(self.config.searchFormSelector);
                self.performSearch(form);
            });
            
            // Pagination clicks
            $(document).on('click', this.config.paginationSelector + ' a', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                const form = $(this).closest('.carbon-marketplace-widget').find(self.config.searchFormSelector);
                self.performSearch(form, { page: page });
            });
            
            // Suggestions
            $(document).on('input', this.config.searchFormSelector + ' input[name="keyword"]', function() {
                const input = $(this);
                self.debounceSuggestions(input);
            });
            
            // Hide suggestions on blur (with delay for clicks)
            $(document).on('blur', this.config.searchFormSelector + ' input[name="keyword"]', function() {
                setTimeout(() => {
                    $(this).siblings(self.config.suggestionsSelector).hide();
                }, 200);
            });
            
            // Suggestion clicks
            $(document).on('click', this.config.suggestionsSelector + ' .suggestion-item', function() {
                const suggestion = $(this).text();
                const input = $(this).closest('.search-input-container').find('input[name="keyword"]');
                const form = input.closest(self.config.searchFormSelector);
                
                input.val(suggestion);
                $(this).parent().hide();
                self.performSearch(form);
            });
            
            // Project detail requests
            $(document).on('click', '.project-card[data-project-id]', function() {
                const projectId = $(this).data('project-id');
                self.loadProjectDetails(projectId);
            });
        },
        
        /**
         * Initialize existing forms on page load
         */
        initializeExistingForms: function() {
            const self = this;
            $(this.config.searchFormSelector).each(function() {
                const form = $(this);
                // Perform initial search if form has default values
                if (self.hasSearchParams(form)) {
                    self.performSearch(form);
                }
            });
        },
        
        /**
         * Check if form has search parameters
         */
        hasSearchParams: function(form) {
            const keyword = form.find('input[name="keyword"]').val();
            const location = form.find('select[name="location"]').val();
            const projectType = form.find('select[name="project_type"]').val();
            
            return keyword || location || projectType;
        },
        
        /**
         * Debounce search execution
         */
        debounceSearch: function(form) {
            const self = this;
            
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(function() {
                self.performSearch(form);
            }, this.config.debounceDelay);
        },
        
        /**
         * Debounce suggestions
         */
        debounceSuggestions: function(input) {
            const self = this;
            
            clearTimeout(this.suggestionsTimer);
            this.suggestionsTimer = setTimeout(function() {
                self.loadSuggestions(input);
            }, this.config.debounceDelay);
        },
        
        /**
         * Perform search
         */
        performSearch: function(form, options = {}) {
            const self = this;
            
            // Cancel previous request
            if (this.currentSearchRequest) {
                this.currentSearchRequest.abort();
            }
            
            // Get form data
            const formData = this.getFormData(form, options);
            
            // Show loading state
            this.showLoading(form);
            
            // Perform AJAX request
            this.currentSearchRequest = $.ajax({
                url: carbonMarketplaceAjax.ajax_url,
                type: 'POST',
                data: {
                    action: carbonMarketplaceAjax.actions.search,
                    nonce: carbonMarketplaceAjax.nonce,
                    ...formData
                },
                success: function(response) {
                    self.handleSearchResponse(form, response);
                },
                error: function(xhr, status, error) {
                    if (status !== 'abort') {
                        self.handleSearchError(form, error);
                    }
                },
                complete: function() {
                    self.hideLoading(form);
                    self.currentSearchRequest = null;
                }
            });
        },
        
        /**
         * Load suggestions
         */
        loadSuggestions: function(input) {
            const self = this;
            const inputValue = input.val().trim();
            
            if (inputValue.length < this.config.suggestionsMinLength) {
                this.hideSuggestions(input);
                return;
            }
            
            // Cancel previous request
            if (this.currentSuggestionsRequest) {
                this.currentSuggestionsRequest.abort();
            }
            
            this.currentSuggestionsRequest = $.ajax({
                url: carbonMarketplaceAjax.ajax_url,
                type: 'POST',
                data: {
                    action: carbonMarketplaceAjax.actions.suggestions,
                    nonce: carbonMarketplaceAjax.nonce,
                    input: inputValue,
                    limit: 10
                },
                success: function(response) {
                    self.handleSuggestionsResponse(input, response);
                },
                error: function(xhr, status, error) {
                    if (status !== 'abort') {
                        console.error('Suggestions error:', error);
                    }
                },
                complete: function() {
                    self.currentSuggestionsRequest = null;
                }
            });
        },
        
        /**
         * Load project details
         */
        loadProjectDetails: function(projectId) {
            const self = this;
            
            $.ajax({
                url: carbonMarketplaceAjax.ajax_url,
                type: 'POST',
                data: {
                    action: carbonMarketplaceAjax.actions.project_details,
                    nonce: carbonMarketplaceAjax.nonce,
                    project_id: projectId
                },
                success: function(response) {
                    self.handleProjectDetailsResponse(response);
                },
                error: function(xhr, status, error) {
                    self.handleProjectDetailsError(error);
                }
            });
        },
        
        /**
         * Get form data
         */
        getFormData: function(form, options = {}) {
            const data = {};
            
            // Get form fields
            form.find('input, select').each(function() {
                const field = $(this);
                const name = field.attr('name');
                const value = field.val();
                
                if (name && value) {
                    data[name] = value;
                }
            });
            
            // Handle pagination
            if (options.page) {
                const limit = parseInt(data.limit) || 20;
                data.offset = (options.page - 1) * limit;
            }
            
            return data;
        },
        
        /**
         * Handle search response
         */
        handleSearchResponse: function(form, response) {
            if (response.success) {
                this.displayResults(form, response.data);
                this.updatePagination(form, response.data.pagination);
                this.updateUrl(response.data.filters_applied);
            } else {
                this.displayError(form, response.error.message);
            }
        },
        
        /**
         * Handle search error
         */
        handleSearchError: function(form, error) {
            this.displayError(form, 'Search failed. Please try again.');
            console.error('Search error:', error);
        },
        
        /**
         * Handle suggestions response
         */
        handleSuggestionsResponse: function(input, response) {
            if (response.success && response.data.suggestions.length > 0) {
                this.displaySuggestions(input, response.data.suggestions);
            } else {
                this.hideSuggestions(input);
            }
        },
        
        /**
         * Handle project details response
         */
        handleProjectDetailsResponse: function(response) {
            if (response.success) {
                this.displayProjectModal(response.data.project);
            } else {
                alert('Failed to load project details: ' + response.error.message);
            }
        },
        
        /**
         * Handle project details error
         */
        handleProjectDetailsError: function(error) {
            alert('Failed to load project details. Please try again.');
            console.error('Project details error:', error);
        },
        
        /**
         * Display search results
         */
        displayResults: function(form, data) {
            const container = form.siblings(this.config.resultsContainerSelector);
            
            if (data.projects.length === 0) {
                container.html('<div class="no-results">No projects found matching your criteria.</div>');
                return;
            }
            
            let html = '<div class="projects-grid">';
            
            data.projects.forEach(function(project) {
                html += `
                    <div class="project-card" data-project-id="${project.id}">
                        <div class="project-header">
                            <h3 class="project-name">${project.name}</h3>
                            <span class="project-vendor">${project.vendor}</span>
                        </div>
                        <div class="project-details">
                            <div class="project-location">${project.location}</div>
                            <div class="project-type">${project.project_type}</div>
                            <div class="project-price">${project.formatted_price}</div>
                            <div class="project-availability ${project.available ? 'available' : 'unavailable'}">
                                ${project.available ? 'Available' : 'Sold Out'}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.html(html);
        },
        
        /**
         * Display error message
         */
        displayError: function(form, message) {
            const container = form.siblings(this.config.resultsContainerSelector);
            container.html(`<div class="search-error">${message}</div>`);
        },
        
        /**
         * Display suggestions
         */
        displaySuggestions: function(input, suggestions) {
            let suggestionsContainer = input.siblings(this.config.suggestionsSelector);
            
            if (suggestionsContainer.length === 0) {
                suggestionsContainer = $('<div class="carbon-marketplace-suggestions"></div>');
                input.after(suggestionsContainer);
            }
            
            let html = '';
            suggestions.forEach(function(suggestion) {
                html += `<div class="suggestion-item">${suggestion}</div>`;
            });
            
            suggestionsContainer.html(html).show();
        },
        
        /**
         * Hide suggestions
         */
        hideSuggestions: function(input) {
            input.siblings(this.config.suggestionsSelector).hide();
        },
        
        /**
         * Update pagination
         */
        updatePagination: function(form, pagination) {
            const container = form.siblings(this.config.paginationSelector);
            
            if (pagination.total_pages <= 1) {
                container.empty();
                return;
            }
            
            let html = '<div class="pagination-wrapper">';
            
            // Previous button
            if (pagination.has_previous_page) {
                html += `<a href="#" class="pagination-btn prev" data-page="${pagination.current_page - 1}">Previous</a>`;
            }
            
            // Page numbers
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === pagination.current_page ? 'active' : '';
                html += `<a href="#" class="pagination-btn ${activeClass}" data-page="${i}">${i}</a>`;
            }
            
            // Next button
            if (pagination.has_next_page) {
                html += `<a href="#" class="pagination-btn next" data-page="${pagination.current_page + 1}">Next</a>`;
            }
            
            html += '</div>';
            html += `<div class="pagination-info">Showing ${pagination.result_count} of ${pagination.total_count} projects</div>`;
            
            container.html(html);
        },
        
        /**
         * Display project modal
         */
        displayProjectModal: function(project) {
            // This would open a modal with project details
            // Implementation depends on the modal library being used
            console.log('Project details:', project);
            
            // Example implementation with a simple modal
            const modalHtml = `
                <div class="project-modal-overlay">
                    <div class="project-modal">
                        <div class="modal-header">
                            <h2>${project.name}</h2>
                            <button class="modal-close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="project-info">
                                <p><strong>Location:</strong> ${project.location}</p>
                                <p><strong>Type:</strong> ${project.project_type}</p>
                                <p><strong>Methodology:</strong> ${project.methodology}</p>
                                <p><strong>Price:</strong> $${project.price_per_kg}/kg</p>
                                <p><strong>Available:</strong> ${project.available_quantity} kg</p>
                            </div>
                            <div class="project-description">
                                <h3>Description</h3>
                                <p>${project.description}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Close modal handlers
            $('.modal-close, .project-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    $('.project-modal-overlay').remove();
                }
            });
        },
        
        /**
         * Update URL with search parameters
         */
        updateUrl: function(filters) {
            if (history.pushState) {
                const url = new URL(window.location);
                
                // Clear existing search params
                url.searchParams.delete('keyword');
                url.searchParams.delete('location');
                url.searchParams.delete('project_type');
                url.searchParams.delete('min_price');
                url.searchParams.delete('max_price');
                
                // Add current filters
                Object.keys(filters).forEach(function(key) {
                    if (filters[key]) {
                        url.searchParams.set(key, filters[key]);
                    }
                });
                
                history.pushState(null, '', url.toString());
            }
        },
        
        /**
         * Show loading state
         */
        showLoading: function(form) {
            const container = form.siblings(this.config.resultsContainerSelector);
            const loadingElement = form.siblings(this.config.loadingSelector);
            
            if (loadingElement.length > 0) {
                loadingElement.show();
            } else {
                container.html('<div class="search-loading">Searching...</div>');
            }
        },
        
        /**
         * Hide loading state
         */
        hideLoading: function(form) {
            const loadingElement = form.siblings(this.config.loadingSelector);
            loadingElement.hide();
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        CarbonMarketplaceSearch.init();
    });
    
    // Expose to global scope for external access
    window.CarbonMarketplaceSearch = CarbonMarketplaceSearch;
    
})(jQuery);