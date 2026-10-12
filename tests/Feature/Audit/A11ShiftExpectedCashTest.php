<?php

namespace Tests\Feature\Audit;

use App\Models\Counter;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A11ShiftExpectedCashTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Counter $counter;
    protected Shift $shift;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->counter = Counter::create(['name' => 'Counter 1', 'code' => 'C-01', 'is_active' => true]);

        // Opening float: PKR 5,000 (500,000 paisa)
        $this->shift = Shift::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->cashier->id,
            'opening_float_paisa' => 500000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'MediCorp Distributors',
            'company_name' => 'MediCorp',
            'phone' => '03001112233',
            'balance_paisa' => 1000000,
            'is_active' => true,
        ]);
    }

    public function test_supplier_cash_payment_deducts_from_shift_expected_cash(): void
    {
        // Initial expected cash is 500,000 paisa (PKR 5,000)
        $this->assertEquals(500000, $this->shift->calculateExpectedCashPaisa());

        // Pay supplier PKR 1,500 (150,000 paisa) in cash from drawer
        SupplierPayment::create([
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->cashier->id,
            'shift_id' => $this->shift->id,
            'amount_paisa' => 150000,
            'payment_method' => 'cash',
        ]);

        // Expected cash MUST be 500,000 - 150,000 = 350,000 paisa
        $this->assertEquals(350000, $this->shift->calculateExpectedCashPaisa());
    }

    public function test_card_sale_change_does_not_deduct_from_cash_drawer(): void
    {
        // Card sale: Total PKR 1,000 (100,000 paisa), received recorded as PKR 1,200 (120,000 paisa)
        // change_paisa = 20,000.
        // Since payment method is CARD, no cash came in, and cash drawer should NOT have change deducted!
        Sale::create([
            'invoice_number' => 'INV-CARD-001',
            'idempotency_key' => 'idem-card-001',
            'shift_id' => $this->shift->id,
            'cashier_id' => $this->cashier->id,
            'total_paisa' => 100000,
            'received_paisa' => 120000,
            'change_paisa' => 20000,
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'status' => 'posted',
        ]);

        // Expected cash should remain 500,000 paisa (opening float), NOT 480,000!
        $this->assertEquals(500000, $this->shift->calculateExpectedCashPaisa());
    }
}
