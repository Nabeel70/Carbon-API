/**
 * Vendor Configuration JavaScript
 */

(function($) {
    'use strict';
    
    var VendorConfig = {
        
        /**
         * Initialize vendor configuration
         */
        init: function() {
            this.bindEvents();
            this.initToggles();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Vendor enable/disable toggle
            $(document).on('change', '.vendor-enabled-toggle', this.handleVendorToggle);
            
            // Test connection button
            $(document).on('click', '.test-connection-btn', this.handleTestConnection);
            
            // Save configuration form
            $(document).on('submit', '.vendor-form', this.handleSaveConfig);
            
            // Reset configuration button
            $(document).on('click', '.reset-config-btn', this.handleResetConfig);
            
            // Form field changes
            $(document).on('input change', '.vendor-form input, .vendor-form select, .vendor-form textarea', this.handleFieldChange);
        },
        
        /**
         * Initialize toggle switches
         */
        initToggles: function() {
            $('.vendor-enabled-toggle').each(function() {
                var $toggle = $(this);
                var $card = $toggle.closest('.vendor-config-card');
                var $form = $card.find('.vendor-config-form');
                
                if ($toggle.is(':checked')) {
                    $form.show();
                } else {
                    $form.hide();
                }
            });
        },
        
        /**
         * Handle vendor enable/disable toggle
         */
        handleVendorToggle: function(e) {
            var $toggle = $(this);
            var $card = $toggle.closest('.vendor-config-card');
            var $form = $card.find('.vendor-config-form');
            var vendor = $toggle.data('vendor');
            var enabled = $toggle.is(':checked');
            
            // Show/hide form
            if (enabled) {
                $form.slideDown();
            } else {
                $form.slideUp();
            }
            
            // Update vendor status via AJAX
            VendorConfig.toggleVendor(vendor, enabled, function(success, message) {
                if (success) {
                    VendorConfig.updateVendorStatus($card, enabled ? 'enabled' : 'disabled', message);
                } else {
                    // Revert toggle on error
                    $toggle.prop('checked', !enabled);
                    if (!enabled) {
                        $form.slideDown();
                    } else {
                        $form.slideUp();
                    }
                    VendorConfig.showError($card, message);
                }
            });
        },
        
        /**
         * Handle test connection button click
         */
        handleTestConnection: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $form = $btn.closest('.vendor-form');
            var $card = $btn.closest('.vendor-config-card');
            var vendor = $form.data('vendor');
            var formData = VendorConfig.getFormData($form);
            
            $btn.prop('disabled', true).text(carbonMarketplaceVendor.strings.testing);
            VendorConfig.hideMessages($card);
            
            VendorConfig.testConnection(vendor, formData, function(success, message) {
                $btn.prop('disabled', false).text(carbonMarketplaceVendor.strings.testConnection || 'Test Connection');
                
                if (success) {
                    VendorConfig.showSuccess($card, carbonMarketplaceVendor.strings.connectionSuccess);
                } else {
                    VendorConfig.showError($card, message);
                }
            });
        },
        
        /**
         * Handle save configuration form submission
         */
        handleSaveConfig: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $card = $form.closest('.vendor-config-card');
            var $btn = $form.find('.save-config-btn');
            var vendor = $form.data('vendor');
            var formData = VendorConfig.getFormData($form);
            
            $btn.prop('disabled', true).text(carbonMarketplaceVendor.strings.saving);
            VendorConfig.hideMessages($card);
            
            VendorConfig.saveConfig(vendor, formData, function(success, message) {
                $btn.prop('disabled', false).text('Save Configuration');
                
                if (success) {
                    VendorConfig.showSuccess($card, message);
                    VendorConfig.updateVendorStatus($card, 'enabled', 'Enabled and configured');
                    
                    // Mark form as saved
                    $form.data('saved', true);
                } else {
                    VendorConfig.showError($card, message);
                }
            });
        },
        
        /**
         * Handle reset configuration button click
         */
        handleResetConfig: function(e) {
            e.preventDefault();
            
            if (!confirm(carbonMarketplaceVendor.strings.confirmReset)) {
                return;
            }
            
            var $btn = $(this);
            var $form = $btn.closest('.vendor-form');
            var $card = $btn.closest('.vendor-config-card');
            var vendor = $form.data('vendor');
            
            $btn.prop('disabled', true);
            VendorConfig.hideMessages($card);
            
            VendorConfig.resetConfig(vendor, function(success, message) {
                $btn.prop('disabled', false);
                
                if (success) {
                    // Reset form fields
                    $form[0].reset();
                    
                    // Disable vendor toggle
                    $card.find('.vendor-enabled-toggle').prop('checked', false);
                    $card.find('.vendor-config-form').slideUp();
                    
                    VendorConfig.updateVendorStatus($card, 'disabled', 'Disabled');
                    VendorConfig.showSuccess($card, message);
                } else {
                    VendorConfig.showError($card, message);
                }
            });
        },
        
        /**
         * Handle form field changes
         */
        handleFieldChange: function() {
            var $form = $(this).closest('.vendor-form');
            $form.data('saved', false);
        },
        
        /**
         * Get form data as object
         */
        getFormData: function($form) {
            var data = {};
            
            $form.find('input, select, textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                
                if ($field.attr('type') === 'checkbox') {
                    value = $field.is(':checked') ? '1' : '0';
                }
                
                if (name) {
                    data[name] = value;
                }
            });
            
            return data;
        },
        
        /**
         * Toggle vendor enabled status
         */
        toggleVendor: function(vendor, enabled, callback) {
            $.ajax({
                url: carbonMarketplaceVendor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'carbon_marketplace_toggle_vendor',
                    vendor: vendor,
                    enabled: enabled ? '1' : '0',
                    nonce: carbonMarketplaceVendor.nonce
                },
                success: function(response) {
                    if (response.success) {
                        callback(true, response.data);
                    } else {
                        callback(false, response.data || 'Unknown error');
                    }
                },
                error: function() {
                    callback(false, 'Network error');
                }
            });
        },
        
        /**
         * Test vendor connection
         */
        testConnection: function(vendor, config, callback) {
            $.ajax({
                url: carbonMarketplaceVendor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'carbon_marketplace_test_vendor_connection',
                    vendor: vendor,
                    config: config,
                    nonce: carbonMarketplaceVendor.nonce
                },
                success: function(response) {
                    if (response.success) {
                        callback(true, response.data);
                    } else {
                        callback(false, response.data || 'Connection test failed');
                    }
                },
                error: function() {
                    callback(false, 'Network error');
                }
            });
        },
        
        /**
         * Save vendor configuration
         */
        saveConfig: function(vendor, config, callback) {
            $.ajax({
                url: carbonMarketplaceVendor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'carbon_marketplace_save_vendor_config',
                    vendor: vendor,
                    config: config,
                    nonce: carbonMarketplaceVendor.nonce
                },
                success: function(response) {
                    if (response.success) {
                        callback(true, response.data);
                    } else {
                        callback(false, response.data || 'Save failed');
                    }
                },
                error: function() {
                    callback(false, 'Network error');
                }
            });
        },
        
        /**
         * Reset vendor configuration
         */
        resetConfig: function(vendor, callback) {
            $.ajax({
                url: carbonMarketplaceVendor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'carbon_marketplace_reset_vendor',
                    vendor: vendor,
                    nonce: carbonMarketplaceVendor.nonce
                },
                success: function(response) {
                    if (response.success) {
                        callback(true, response.data);
                    } else {
                        callback(false, response.data || 'Reset failed');
                    }
                },
                error: function() {
                    callback(false, 'Network error');
                }
            });
        },
        
        /**
         * Update vendor status display
         */
        updateVendorStatus: function($card, status, message) {
            var $indicator = $card.find('.status-indicator');
            var $text = $card.find('.status-text');
            
            // Remove existing status classes
            $indicator.removeClass('enabled disabled error warning');
            
            // Add new status class
            $indicator.addClass(status);
            
            // Update status text
            $text.text(message);
        },
        
        /**
         * Show success message
         */
        showSuccess: function($card, message) {
            var $result = $card.find('.connection-result');
            $result.removeClass('error').addClass('success')
                   .html('<p>' + message + '</p>')
                   .slideDown();
            
            // Auto-hide after 3 seconds
            setTimeout(function() {
                $result.slideUp();
            }, 3000);
        },
        
        /**
         * Show error message
         */
        showError: function($card, message) {
            var $result = $card.find('.connection-result');
            $result.removeClass('success').addClass('error')
                   .html('<p>' + message + '</p>')
                   .slideDown();
        },
        
        /**
         * Hide messages
         */
        hideMessages: function($card) {
            $card.find('.connection-result').slideUp();
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        VendorConfig.init();
    });
    
    // Warn about unsaved changes
    $(window).on('beforeunload', function() {
        var hasUnsavedChanges = false;
        
        $('.vendor-form').each(function() {
            if ($(this).data('saved') === false) {
                hasUnsavedChanges = true;
                return false;
            }
        });
        
        if (hasUnsavedChanges) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
})(jQuery);