<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Category;
use App\Models\Counter;
use App\Models\Expense;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierShiftTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Counter $counter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->counter = Counter::create([
            'name' => 'Counter 1',
            'code' => 'C-01',
            'is_active' => true,
        ]);
    }

    public function test_cashier_can_open_shift_and_cannot_open_duplicate(): void
    {
        $response = $this->actingAs($this->cashier)->post(route('shifts.open'), [
            'counter_id' => $this->counter->id,
            'opening_float_paisa' => 500000, // PKR 5,000
            'notes' => 'Morning shift',
        ]);

        $response->assertRedirect(route('shifts.index'));
        $this->assertDatabaseHas('shifts', [
            'counter_id' => $this->counter->id,
            'user_id' => $this->cashier->id,
            'opening_float_paisa' => 500000,
            'status' => 'open',
        ]);

        // Second attempt to open while active must fail with validation error
        $secondResponse = $this->actingAs($this->cashier)->post(route('shifts.open'), [
            'counter_id' => $this->counter->id,
            'opening_float_paisa' => 200000,
        ]);

        $secondResponse->assertSessionHasErrors('shift');
    }

    public function test_shift_expected_cash_reconciliation_math(): void
    {
        // 1. Opening float: PKR 5,000 (500,000 paisa)
        $shift = Shift::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->cashier->id,
            'opening_float_paisa' => 500000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        // 2. Cash sale: Total PKR 2,000 (Received 2,500, Change given 500 => Net cash added 200,000 paisa)
        Sale::create([
            'invoice_number' => 'INV-TEST-001',
            'idempotency_key' => 'IDEM-KEY-001',
            'shift_id' => $shift->id,
            'counter_id' => $this->counter->id,
            'cashier_id' => $this->cashier->id,
            'subtotal_paisa' => 200000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 200000,
            'received_paisa' => 250000,
            'change_paisa' => 50000,
            'balance_paisa' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'posted',
        ]);

        // 3. Cash in movement: PKR 1,000 (100,000 paisa)
        CashMovement::create([
            'shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'type' => 'cash_in',
            'amount_paisa' => 100000,
            'reason' => 'Extra change added by manager',
        ]);

        // 4. Cash out movement: PKR 500 (50,000 paisa)
        CashMovement::create([
            'shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'type' => 'cash_out',
            'amount_paisa' => 50000,
            'reason' => 'Mid-day cash drop to safe',
        ]);

        // 5. Operating expense from drawer: PKR 300 (30,000 paisa)
        Expense::create([
            'shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'category' => 'Tea & Refreshment',
            'amount_paisa' => 30000,
            'payment_method' => 'cash',
            'description' => 'Staff tea',
            'expense_date' => now()->toDateString(),
        ]);

        // 6. Cash refund return: PKR 200 (20,000 paisa)
        SalesReturn::create([
            'return_number' => 'RET-TEST-001',
            'sale_id' => 1,
            'cashier_id' => $this->cashier->id,
            'shift_id' => $shift->id,
            'total_refund_paisa' => 20000,
            'refund_method' => 'cash',
            'reason' => 'Customer refund',
            'status' => 'completed',
        ]);

        // Formula: 500,000 (opening) + 200,000 (net sale) + 100,000 (cash in)
        //          - 50,000 (cash out) - 30,000 (expense) - 20,000 (return) = 700,000 paisa
        $expectedCash = $shift->calculateExpectedCashPaisa();
        $this->assertEquals(700000, $expectedCash);
    }

    public function test_cashier_can_close_shift_and_computes_exact_variance(): void
    {
        $shift = Shift::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->cashier->id,
            'opening_float_paisa' => 500000, // PKR 5,000
            'opened_at' => now(),
            'status' => 'open',
        ]);

        // Sale of PKR 1,000 cash
        Sale::create([
            'invoice_number' => 'INV-TEST-002',
            'idempotency_key' => 'IDEM-KEY-002',
            'shift_id' => $shift->id,
            'counter_id' => $this->counter->id,
            'cashier_id' => $this->cashier->id,
            'subtotal_paisa' => 100000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 100000,
            'received_paisa' => 100000,
            'change_paisa' => 0,
            'balance_paisa' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'posted',
        ]);

        // Expected cash = 500,000 + 100,000 = 600,000 paisa (PKR 6,000)
        // Cashier physically counted PKR 5,950 (595,000 paisa) -> 50 PKR short (-5,000 paisa variance)
        $response = $this->actingAs($this->cashier)->post(route('shifts.close', $shift), [
            'counted_cash_paisa' => 595000,
            'notes' => 'Short by 50 rupees due to coin rounding',
        ]);

        $response->assertRedirect(route('shifts.index'));

        $shift->refresh();
        $this->assertEquals('closed', $shift->status);
        $this->assertEquals(600000, $shift->expected_cash_paisa);
        $this->assertEquals(595000, $shift->counted_cash_paisa);
        $this->assertEquals(-5000, $shift->variance_paisa);
        $this->assertNotNull($shift->closed_at);
    }
}
