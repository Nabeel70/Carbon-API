<?php
/**
 * Order Model Tests
 *
 * @package CarbonMarketplace
 * @since 1.0.0
 */

use CarbonMarketplace\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase {
    
    /**
     * Test order creation with valid data
     */
    public function test_order_creation_with_valid_data() {
        $data = [
            'id' => 'order_123',
            'vendor_order_id' => 'vendor_456',
            'vendor' => 'cnaught',
            'user_id' => 1,
            'amount_kg' => 10.5,
            'total_price' => 157.50,
            'currency' => 'USD',
            'status' => 'completed',
            'project_allocations' => [
                ['project_id' => 'proj_1', 'amount_kg' => 5.0],
                ['project_id' => 'proj_2', 'amount_kg' => 5.5],
            ],
            'commission_amount' => 15.75,
        ];
        
        $order = new Order($data);
        
        $this->assertEquals('order_123', $order->id);
        $this->assertEquals('vendor_456', $order->vendor_order_id);
        $this->assertEquals('cnaught', $order->vendor);
        $this->assertEquals(1, $order->user_id);
        $this->assertEquals(10.5, $order->amount_kg);
        $this->assertEquals(157.50, $order->total_price);
        $this->assertEquals('USD', $order->currency);
        $this->assertEquals('completed', $order->status);
        $this->assertIsArray($order->project_allocations);
        $this->assertEquals(15.75, $order->commission_amount);
    }
    
    /**
     * Test order with default values
     */
    public function test_order_defaults() {
        $order = new Order();
        
        $this->assertEquals('USD', $order->currency);
        $this->assertEquals('pending', $order->status);
        $this->assertIsArray($order->project_allocations);
        $this->assertIsArray($order->retirement_data);
        $this->assertInstanceOf(\DateTime::class, $order->created_at);
    }
    
    /**
     * Test order validation with valid data
     */
    public function test_order_validation_valid() {
        $data = [
            'id' => 'order_123',
            'vendor_order_id' => 'vendor_456',
            'vendor' => 'cnaught',
            'amount_kg' => 10.0,
            'total_price' => 150.00,
            'status' => 'completed',
        ];
        
        $order = new Order($data);
        $this->assertTrue($order->validate());
        $this->assertEmpty($order->get_validation_errors());
    }
    
    /**
     * Test order validation with missing required fields
     */
    public function test_order_validation_missing_required() {
        $order = new Order();
        $this->assertFalse($order->validate());
        
        $errors = $order->get_validation_errors();
        $this->assertArrayHasKey('id', $errors);
        $this->assertArrayHasKey('vendor_order_id', $errors);
        $this->assertArrayHasKey('vendor', $errors);
    }
    
    /**
     * Test order validation with invalid amount
     */
    public function test_order_validation_invalid_amount() {
        $data = [
            'id' => 'order_123',
            'vendor_order_id' => 'vendor_456',
            'vendor' => 'cnaught',
            'amount_kg' => 0,
        ];
        
        $order = new Order($data);
        $this->assertFalse($order->validate());
        
        $errors = $order->get_validation_errors();
        $this->assertArrayHasKey('amount_kg', $errors);
    }
    
    /**
     * Test order validation with invalid status
     */
    public function test_order_validation_invalid_status() {
        $data = [
            'id' => 'order_123',
            'vendor_order_id' => 'vendor_456',
            'vendor' => 'cnaught',
            'amount_kg' => 10.0,
            'status' => 'invalid_status',
        ];
        
        $order = new Order($data);
        $this->assertFalse($order->validate());
        
        $errors = $order->get_validation_errors();
        $this->assertArrayHasKey('status', $errors);
    }
    
    /**
     * Test order validation with invalid currency
     */
    public function test_order_validation_invalid_currency() {
        $data = [
            'id' => 'order_123',
            'vendor_order_id' => 'vendor_456',
            'vendor' => 'cnaught',
            'amount_kg' => 10.0,
            'currency' => 'INVALID',
        ];
        
        $order = new Order($data);
        $this->assertFalse($order->validate());
        
        $errors = $order->get_validation_errors();
        $this->assertArrayHasKey('currency', $errors);
    }
    
    /**
     * Test order to_array method
     */
    public function test_order_to_array() {
        $data = [
            'id' => 'order_123',
            'vendor_order_id' => 'vendor_456',
            'vendor' => 'cnaught',
            'amount_kg' => 10.0,
            'total_price' => 150.00,
        ];
        
        $order = new Order($data);
        $array = $order->to_array();
        
        $this->assertIsArray($array);
        $this->assertEquals('order_123', $array['id']);
        $this->assertEquals('vendor_456', $array['vendor_order_id']);
        $this->assertEquals('cnaught', $array['vendor']);
        $this->assertEquals(10.0, $array['amount_kg']);
        $this->assertEquals(150.00, $array['total_price']);
    }
    
    /**
     * Test order from_array method
     */
    public function test_order_from_array() {
        $data = [
            'id' => 'order_123',
            'vendor_order_id' => 'vendor_456',
            'vendor' => 'cnaught',
        ];
        
        $order = Order::from_array($data);
        
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals('order_123', $order->id);
        $this->assertEquals('vendor_456', $order->vendor_order_id);
    }
    
    /**
     * Test order JSON serialization
     */
    public function test_order_json_serialization() {
        $data = [
            'id' => 'order_123',
            'vendor_order_id' => 'vendor_456',
            'vendor' => 'cnaught',
        ];
        
        $order = new Order($data);
        $json = $order->to_json();
        
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals('order_123', $decoded['id']);
        
        $order_from_json = Order::from_json($json);
        $this->assertInstanceOf(Order::class, $order_from_json);
        $this->assertEquals('order_123', $order_from_json->id);
    }
    
    /**
     * Test order status methods
     */
    public function test_order_status_methods() {
        $pending_order = new Order(['status' => 'pending']);
        $this->assertTrue($pending_order->is_pending());
        $this->assertFalse($pending_order->is_completed());
        $this->assertFalse($pending_order->is_cancelled());
        
        $completed_order = new Order(['status' => 'completed']);
        $this->assertFalse($completed_order->is_pending());
        $this->assertTrue($completed_order->is_completed());
        $this->assertFalse($completed_order->is_cancelled());
        
        $cancelled_order = new Order(['status' => 'cancelled']);
        $this->assertFalse($cancelled_order->is_pending());
        $this->assertFalse($cancelled_order->is_completed());
        $this->assertTrue($cancelled_order->is_cancelled());
    }
    
    /**
     * Test mark order as completed
     */
    public function test_mark_completed() {
        $order = new Order(['status' => 'pending']);
        $this->assertTrue($order->is_pending());
        
        $order->mark_completed();
        $this->assertTrue($order->is_completed());
        $this->assertInstanceOf(\DateTime::class, $order->completed_at);
    }
    
    /**
     * Test mark order as cancelled
     */
    public function test_mark_cancelled() {
        $order = new Order(['status' => 'pending']);
        $this->assertTrue($order->is_pending());
        
        $order->mark_cancelled();
        $this->assertTrue($order->is_cancelled());
    }
    
    /**
     * Test formatted total price
     */
    public function test_formatted_total() {
        $usd_order = new Order(['total_price' => 150.50, 'currency' => 'USD']);
        $this->assertEquals('$150.50', $usd_order->get_formatted_total());
        
        $eur_order = new Order(['total_price' => 150.50, 'currency' => 'EUR']);
        $this->assertEquals('€150.50', $eur_order->get_formatted_total());
    }
    
    /**
     * Test price per kg calculation
     */
    public function test_price_per_kg() {
        $order = new Order(['amount_kg' => 10.0, 'total_price' => 150.00]);
        $this->assertEquals(15.0, $order->get_price_per_kg());
        
        $formatted = $order->get_formatted_price_per_kg();
        $this->assertEquals('$15.00/kg', $formatted);
    }
    
    /**
     * Test project allocation methods
     */
    public function test_project_allocations() {
        $order = new Order();
        
        $order->add_project_allocation('proj_1', 5.0);
        $order->add_project_allocation('proj_2', 3.5, ['additional' => 'data']);
        
        $this->assertEquals(2, count($order->project_allocations));
        $this->assertEquals(8.5, $order->get_total_allocated());
        
        $allocation = $order->project_allocations[1];
        $this->assertEquals('proj_2', $allocation['project_id']);
        $this->assertEquals(3.5, $allocation['amount_kg']);
        $this->assertEquals('data', $allocation['additional']);
    }
    
    /**
     * Test retirement certificate methods
     */
    public function test_retirement_certificate() {
        $order_without_cert = new Order();
        $this->assertFalse($order_without_cert->has_retirement_certificate());
        
        $order_with_cert = new Order(['retirement_certificate' => 'cert_123']);
        $this->assertTrue($order_with_cert->has_retirement_certificate());
    }
    
    /**
     * Test order summary
     */
    public function test_order_summary() {
        $data = [
            'id' => 'order_123',
            'vendor' => 'cnaught',
            'amount_kg' => 10.0,
            'total_price' => 150.00,
            'status' => 'completed',
            'retirement_certificate' => 'cert_123',
        ];
        
        $order = new Order($data);
        $summary = $order->get_summary();
        
        $this->assertIsArray($summary);
        $this->assertEquals('order_123', $summary['id']);
        $this->assertEquals('cnaught', $summary['vendor']);
        $this->assertEquals(10.0, $summary['amount_kg']);
        $this->assertEquals('$150.00', $summary['formatted_total']);
        $this->assertEquals('$15.00/kg', $summary['formatted_price_per_kg']);
        $this->assertTrue($summary['is_completed']);
        $this->assertTrue($summary['has_certificate']);
    }
}