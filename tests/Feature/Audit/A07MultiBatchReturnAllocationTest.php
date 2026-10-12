<?php

namespace Tests\Feature\Audit;

use App\Actions\Returns\ProcessCustomerReturn;
use App\Models\Batch;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Sale;
use App\Models\SaleAllocation;
use App\Models\SaleLine;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A07MultiBatchReturnAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_batch_sale_returns_restore_quantities_to_their_respective_lots(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $location = Location::create(['name' => 'Main', 'code' => 'MAIN-01']);
        $medicine = Medicine::create(['name' => 'Augmentin', 'brand_name' => 'Augmentin 625mg', 'code' => 'MED-002', 'base_unit' => 'tablet']);
        $batchA = Batch::create(['medicine_id' => $medicine->id, 'batch_number' => 'BATCH-A', 'expiry_date' => now()->addMonths(6)]);
        $batchB = Batch::create(['medicine_id' => $medicine->id, 'batch_number' => 'BATCH-B', 'expiry_date' => now()->addMonths(12)]);

        $pkg = MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Pack',
            'base_quantity' => 10,
            'sell_price_paisa' => 20000,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-MULTI-001',
            'idempotency_key' => 'idemp-multi-001',
            'cashier_id' => $cashier->id,
            'subtotal_paisa' => 100000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 100000,
            'received_paisa' => 100000,
            'balance_paisa' => 0,
            'status' => 'posted',
            'payment_status' => 'paid',
        ]);

        // 5 packs sold (50 base units)
        $line = SaleLine::create([
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'medicine_package_id' => $pkg->id,
            'medicine_name' => 'Augmentin 625mg',
            'package_name' => 'Pack',
            'conversion_factor' => 10,
            'quantity' => 5,
            'total_base_quantity' => 50,
            'unit_price_paisa' => 20000,
            'subtotal_paisa' => 100000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 100000,
        ]);

        // Lot A started at 0 after sale (10 units were sold from Lot A)
        $lotA = StockLot::create([
            'medicine_id' => $medicine->id,
            'batch_id' => $batchA->id,
            'location_id' => $location->id,
            'quantity_base_unit' => 0,
            'cost_per_base_unit' => 10.0,
            'status' => 'active',
        ]);

        // Lot B started at 0 after sale (40 units were sold from Lot B)
        $lotB = StockLot::create([
            'medicine_id' => $medicine->id,
            'batch_id' => $batchB->id,
            'location_id' => $location->id,
            'quantity_base_unit' => 0,
            'cost_per_base_unit' => 10.0,
            'status' => 'active',
        ]);

        SaleAllocation::create([
            'sale_id' => $sale->id,
            'sale_line_id' => $line->id,
            'stock_lot_id' => $lotA->id,
            'base_quantity' => 10, // 1 pack from Batch A
            'cost_per_base_unit_paisa' => 1000.0,
        ]);

        SaleAllocation::create([
            'sale_id' => $sale->id,
            'sale_line_id' => $line->id,
            'stock_lot_id' => $lotB->id,
            'base_quantity' => 40, // 4 packs from Batch B
            'cost_per_base_unit_paisa' => 1000.0,
        ]);

        $processReturn = app(ProcessCustomerReturn::class);

        // Return all 5 packs
        $processReturn->execute([
            'sale_id' => $sale->id,
            'items' => [
                ['sale_line_id' => $line->id, 'quantity' => 5, 'disposition' => 'restock'],
            ],
            'refund_method' => 'cash',
        ], $cashier);

        // Lot A must receive 10 base units, Lot B must receive 40 base units
        $this->assertEquals(10, $lotA->fresh()->remaining_base_qty);
        $this->assertEquals(40, $lotB->fresh()->remaining_base_qty);
    }
}
