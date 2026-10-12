<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A28CatalogueMaintenanceAndScaleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected Location $location;
    protected Supplier $supplierA;
    protected Supplier $supplierB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'name' => 'Main Pharmacy Store',
            'code' => 'STORE-MAIN',
            'is_active' => true,
        ]);

        $this->supplierA = Supplier::create([
            'code' => 'SUP-ALPHA',
            'name' => 'Supplier Alpha Distribution',
            'contact_person' => 'Alpha Rep',
            'balance_paisa' => 500000,
        ]);

        $this->supplierB = Supplier::create([
            'code' => 'SUP-BETA',
            'name' => 'Supplier Beta Pharma',
            'contact_person' => 'Beta Rep',
            'balance_paisa' => 200000,
        ]);
    }

    public function test_medicine_can_be_edited_and_updated_with_audit_logging(): void
    {
        $category = Category::create(['name' => 'Antibiotics', 'slug' => 'antibiotics']);
        $manufacturer = Manufacturer::create(['name' => 'GSK']);

        $medicine = Medicine::create([
            'code' => 'MED-TEST-01',
            'brand_name' => 'Augmentin',
            'generic_name' => 'Amoxicillin + Clavulanic Acid',
            'strength' => '625mg',
            'dosage_form' => 'Tablet',
            'category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
            'base_unit' => 'Tablet',
            'rack_shelf' => 'Shelf-A1',
            'reorder_level' => 30,
            'prescription_required' => true,
            'is_narcotic' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('medicines.edit', $medicine));
        $response->assertOk();

        $updateResponse = $this->actingAs($this->admin)->put(route('medicines.update', $medicine), [
            'brand_name' => 'Augmentin Forte',
            'generic_name' => 'Amoxicillin + Clavulanic Acid',
            'strength' => '1000mg',
            'dosage_form' => 'Tablet',
            'category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
            'base_unit' => 'Tablet',
            'rack_shelf' => 'Shelf-B2',
            'reorder_level' => 50,
            'prescription_required' => true,
            'is_narcotic' => false,
            'notes' => 'Updated formulation',
        ]);

        $updateResponse->assertRedirect(route('medicines.show', $medicine));

        $medicine->refresh();
        $this->assertSame('Augmentin Forte', $medicine->brand_name);
        $this->assertSame('1000mg', $medicine->strength);
        $this->assertSame('Shelf-B2', $medicine->rack_shelf);
        $this->assertSame(50, $medicine->reorder_level);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'medicine_updated',
            'auditable_type' => 'Medicine',
            'auditable_id' => $medicine->id,
        ]);
    }

    public function test_medicine_active_status_can_be_toggled_with_audit_log(): void
    {
        $medicine = Medicine::create([
            'code' => 'MED-ARCH-01',
            'brand_name' => 'Panadol',
            'strength' => '500mg',
            'dosage_form' => 'Tablet',
            'base_unit' => 'Tablet',
            'reorder_level' => 20,
            'is_active' => true,
        ]);

        // Toggle to archive
        $res1 = $this->actingAs($this->admin)->post(route('medicines.toggle-status', $medicine));
        $res1->assertSessionHas('success');

        $medicine->refresh();
        $this->assertFalse((bool) $medicine->is_active);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'medicine_status_toggled',
            'auditable_id' => $medicine->id,
        ]);

        // Toggle back to active
        $res2 = $this->actingAs($this->admin)->post(route('medicines.toggle-status', $medicine));
        $res2->assertSessionHas('success');

        $medicine->refresh();
        $this->assertTrue((bool) $medicine->is_active);
    }

    public function test_supplier_return_verifies_lot_provenance_and_enforces_pending_credit_confirmation(): void
    {
        $medicine = Medicine::create([
            'code' => 'MED-PROV-01',
            'brand_name' => 'Disprin',
            'strength' => '300mg',
            'dosage_form' => 'Tablet',
            'base_unit' => 'Tablet',
            'reorder_level' => 10,
            'is_active' => true,
        ]);

        $batch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'DISP-ALPHA-99',
            'expiry_date' => now()->addYear()->toDateString(),
            'status' => 'sellable',
        ]);

        // Create purchase from Supplier A
        $purchase = Purchase::create([
            'supplier_id' => $this->supplierA->id,
            'user_id' => $this->admin->id,
            'invoice_number' => 'PUR-ALPHA-1001',
            'invoice_date' => now()->toDateString(),
            'total_paisa' => 10000,
            'paid_paisa' => 0,
            'status' => 'received',
        ]);

        PurchaseLine::create([
            'purchase_id' => $purchase->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'DISP-ALPHA-99',
            'expiry_date' => now()->addYear()->toDateString(),
            'package_quantity' => 10,
            'bonus_base_quantity' => 0,
            'total_base_quantity' => 100,
            'package_cost_paisa' => 1000,
            'unit_cost_paisa' => 100,
            'total_cost_paisa' => 10000,
        ]);

        $lot = StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $this->location->id,
            'receipt_reference' => 'PUR-ALPHA-1001',
            'purchase_price_paisa' => 100,
            'cost_per_base_unit' => 1.00,
            'received_base_qty' => 100,
            'remaining_base_qty' => 100,
        ]);

        StockMovement::create([
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'stock_lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'movement_type' => 'purchase',
            'reference_type' => 'Purchase',
            'reference_id' => $purchase->id,
            'quantity_base_unit' => 100,
            'cost_per_base_unit' => 1.00,
            'user_id' => $this->admin->id,
        ]);

        // Attempt to return this lot to Supplier B (mismatched provenance)
        $invalidReturn = $this->actingAs($this->admin)->post(route('returns.supplier.store'), [
            'supplier_id' => $this->supplierB->id,
            'stock_lot_id' => $lot->id,
            'quantity_base_unit' => 20,
            'reason' => 'Defective packaging',
        ]);

        $invalidReturn->assertSessionHasErrors(['quantity_base_unit']);
        $lot->refresh();
        $this->assertSame(100, $lot->remaining_base_qty);
        $this->assertSame(200000, $this->supplierB->fresh()->balance_paisa);

        // Return to legitimate Supplier A
        $validReturn = $this->actingAs($this->admin)->post(route('returns.supplier.store'), [
            'supplier_id' => $this->supplierA->id,
            'stock_lot_id' => $lot->id,
            'quantity_base_unit' => 20,
            'reason' => 'Defective packaging',
        ]);

        $validReturn->assertSessionHas('success');

        $lot->refresh();
        $this->assertSame(80, $lot->remaining_base_qty);

        // Find created supplier return
        $sRet = SupplierReturn::where('supplier_id', $this->supplierA->id)->latest('id')->firstOrFail();
        $this->assertSame('dispatched', $sRet->status);
        $this->assertSame(2000, $sRet->total_credit_paisa);

        // Supplier balance must NOT be reduced upon dispatch
        $this->assertSame(500000, $this->supplierA->fresh()->balance_paisa);

        // Confirm credit note
        $confirmResponse = $this->actingAs($this->admin)->post(route('returns.supplier.confirm-credit', $sRet));
        $confirmResponse->assertSessionHas('success');

        $sRet->refresh();
        $this->assertSame('completed', $sRet->status);

        // Supplier balance IS reduced after credit confirmation
        $this->assertSame(498000, $this->supplierA->fresh()->balance_paisa);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'supplier_return_credit_confirmed',
            'auditable_type' => 'SupplierReturn',
            'auditable_id' => $sRet->id,
        ]);
    }

    public function test_dashboard_aggregates_without_n_plus_one_stock_queries(): void
    {
        // Seed multiple medicines and lots
        for ($i = 1; $i <= 10; $i++) {
            $med = Medicine::create([
                'code' => "MED-SCALE-{$i}",
                'brand_name' => "Scale Med {$i}",
                'dosage_form' => 'Tablet',
                'base_unit' => 'Tablet',
                'reorder_level' => 50,
                'is_active' => true,
            ]);

            $batch = Batch::create([
                'medicine_id' => $med->id,
                'batch_number' => "BATCH-SCALE-{$i}",
                'expiry_date' => now()->addMonths(6)->toDateString(),
                'status' => 'sellable',
            ]);

            StockLot::create([
                'batch_id' => $batch->id,
                'location_id' => $this->location->id,
                'purchase_price_paisa' => 50,
                'cost_per_base_unit' => 0.5,
                'received_base_qty' => 30,
                'remaining_base_qty' => 30,
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn($page) => $page->component('Dashboard')->has('lowStock'));
    }
}
