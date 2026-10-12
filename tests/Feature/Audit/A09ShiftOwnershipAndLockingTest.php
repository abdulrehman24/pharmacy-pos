<?php

namespace Tests\Feature\Audit;

use App\Actions\Sales\FinalizeSale;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Counter;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Shift;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class A09ShiftOwnershipAndLockingTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashierA;
    protected User $cashierB;
    protected Counter $counter;
    protected Shift $shiftA;
    protected Medicine $medicine;
    protected MedicinePackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashierA = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->cashierB = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->counter = Counter::create(['name' => 'Counter 1', 'code' => 'C-01', 'is_active' => true]);

        $this->shiftA = Shift::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->cashierA->id,
            'opening_float_paisa' => 500000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $loc = Location::create(['name' => 'Main', 'code' => 'LOC-01', 'is_default' => true]);
        $cat = Category::create(['name' => 'General', 'slug' => 'general']);
        $man = Manufacturer::create(['name' => 'GSK']);

        $this->medicine = Medicine::create([
            'code' => 'MED-01',
            'category_id' => $cat->id,
            'manufacturer_id' => $man->id,
            'brand_name' => 'Panadol 500mg',
            'generic_name' => 'Paracetamol',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        $this->package = MedicinePackage::create([
            'medicine_id' => $this->medicine->id,
            'name' => 'Tablet',
            'base_quantity' => 1,
            'default_sell_price_paisa' => 500,
            'is_default_sale' => true,
        ]);

        $batch = Batch::create([
            'medicine_id' => $this->medicine->id,
            'batch_number' => 'B001',
            'expiry_date' => now()->addYear(),
            'status' => 'sellable',
        ]);

        StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $loc->id,
            'initial_base_qty' => 100,
            'remaining_base_qty' => 100,
            'cost_per_base_unit' => 3.00,
        ]);
    }

    public function test_cashier_cannot_checkout_using_another_cashiers_shift(): void
    {
        $finalizeSale = app(FinalizeSale::class);

        // Cashier B attempts to pass Cashier A's shift ID
        $payload = [
            'idempotency_key' => 'shift-test-cross-1',
            'shift_id' => $this->shiftA->id,
            'payment_method' => 'cash',
            'received_paisa' => 1000,
            'items' => [
                [
                    'medicine_id' => $this->medicine->id,
                    'medicine_package_id' => $this->package->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $finalizeSale->execute($payload, $this->cashierB);
    }

    public function test_cashier_without_open_shift_cannot_checkout_by_silently_hijacking_other_shift(): void
    {
        $finalizeSale = app(FinalizeSale::class);

        // Cashier B has no open shift and passes no shift_id
        $payload = [
            'idempotency_key' => 'shift-test-auto-hijack',
            'payment_method' => 'cash',
            'received_paisa' => 1000,
            'items' => [
                [
                    'medicine_id' => $this->medicine->id,
                    'medicine_package_id' => $this->package->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $sale = $finalizeSale->execute($payload, $this->cashierB);

        // Sale succeeds without compulsory shift, but must never hijack Cashier A's shift
        $this->assertNull($sale->shift_id);
        $this->assertNotEquals($this->shiftA->id, $sale->shift_id);
    }

    public function test_cashier_cannot_close_another_cashiers_shift(): void
    {
        $response = $this->actingAs($this->cashierB)->post(route('shifts.close', $this->shiftA->id), [
            'counted_cash_paisa' => 500000,
            'notes' => 'Unauthorized close attempt',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('open', $this->shiftA->fresh()->status);
    }

    public function test_checkout_fails_if_shift_is_closed(): void
    {
        $this->shiftA->update(['status' => 'closed', 'closed_at' => now()]);

        $finalizeSale = app(FinalizeSale::class);
        $payload = [
            'idempotency_key' => 'shift-test-closed',
            'shift_id' => $this->shiftA->id,
            'payment_method' => 'cash',
            'received_paisa' => 1000,
            'items' => [
                [
                    'medicine_id' => $this->medicine->id,
                    'medicine_package_id' => $this->package->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $finalizeSale->execute($payload, $this->cashierA);
    }
}
