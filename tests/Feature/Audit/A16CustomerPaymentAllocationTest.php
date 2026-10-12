<?php

namespace Tests\Feature\Audit;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A16CustomerPaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_payment_settles_unpaid_sale_invoices(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $customer = Customer::create([
            'code' => 'CUST-001',
            'name' => 'John Doe',
            'phone' => '03001234567',
            'credit_limit_paisa' => 500000,
            'balance_paisa' => 20000, // PKR 200 outstanding
            'is_active' => true,
        ]);

        // Sale 1: Total PKR 150 (15,000 paisa), unpaid, balance = 15,000
        $sale1 = Sale::create([
            'invoice_number' => 'INV-CREDIT-001',
            'idempotency_key' => 'idem-c-001',
            'cashier_id' => $cashier->id,
            'customer_id' => $customer->id,
            'total_paisa' => 15000,
            'received_paisa' => 0,
            'balance_paisa' => 15000,
            'payment_method' => 'credit',
            'payment_status' => 'unpaid',
            'status' => 'posted',
        ]);

        // Sale 2: Total PKR 50 (5,000 paisa), unpaid, balance = 5,000
        $sale2 = Sale::create([
            'invoice_number' => 'INV-CREDIT-002',
            'idempotency_key' => 'idem-c-002',
            'cashier_id' => $cashier->id,
            'customer_id' => $customer->id,
            'total_paisa' => 5000,
            'received_paisa' => 0,
            'balance_paisa' => 5000,
            'payment_method' => 'credit',
            'payment_status' => 'unpaid',
            'status' => 'posted',
        ]);

        // Customer pays PKR 180 (18,000 paisa)
        // Sale 1 (15,000) should be fully settled: balance 0, status 'paid'
        // Sale 2 (5,000) should be partially settled: balance 2,000, status 'partial'
        $response = $this->actingAs($cashier)->post(route('customers.payment', $customer->id), [
            'amount_paisa' => 18000,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();

        // Customer balance should be 20,000 - 18,000 = 2,000 paisa
        $this->assertEquals(2000, $customer->fresh()->balance_paisa);

        // Sale 1 must be marked paid with 0 balance
        $sale1Fresh = $sale1->fresh();
        $this->assertEquals(0, $sale1Fresh->balance_paisa, 'Sale 1 balance must be settled to 0');
        $this->assertEquals('paid', $sale1Fresh->payment_status, 'Sale 1 payment status must be paid');

        // Sale 2 must have remaining balance of 2,000 and status partial
        $sale2Fresh = $sale2->fresh();
        $this->assertEquals(2000, $sale2Fresh->balance_paisa, 'Sale 2 balance must be updated to 2000');
        $this->assertEquals('partial', $sale2Fresh->payment_status, 'Sale 2 payment status must be partial');
    }
}
