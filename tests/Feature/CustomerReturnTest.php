<?php

namespace Tests\Feature;

use App\Actions\Returns\ProcessCustomerReturn;
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
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CustomerReturnTest extends TestCase
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
            'opening_float_paisa' => 500000,
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

    public function test_can_return_items_with_restock_disposition_restores_lot_inventory(): void
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

        $batch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'PAN-BATCH-RET-01',
            'expiry_date' => now()->addDays(90)->toDateString(),
            'status' => 'sellable',
        ]);

        $lot = StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $this->location->id,
            'received_base_qty' => 50,
            'remaining_base_qty' => 50,
            'cost_per_base_unit' => 3.20,
        ]);

        // Sell 5 tablets
        $finalizeSale = app(FinalizeSale::class);
        $sale = $finalizeSale->execute([
            'shift_id' => $this->shift->id,
            'payment_method' => 'cash',
            'received_paisa' => 5000,
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 5],
            ],
        ], $this->cashier);

        $lot->refresh();
        $this->assertEquals(45, $lot->remaining_base_qty);

        $saleLine = $sale->lines->first();
        $this->assertEquals(5, $saleLine->remainingReturnableQty());

        // Return 2 tablets with 'restock' disposition
        $processReturn = app(ProcessCustomerReturn::class);
        $return = $processReturn->execute([
            'sale_id' => $sale->id,
            'shift_id' => $this->shift->id,
            'refund_method' => 'cash',
            'reason' => 'Customer bought wrong pack',
            'items' => [
                [
                    'sale_line_id' => $saleLine->id,
                    'quantity' => 2,
                    'disposition' => 'restock',
                ],
            ],
        ], $this->cashier);

        $this->assertEquals(2 * 450, $return->total_refund_paisa);

        // Lot remaining stock must have increased by 2 (from 45 to 47)
        $lot->refresh();
        $this->assertEquals(47, $lot->remaining_base_qty);

        // Sale line returnable qty must now be 3
        $this->assertEquals(3, $saleLine->remainingReturnableQty());

        // Stock movement ledger recorded
        $movement = StockMovement::where('reference_type', 'SalesReturn')
            ->where('movement_type', 'customer_return')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(2, $movement->quantity_base_unit);
    }

    public function test_cannot_return_more_than_sold_quantity(): void
    {
        $medicine = Medicine::create([
            'code' => 'MED-DIS-300',
            'category_id' => $this->category->id,
            'manufacturer_id' => $this->manufacturer->id,
            'brand_name' => 'Disprin 300mg',
            'generic_name' => 'Aspirin',
            'strength' => '300mg',
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Tablet',
            'base_quantity' => 1,
            'default_sell_price_paisa' => 300,
            'is_default_sale' => true,
        ]);

        $batch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'DIS-BATCH-01',
            'expiry_date' => now()->addDays(90)->toDateString(),
            'status' => 'sellable',
        ]);

        StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $this->location->id,
            'received_base_qty' => 50,
            'remaining_base_qty' => 50,
            'cost_per_base_unit' => 2.00,
        ]);

        $finalizeSale = app(FinalizeSale::class);
        $sale = $finalizeSale->execute([
            'shift_id' => $this->shift->id,
            'payment_method' => 'cash',
            'received_paisa' => 5000,
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 4],
            ],
        ], $this->cashier);

        $saleLine = $sale->lines->first();

        // Return 3 tablets first
        $processReturn = app(ProcessCustomerReturn::class);
        $processReturn->execute([
            'sale_id' => $sale->id,
            'shift_id' => $this->shift->id,
            'items' => [
                [
                    'sale_line_id' => $saleLine->id,
                    'quantity' => 3,
                    'disposition' => 'restock',
                ],
            ],
        ], $this->cashier);

        // Attempting to return 2 more when only 1 is remaining returnable must fail
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot return 2 units");

        $processReturn->execute([
            'sale_id' => $sale->id,
            'shift_id' => $this->shift->id,
            'items' => [
                [
                    'sale_line_id' => $saleLine->id,
                    'quantity' => 2,
                    'disposition' => 'restock',
                ],
            ],
        ], $this->cashier);
    }

    public function test_credit_sale_return_reduces_customer_balance(): void
    {
        $customer = Customer::create([
            'code' => 'CUST-002',
            'name' => 'Tariq Mehmood',
            'phone' => '03339876543',
            'credit_limit_paisa' => 500000,
            'balance_paisa' => 0,
            'is_active' => true,
        ]);

        $medicine = Medicine::create([
            'code' => 'MED-CAL-500',
            'category_id' => $this->category->id,
            'manufacturer_id' => $this->manufacturer->id,
            'brand_name' => 'CaC 1000 Plus',
            'generic_name' => 'Calcium + Vitamin C',
            'strength' => '1000mg',
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Tube of 10',
            'base_quantity' => 1,
            'default_sell_price_paisa' => 45000, // PKR 450
            'is_default_sale' => true,
        ]);

        $batch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'CAC-01',
            'expiry_date' => now()->addDays(120)->toDateString(),
            'status' => 'sellable',
        ]);

        StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $this->location->id,
            'received_base_qty' => 20,
            'remaining_base_qty' => 20,
            'cost_per_base_unit' => 350.00,
        ]);

        // Sale on credit for 2 tubes: 2 * 45,000 = 90,000 paisa (PKR 900)
        $finalizeSale = app(FinalizeSale::class);
        $sale = $finalizeSale->execute([
            'shift_id' => $this->shift->id,
            'customer_id' => $customer->id,
            'payment_method' => 'credit',
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 2],
            ],
        ], $this->cashier);

        $customer->refresh();
        $this->assertEquals(90000, $customer->balance_paisa);

        // Customer returns 1 tube with refund_method 'credit_adjustment'
        $processReturn = app(ProcessCustomerReturn::class);
        $saleLine = $sale->lines->first();

        $processReturn->execute([
            'sale_id' => $sale->id,
            'shift_id' => $this->shift->id,
            'refund_method' => 'credit_adjustment',
            'reason' => 'Partial return on account',
            'items' => [
                [
                    'sale_line_id' => $saleLine->id,
                    'quantity' => 1,
                    'disposition' => 'restock',
                ],
            ],
        ], $this->cashier);

        // Customer balance should be reduced by 45,000 paisa (now 45,000 paisa)
        $customer->refresh();
        $this->assertEquals(45000, $customer->balance_paisa);
    }
}
