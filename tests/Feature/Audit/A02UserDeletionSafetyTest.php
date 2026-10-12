<?php

namespace Tests\Feature\Audit;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A02UserDeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_self_deletion_does_not_cascade_and_destroy_posted_sales(): void
    {
        $cashier = User::factory()->create([
            'password' => bcrypt('password123'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-TEST-0001',
            'idempotency_key' => 'key-test-0001',
            'cashier_id' => $cashier->id,
            'subtotal_paisa' => 50000,
            'total_paisa' => 50000,
            'received_paisa' => 50000,
            'status' => 'posted',
        ]);

        $response = $this->actingAs($cashier)->delete('/profile', [
            'password' => 'password123',
        ]);

        // Self-deletion of accounts with financial records must be rejected (403),
        // and under no circumstance should the posted sale be cascade-deleted!
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $response->assertForbidden();
    }
}
