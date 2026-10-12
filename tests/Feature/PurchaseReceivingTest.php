<?php

namespace Tests\Feature;

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

class PurchaseReceivingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Location $location;
    protected Supplier $supplier;
    protected Category $category;
    protected Manufacturer $manufacturer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'inventory',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'is_default' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUPP-001',
            'name' => 'Ali Medical Distributors',
            'phone' => '03211234567',
            'balance_paisa' => 0,
        ]);

        $this->category = Category::create([
            'name' => 'Antibiotics',
            'slug' => 'antibiotics',
        ]);

        $this->manufacturer = Manufacturer::create([
            'name' => 'Getz Pharma',
        ]);
    }

    public function test_purchase_intake_allocates_bonus_quantities_to_reduce_unit_cost(): void
    {
        $medicine = Medicine::create([
            'code' => 'MED-GET-500',
            'category_id' => $this->category->id,
            'manufacturer_id' => $this->manufacturer->id,
            'brand_name' => 'Ciproget 500mg',
            'generic_name' => 'Ciprofloxacin',
            'strength' => '500mg',
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        $boxPackage = MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Box of 10',
            'base_quantity' => 10, // 10 tablets per box
            'default_sell_price_paisa' => 120000,
            'default_purchase_price_paisa' => 100000, // PKR 1,000 per box
            'is_default_sale' => true,
            'is_default_purchase' => true,
        ]);

        $expiryDate = now()->addMonths(18)->toDateString();

        // Buy 10 boxes (100 tablets) @ PKR 1,000/box = PKR 10,000 (1,000,000 paisa)
        // Supplier gives 25 bonus tablets (free)
        // Total base qty received = 125 tablets.
        // Effective unit cost per tablet = 1,000,000 paisa / 125 = 8,000 paisa = PKR 80.00
        $postPurchase = app(PostPurchase::class);
        $purchase = $postPurchase->execute([
            'supplier_id' => $this->supplier->id,
            'supplier_invoice_number' => 'INV-SUP-9988',
            'location_id' => $this->location->id,
            'paid_paisa' => 400000, // Paid PKR 4,000, PKR 6,000 unpaid
            'lines' => [
                [
                    'medicine_id' => $medicine->id,
                    'medicine_package_id' => $boxPackage->id,
                    'batch_number' => 'CIP-BATCH-B1',
                    'expiry_date' => $expiryDate,
                    'package_quantity' => 10,
                    'bonus_base_quantity' => 25,
                    'package_cost_paisa' => 100000,
                ],
            ],
        ], $this->user);

        $this->assertEquals(1000000, $purchase->subtotal_paisa);
        $this->assertEquals(1000000, $purchase->total_paisa);
        $this->assertEquals(400000, $purchase->paid_paisa);

        // Supplier balance should have increased by unpaid amount (PKR 6,000 = 600,000 paisa)
        $this->supplier->refresh();
        $this->assertEquals(600000, $this->supplier->balance_paisa);

        // Purchase line verification
        $line = $purchase->lines->first();
        $this->assertEquals(125, $line->total_base_quantity);
        $this->assertEquals(8000, $line->unit_cost_paisa);

        // Stock lot verification
        $lot = StockLot::where('location_id', $this->location->id)->first();
        $this->assertNotNull($lot);
        $this->assertEquals(125, $lot->remaining_base_qty);
        $this->assertEquals(125, $lot->received_base_qty);
        $this->assertEquals(80.0, (float) $lot->cost_per_base_unit);

        $movement = StockMovement::where('movement_type', 'purchase')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(125, $movement->quantity_base_unit);
        $this->assertEquals(80.0, (float) $movement->cost_per_base_unit);
    }
}
