<?php

namespace Tests\Feature\Audit;

use App\Actions\Returns\ProcessCustomerReturn;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Sale;
use App\Models\SaleAllocation;
use App\Models\SaleLine;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A05ReturnDiscountsAndTaxesTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_refund_allocates_invoice_level_discount(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $location = \App\Models\Location::create(['name' => 'Main', 'code' => 'MAIN-01']);
        $medicine = Medicine::create(['name' => 'Panadol', 'brand_name' => 'Panadol 500mg', 'code' => 'MED-001', 'base_unit' => 'tablet']);
        $batch = \App\Models\Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'B123',
            'expiry_date' => now()->addYear(),
        ]);
        $pkg = MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Strip',
            'base_quantity' => 10,
            'sell_price_paisa' => 10000, // PKR 100
        ]);

        // Two items at 100 PKR = 200 PKR (20,000 paisa).
        // Invoice discount = 40 PKR (4,000 paisa). Net total = 160 PKR (16,000 paisa).
        $sale = Sale::create([
            'invoice_number' => 'INV-DISC-001',
            'idempotency_key' => 'idemp-disc-001',
            'cashier_id' => $cashier->id,
            'subtotal_paisa' => 20000,
            'discount_paisa' => 4000, // 40 PKR invoice discount
            'tax_paisa' => 0,
            'total_paisa' => 16000,
            'received_paisa' => 16000,
            'balance_paisa' => 0,
            'status' => 'posted',
            'payment_status' => 'paid',
        ]);

        $line = SaleLine::create([
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'medicine_package_id' => $pkg->id,
            'medicine_name' => 'Panadol 500mg',
            'package_name' => 'Strip',
            'conversion_factor' => 10,
            'quantity' => 2,
            'total_base_quantity' => 20,
            'unit_price_paisa' => 10000,
            'subtotal_paisa' => 20000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 20000,
        ]);

        $lot = StockLot::create([
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'location_id' => $location->id,
            'quantity_base_unit' => 20,
            'cost_per_base_unit' => 5.0,
            'status' => 'active',
        ]);

        SaleAllocation::create([
            'sale_id' => $sale->id,
            'sale_line_id' => $line->id,
            'stock_lot_id' => $lot->id,
            'base_quantity' => 20,
            'cost_per_base_unit_paisa' => 500.0,
        ]);

        $processReturn = app(ProcessCustomerReturn::class);

        // Return 1 of the 2 units
        $salesReturn = $processReturn->execute([
            'sale_id' => $sale->id,
            'items' => [
                ['sale_line_id' => $line->id, 'quantity' => 1, 'disposition' => 'restock'],
            ],
            'refund_method' => 'cash',
        ], $cashier);

        // Refund must be PKR 80 (8,000 paisa) due to invoice discount allocation, NOT PKR 100 (10,000 paisa)!
        $this->assertEquals(8000, $salesReturn->total_refund_paisa);
    }
}
