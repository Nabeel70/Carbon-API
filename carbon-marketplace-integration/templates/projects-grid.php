<?php
/**
 * Template for projects grid shortcode
 *
 * @package CarbonMarketplace
 * @var array $atts Shortcode attributes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="carbon-marketplace-projects-grid">
    <div id="carbon-projects-container" class="projects-grid">
        <!-- Projects will be loaded here -->
        <p><?php _e('Loading carbon projects...', 'carbon-marketplace'); ?></p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load projects on page load
    loadProjects();
    
    function loadProjects() {
        var data = {
            action: 'carbon_marketplace_get_projects',
            nonce: carbonMarketplace.nonce,
            limit: <?php echo (int)$atts['limit']; ?>,
            vendor: '<?php echo esc_js($atts['vendor']); ?>',
            project_type: '<?php echo esc_js($atts['project_type']); ?>',
            location: '<?php echo esc_js($atts['location']); ?>'
        };
        
        $.ajax({
            url: carbonMarketplace.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success && response.data) {
                    $('#carbon-projects-container').html(response.data.html);
                } else {
                    $('#carbon-projects-container').html('<div class="no-projects"><?php _e("No projects found.", "carbon-marketplace"); ?></div>');
                }
            },
            error: function() {
                $('#carbon-projects-container').html('<div class="error"><?php _e("Error loading projects.", "carbon-marketplace"); ?></div>');
            }
        });
    }
});
</script>

<style>
.carbon-marketplace-projects-grid {
    margin: 20px 0;
}

.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.no-projects, .error {
    text-align: center;
    padding: 40px;
    background: #f0f0f0;
    border-radius: 5px;
    color: #666;
    grid-column: 1 / -1;
}

.error {
    background: #ffeaea;
    color: #d63638;
}
</style>