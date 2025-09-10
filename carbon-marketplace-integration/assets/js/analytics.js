/**
 * Analytics tracking for Carbon Marketplace Integration
 */

(function($) {
    'use strict';
    
    class CarbonAnalytics {
        constructor() {
            this.config = window.carbonAnalytics || {};
            this.sessionId = this.config.sessionId;
            this.userId = this.config.userId;
            this.init();
        }
        
        init() {
            this.trackPageView();
            this.bindEvents();
        }
        
        bindEvents() {
            // Track search events
            $(document).on('submit', '.carbon-search-form', (e) => {
                const form = $(e.target);
                const keyword = form.find('input[name="keyword"]').val();
                const filters = this.getFormFilters(form);
                
                this.trackEvent('search', {
                    keyword: keyword,
                    filters: filters
                });
            });
            
            // Track project view events
            $(document).on('click', '.project-card, .view-project-details', (e) => {
                const projectId = $(e.target).closest('[data-project-id]').data('project-id');
                const projectName = $(e.target).closest('[data-project-name]').data('project-name');
                
                if (projectId) {
                    this.trackEvent('project_view', {
                        project_id: projectId,
                        project_name: projectName
                    });
                }
            });
            
            // Track quote request events
            $(document).on('click', '.get-quote-btn, .request-quote', (e) => {
                const projectId = $(e.target).closest('[data-project-id]').data('project-id');
                const amount = $(e.target).closest('form').find('input[name="amount"]').val();
                
                this.trackEvent('quote_request', {
                    project_id: projectId,
                    amount_kg: amount
                });
            });
            
            // Track checkout start events
            $(document).on('click', '.checkout-btn, .purchase-btn', (e) => {
                const projectId = $(e.target).data('project-id');
                const amount = $(e.target).data('amount');
                const price = $(e.target).data('price');
                
                this.trackEvent('checkout_start', {
                    project_id: projectId,
                    amount_kg: amount,
                    total_price: price
                });
            });
            
            // Track filter usage
            $(document).on('change', '.search-filters input, .search-filters select', (e) => {
                const filterType = $(e.target).attr('name');
                const filterValue = $(e.target).val();
                
                this.trackEvent('filter_used', {
                    filter_type: filterType,
                    filter_value: filterValue
                });
            });
            
            // Track pagination
            $(document).on('click', '.pagination a', (e) => {
                const page = $(e.target).data('page') || this.getPageFromUrl($(e.target).attr('href'));
                
                this.trackEvent('pagination', {
                    page: page
                });
            });
            
            // Track certificate views
            $(document).on('click', '.view-certificate, .certificate-link', (e) => {
                const orderId = $(e.target).data('order-id');
                
                this.trackEvent('certificate_view', {
                    order_id: orderId
                });
            });
            
            // Track social sharing
            $(document).on('click', '.share-btn', (e) => {
                const platform = $(e.target).data('platform');
                const projectId = $(e.target).data('project-id');
                
                this.trackEvent('social_share', {
                    platform: platform,
                    project_id: projectId
                });
            });
            
            // Track time on page
            this.startTimeTracking();
        }
        
        trackPageView() {
            this.trackEvent('page_view', {
                page_url: window.location.pathname,
                page_title: document.title,
                referrer: document.referrer
            });
        }
        
        trackEvent(eventType, eventData = {}) {
            // Add common data to all events
            const enrichedData = {
                ...eventData,
                timestamp: new Date().toISOString(),
                page_url: window.location.pathname,
                user_agent: navigator.userAgent,
                screen_resolution: `${screen.width}x${screen.height}`,
                viewport_size: `${window.innerWidth}x${window.innerHeight}`
            };
            
            // Send to server
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'carbon_marketplace_track_event',
                    nonce: this.config.nonce,
                    event_type: eventType,
                    event_data: enrichedData
                },
                success: (response) => {
                    if (window.console && window.console.log) {
                        console.log('Analytics event tracked:', eventType, enrichedData);
                    }
                },
                error: (xhr, status, error) => {
                    if (window.console && window.console.error) {
                        console.error('Failed to track analytics event:', error);
                    }
                }
            });
            
            // Also send to Google Analytics if available
            this.sendToGoogleAnalytics(eventType, enrichedData);
            
            // Send to Facebook Pixel if available
            this.sendToFacebookPixel(eventType, enrichedData);
        }
        
        sendToGoogleAnalytics(eventType, eventData) {
            if (typeof gtag !== 'undefined') {
                gtag('event', eventType, {
                    event_category: 'carbon_marketplace',
                    event_label: eventData.project_id || eventData.keyword || '',
                    value: eventData.total_price || eventData.amount_kg || 1,
                    custom_map: {
                        session_id: this.sessionId,
                        user_id: this.userId
                    }
                });
            } else if (typeof ga !== 'undefined') {
                ga('send', 'event', 'carbon_marketplace', eventType, eventData.project_id || eventData.keyword || '', eventData.total_price || 1);
            }
        }
        
        sendToFacebookPixel(eventType, eventData) {
            if (typeof fbq !== 'undefined') {
                const pixelEvents = {
                    'search': 'Search',
                    'project_view': 'ViewContent',
                    'quote_request': 'InitiateCheckout',
                    'checkout_start': 'InitiateCheckout',
                    'conversion': 'Purchase'
                };
                
                const pixelEvent = pixelEvents[eventType];
                if (pixelEvent) {
                    const pixelData = {
                        content_ids: [eventData.project_id],
                        content_type: 'carbon_project',
                        value: eventData.total_price || 0,
                        currency: 'USD'
                    };
                    
                    fbq('track', pixelEvent, pixelData);
                }
            }
        }
        
        getFormFilters(form) {
            const filters = {};
            
            form.find('input, select').each(function() {
                const name = $(this).attr('name');
                const value = $(this).val();
                
                if (name && value && name !== 'keyword') {
                    filters[name] = value;
                }
            });
            
            return filters;
        }
        
        getPageFromUrl(url) {
            if (!url) return 1;
            
            const match = url.match(/[?&]page=(\d+)/);
            return match ? parseInt(match[1]) : 1;
        }
        
        startTimeTracking() {
            this.pageStartTime = Date.now();
            
            // Track time on page when user leaves
            $(window).on('beforeunload', () => {
                const timeOnPage = Math.round((Date.now() - this.pageStartTime) / 1000);
                
                if (timeOnPage > 5) { // Only track if user spent more than 5 seconds
                    this.trackEvent('time_on_page', {
                        duration_seconds: timeOnPage
                    });
                }
            });
            
            // Track scroll depth
            this.trackScrollDepth();
        }
        
        trackScrollDepth() {
            let maxScrollDepth = 0;
            const trackingPoints = [25, 50, 75, 90, 100];
            const trackedPoints = new Set();
            
            $(window).on('scroll', () => {
                const scrollTop = $(window).scrollTop();
                const docHeight = $(document).height() - $(window).height();
                const scrollPercent = Math.round((scrollTop / docHeight) * 100);
                
                if (scrollPercent > maxScrollDepth) {
                    maxScrollDepth = scrollPercent;
                }
                
                // Track milestone scroll depths
                trackingPoints.forEach(point => {
                    if (scrollPercent >= point && !trackedPoints.has(point)) {
                        trackedPoints.add(point);
                        this.trackEvent('scroll_depth', {
                            depth_percent: point
                        });
                    }
                });
            });
        }
        
        // Public methods for manual tracking
        trackConversion(orderData) {
            this.trackEvent('conversion', {
                order_id: orderData.order_id,
                vendor_order_id: orderData.vendor_order_id,
                vendor: orderData.vendor,
                amount_kg: orderData.amount_kg,
                total_price: orderData.total_price,
                currency: orderData.currency
            });
        }
        
        trackCustomEvent(eventType, eventData) {
            this.trackEvent(eventType, eventData);
        }
        
        setUserId(userId) {
            this.userId = userId;
        }
        
        setCustomDimension(key, value) {
            if (!this.customDimensions) {
                this.customDimensions = {};
            }
            this.customDimensions[key] = value;
        }
    }
    
    // Initialize analytics when DOM is ready
    $(document).ready(function() {
        window.carbonMarketplaceAnalytics = new CarbonAnalytics();
    });
    
    // Expose analytics object globally
    window.CarbonAnalytics = CarbonAnalytics;
    
})(jQuery);