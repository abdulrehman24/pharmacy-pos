<?php

namespace Tests\Feature\Audit;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A24TimezoneAndOfflineAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_assets_contain_no_external_cdn_references(): void
    {
        $bladePath = resource_path('views/app.blade.php');
        $this->assertFileExists($bladePath);

        $content = file_get_contents($bladePath);

        // Must not reference fonts.bunny.net or external CDNs
        $this->assertStringNotContainsString('fonts.bunny.net', $content, 'Application layout must not reference fonts.bunny.net');
        $this->assertStringNotContainsString('fonts.googleapis.com', $content, 'Application layout must not reference Google Fonts');
    }

    public function test_application_timezone_is_configured_to_asia_karachi(): void
    {
        $this->assertEquals('Asia/Karachi', config('app.timezone'));
    }

    public function test_midnight_boundary_consistency_under_karachi_timezone(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $counter = \App\Models\Counter::create(['name' => 'Counter 1', 'code' => 'C1', 'is_active' => true]);

        $shift = Shift::create([
            'user_id' => $cashier->id,
            'counter_id' => $counter->id,
            'opening_float_paisa' => 50000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $category = Category::create(['name' => 'General', 'slug' => 'general']);
        $medicine = Medicine::create([
            'code' => 'MED-MID-01',
            'brand_name' => 'Midazolam 5mg',
            'generic_name' => 'Midazolam',
            'dosage_form' => 'injection',
            'base_unit' => 'ampoule',
            'category_id' => $category->id,
            'prescription_required' => false,
            'is_active' => true,
        ]);

        // Transaction recorded at 23:55 on 2026-10-12 in Karachi
        $saleDate = Carbon::parse('2026-10-12 23:55:00', 'Asia/Karachi');
        Carbon::setTestNow($saleDate);

        $sale = Sale::create([
            'invoice_number' => 'INV-20261012-MIDNIGHT',
            'idempotency_key' => 'idemp-midnight-01',
            'shift_id' => $shift->id,
            'counter_id' => $counter->id,
            'cashier_id' => $cashier->id,
            'subtotal_paisa' => 15000,
            'discount_paisa' => 0,
            'tax_paisa' => 0,
            'total_paisa' => 15000,
            'received_paisa' => 15000,
            'change_paisa' => 0,
            'balance_paisa' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'posted',
        ]);

        // Query for today (2026-10-12)
        $startOfDay = Carbon::parse('2026-10-12', 'Asia/Karachi')->startOfDay();
        $endOfDay = Carbon::parse('2026-10-12', 'Asia/Karachi')->endOfDay();

        $matchingSales = Sale::whereBetween('created_at', [$startOfDay, $endOfDay])->get();
        $this->assertTrue($matchingSales->contains('id', $sale->id), 'Sale at 23:55 PKT must fall within 2026-10-12 date range');

        // Next day (2026-10-13) query should NOT contain this sale
        $nextDayStart = Carbon::parse('2026-10-13', 'Asia/Karachi')->startOfDay();
        $nextDayEnd = Carbon::parse('2026-10-13', 'Asia/Karachi')->endOfDay();

        $nextDaySales = Sale::whereBetween('created_at', [$nextDayStart, $nextDayEnd])->get();
        $this->assertFalse($nextDaySales->contains('id', $sale->id), 'Sale at 23:55 PKT on Oct 12 must not fall into Oct 13');

        Carbon::setTestNow();
    }
}
