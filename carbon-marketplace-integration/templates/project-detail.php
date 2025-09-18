<?php
/**
 * Template for project detail shortcode
 *
 * @package CarbonMarketplace
 * @var array $atts Shortcode attributes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="carbon-marketplace-project-detail">
    <div id="carbon-project-detail-container">
        <!-- Project detail will be loaded here -->
        <p><?php _e('Loading project details...', 'carbon-marketplace'); ?></p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load project details on page load
    loadProjectDetail();
    
    function loadProjectDetail() {
        var data = {
            action: 'carbon_marketplace_get_project_detail',
            nonce: carbonMarketplace.nonce,
            project_id: '<?php echo esc_js($atts['project_id']); ?>',
            vendor: '<?php echo esc_js($atts['vendor']); ?>'
        };
        
        $.ajax({
            url: carbonMarketplace.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success && response.data) {
                    $('#carbon-project-detail-container').html(response.data.html);
                } else {
                    $('#carbon-project-detail-container').html('<div class="no-project"><?php _e("Project not found.", "carbon-marketplace"); ?></div>');
                }
            },
            error: function() {
                $('#carbon-project-detail-container').html('<div class="error"><?php _e("Error loading project details.", "carbon-marketplace"); ?></div>');
            }
        });
    }
});
</script>

<style>
.carbon-marketplace-project-detail {
    margin: 20px 0;
}

.no-project, .error {
    text-align: center;
    padding: 40px;
    background: #f0f0f0;
    border-radius: 5px;
    color: #666;
}

.error {
    background: #ffeaea;
    color: #d63638;
}
</style>