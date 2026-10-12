<?php

namespace Tests\Feature\Audit;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\PrinterProfile;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A21LanPrintRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $counter = \App\Models\Counter::create(['name' => 'Counter 1', 'code' => 'C1', 'is_active' => true]);
        $shift = Shift::create([
            'user_id' => $this->cashier->id,
            'counter_id' => $counter->id,
            'opening_float_paisa' => 50000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $category = Category::create(['name' => 'General', 'slug' => 'general']);
        $medicine = Medicine::create([
            'code' => 'MED-PAN-01',
            'brand_name' => 'Panadol 500mg',
            'generic_name' => 'Paracetamol',
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
            'category_id' => $category->id,
            'prescription_required' => false,
            'is_active' => true,
        ]);

        $package = MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Strip of 10',
            'package_type' => 'strip',
            'conversion_factor' => 10,
            'retail_price_paisa' => 10000,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->sale = Sale::create([
            'invoice_number' => 'INV-20261012-TEST01',
            'idempotency_key' => 'idemp-print-test-01',
            'shift_id' => $shift->id,
            'counter_id' => $counter->id,
            'cashier_id' => $this->cashier->id,
            'subtotal_paisa' => 10000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 10000,
            'received_paisa' => 10000,
            'change_paisa' => 0,
            'balance_paisa' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'posted',
        ]);

        SaleLine::create([
            'sale_id' => $this->sale->id,
            'medicine_id' => $medicine->id,
            'medicine_package_id' => $package->id,
            'medicine_name' => $medicine->brand_name,
            'package_name' => $package->name,
            'conversion_factor' => 10,
            'quantity' => 1,
            'total_base_quantity' => 10,
            'unit_price_paisa' => 10000,
            'subtotal_paisa' => 10000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 10000,
        ]);

        // Default browser printer profile
        PrinterProfile::create([
            'name' => 'Browser Printer',
            'connection_type' => 'browser',
            'is_default' => true,
        ]);
    }

    public function test_print_endpoint_resolves_when_called_with_invoice_number(): void
    {
        // Currently Receipts.tsx calls /sales/{sale.number}/print
        $response = $this->actingAs($this->cashier)
            ->postJson("/sales/{$this->sale->invoice_number}/print");

        $response->assertOk();
    }

    public function test_print_endpoint_resolves_when_called_with_sale_id(): void
    {
        $response = $this->actingAs($this->cashier)
            ->postJson("/sales/{$this->sale->id}/print");

        $response->assertOk();
    }
}
