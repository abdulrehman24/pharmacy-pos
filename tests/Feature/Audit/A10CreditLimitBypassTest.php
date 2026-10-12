<?php

namespace Tests\Feature\Audit;

use App\Actions\Sales\FinalizeSale;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Shift;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class A10CreditLimitBypassTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Counter $counter;
    protected Shift $shift;
    protected Medicine $medicine;
    protected MedicinePackage $package;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->counter = Counter::create(['name' => 'Counter 1', 'code' => 'C-01', 'is_active' => true]);

        $this->shift = Shift::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->cashier->id,
            'opening_float_paisa' => 500000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $loc = Location::create(['name' => 'Main', 'code' => 'LOC-01', 'is_default' => true]);
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

        // Each tablet is PKR 100 = 10,000 paisa
        $this->package = MedicinePackage::create([
            'medicine_id' => $this->medicine->id,
            'name' => 'Tablet',
            'base_quantity' => 1,
            'default_sell_price_paisa' => 10000,
            'is_default_sale' => true,
        ]);

        $batch = Batch::create([
            'medicine_id' => $this->medicine->id,
            'batch_number' => 'B001',
            'expiry_date' => now()->addYear(),
            'status' => 'sellable',
        ]);

        StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $loc->id,
            'initial_base_qty' => 100,
            'remaining_base_qty' => 100,
            'cost_per_base_unit' => 50.00,
        ]);

        // Customer with credit limit of PKR 150 (15,000 paisa), current balance 0
        $this->customer = Customer::create([
            'code' => 'CUST-001',
            'name' => 'Test Patient',
            'phone' => '03001234567',
            'credit_limit_paisa' => 15000,
            'balance_paisa' => 0,
            'is_active' => true,
        ]);
    }

    public function test_partial_cash_sale_exceeding_credit_limit_is_rejected(): void
    {
        $finalizeSale = app(FinalizeSale::class);

        // Sale total = 2 tablets * PKR 100 = PKR 200 (20,000 paisa)
        // Customer credit limit is PKR 150 (15,000 paisa)
        // Cash payment received = PKR 10 (1,000 paisa), remaining debt = PKR 190 (19,000 paisa)
        // This exceeds credit limit of 15,000 paisa and must be rejected!
        $payload = [
            'idempotency_key' => 'credit-bypass-1',
            'shift_id' => $this->shift->id,
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'received_paisa' => 1000,
            'items' => [
                [
                    'medicine_id' => $this->medicine->id,
                    'medicine_package_id' => $this->package->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $finalizeSale->execute($payload, $this->cashier);
    }

    public function test_partial_payment_with_inactive_customer_is_rejected(): void
    {
        $finalizeSale = app(FinalizeSale::class);
        $this->customer->update(['is_active' => false]);

        $payload = [
            'idempotency_key' => 'credit-inactive-cust',
            'shift_id' => $this->shift->id,
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'received_paisa' => 5000, // PKR 50 paid, PKR 150 debt
            'items' => [
                [
                    'medicine_id' => $this->medicine->id,
                    'medicine_package_id' => $this->package->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $finalizeSale->execute($payload, $this->cashier);
    }
}
