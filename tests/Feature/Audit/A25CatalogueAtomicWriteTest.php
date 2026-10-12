<?php

namespace Tests\Feature\Audit;

use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A25CatalogueAtomicWriteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_duplicate_in_request_barcodes_are_rejected_and_writes_roll_back(): void
    {
        $payload = [
            'code' => 'MED-ATOMIC-01',
            'brand_name' => 'Atomic Test Med',
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
            'reorder_level' => 10,
            'packages' => [
                [
                    'name' => 'Strip of 10',
                    'base_quantity' => 10,
                    'default_sell_price_paisa' => 5000,
                    'barcode' => 'BARCODE-DUPLICATE-01',
                    'is_default_sale' => true,
                ],
                [
                    'name' => 'Box of 100',
                    'base_quantity' => 100,
                    'default_sell_price_paisa' => 45000,
                    'barcode' => '  BARCODE-DUPLICATE-01  ', // Duplicate after trimming
                    'is_default_sale' => false,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('medicines.store'), $payload);

        // Should return validation error for duplicate barcode
        $response->assertSessionHasErrors();

        // Must roll back atomically: no orphaned medicine should exist
        $this->assertDatabaseMissing('medicines', ['code' => 'MED-ATOMIC-01']);
    }

    public function test_omitted_optional_fields_succeeds_cleanly_with_safe_defaults(): void
    {
        // Minimal valid payload omitting all nullable keys
        $payload = [
            'code' => 'MED-MINIMAL-01',
            'brand_name' => 'Minimal Medicine',
            'dosage_form' => 'capsule',
            'base_unit' => 'capsule',
            'reorder_level' => 5,
            'packages' => [
                [
                    'name' => 'Single Capsule',
                    'base_quantity' => 1,
                    'default_sell_price_paisa' => 200,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('medicines.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $medicine = Medicine::where('code', 'MED-MINIMAL-01')->first();
        $this->assertNotNull($medicine);
        $this->assertNull($medicine->generic_name);
        $this->assertNull($medicine->strength);
        $this->assertNull($medicine->notes);
        $this->assertNull($medicine->rack_shelf);
        $this->assertEquals(5, $medicine->reorder_level);
    }
}
