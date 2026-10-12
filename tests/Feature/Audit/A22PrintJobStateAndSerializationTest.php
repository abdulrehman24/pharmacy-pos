<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\PrinterProfile;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A22PrintJobStateAndSerializationTest extends TestCase
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
            'code' => 'MED-DIS-01',
            'brand_name' => 'Disprin 300mg',
            'generic_name' => 'Aspirin',
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
            'retail_price_paisa' => 5000,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->sale = Sale::create([
            'invoice_number' => 'INV-20261012-PRINT01',
            'idempotency_key' => 'idemp-print-test-02',
            'shift_id' => $shift->id,
            'counter_id' => $counter->id,
            'cashier_id' => $this->cashier->id,
            'subtotal_paisa' => 5000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 5000,
            'received_paisa' => 5000,
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
            'unit_price_paisa' => 5000,
            'subtotal_paisa' => 5000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 5000,
        ]);
    }

    public function test_browser_print_sets_status_to_browser_ready_not_submitted(): void
    {
        $profile = PrinterProfile::create([
            'name' => 'Default Browser',
            'connection_type' => 'browser',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->cashier)
            ->postJson("/sales/{$this->sale->id}/print", [
                'printer_profile_id' => $profile->id,
            ]);

        $response->assertOk();
        $response->assertJson(['status' => 'browser_ready']);

        $job = PrintJob::where('sale_id', $this->sale->id)->latest()->first();
        $this->assertNotNull($job);
        $this->assertEquals('browser_ready', $job->status, 'Browser print job status must be browser_ready, not submitted');
        $this->assertEquals('receipt', $job->job_type);
    }

    public function test_subsequent_prints_are_strictly_marked_as_duplicates(): void
    {
        $profile = PrinterProfile::create([
            'name' => 'Default Browser',
            'connection_type' => 'browser',
            'is_default' => true,
        ]);

        // First print
        $this->actingAs($this->cashier)->postJson("/sales/{$this->sale->id}/print", [
            'printer_profile_id' => $profile->id,
        ])->assertOk();

        // Second print attempt claiming copy=false
        $response = $this->actingAs($this->cashier)->postJson("/sales/{$this->sale->id}/print", [
            'printer_profile_id' => $profile->id,
            'copy' => false,
        ]);

        $response->assertOk();

        $secondJob = PrintJob::where('sale_id', $this->sale->id)->latest('id')->first();
        $this->assertEquals('duplicate', $secondJob->job_type, 'Subsequent print must be automatically marked as duplicate');

        $this->assertTrue(
            AuditEvent::where('event_type', 'receipt_printed')->exists(),
            'AuditEvent must record receipt_printed'
        );
    }

    public function test_windows_raw_profile_without_accessible_spooler_returns_unknown_status(): void
    {
        $profile = PrinterProfile::create([
            'name' => 'Windows USB Thermal',
            'connection_type' => 'windows_raw',
            'host' => 'POS-80',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->cashier)
            ->postJson("/sales/{$this->sale->id}/print", [
                'printer_profile_id' => $profile->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['status' => 'unknown']);

        $job = PrintJob::where('sale_id', $this->sale->id)->latest()->first();
        $this->assertNotNull($job);
        $this->assertEquals('unknown', $job->status);
    }
}
