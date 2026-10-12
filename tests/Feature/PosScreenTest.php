<?php

namespace Tests\Feature;

use App\Models\Counter;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_screen_renders_with_active_shift_and_sales(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);
        $counter = Counter::create(['code' => 'POS-01', 'name' => 'Counter 1', 'is_active' => true]);

        $shift = Shift::create([
            'user_id' => $user->id,
            'counter_id' => $counter->id,
            'opened_at' => now(),
            'opening_float_paisa' => 500000,
            'status' => 'open',
        ]);

        // Create a dummy sale under this shift
        Sale::create([
            'invoice_number' => 'INV-TEST-001',
            'idempotency_key' => 'IDEM-TEST-001',
            'shift_id' => $shift->id,
            'counter_id' => $counter->id,
            'cashier_id' => $user->id,
            'subtotal_paisa' => 100000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 100000,
            'received_paisa' => 100000,
            'change_paisa' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'posted',
        ]);

        $response = $this->actingAs($user)->get(route('pos.index'));

        $response->assertOk();
    }
}
