<?php

namespace Tests\Feature\Audit;

use App\Actions\Returns\ProcessCustomerReturn;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Sale;
use App\Models\SaleAllocation;
use App\Models\SaleLine;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A08ReturnDebtReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_refund_cannot_be_given_on_unpaid_credit_sale(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'Ali Khan',
            'code' => 'CUST-001',
            'credit_limit_paisa' => 500000,
            'balance_paisa' => 10000, // 100 PKR owed
        ]);

        $location = Location::create(['name' => 'Main', 'code' => 'MAIN-01']);
        $medicine = Medicine::create(['name' => 'Flagyl', 'brand_name' => 'Flagyl 400mg', 'code' => 'MED-003', 'base_unit' => 'tablet']);
        $batch = Batch::create(['medicine_id' => $medicine->id, 'batch_number' => 'B003', 'expiry_date' => now()->addYear()]);
        $pkg = MedicinePackage::create([
            'medicine_id' => $medicine->id,
            'name' => 'Strip',
            'base_quantity' => 10,
            'sell_price_paisa' => 10000,
        ]);

        // Unpaid credit sale (customer paid 0 cash, owes 100 PKR)
        $sale = Sale::create([
            'invoice_number' => 'INV-CRED-001',
            'idempotency_key' => 'idemp-cred-001',
            'cashier_id' => $cashier->id,
            'customer_id' => $customer->id,
            'subtotal_paisa' => 10000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 10000,
            'received_paisa' => 0,
            'balance_paisa' => 10000,
            'payment_method' => 'credit',
            'payment_status' => 'unpaid',
            'status' => 'posted',
        ]);

        $line = SaleLine::create([
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'medicine_package_id' => $pkg->id,
            'medicine_name' => 'Flagyl 400mg',
            'package_name' => 'Strip',
            'conversion_factor' => 10,
            'quantity' => 1,
            'total_base_quantity' => 10,
            'unit_price_paisa' => 10000,
            'subtotal_paisa' => 10000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 10000,
        ]);

        $lot = StockLot::create([
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'location_id' => $location->id,
            'quantity_base_unit' => 10,
            'cost_per_base_unit' => 5.0,
            'status' => 'active',
        ]);

        SaleAllocation::create([
            'sale_id' => $sale->id,
            'sale_line_id' => $line->id,
            'stock_lot_id' => $lot->id,
            'base_quantity' => 10,
            'cost_per_base_unit_paisa' => 500.0,
        ]);

        $processReturn = app(ProcessCustomerReturn::class);

        // Attempting a cash refund on this unpaid credit sale must be rejected!
        $this->expectException(\InvalidArgumentException::class);

        $processReturn->execute([
            'sale_id' => $sale->id,
            'items' => [
                ['sale_line_id' => $line->id, 'quantity' => 1, 'disposition' => 'restock'],
            ],
            'refund_method' => 'cash',
        ], $cashier);
    }
}
