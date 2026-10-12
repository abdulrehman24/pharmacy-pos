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
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A27QuarantinedReturnLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_quarantined_returns_record_actual_physical_base_quantity_in_movement(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $location = Location::create(['name' => 'Main', 'code' => 'MAIN-01']);
        $medicine = Medicine::create(['name' => 'Brufen', 'brand_name' => 'Brufen 400mg', 'code' => 'MED-004', 'base_unit' => 'tablet']);
        $batch = Batch::create(['medicine_id' => $medicine->id, 'batch_number' => 'B004', 'expiry_date' => now()->addYear()]);
        $pkg = MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Pack',
            'base_quantity' => 10,
            'sell_price_paisa' => 5000,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-QUAR-001',
            'idempotency_key' => 'idemp-quar-001',
            'cashier_id' => $cashier->id,
            'subtotal_paisa' => 5000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 5000,
            'received_paisa' => 5000,
            'balance_paisa' => 0,
            'status' => 'posted',
            'payment_status' => 'paid',
        ]);

        $line = SaleLine::create([
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'medicine_package_id' => $pkg->id,
            'medicine_name' => 'Brufen 400mg',
            'package_name' => 'Pack',
            'conversion_factor' => 10,
            'quantity' => 1,
            'total_base_quantity' => 10,
            'unit_price_paisa' => 5000,
            'subtotal_paisa' => 5000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 5000,
        ]);

        $lot = StockLot::create([
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'location_id' => $location->id,
            'quantity_base_unit' => 0,
            'cost_per_base_unit' => 2.0,
            'status' => 'active',
        ]);

        SaleAllocation::create([
            'sale_id' => $sale->id,
            'sale_line_id' => $line->id,
            'stock_lot_id' => $lot->id,
            'base_quantity' => 10,
            'cost_per_base_unit_paisa' => 200.0,
        ]);

        $processReturn = app(ProcessCustomerReturn::class);

        // Return with disposition = quarantine
        $salesReturn = $processReturn->execute([
            'sale_id' => $sale->id,
            'items' => [
                ['sale_line_id' => $line->id, 'quantity' => 1, 'disposition' => 'quarantine'],
            ],
            'refund_method' => 'cash',
        ], $cashier);

        // Movement must record actual physical quantity 10, NOT 0
        $movement = StockMovement::where('reference_type', 'SalesReturn')
            ->where('reference_id', $salesReturn->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(10, $movement->quantity_base_unit);
    }
}
