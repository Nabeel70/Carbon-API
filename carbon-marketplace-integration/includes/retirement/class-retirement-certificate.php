<?php
/**
 * Retirement Certificate for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace\Retirement;

use CarbonMarketplace\Models\Order;
use CarbonMarketplace\Orders\OrderManager;

/**
 * Handles retirement certificate generation and display
 */
class RetirementCertificate {
    
    private OrderManager $order_manager;
    
    public function __construct(OrderManager $order_manager) {
        $this->order_manager = $order_manager;
    }
    
    /**
     * Initialize certificate functionality
     */
    public function init(): void {
        add_action('init', [$this, 'handle_certificate_requests']);
        add_shortcode('carbon_retirement_certificate', [$this, 'certificate_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_certificate_styles']);
    }
    
    /**
     * Handle certificate page requests
     */
    public function handle_certificate_requests(): void {
        if (!isset($_GET['action']) || $_GET['action'] !== 'view_certificate') {
            return;
        }
        
        $order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        $token = $_GET['token'] ?? '';
        
        if (!$order_id || !wp_verify_nonce($token, 'certificate_' . $order_id)) {
            wp_die('Invalid certificate request', 'Certificate Error', ['response' => 403]);
        }
        
        $this->display_certificate_page($order_id);
        exit;
    }
    
    /**
     * Display certificate page
     */
    private function display_certificate_page(int $order_id): void {
        $certificate_data = $this->order_manager->get_retirement_certificate($order_id);
        
        if (!$certificate_data) {
            wp_die('Certificate not found or not available', 'Certificate Error', ['response' => 404]);
        }
        
        // Load certificate template
        $this->render_certificate_template($certificate_data);
    }
    
