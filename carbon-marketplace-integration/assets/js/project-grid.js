/**
 * Project Grid JavaScript
 * Carbon Marketplace Integration
 */

(function($) {
    'use strict';

    class ProjectGrid {
        constructor(container) {
            this.container = $(container);
            this.currentPage = 1;
            this.isLoading = false;
            this.filters = {};
            this.sortBy = 'name';
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.initLazyLoading();
            this.initInfiniteScroll();
            this.initFilters();
        }

        bindEvents() {
            // Filter events
            this.container.on('change', '.filter-select', (e) => {
                this.handleFilterChange(e);
            });

            this.container.on('input', '.price-slider', (e) => {
                this.handlePriceRangeChange(e);
            });

            // Pagination events
            this.container.on('click', '.pagination-btn', (e) => {
                this.handlePaginationClick(e);
            });

            // Card events
            this.container.on('click', '.view-details', (e) => {
                this.handleViewDetails(e);
            });

            this.container.on('click', '.get-quote', (e) => {
                this.handleGetQuote(e);
            });

            this.container.on('click', '.read-more-btn', (e) => {
                this.handleReadMore(e);
            });

            // Clear filters
            this.container.on('click', '.btn-clear-filters', (e) => {
                this.clearAllFilters();
            });
        }

        initLazyLoading() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.add('loaded');
                            observer.unobserve(img);
                        }
                    });
                });

                this.container.find('.lazy-load').each(function() {
                    imageObserver.observe(this);
                });
            }
        }

        initInfiniteScroll() {
            if (this.container.find('.infinite-scroll-trigger').length) {
                if ('IntersectionObserver' in window) {
                    const scrollObserver = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting && !this.isLoading) {
                                this.loadNextPage();
                            }
                        });
                    });

                    scrollObserver.observe(this.container.find('.infinite-scroll-trigger')[0]);
                }
            }
        }

        initFilters() {
            // Initialize price range display
            this.updatePriceDisplay();
        }

        handleFilterChange(e) {
            const $filter = $(e.target);
            const filterName = $filter.data('filter');
            const filterValue = $filter.val();

            if (filterValue) {
                this.filters[filterName] = filterValue;
            } else {
                delete this.filters[filterName];
            }

            this.currentPage = 1;
            this.performSearch();
        }

        handlePriceRangeChange(e) {
            const $slider = $(e.target);
            const filterName = $slider.data('filter');
            const value = parseFloat($slider.val());

            this.filters[filterName] = value;
            this.updatePriceDisplay();

            // Debounce the search
            clearTimeout(this.priceTimeout);
            this.priceTimeout = setTimeout(() => {
                this.currentPage = 1;
                this.performSearch();
            }, 500);
        }

        updatePriceDisplay() {
            const minPrice = this.filters.min_price || 0;
            const maxPrice = this.filters.max_price || 100;

            $('#min-price-display').text(minPrice);
            $('#max-price-display').text(maxPrice);
        }

        handlePaginationClick(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const page = parseInt($btn.data('page'));

            if (page && page !== this.currentPage) {
                this.currentPage = page;
                this.performSearch();
                this.scrollToTop();
            }
        }

        handleViewDetails(e) {
            e.preventDefault();
            const projectId = $(e.target).data('project-id');
            this.showProjectDetails(projectId);
        }

        handleGetQuote(e) {
            e.preventDefault();
            const projectId = $(e.target).data('project-id');
            this.showQuoteModal(projectId);
        }

        handleReadMore(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const $description = $btn.closest('.project-description');
            const $p = $description.find('p');
            const fullText = $btn.data('full-text');

            if ($btn.text() === 'Read More') {
                $p.text(fullText);
                $btn.text('Read Less');
            } else {
                const truncatedText = this.truncateText(fullText, 150);
                $p.text(truncatedText);
                $btn.text('Read More');
            }
        }

        clearAllFilters() {
            this.filters = {};
            this.currentPage = 1;

            // Reset form elements
            this.container.find('.filter-select').val('');
            this.container.find('.price-slider').each(function() {
                const $slider = $(this);
                const defaultValue = $slider.attr('name') === 'min_price' ? 0 : 100;
                $slider.val(defaultValue);
            });

            this.updatePriceDisplay();
            this.performSearch();
        }

        performSearch() {
            if (this.isLoading) return;

            this.isLoading = true;
            this.showLoading();

            const searchData = {
                action: 'carbon_marketplace_search',
                nonce: carbonProjectGrid.nonce,
                ...this.filters,
                sort_by: this.sortBy,
                limit: 12,
                offset: (this.currentPage - 1) * 12
            };

            $.ajax({
                url: carbonProjectGrid.ajaxUrl,
                type: 'POST',
                data: searchData,
                success: (response) => {
                    this.handleSearchSuccess(response);
                },
                error: (xhr, status, error) => {
                    this.handleSearchError(error);
                },
                complete: () => {
                    this.isLoading = false;
                    this.hideLoading();
                }
            });
        }

        loadNextPage() {
            if (this.isLoading) return;

            this.currentPage++;
            this.isLoading = true;

            const searchData = {
                action: 'carbon_marketplace_search',
                nonce: carbonProjectGrid.nonce,
                ...this.filters,
                sort_by: this.sortBy,
                limit: 12,
                offset: (this.currentPage - 1) * 12
            };

            $.ajax({
                url: carbonProjectGrid.ajaxUrl,
                type: 'POST',
                data: searchData,
                success: (response) => {
                    this.handleInfiniteScrollSuccess(response);
                },
                error: (xhr, status, error) => {
                    this.currentPage--; // Revert page increment
                    this.handleSearchError(error);
                },
                complete: () => {
                    this.isLoading = false;
                }
            });
        }

        handleSearchSuccess(response) {
            if (response.success) {
                const $gridContainer = this.container.find('.project-grid-container');
                
                if (response.data.projects && response.data.projects.length > 0) {
                    $gridContainer.html(this.renderProjects(response.data.projects));
                    this.updatePagination(response.data.pagination);
                } else {
                    $gridContainer.html(this.renderEmptyState());
                }

                // Reinitialize lazy loading for new images
                this.initLazyLoading();
            } else {
                this.handleSearchError(response.data || 'Search failed');
            }
        }

        handleInfiniteScrollSuccess(response) {
            if (response.success && response.data.projects && response.data.projects.length > 0) {
                const $gridContainer = this.container.find('.project-grid-container');
                $gridContainer.append(this.renderProjects(response.data.projects));
                
                // Reinitialize lazy loading for new images
                this.initLazyLoading();
            }
        }

        handleSearchError(error) {
            console.error('Search error:', error);
            
            const $gridContainer = this.container.find('.project-grid-container');
            $gridContainer.html(`
                <div class="search-error">
                    <h3>Search Error</h3>
                    <p>We encountered an error while searching for projects. Please try again.</p>
                    <button class="btn btn-primary retry-search">Retry Search</button>
                </div>
            `);

            this.container.on('click', '.retry-search', () => {
                this.performSearch();
            });
        }

        renderProjects(projects) {
            // This would typically be handled server-side, but for completeness:
            return projects.map(project => this.renderProjectCard(project)).join('');
        }

        renderProjectCard(project) {
            // Basic project card template - in real implementation, 
            // this would be handled by the PHP ProjectCard class
            return `
                <div class="carbon-project-card" data-project-id="${project.id}">
                    <div class="card-image">
                        ${project.images && project.images.length > 0 ? 
                            `<img class="project-image lazy-load" data-src="${project.images[0]}" alt="${project.name}">` :
                            '<div class="project-image-placeholder"><span>No Image</span></div>'
                        }
                        ${project.project_type ? `<div class="project-type-badge">${project.project_type}</div>` : ''}
                    </div>
                    <div class="card-content">
                        <h3 class="project-title">${project.name}</h3>
                        <div class="project-meta">
                            ${project.location ? `<span class="project-location">${project.location}</span>` : ''}
                        </div>
                        <div class="card-footer">
                            <div class="price-availability">
                                ${project.price_per_kg ? `<div class="project-price">$${project.price_per_kg}/tCO₂</div>` : ''}
                            </div>
                            <div class="card-actions">
                                <button class="btn btn-primary view-details" data-project-id="${project.id}">View Details</button>
                                <button class="btn btn-secondary get-quote" data-project-id="${project.id}">Get Quote</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        renderEmptyState() {
            return `
                <div class="project-grid-empty">
                    <div class="empty-state-content">
                        <h3>No Projects Found</h3>
                        <p>We couldn't find any carbon offset projects matching your criteria.</p>
                        <div class="empty-state-suggestions">
                            <h4>Try:</h4>
                            <ul>
                                <li>Adjusting your search filters</li>
                                <li>Expanding your location criteria</li>
                                <li>Increasing your price range</li>
                                <li>Browsing all available projects</li>
                            </ul>
                        </div>
                        <button class="btn-clear-filters">Clear All Filters</button>
                    </div>
                </div>
            `;
        }

        updatePagination(pagination) {
            if (!pagination || pagination.total_pages <= 1) {
                this.container.find('.project-grid-pagination').hide();
                return;
            }

            const $pagination = this.container.find('.project-grid-pagination');
            $pagination.show();

            // Update pagination info
            $pagination.find('.pagination-info').text(
                `Showing page ${pagination.current_page} of ${pagination.total_pages} (${pagination.total_count} total projects)`
            );

            // Update pagination controls
            const $controls = $pagination.find('.pagination-controls');
            $controls.html(this.renderPaginationControls(pagination));
        }

        renderPaginationControls(pagination) {
            const { current_page, total_pages } = pagination;
            let html = '';

            // Previous button
            if (current_page > 1) {
                html += `<button class="pagination-btn prev-btn" data-page="${current_page - 1}">Previous</button>`;
            }

            // Page numbers
            const startPage = Math.max(1, current_page - 2);
            const endPage = Math.min(total_pages, current_page + 2);

            if (startPage > 1) {
                html += '<button class="pagination-btn page-btn" data-page="1">1</button>';
                if (startPage > 2) {
                    html += '<span class="pagination-ellipsis">...</span>';
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === current_page ? ' active' : '';
                html += `<button class="pagination-btn page-btn${activeClass}" data-page="${i}">${i}</button>`;
            }

            if (endPage < total_pages) {
                if (endPage < total_pages - 1) {
                    html += '<span class="pagination-ellipsis">...</span>';
                }
                html += `<button class="pagination-btn page-btn" data-page="${total_pages}">${total_pages}</button>`;
            }

            // Next button
            if (current_page < total_pages) {
                html += `<button class="pagination-btn next-btn" data-page="${current_page + 1}">Next</button>`;
            }

            return html;
        }

        showProjectDetails(projectId) {
            // Trigger project details modal or navigation
            $(document).trigger('carbon-marketplace:show-project-details', { projectId });
        }

        showQuoteModal(projectId) {
            // Trigger quote modal
            $(document).trigger('carbon-marketplace:show-quote-modal', { projectId });
        }

        showLoading() {
            const $gridContainer = this.container.find('.project-grid-container');
            $gridContainer.html(`
                <div class="project-grid-loading">
                    <div class="loading-spinner"></div>
                </div>
            `);
        }

        hideLoading() {
            this.container.find('.project-grid-loading').remove();
        }

        scrollToTop() {
            $('html, body').animate({
                scrollTop: this.container.offset().top - 100
            }, 500);
        }

        truncateText(text, length) {
            if (text.length <= length) return text;
            
            const truncated = text.substr(0, length);
            const lastSpace = truncated.lastIndexOf(' ');
            
            return (lastSpace > 0 ? truncated.substr(0, lastSpace) : truncated) + '...';
        }
    }

    // Initialize project grids when document is ready
    $(document).ready(function() {
        $('.carbon-project-grid').each(function() {
            new ProjectGrid(this);
        });
    });

    // Export for external use
    window.CarbonMarketplace = window.CarbonMarketplace || {};
    window.CarbonMarketplace.ProjectGrid = ProjectGrid;

})(jQuery);