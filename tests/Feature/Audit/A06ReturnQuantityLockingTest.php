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

class A06ReturnQuantityLockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_items_for_same_line_cannot_exceed_available_quantity(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $location = Location::create(['name' => 'Main', 'code' => 'MAIN-01']);
        $medicine = Medicine::create(['name' => 'Panadol', 'brand_name' => 'Panadol 500mg', 'code' => 'MED-001', 'base_unit' => 'tablet']);
        $batch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'B123',
            'expiry_date' => now()->addYear(),
        ]);
        $pkg = MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Strip',
            'base_quantity' => 10,
            'sell_price_paisa' => 10000,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-QTY-001',
            'idempotency_key' => 'idemp-qty-001',
            'cashier_id' => $cashier->id,
            'subtotal_paisa' => 10000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 10000,
            'received_paisa' => 10000,
            'balance_paisa' => 0,
            'status' => 'posted',
            'payment_status' => 'paid',
        ]);

        // Sale has only 1 unit sold
        $line = SaleLine::create([
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'medicine_package_id' => $pkg->id,
            'medicine_name' => 'Panadol 500mg',
            'package_name' => 'Strip',
            'conversion_factor' => 10,
            'quantity' => 1,
            'total_base_quantity' => 10,
            'unit_price_paisa' => 10000,
            'subtotal_paisa' => 10000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 10000,
        ]);

        $lot = StockLot::create([
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'location_id' => $location->id,
            'quantity_base_unit' => 10,
            'cost_per_base_unit' => 5.0,
            'status' => 'active',
        ]);

        SaleAllocation::create([
            'sale_id' => $sale->id,
            'sale_line_id' => $line->id,
            'stock_lot_id' => $lot->id,
            'base_quantity' => 10,
            'cost_per_base_unit_paisa' => 500.0,
        ]);

        $processReturn = app(ProcessCustomerReturn::class);

        // Submitting TWO lines for the same sale_line_id with quantity 1 each (total 2 for 1 sold item)
        $this->expectException(\RuntimeException::class);

        $processReturn->execute([
            'sale_id' => $sale->id,
            'items' => [
                ['sale_line_id' => $line->id, 'quantity' => 1, 'disposition' => 'restock'],
                ['sale_line_id' => $line->id, 'quantity' => 1, 'disposition' => 'restock'],
            ],
            'refund_method' => 'cash',
        ], $cashier);
    }
}
