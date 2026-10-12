<?php

namespace Tests\Feature\Audit;

use App\Actions\Purchases\PostPurchase;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A15PurchaseDiscountStockCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_invoice_discount_reduces_stock_lot_unit_cost(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $location = Location::create(['name' => 'Main', 'code' => 'MAIN', 'is_default' => true]);
        $supplier = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Supplier A',
            'phone' => '03001234567',
            'balance_paisa' => 0,
            'is_active' => true,
        ]);

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

        $package = MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Box of 100',
            'base_quantity' => 100,
            'default_sell_price_paisa' => 150000,
        ]);

        // Purchase: 1 box (100 tablets). Gross package cost = PKR 1,000 (100,000 paisa).
        // Invoice discount = PKR 100 (10,000 paisa). Net invoice cost = PKR 900 (90,000 paisa).
        // Net cost per tablet base unit should be: 90,000 / 100 = 900 paisa (PKR 9.00).
        $postPurchase = app(PostPurchase::class);

        $payload = [
            'supplier_id' => $supplier->id,
            'location_id' => $location->id,
            'supplier_invoice_number' => 'INV-DISC-01',
            'invoice_date' => now()->toDateString(),
            'discount_paisa' => 10000, // PKR 100 discount
            'tax_paisa' => 0,
            'paid_paisa' => 90000,
            'payment_method' => 'cash',
            'lines' => [
                [
                    'medicine_id' => $medicine->id,
                    'medicine_package_id' => $package->id,
                    'batch_number' => 'BATCH-DISC-01',
                    'expiry_date' => now()->addYear()->toDateString(),
                    'package_quantity' => 1,
                    'package_cost_paisa' => 100000,
                ],
            ],
        ];

        $purchase = $postPurchase->execute($payload, $admin);

        $lot = StockLot::where('receipt_reference', $purchase->invoice_number)->first();
        $this->assertNotNull($lot);

        // Cost per base unit in rupees must be 9.00 (not 10.00)
        $this->assertEquals(9.00, (float) $lot->cost_per_base_unit, 'Stock lot unit cost must reflect net discounted cost');
        $this->assertEquals(900, $lot->purchase_price_paisa);

        // Stock movement reference_id must point to the Purchase ID
        $movement = StockMovement::where('reference_type', 'Purchase')
            ->where('stock_lot_id', $lot->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals($purchase->id, $movement->reference_id, 'Stock movement reference_id must be the purchase id');
    }
}
