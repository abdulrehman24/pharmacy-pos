<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'pharmacist',
            'is_active' => true,
        ]);
    }

    public function test_can_quick_store_category_via_ajax(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('categories.quick-store'), [
            'name' => 'Cardiovascular',
            'description' => 'Heart and blood pressure medicines',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'category' => [
                    'name' => 'Cardiovascular',
                    'slug' => 'cardiovascular',
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Cardiovascular',
            'slug' => 'cardiovascular',
        ]);
    }

    public function test_can_quick_store_manufacturer_via_ajax(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('manufacturers.quick-store'), [
            'name' => 'Hilton Pharma',
            'contact_info' => 'Karachi, Pakistan',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'manufacturer' => [
                    'name' => 'Hilton Pharma',
                    'contact_info' => 'Karachi, Pakistan',
                ],
            ]);

        $this->assertDatabaseHas('manufacturers', [
            'name' => 'Hilton Pharma',
        ]);
    }

    public function test_can_delete_unused_category(): void
    {
        $category = Category::create([
            'name' => 'Dermatologicals',
            'slug' => 'dermatologicals',
        ]);

        $response = $this->actingAs($this->user)->deleteJson(route('categories.destroy', $category));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_medicines(): void
    {
        $category = Category::create([
            'name' => 'Antipyretics',
            'slug' => 'antipyretics',
        ]);

        \App\Models\Medicine::create([
            'code' => 'MED-TEST-001',
            'brand_name' => 'Test Panadol',
            'category_id' => $category->id,
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
        ]);

        $response = $this->actingAs($this->user)->deleteJson(route('categories.destroy', $category));

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_can_delete_unused_manufacturer(): void
    {
        $mfg = Manufacturer::create([
            'name' => 'Unused Manufacturer',
        ]);

        $response = $this->actingAs($this->user)->deleteJson(route('manufacturers.destroy', $mfg));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('manufacturers', ['id' => $mfg->id]);
    }
}
