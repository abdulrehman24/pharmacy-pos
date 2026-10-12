<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_update_phone_number(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Updated Name',
                'email' => $user->email,
                'phone' => '0300-9876543',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/profile');
        $this->assertSame('0300-9876543', $user->refresh()->phone);
    }

    public function test_user_can_update_quick_pin(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->put('/profile/pin', [
                'pin_code' => '5678',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/profile');
        $this->assertSame('5678', $user->refresh()->pin_code);
    }

    public function test_invalid_quick_pin_is_rejected(): void
    {
        $user = User::factory()->create();

        // 3 digits
        $response = $this
            ->actingAs($user)
            ->put('/profile/pin', [
                'pin_code' => '123',
            ]);

        $response->assertSessionHasErrors(['pin_code']);

        // non-digits
        $response2 = $this
            ->actingAs($user)
            ->put('/profile/pin', [
                'pin_code' => 'abcd',
            ]);

        $response2->assertSessionHasErrors(['pin_code']);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
