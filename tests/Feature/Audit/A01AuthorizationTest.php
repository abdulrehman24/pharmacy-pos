<?php

namespace Tests\Feature\Audit;

use App\Models\StockCount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A01AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_cannot_create_admin_user(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $response = $this->actingAs($cashier)->post(route('settings.user.store'), [
            'name' => 'Malicious Admin',
            'email' => 'malicious@admin.com',
            'password' => 'secret123',
            'role' => 'admin',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'malicious@admin.com']);
    }

    public function test_cashier_cannot_create_backup(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $response = $this->actingAs($cashier)->post(route('settings.backup.create'));

        $response->assertForbidden();
    }

    public function test_cashier_cannot_approve_stock_count(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $location = \App\Models\Location::create(['name' => 'Main', 'code' => 'MAIN-01']);
        $count = StockCount::create([
            'count_number' => 'SC-TEST-01',
            'location_id' => $location->id,
            'status' => 'draft',
            'conducted_by' => $cashier->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($cashier)->post(route('inventory.counts.approve', $count));

        $response->assertForbidden();
    }

    public function test_cashier_cannot_access_settings_page(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $response = $this->actingAs($cashier)->get(route('settings.index'));

        $response->assertForbidden();
    }

    public function test_cashier_cannot_access_reports_page(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $response = $this->actingAs($cashier)->get(route('reports.index'));

        $response->assertForbidden();
    }

    public function test_public_registration_is_blocked_when_system_is_initialized(): void
    {
        // System already has staff
        User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->post('/register', [
            'name' => 'Stranger',
            'email' => 'stranger@external.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'stranger@external.com']);
    }
}
