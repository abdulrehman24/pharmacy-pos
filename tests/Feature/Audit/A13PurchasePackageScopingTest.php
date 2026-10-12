<?php

namespace Tests\Feature\Audit;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Purchase;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A13PurchasePackageScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_rejects_package_belonging_to_another_medicine(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        \App\Models\Location::create(['name' => 'Main', 'code' => 'MAIN', 'is_default' => true]);
        $supplier = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Supplier A',
            'phone' => '03001234567',
            'balance_paisa' => 0,
            'is_active' => true,
        ]);

        $cat = Category::create(['name' => 'General', 'slug' => 'general']);
        $man = Manufacturer::create(['name' => 'GSK']);

        $medicineA = Medicine::create([
            'code' => 'MED-A',
            'category_id' => $cat->id,
            'manufacturer_id' => $man->id,
            'brand_name' => 'Panadol',
            'generic_name' => 'Paracetamol',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        $medicineB = Medicine::create([
            'code' => 'MED-B',
            'category_id' => $cat->id,
            'manufacturer_id' => $man->id,
            'brand_name' => 'Augmentin',
            'generic_name' => 'Co-amoxiclav',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        // Package for Medicine B with huge conversion factor
        $packageB = MedicinePackage::create([
            'medicine_id' => $medicineB->id,
            'name' => 'Carton of 500',
            'base_quantity' => 500,
            'default_sell_price_paisa' => 100000,
        ]);

        // Attempt to purchase Medicine A using Medicine B's package
        $response = $this->actingAs($admin)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'supplier_invoice_number' => 'SUP-INV-999',
            'invoice_date' => now()->toDateString(),
            'payment_method' => 'credit',
            'lines' => [
                [
                    'medicine_id' => $medicineA->id,
                    'medicine_package_id' => $packageB->id, // Mismatched foreign package!
                    'batch_number' => 'BATCH-TEST',
                    'expiry_date' => now()->addYear()->toDateString(),
                    'package_quantity' => 1,
                    'package_cost_paisa' => 5000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertEquals(0, Purchase::count());
        $this->assertEquals(0, StockLot::count());
    }
}
