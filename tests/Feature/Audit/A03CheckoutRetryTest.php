<?php

namespace Tests\Feature\Audit;

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
use Tests\TestCase;

class A03CheckoutRetryTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Counter $counter;
    protected Shift $shift;
    protected Medicine $medicine;
    protected MedicinePackage $package;
    protected StockLot $lot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->counter = Counter::create(['name' => 'Counter 1', 'code' => 'C-01', 'is_active' => true]);
        $this->shift = Shift::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->cashier->id,
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

        $this->lot = StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $loc->id,
            'initial_base_qty' => 10,
            'remaining_base_qty' => 10,
            'cost_per_base_unit' => 3.00,
        ]);
    }

    public function test_checkout_retry_with_retained_idempotency_key_does_not_double_deduct_stock(): void
    {
        $idempotencyKey = 'pos_retry_stable_key_123';
        $payload = [
            'idempotency_key' => $idempotencyKey,
            'shift_id' => $this->shift->id,
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

        // Attempt 1: Checkout succeeds on server, returns sale
        $response1 = $this->actingAs($this->cashier)->postJson(route('pos.checkout'), $payload);
        $response1->assertOk();
        $saleId1 = $response1->json('sale.id');

        // Remaining stock should be 10 - 2 = 8
        $this->assertEquals(8, $this->lot->fresh()->remaining_base_qty);

        // Attempt 2: UI retries with same retained idempotency key (e.g. simulated network glitch on client)
        $response2 = $this->actingAs($this->cashier)->postJson(route('pos.checkout'), $payload);
        $response2->assertOk();
        $saleId2 = $response2->json('sale.id');

        // Must return the EXACT same sale
        $this->assertEquals($saleId1, $saleId2);

        // Stock MUST NOT be deducted twice (remains 8, not 6)
        $this->assertEquals(8, $this->lot->fresh()->remaining_base_qty);
    }
}
