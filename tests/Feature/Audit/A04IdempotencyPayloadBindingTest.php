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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class A04IdempotencyPayloadBindingTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Counter $counter;
    protected Shift $shift;
    protected Medicine $medicine;
    protected MedicinePackage $package;

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

        StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $loc->id,
            'initial_base_qty' => 100,
            'remaining_base_qty' => 100,
            'cost_per_base_unit' => 3.00,
        ]);
    }

    public function test_same_idempotency_key_with_identical_payload_returns_same_sale(): void
    {
        $finalizeSale = app(FinalizeSale::class);

        $payload = [
            'idempotency_key' => 'idem-test-key-1',
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

        $sale1 = $finalizeSale->execute($payload, $this->cashier);
        $sale2 = $finalizeSale->execute($payload, $this->cashier);

        $this->assertEquals($sale1->id, $sale2->id);
        $this->assertEquals($sale1->invoice_number, $sale2->invoice_number);
    }

    public function test_same_idempotency_key_with_different_payload_is_rejected_with_conflict(): void
    {
        $finalizeSale = app(FinalizeSale::class);

        $payload1 = [
            'idempotency_key' => 'idem-conflict-key',
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

        $payload2 = [
            'idempotency_key' => 'idem-conflict-key',
            'shift_id' => $this->shift->id,
            'payment_method' => 'cash',
            'received_paisa' => 1500,
            'items' => [
                [
                    'medicine_id' => $this->medicine->id,
                    'medicine_package_id' => $this->package->id,
                    'quantity' => 3, // Different quantity
                ],
            ],
        ];

        $sale1 = $finalizeSale->execute($payload1, $this->cashier);
        $this->assertNotNull($sale1);

        $this->expectException(ConflictHttpException::class);
        $finalizeSale->execute($payload2, $this->cashier);
    }

    public function test_same_idempotency_key_from_different_cashier_is_rejected_with_conflict(): void
    {
        $finalizeSale = app(FinalizeSale::class);

        $otherCashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $otherShift = Shift::create([
            'counter_id' => $this->counter->id,
            'user_id' => $otherCashier->id,
            'opening_float_paisa' => 500000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $payload = [
            'idempotency_key' => 'idem-cashier-mismatch',
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

        $sale1 = $finalizeSale->execute($payload, $this->cashier);
        $this->assertNotNull($sale1);

        $payloadForOther = $payload;
        $payloadForOther['shift_id'] = $otherShift->id;

        $this->expectException(ConflictHttpException::class);
        $finalizeSale->execute($payloadForOther, $otherCashier);
    }
}
