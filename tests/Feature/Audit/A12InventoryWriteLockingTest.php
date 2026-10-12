<?php

namespace Tests\Feature\Audit;

use App\Actions\Inventory\PostStockAdjustment;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WriteOff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A12InventoryWriteLockingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Location $location;
    protected Medicine $medicine;
    protected Batch $batch;
    protected StockLot $lot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->location = Location::create(['name' => 'Main', 'code' => 'LOC-01', 'is_default' => true]);
        $cat = Category::create(['name' => 'General', 'slug' => 'general']);
        $man = Manufacturer::create(['name' => 'GSK']);

        $this->medicine = Medicine::create([
            'code' => 'MED-01',
            'category_id' => $cat->id,
            'manufacturer_id' => $man->id,
            'brand_name' => 'Panadol 500mg',
            'generic_name' => 'Paracetamol',
            'base_unit' => 'tablet',
            'is_active' => true,
        ]);

        $this->batch = Batch::create([
            'medicine_id' => $this->medicine->id,
            'batch_number' => 'B001',
            'expiry_date' => now()->addYear(),
            'status' => 'sellable',
        ]);

        $this->lot = StockLot::create([
            'batch_id' => $this->batch->id,
            'location_id' => $this->location->id,
            'initial_base_qty' => 10,
            'remaining_base_qty' => 10,
            'cost_per_base_unit' => 5.00,
        ]);
    }

    public function test_write_off_cannot_exceed_available_quantity_and_updates_atomically(): void
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.write-off'), [
            'stock_lot_id' => $this->lot->id,
            'quantity' => 15, // Only 10 available
            'type' => 'damaged',
            'reason' => 'Broken bottles',
        ]);

        $response->assertSessionHasErrors(['quantity']);
        $this->assertEquals(10, $this->lot->fresh()->remaining_base_qty);
        $this->assertEquals(0, WriteOff::count());
    }

    public function test_stock_count_derives_system_quantity_server_side(): void
    {
        // Client maliciously or erroneously sends system_quantity = 50 while server has remaining_base_qty = 10
        // Counted is 8.
        // If server trusts client, variance is 8 - 50 = -42.
        // Server MUST derive system_quantity as 10, so variance is 8 - 10 = -2!
        $response = $this->actingAs($this->admin)->post(route('inventory.counts.store'), [
            'location_id' => $this->location->id,
            'notes' => 'Shelf audit',
            'lines' => [
                [
                    'medicine_id' => $this->medicine->id,
                    'batch_id' => $this->batch->id,
                    'stock_lot_id' => $this->lot->id,
                    'system_quantity' => 50, // Erroneous client input
                    'counted_quantity' => 8,
                    'reason' => 'Audit',
                ],
            ],
        ]);

        $response->assertRedirect(route('inventory.counts'));
        $line = StockCountLine::where('stock_lot_id', $this->lot->id)->first();
        $this->assertNotNull($line);
        $this->assertEquals(10, $line->system_quantity, 'System quantity must be derived server-side from actual stock lot');
        $this->assertEquals(-2, $line->variance_quantity, 'Variance quantity must be counted - actual server system quantity');
    }

    public function test_stock_adjustment_clamps_at_zero_and_records_actual_applied_delta_in_movement(): void
    {
        // Lot has 2 items left
        $this->lot->update(['remaining_base_qty' => 2]);

        $stockCount = StockCount::create([
            'count_number' => 'SC-TEST-001',
            'location_id' => $this->location->id,
            'conducted_by' => $this->admin->id,
            'status' => 'draft',
        ]);

        // Variance is -5 (e.g. counted 0 vs system 5, but lot now only has 2 left)
        StockCountLine::create([
            'stock_count_id' => $stockCount->id,
            'medicine_id' => $this->medicine->id,
            'batch_id' => $this->batch->id,
            'stock_lot_id' => $this->lot->id,
            'system_quantity' => 5,
            'counted_quantity' => 0,
            'variance_quantity' => -5,
            'cost_per_base_unit' => 5.00,
            'reason' => 'Audit loss',
        ]);

        $postAdjustment = app(PostStockAdjustment::class);
        $postAdjustment->execute($stockCount, $this->admin);

        // Physical lot clamped to 0
        $this->assertEquals(0, $this->lot->fresh()->remaining_base_qty);

        // Movement MUST record actual reduction of -2, NOT -5, to keep ledger in sync with lot balance
        $movement = StockMovement::where('reference_type', 'StockCount')
            ->where('reference_id', $stockCount->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(-2, $movement->quantity_base_unit, 'StockMovement quantity must match the actual applied adjustment delta');
    }
}
