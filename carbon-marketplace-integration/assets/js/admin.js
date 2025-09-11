/**
 * Carbon Marketplace Admin JavaScript
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Admin functionality object
     */
    const CarbonMarketplaceAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.initCredentialTesting();
            this.initDataSync();
            this.initCacheClear();
            this.initFormValidation();
            this.initDashboardWidgets();
        },
        
        /**
         * Initialize credential testing
         */
        initCredentialTesting: function() {
            $('.test-credentials-btn').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const vendor = $button.data('vendor');
                const $status = $button.siblings('.api-status');
                
                // Disable button and show testing state
                $button.prop('disabled', true).text('Testing...');
                $status.removeClass('connected disconnected').addClass('testing').text('Testing');
                
                // Get credentials from form
                const credentials = CarbonMarketplaceAdmin.getCredentials(vendor);
                
                // Test credentials
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'carbon_marketplace_test_credentials',
                        nonce: carbonMarketplaceAdmin.nonce,
                        vendor: vendor,
                        credentials: credentials
                    },
                    success: function(response) {
                        $button.prop('disabled', false).text('Test Credentials');
                        
                        if (response.success) {
                            $status.removeClass('testing disconnected').addClass('connected').text('Connected');
                            CarbonMarketplaceAdmin.showNotice('Credentials verified successfully!', 'success');
                        } else {
                            $status.removeClass('testing connected').addClass('disconnected').text('Failed');
                            CarbonMarketplaceAdmin.showNotice('Credential test failed: ' + (response.data || 'Unknown error'), 'error');
                        }
                    },
                    error: function() {
                        $button.prop('disabled', false).text('Test Credentials');
                        $status.removeClass('testing connected').addClass('disconnected').text('Error');
                        CarbonMarketplaceAdmin.showNotice('Failed to test credentials. Please try again.', 'error');
                    }
                });
            });
        },
        
        /**
         * Get credentials from form
         */
        getCredentials: function(vendor) {
            const credentials = {};
            
            $(`[data-vendor="${vendor}"]`).each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                if (name) {
                    credentials[name] = $input.val();
                }
            });
            
            return credentials;
        },
        
        /**
         * Initialize data synchronization
         */
        initDataSync: function() {
            $('.sync-data-btn').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const vendor = $button.data('vendor') || 'all';
                
                $button.prop('disabled', true).html('<span class="spinner is-active"></span> Syncing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'carbon_marketplace_sync_data',
                        nonce: carbonMarketplaceAdmin.nonce,
                        vendor: vendor
                    },
                    success: function(response) {
                        $button.prop('disabled', false).text('Sync Data');
                        
                        if (response.success) {
                            CarbonMarketplaceAdmin.showNotice('Data synchronized successfully!', 'success');
                            // Refresh any data displays
                            CarbonMarketplaceAdmin.refreshDashboardWidgets();
                        } else {
                            CarbonMarketplaceAdmin.showNotice('Data sync failed: ' + (response.data || 'Unknown error'), 'error');
                        }
                    },
                    error: function() {
                        $button.prop('disabled', false).text('Sync Data');
                        CarbonMarketplaceAdmin.showNotice('Failed to sync data. Please try again.', 'error');
                    }
                });
            });
        },
        
        /**
         * Initialize cache clearing
         */
        initCacheClear: function() {
            $('.clear-cache-btn').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                
                $button.prop('disabled', true).html('<span class="spinner is-active"></span> Clearing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'carbon_marketplace_clear_cache',
                        nonce: carbonMarketplaceAdmin.nonce
                    },
                    success: function(response) {
                        $button.prop('disabled', false).text('Clear Cache');
                        
                        if (response.success) {
                            CarbonMarketplaceAdmin.showNotice('Cache cleared successfully!', 'success');
                        } else {
                            CarbonMarketplaceAdmin.showNotice('Failed to clear cache: ' + (response.data || 'Unknown error'), 'error');
                        }
                    },
                    error: function() {
                        $button.prop('disabled', false).text('Clear Cache');
                        CarbonMarketplaceAdmin.showNotice('Failed to clear cache. Please try again.', 'error');
                    }
                });
            });
        },
        
        /**
         * Initialize form validation
         */
        initFormValidation: function() {
            // API key validation
            $('input[type="password"][name*="api_key"]').on('blur', function() {
                const $input = $(this);
                const value = $input.val();
                
                if (value && value.length < 10) {
                    CarbonMarketplaceAdmin.showFieldError($input, 'API key appears to be too short');
                } else {
                    CarbonMarketplaceAdmin.clearFieldError($input);
                }
            });
            
            // URL validation
            $('input[type="url"]').on('blur', function() {
                const $input = $(this);
                const value = $input.val();
                
                if (value && !CarbonMarketplaceAdmin.isValidUrl(value)) {
                    CarbonMarketplaceAdmin.showFieldError($input, 'Please enter a valid URL');
                } else {
                    CarbonMarketplaceAdmin.clearFieldError($input);
                }
            });
            
            // Number validation
            $('input[type="number"]').on('blur', function() {
                const $input = $(this);
                const value = parseFloat($input.val());
                const min = parseFloat($input.attr('min'));
                const max = parseFloat($input.attr('max'));
                
                if (!isNaN(min) && value < min) {
                    CarbonMarketplaceAdmin.showFieldError($input, `Value must be at least ${min}`);
                } else if (!isNaN(max) && value > max) {
                    CarbonMarketplaceAdmin.showFieldError($input, `Value must be at most ${max}`);
                } else {
                    CarbonMarketplaceAdmin.clearFieldError($input);
                }
            });
        },
        
        /**
         * Initialize dashboard widgets
         */
        initDashboardWidgets: function() {
            // Auto-refresh widgets every 5 minutes
            setInterval(function() {
                CarbonMarketplaceAdmin.refreshDashboardWidgets();
            }, 300000);
            
            // Manual refresh buttons
            $('.widget-refresh').on('click', function(e) {
                e.preventDefault();
                CarbonMarketplaceAdmin.refreshDashboardWidgets();
            });
        },
        
        /**
         * Refresh dashboard widgets
         */
        refreshDashboardWidgets: function() {
            $('.carbon-dashboard-widget[data-refresh="true"]').each(function() {
                const $widget = $(this);
                const widgetType = $widget.data('widget');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'carbon_marketplace_refresh_widget',
                        nonce: carbonMarketplaceAdmin.nonce,
                        widget: widgetType
                    },
                    success: function(response) {
                        if (response.success) {
                            $widget.find('.widget-content').html(response.data.html);
                        }
                    }
                });
            });
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type = 'info') {
            const noticeClass = `notice notice-${type} is-dismissible`;
            const notice = `<div class="${noticeClass}"><p>${message}</p></div>`;
            
            // Remove existing notices
            $('.notice').remove();
            
            // Add new notice
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.notice').fadeOut();
            }, 5000);
        },
        
        /**
         * Show field error
         */
        showFieldError: function($input, message) {
            $input.addClass('error');
            
            // Remove existing error message
            $input.siblings('.field-error').remove();
            
            // Add error message
            $input.after(`<span class="field-error" style="color: #d63384; font-size: 12px; display: block; margin-top: 5px;">${message}</span>`);
        },
        
        /**
         * Clear field error
         */
        clearFieldError: function($input) {
            $input.removeClass('error');
            $input.siblings('.field-error').remove();
        },
        
        /**
         * Validate URL
         */
        isValidUrl: function(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        CarbonMarketplaceAdmin.init();
    });
    
})(jQuery);