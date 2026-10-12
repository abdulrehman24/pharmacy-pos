<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class QuickPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_lists_active_staff_with_pins(): void
    {
        $userWithPin = User::factory()->create([
            'name' => 'Cashier One',
            'role' => 'cashier',
            'is_active' => true,
            'pin_code' => '4567',
        ]);

        $userWithoutPin = User::factory()->create([
            'name' => 'No Pin User',
            'role' => 'cashier',
            'is_active' => true,
            'pin_code' => null,
        ]);

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Auth/Login')
                ->has('staff', 1)
                ->where('staff.0.name', 'Cashier One')
        );
    }

    public function test_user_can_authenticate_using_valid_pin(): void
    {
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
            'pin_code' => '1234',
        ]);

        $response = $this->post('/login/pin', [
            'user_id' => $cashier->id,
            'pin_code' => '1234',
        ]);

        $this->assertAuthenticatedAs($cashier);
        $response->assertRedirect(route('pos.index', absolute: false));
    }

    public function test_user_cannot_authenticate_with_invalid_pin(): void
    {
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
            'pin_code' => '1234',
        ]);

        $response = $this->from('/login')->post('/login/pin', [
            'user_id' => $cashier->id,
            'pin_code' => '9999',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['pin_code']);
    }

    public function test_pin_login_is_rate_limited(): void
    {
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
            'pin_code' => '1234',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login/pin', [
                'user_id' => $cashier->id,
                'pin_code' => '0000',
            ]);
        }

        // 6th attempt should be blocked with rate limiting error
        $response = $this->post('/login/pin', [
            'user_id' => $cashier->id,
            'pin_code' => '1234',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['pin_code']);
    }

    public function test_cashier_can_switch_user_at_pos_terminal(): void
    {
        $cashier1 = User::factory()->create([
            'name' => 'Cashier One',
            'role' => 'cashier',
            'is_active' => true,
            'pin_code' => '1111',
        ]);

        $cashier2 = User::factory()->create([
            'name' => 'Cashier Two',
            'role' => 'cashier',
            'is_active' => true,
            'pin_code' => '2222',
        ]);

        $response = $this->actingAs($cashier1)->postJson('/pos/switch-cashier', [
            'user_id' => $cashier2->id,
            'pin_code' => '2222',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $cashier2->id,
                    'name' => 'Cashier Two',
                ],
            ]);

        $this->assertAuthenticatedAs($cashier2);
    }

    public function test_pos_cashier_switch_rejects_wrong_pin(): void
    {
        $cashier1 = User::factory()->create([
            'name' => 'Cashier One',
            'role' => 'cashier',
            'is_active' => true,
            'pin_code' => '1111',
        ]);

        $cashier2 = User::factory()->create([
            'name' => 'Cashier Two',
            'role' => 'cashier',
            'is_active' => true,
            'pin_code' => '2222',
        ]);

        $response = $this->actingAs($cashier1)->postJson('/pos/switch-cashier', [
            'user_id' => $cashier2->id,
            'pin_code' => '9999',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertAuthenticatedAs($cashier1);
    }
}
