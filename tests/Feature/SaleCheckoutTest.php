<?php

namespace Tests\Feature;

use App\Actions\Sales\FinalizeSale;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Shift;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SaleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Counter $counter;
    protected Shift $shift;
    protected Location $location;
    protected Category $category;
    protected Manufacturer $manufacturer;

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

        $this->location = Location::create([
            'name' => 'Main Pharmacy Store',
            'code' => 'LOC-MAIN',
            'is_default' => true,
        ]);

        $this->shift = Shift::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->cashier->id,
            'opening_float_paisa' => 500000, // PKR 5,000
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->category = Category::create([
            'name' => 'Analgesics',
            'slug' => 'analgesics',
        ]);

        $this->manufacturer = Manufacturer::create([
            'name' => 'GSK Pakistan',
        ]);
    }

    public function test_sale_checkout_deducts_stock_using_fefo_order(): void
    {
        $medicine = Medicine::create([
            'code' => 'MED-PAN-500',
            'category_id' => $this->category->id,
            'manufacturer_id' => $this->manufacturer->id,
            'brand_name' => 'Panadol 500mg',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Tablet',
            'base_quantity' => 1,
            'default_sell_price_paisa' => 450,
            'is_default_sale' => true,
        ]);

        // Batch 1: Expiring earlier in 30 days, 20 tablets
        $batch1 = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'PAN-BATCH-EARLY',
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'sellable',
        ]);

        $lot1 = StockLot::create([
            'batch_id' => $batch1->id,
            'location_id' => $this->location->id,
            'initial_base_qty' => 20,
            'remaining_base_qty' => 20,
            'cost_per_base_unit' => 3.20,
        ]);

        // Batch 2: Expiring later in 180 days, 50 tablets
        $batch2 = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'PAN-BATCH-LATER',
            'expiry_date' => now()->addDays(180)->toDateString(),
            'status' => 'sellable',
        ]);

        $lot2 = StockLot::create([
            'batch_id' => $batch2->id,
            'location_id' => $this->location->id,
            'initial_base_qty' => 50,
            'remaining_base_qty' => 50,
            'cost_per_base_unit' => 3.40,
        ]);

        // Sell 25 tablets (FEFO: should take 20 from Batch 1, and 5 from Batch 2)
        $finalizeSale = app(FinalizeSale::class);
        $sale = $finalizeSale->execute([
            'shift_id' => $this->shift->id,
            'counter_id' => $this->counter->id,
            'payment_method' => 'cash',
            'received_paisa' => 20000,
            'items' => [
                [
                    'medicine_id' => $medicine->id,
                    'quantity' => 25,
                ]
            ],
        ], $this->cashier);

        $this->assertEquals('posted', $sale->status);
        $this->assertEquals(25 * 450, $sale->total_paisa);

        // Check lots: Lot 1 should have 0 remaining, Lot 2 should have 45 remaining
        $lot1->refresh();
        $lot2->refresh();
        $this->assertEquals(0, $lot1->remaining_base_qty);
        $this->assertEquals(45, $lot2->remaining_base_qty);

        // Verify allocations recorded
        $this->assertCount(2, $sale->allocations);
        $alloc1 = $sale->allocations->firstWhere('stock_lot_id', $lot1->id);
        $alloc2 = $sale->allocations->firstWhere('stock_lot_id', $lot2->id);

        $this->assertEquals(20, $alloc1->base_quantity);
        $this->assertEquals(5, $alloc2->base_quantity);
    }

    public function test_sale_checkout_is_idempotent(): void
    {
        $medicine = Medicine::create([
            'code' => 'MED-BRU-400',
            'category_id' => $this->category->id,
            'manufacturer_id' => $this->manufacturer->id,
            'brand_name' => 'Brufen 400mg',
            'generic_name' => 'Ibuprofen',
            'strength' => '400mg',
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Tablet',
            'base_quantity' => 1,
            'default_sell_price_paisa' => 600,
            'is_default_sale' => true,
        ]);

        $batch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'BRU-101',
            'expiry_date' => now()->addDays(90)->toDateString(),
            'status' => 'sellable',
        ]);

        $lot = StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $this->location->id,
            'initial_base_qty' => 30,
            'remaining_base_qty' => 30,
            'cost_per_base_unit' => 4.00,
        ]);

        $idempotencyKey = 'unique-req-uuid-12345';
        $finalizeSale = app(FinalizeSale::class);

        // First checkout call
        $sale1 = $finalizeSale->execute([
            'idempotency_key' => $idempotencyKey,
            'shift_id' => $this->shift->id,
            'payment_method' => 'cash',
            'received_paisa' => 10000,
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 10]
            ],
        ], $this->cashier);

        $lot->refresh();
        $this->assertEquals(20, $lot->remaining_base_qty);

        // Duplicate call with same idempotency key
        $sale2 = $finalizeSale->execute([
            'idempotency_key' => $idempotencyKey,
            'shift_id' => $this->shift->id,
            'payment_method' => 'cash',
            'received_paisa' => 10000,
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 10]
            ],
        ], $this->cashier);

        // Must return identical sale and not deduct stock again
        $this->assertEquals($sale1->id, $sale2->id);
        $lot->refresh();
        $this->assertEquals(20, $lot->remaining_base_qty);
    }

    public function test_sale_rejects_insufficient_stock(): void
    {
        $medicine = Medicine::create([
            'code' => 'MED-RIS-20',
            'category_id' => $this->category->id,
            'manufacturer_id' => $this->manufacturer->id,
            'brand_name' => 'Risek 20mg',
            'generic_name' => 'Omeprazole',
            'strength' => '20mg',
            'dosage_form' => 'capsule',
            'base_unit' => 'capsule',
            'is_active' => true,
        ]);

        MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Capsule',
            'base_quantity' => 1,
            'default_sell_price_paisa' => 2800,
            'is_default_sale' => true,
        ]);

        $batch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'RIS-001',
            'expiry_date' => now()->addDays(60)->toDateString(),
            'status' => 'sellable',
        ]);

        StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $this->location->id,
            'initial_base_qty' => 5,
            'remaining_base_qty' => 5,
            'cost_per_base_unit' => 20.00,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Insufficient stock");

        $finalizeSale = app(FinalizeSale::class);
        $finalizeSale->execute([
            'shift_id' => $this->shift->id,
            'payment_method' => 'cash',
            'received_paisa' => 100000,
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 10] // requested 10, only 5 available
            ],
        ], $this->cashier);
    }

    public function test_sale_blocks_expired_batches(): void
    {
        $medicine = Medicine::create([
            'code' => 'MED-AUG-625',
            'category_id' => $this->category->id,
            'manufacturer_id' => $this->manufacturer->id,
            'brand_name' => 'Augmentin 625mg',
            'generic_name' => 'Amoxicillin + Clavulanic Acid',
            'strength' => '625mg',
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Tablet',
            'base_quantity' => 1,
            'default_sell_price_paisa' => 3500,
            'is_default_sale' => true,
        ]);

        // Batch expired 2 days ago
        $expiredBatch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'AUG-EXP-001',
            'expiry_date' => now()->subDays(2)->toDateString(),
            'status' => 'sellable',
        ]);

        StockLot::create([
            'batch_id' => $expiredBatch->id,
            'location_id' => $this->location->id,
            'initial_base_qty' => 50,
            'remaining_base_qty' => 50,
            'cost_per_base_unit' => 25.00,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Insufficient stock");

        $finalizeSale = app(FinalizeSale::class);
        $finalizeSale->execute([
            'shift_id' => $this->shift->id,
            'payment_method' => 'cash',
            'received_paisa' => 100000,
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 5]
            ],
        ], $this->cashier);
    }

    public function test_package_conversion_deducts_correct_base_quantity(): void
    {
        // 1 Box = 10 Strips = 100 Tablets
        $medicine = Medicine::create([
            'code' => 'MED-MULTI-100',
            'category_id' => $this->category->id,
            'manufacturer_id' => $this->manufacturer->id,
            'brand_name' => 'Multi-Unit Medicine',
            'generic_name' => 'Sample Drug',
            'strength' => '100mg',
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        $boxPackage = MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Box of 100',
            'base_quantity' => 100,
            'default_sell_price_paisa' => 45000, // PKR 450 per box
            'is_default_sale' => false,
        ]);

        $stripPackage = MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Strip of 10',
            'base_quantity' => 10,
            'default_sell_price_paisa' => 4800, // PKR 48 per strip
            'is_default_sale' => false,
        ]);

        $batch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'MULTI-BATCH-1',
            'expiry_date' => now()->addDays(200)->toDateString(),
            'status' => 'sellable',
        ]);

        $lot = StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $this->location->id,
            'initial_base_qty' => 500,
            'remaining_base_qty' => 500,
            'cost_per_base_unit' => 3.50,
        ]);

        $finalizeSale = app(FinalizeSale::class);

        // Sell 1 Box (100 tablets) and 2 Strips (20 tablets) = 120 tablets total
        $sale = $finalizeSale->execute([
            'shift_id' => $this->shift->id,
            'payment_method' => 'cash',
            'received_paisa' => 100000,
            'items' => [
                [
                    'medicine_id' => $medicine->id,
                    'medicine_package_id' => $boxPackage->id,
                    'quantity' => 1,
                ],
                [
                    'medicine_id' => $medicine->id,
                    'medicine_package_id' => $stripPackage->id,
                    'quantity' => 2,
                ],
            ],
        ], $this->cashier);

        // Total price = 45,000 + (2 * 4,800) = 45,000 + 9,600 = 54,600 paisa
        $this->assertEquals(54600, $sale->total_paisa);

        // Total base quantity deducted = 100 + 20 = 120
        $lot->refresh();
        $this->assertEquals(380, $lot->remaining_base_qty);
    }

    public function test_customer_credit_sale_and_credit_limit_enforcement(): void
    {
        $customer = Customer::create([
            'code' => 'CUST-001',
            'name' => 'Dr. Farhan',
            'phone' => '03001234567',
            'credit_limit_paisa' => 100000, // PKR 1,000 credit limit
            'balance_paisa' => 20000,        // PKR 200 current balance
            'is_active' => true,
        ]);

        $medicine = Medicine::create([
            'code' => 'MED-FLA-400',
            'category_id' => $this->category->id,
            'manufacturer_id' => $this->manufacturer->id,
            'brand_name' => 'Flagyl 400mg',
            'generic_name' => 'Metronidazole',
            'strength' => '400mg',
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Tablet',
            'base_quantity' => 1,
            'default_sell_price_paisa' => 500,
            'is_default_sale' => true,
        ]);

        $batch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'FLA-01',
            'expiry_date' => now()->addDays(90)->toDateString(),
            'status' => 'sellable',
        ]);

        StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $this->location->id,
            'initial_base_qty' => 500,
            'remaining_base_qty' => 500,
            'cost_per_base_unit' => 3.00,
        ]);

        $finalizeSale = app(FinalizeSale::class);

        // Sale 1: PKR 500 (50,000 paisa). New balance: 200 + 500 = PKR 700 <= 1,000 limit (Passes)
        $sale1 = $finalizeSale->execute([
            'shift_id' => $this->shift->id,
            'customer_id' => $customer->id,
            'payment_method' => 'credit',
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 100] // 100 * 500 = 50,000 paisa
            ],
        ], $this->cashier);

        $this->assertEquals('unpaid', $sale1->payment_status);
        $customer->refresh();
        $this->assertEquals(70000, $customer->balance_paisa);

        // Sale 2: Attempt to buy 100 more tablets (PKR 500 / 50,000 paisa).
        // Projected balance: 700 + 500 = PKR 1,200 > PKR 1,000 limit (Must throw RuntimeException)
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Credit limit exceeded");

        $finalizeSale->execute([
            'shift_id' => $this->shift->id,
            'customer_id' => $customer->id,
            'payment_method' => 'credit',
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 100]
            ],
        ], $this->cashier);
    }
}
