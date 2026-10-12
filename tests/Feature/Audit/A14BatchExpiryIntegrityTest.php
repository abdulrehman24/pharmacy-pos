<?php

namespace Tests\Feature\Audit;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\StockLot;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class A14BatchExpiryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_same_batch_with_different_expiry_does_not_overwrite_existing_batch_expiry(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $location = Location::create(['name' => 'Main', 'code' => 'MAIN', 'is_default' => true]);
        $cat = Category::create(['name' => 'General', 'slug' => 'general']);
        $man = Manufacturer::create(['name' => 'GSK']);

        $medicine = Medicine::create([
            'code' => 'MED-01',
            'category_id' => $cat->id,
            'manufacturer_id' => $man->id,
            'brand_name' => 'Panadol 500mg',
            'generic_name' => 'Paracetamol',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        // Original batch created expiring on 2026-06-01
        $originalExpiry = '2026-06-01';
        $batch = Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-EXP-01',
            'expiry_date' => $originalExpiry,
            'status' => 'sellable',
        ]);

        $stockLot = StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $location->id,
            'initial_base_qty' => 50,
            'remaining_base_qty' => 50,
            'cost_per_base_unit' => 3.00,
        ]);

        $inventoryService = app(InventoryService::class);

        // Later receipt attempts to receive BATCH-EXP-01 with a conflicting expiry date '2027-12-31'
        $conflictingExpiry = '2027-12-31';

        $this->expectException(InvalidArgumentException::class);
        $inventoryService->receiveStock(
            $medicine->id,
            'BATCH-EXP-01',
            $conflictingExpiry,
            100,
            300.0,
            $location->id,
            $user->id,
            'PUR-002',
            'Second purchase'
        );

        // Verify the original batch expiry was NOT modified
        $this->assertEquals($originalExpiry, $batch->fresh()->expiry_date->format('Y-m-d'));
    }
}
