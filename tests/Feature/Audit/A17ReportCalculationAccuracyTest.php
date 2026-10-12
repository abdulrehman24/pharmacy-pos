<?php

namespace Tests\Feature\Audit;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Sale;
use App\Models\SaleAllocation;
use App\Models\SaleLine;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A17ReportCalculationAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_subtract_returned_cogs_and_allow_negative_margin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $location = Location::create(['name' => 'Main', 'code' => 'MAIN', 'is_default' => true]);
        $cat = Category::create(['name' => 'General', 'slug' => 'general']);
        $man = Manufacturer::create(['name' => 'GSK']);

        $medicine = Medicine::create([
            'code' => 'MED-01',
            'category_id' => $cat->id,
            'manufacturer_id' => $man->id,
            'brand_name' => 'Panadol',
            'generic_name' => 'Paracetamol',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        $batch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'B001',
            'expiry_date' => now()->addYear(),
            'status' => 'sellable',
        ]);

        $lot = StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $location->id,
            'initial_base_qty' => 100,
            'remaining_base_qty' => 100,
            'cost_per_base_unit' => 8.00, // PKR 8.00 (800 paisa)
        ]);

        // Sale: Sold 10 units for PKR 6.00 each = PKR 60 (6,000 paisa).
        // Total cost was 10 * PKR 8.00 = PKR 80 (8,000 paisa).
        // Sold at a LOSS: Net sales 60 - Tax 0 - COGS 80 = -20 PKR (-2,000 paisa margin).
        $sale = Sale::create([
            'invoice_number' => 'INV-LOSS-001',
            'idempotency_key' => 'idem-loss-001',
            'cashier_id' => $admin->id,
            'subtotal_paisa' => 6000,
            'total_paisa' => 6000,
            'received_paisa' => 6000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'posted',
        ]);

        $line = SaleLine::create([
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'medicine_name' => 'Panadol',
            'package_name' => 'Tablet',
            'conversion_factor' => 1,
            'quantity' => 10,
            'total_base_quantity' => 10,
            'unit_price_paisa' => 600,
            'subtotal_paisa' => 6000,
            'total_paisa' => 6000,
        ]);

        SaleAllocation::create([
            'sale_id' => $sale->id,
            'sale_line_id' => $line->id,
            'stock_lot_id' => $lot->id,
            'base_quantity' => 10,
            'cost_per_base_unit_paisa' => 800,
        ]);

        // Customer returns 5 units restocked
        // Return refund = PKR 30 (3,000 paisa).
        // Returned COGS = 5 * 800 paisa = 4,000 paisa.
        // Effective COGS = 8,000 - 4,000 = 4,000 paisa.
        // Net sales = 6,000 - 3,000 = 3,000 paisa.
        // Margin = 3,000 - 4,000 = -1,000 paisa (negative margin PKR -10.00).
        $return = SalesReturn::create([
            'return_number' => 'RET-001',
            'sale_id' => $sale->id,
            'cashier_id' => $admin->id,
            'total_refund_paisa' => 3000,
            'refund_method' => 'cash',
            'reason' => 'Defective',
            'status' => 'completed',
        ]);

        SalesReturnLine::create([
            'sales_return_id' => $return->id,
            'sale_line_id' => $line->id,
            'medicine_id' => $medicine->id,
            'stock_lot_id' => $lot->id,
            'quantity' => 5,
            'base_quantity' => 5,
            'disposition' => 'restock',
            'refund_paisa' => 3000,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.index', [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $salesSummary = $response->viewData('page')['props']['salesSummary'];

        // Returned COGS must be deducted from COGS
        $this->assertEquals(4000, $salesSummary['cogs_paisa'], 'COGS must deduct restocked returned units');

        // Gross margin must reflect actual negative loss, NOT clamped to 0
        $this->assertEquals(-1000, $salesSummary['gross_margin_paisa'], 'Gross margin must allow negative numbers to reflect true losses');
    }
}