    /**
     * Render certificate template
     */
    private function render_certificate_template(array $certificate_data): void {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Carbon Retirement Certificate - <?php echo esc_html($certificate_data['vendor_order_id']); ?></title>
            <?php wp_head(); ?>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .certificate-container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .certificate-header { text-align: center; margin-bottom: 40px; border-bottom: 3px solid #2c5530; padding-bottom: 20px; }
                .certificate-title { font-size: 32px; font-weight: bold; color: #2c5530; margin-bottom: 10px; }
                .certificate-subtitle { font-size: 18px; color: #666; }
                .certificate-body { margin-bottom: 40px; }
                .certificate-section { margin-bottom: 30px; }
                .section-title { font-size: 20px; font-weight: bold; color: #2c5530; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
                .info-item { padding: 15px; background: #f9f9f9; border-radius: 4px; }
                .info-label { font-weight: bold; color: #333; margin-bottom: 5px; }
                .info-value { color: #666; }
                .project-allocation { background: #e8f5e8; padding: 15px; border-radius: 4px; margin-bottom: 10px; }
                .retirement-data { background: #f0f8ff; padding: 15px; border-radius: 4px; }
                .certificate-footer { text-align: center; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px; }
                .print-button { background: #2c5530; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 20px; }
                @media print { .print-button { display: none; } }
            </style>
        </head>
        <body>
            <div class="certificate-container">
                <button class="print-button" onclick="window.print()">Print Certificate</button>
                
                <div class="certificate-header">
                    <div class="certificate-title">Carbon Retirement Certificate</div>
                    <div class="certificate-subtitle">Verified Carbon Offset Retirement</div>
                </div>
                
                <div class="certificate-body">
                    <div class="certificate-section">
                        <div class="section-title">Certificate Details</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Certificate ID</div>
                                <div class="info-value"><?php echo esc_html($certificate_data['vendor_order_id']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Retirement Date</div>
                                <div class="info-value"><?php echo esc_html($certificate_data['completed_at']->format('F j, Y')); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Carbon Offset Amount</div>
                                <div class="info-value"><?php echo esc_html(number_format($certificate_data['amount_kg'], 2)); ?> kg CO₂e</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Vendor</div>
                                <div class="info-value"><?php echo esc_html(ucfirst($certificate_data['vendor'])); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($certificate_data['project_allocations'])): ?>
                    <div class="certificate-section">
                        <div class="section-title">Project Allocations</div>
                        <?php foreach ($certificate_data['project_allocations'] as $allocation): ?>
                        <div class="project-allocation">
                            <div class="info-grid">
                                <div>
                                    <div class="info-label">Project Name</div>
                                    <div class="info-value"><?php echo esc_html($allocation['project_name'] ?? 'N/A'); ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Amount Allocated</div>
                                    <div class="info-value"><?php echo esc_html(number_format($allocation['amount_kg'] ?? 0, 2)); ?> kg CO₂e</div>
                                </div>
                                <div>
                                    <div class="info-label">Project ID</div>
                                    <div class="info-value"><?php echo esc_html($allocation['project_id'] ?? 'N/A'); ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Registry</div>
                                    <div class="info-value"><?php echo esc_html($allocation['registry'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                            <?php if (!empty($allocation['serial_numbers'])): ?>
                            <div style="margin-top: 10px;">
                                <div class="info-label">Serial Numbers</div>
                                <div class="info-value"><?php echo esc_html(implode(', ', $allocation['serial_numbers'])); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($certificate_data['retirement_data'])): ?>
                    <div class="certificate-section">
                        <div class="section-title">Retirement Information</div>
                        <div class="retirement-data">
                            <?php foreach ($certificate_data['retirement_data'] as $key => $value): ?>
                                <?php if (is_string($value) || is_numeric($value)): ?>
                                <div style="margin-bottom: 10px;">
                                    <div class="info-label"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></div>
                                    <div class="info-value"><?php echo esc_html($value); ?></div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="certificate-section">
                        <div class="section-title">Verification</div>
                        <p>This certificate confirms that the above-mentioned carbon credits have been permanently retired and removed from circulation. The retirement has been verified and recorded in the appropriate registry.</p>
                        
                        <?php if (!empty($certificate_data['retirement_data']['registry_url'])): ?>
                        <p><strong>Registry Verification:</strong> <a href="<?php echo esc_url($certificate_data['retirement_data']['registry_url']); ?>" target="_blank">View on Registry</a></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="certificate-footer">
                    <p>This certificate was generated on <?php echo date('F j, Y \a\t g:i A T'); ?></p>
                    <p>Certificate ID: <?php echo esc_html($certificate_data['vendor_order_id']); ?></p>
                </div>
            </div>
            
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Certificate shortcode
     */
    public function certificate_shortcode($atts): string {
        $atts = shortcode_atts([
            'order_id' => 0,
            'user_id' => get_current_user_id()
        ], $atts);
        
        if (!$atts['order_id']) {
            return '<p>Invalid order ID</p>';
        }
        
        $order = $this->order_manager->get_order($atts['order_id']);
        
        if (!$order) {
            return '<p>Order not found</p>';
        }
        
        // Check if user owns this order
        if ($order->get_user_id() !== $atts['user_id'] && !current_user_can('manage_options')) {
            return '<p>Access denied</p>';
        }
        
        $certificate_data = $this->order_manager->get_retirement_certificate($atts['order_id']);
        
        if (!$certificate_data) {
            return '<p>Certificate not available. Order may not be completed yet.</p>';
        }
        
        return $this->render_certificate_widget($certificate_data);
    }
    
    /**
     * Render certificate widget
     */
    private function render_certificate_widget(array $certificate_data): string {
        ob_start();
        ?>
        <div class="carbon-certificate-widget">
            <div class="certificate-summary">
                <h3>Carbon Retirement Certificate</h3>
                <div class="certificate-info">
                    <div class="info-row">
                        <span class="label">Certificate ID:</span>
                        <span class="value"><?php echo esc_html($certificate_data['vendor_order_id']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Amount Retired:</span>
                        <span class="value"><?php echo esc_html(number_format($certificate_data['amount_kg'], 2)); ?> kg CO₂e</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Retirement Date:</span>
                        <span class="value"><?php echo esc_html($certificate_data['completed_at']->format('F j, Y')); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Vendor:</span>
                        <span class="value"><?php echo esc_html(ucfirst($certificate_data['vendor'])); ?></span>
                    </div>
                </div>
                
                <div class="certificate-actions">
                    <a href="<?php echo esc_url($certificate_data['certificate_url']); ?>" 
                       class="btn btn-primary" target="_blank">
                        View Full Certificate
                    </a>
                    
                    <?php if (!empty($certificate_data['retirement_data']['registry_url'])): ?>
                    <a href="<?php echo esc_url($certificate_data['retirement_data']['registry_url']); ?>" 
                       class="btn btn-secondary" target="_blank">
                        Verify on Registry
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($certificate_data['project_allocations'])): ?>
            <div class="project-allocations">
                <h4>Project Allocations</h4>
                <?php foreach ($certificate_data['project_allocations'] as $allocation): ?>
                <div class="allocation-item">
                    <div class="allocation-name"><?php echo esc_html($allocation['project_name'] ?? 'Unknown Project'); ?></div>
                    <div class="allocation-amount"><?php echo esc_html(number_format($allocation['amount_kg'] ?? 0, 2)); ?> kg CO₂e</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Enqueue certificate styles
     */
    public function enqueue_certificate_styles(): void {
        wp_enqueue_style(
            'carbon-marketplace-certificate',
            CARBON_MARKETPLACE_URL . 'assets/css/certificate.css',
            [],
            CARBON_MARKETPLACE_VERSION
        );
    }
    
    /**
     * Generate certificate PDF
     */
    public function generate_certificate_pdf(int $order_id): string|false {
        $certificate_data = $this->order_manager->get_retirement_certificate($order_id);
        
        if (!$certificate_data) {
            return false;
        }
        
        // This would require a PDF library like TCPDF or DOMPDF
        // For now, return the certificate URL
        return $certificate_data['certificate_url'];
    }
    
    /**
     * Send certificate email
     */
    public function send_certificate_email(int $order_id, string $email): bool {
        $certificate_data = $this->order_manager->get_retirement_certificate($order_id);
        
        if (!$certificate_data) {
            return false;
        }
        
        $subject = 'Your Carbon Retirement Certificate - ' . $certificate_data['vendor_order_id'];
        
        $message = "Dear Customer,\n\n";
        $message .= "Your carbon offset retirement has been completed. Here are the details:\n\n";
        $message .= "Certificate ID: " . $certificate_data['vendor_order_id'] . "\n";
        $message .= "Amount Retired: " . number_format($certificate_data['amount_kg'], 2) . " kg CO₂e\n";
        $message .= "Retirement Date: " . $certificate_data['completed_at']->format('F j, Y') . "\n\n";
        $message .= "You can view your full certificate at: " . $certificate_data['certificate_url'] . "\n\n";
        $message .= "Thank you for your commitment to fighting climate change!\n\n";
        $message .= "Best regards,\n";
        $message .= get_bloginfo('name');
        
        return wp_mail($email, $subject, $message);
    }
}