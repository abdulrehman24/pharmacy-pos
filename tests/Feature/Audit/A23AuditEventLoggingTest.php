<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class A23AuditEventLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_domain_events_generate_audit_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // 1. Staff creation audit
        $this->actingAs($admin)->post(route('settings.user.store'), [
            'name' => 'Pharmacist Bilal',
            'email' => 'bilal@pharmacist.com',
            'password' => 'secret123',
            'role' => 'pharmacist',
        ])->assertRedirect();

        $this->assertTrue(
            AuditEvent::where('event_type', 'user_created')->exists(),
            'Creating staff must generate user_created audit event'
        );

        // 2. Settings update audit
        $this->actingAs($admin)->post(route('settings.update'), [
            'shop_name' => 'Audited Pharmacy Name',
            'shop_address' => 'Mall Road, Lahore',
            'shop_phone' => '042-111-222-333',
            'currency_symbol' => 'PKR',
            'tax_percentage' => 0,
            'receipt_footer' => 'Audited Footer Note',
            'expiry_alert_days' => 90,
            'default_paper_width' => 80,
        ])->assertRedirect();

        $this->assertTrue(
            AuditEvent::where('event_type', 'settings_updated')->exists(),
            'Updating system settings must generate settings_updated audit event'
        );

        // 3. Stock count approval audit
        $category = Category::create(['name' => 'Pain', 'slug' => 'pain']);
        $medicine = Medicine::create([
            'code' => 'MED-AUD-01',
            'brand_name' => 'Brufen 400mg',
            'generic_name' => 'Ibuprofen',
            'dosage_form' => 'tablet',
            'base_unit' => 'tablet',
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        $loc = Location::create(['name' => 'Shelf A', 'code' => 'SHELF-A', 'type' => 'shelf', 'is_sellable' => true]);
        $batch = \App\Models\Batch::create([
            'medicine_id' => $medicine->id,
            'batch_number' => 'B-AUD-1',
            'expiry_date' => now()->addYear(),
            'status' => 'sellable',
        ]);
        $lot = StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $loc->id,
            'initial_base_qty' => 50,
            'remaining_base_qty' => 50,
            'cost_per_base_unit' => 2.00,
        ]);

        $count = StockCount::create([
            'count_number' => 'SC-AUD-001',
            'location_id' => $loc->id,
            'conducted_by' => $admin->id,
            'status' => 'draft',
        ]);
        StockCountLine::create([
            'stock_count_id' => $count->id,
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'stock_lot_id' => $lot->id,
            'system_quantity' => 50,
            'counted_quantity' => 52,
            'variance_quantity' => 2,
            'cost_per_base_unit' => 2.00,
        ]);

        $this->actingAs($admin)->post(route('inventory.counts.approve', $count))
            ->assertRedirect();

        $this->assertTrue(
            AuditEvent::where('event_type', 'stock_count_approved')->exists(),
            'Approving stock count must generate stock_count_approved audit event'
        );

        // 4. Stock write-off audit
        $this->actingAs($admin)->post(route('inventory.write-off'), [
            'stock_lot_id' => $lot->id,
            'quantity' => 5,
            'type' => 'damaged',
            'reason' => 'Dropped box during audit',
        ])->assertRedirect();

        $this->assertTrue(
            AuditEvent::where('event_type', 'stock_written_off')->exists(),
            'Writing off stock must generate stock_written_off audit event'
        );
    }
}
