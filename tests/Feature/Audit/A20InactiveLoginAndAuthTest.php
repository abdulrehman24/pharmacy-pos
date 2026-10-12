<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A20InactiveLoginAndAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@pharmacy.local',
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'disabled@pharmacy.local',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_deactivated_user_session_is_terminated_on_subsequent_request(): void
    {
        $user = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        // Login while active
        $this->actingAs($user);

        // Deactivate user in database
        $user->update(['is_active' => false]);

        // Subsequent request must be rejected / logged out
        $response = $this->get('/pos');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
