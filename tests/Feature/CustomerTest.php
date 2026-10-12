<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_quick_store_customer_via_ajax(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($user)
            ->postJson(route('customers.quick-store'), [
                'name' => 'Chaudhry Akram',
                'phone' => '0300-9876543',
                'credit_limit_paisa' => 1500000,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('customer.name', 'Chaudhry Akram')
            ->assertJsonPath('customer.phone', '0300-9876543')
            ->assertJsonPath('customer.credit_limit_paisa', 1500000);

        $this->assertDatabaseHas('customers', [
            'name' => 'Chaudhry Akram',
            'phone' => '0300-9876543',
            'credit_limit_paisa' => 1500000,
        ]);
    }
}
