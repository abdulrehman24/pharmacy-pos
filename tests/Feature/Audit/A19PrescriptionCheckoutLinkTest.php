<?php

namespace Tests\Feature\Audit;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DispensingRecord;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use App\Models\Shift;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class A19PrescriptionCheckoutLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private User $pharmacist;
    private Shift $shift;
    private Medicine $rxMedicine;
    private MedicinePackage $package;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->pharmacist = User::factory()->create(['role' => 'pharmacist', 'is_active' => true]);

        $counter = \App\Models\Counter::create(['name' => 'Counter 1', 'code' => 'C1', 'is_active' => true]);

        $this->shift = Shift::create([
            'user_id' => $this->cashier->id,
            'counter_id' => $counter->id,
            'opening_float_paisa' => 100000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $category = Category::create(['name' => 'Antibiotics', 'slug' => 'antibiotics']);
        $this->rxMedicine = Medicine::create([
            'code' => 'MED-AMOX-500',
            'brand_name' => 'Amoxicillin 500mg',
            'generic_name' => 'Amoxicillin',
            'strength' => '500mg',
            'dosage_form' => 'capsule',
            'base_unit' => 'capsule',
            'category_id' => $category->id,
            'prescription_required' => true,
            'is_narcotic' => false,
            'is_active' => true,
        ]);

        $this->package = MedicinePackage::create([
            'medicine_id' => $this->rxMedicine->id,
            'name' => 'Box of 20',
            'package_type' => 'box',
            'conversion_factor' => 20,
            'retail_price_paisa' => 50000,
            'is_default' => true,
            'is_active' => true,
        ]);

        $loc = \App\Models\Location::firstOrCreate(
            ['code' => 'MAIN-SHELF'],
            ['name' => 'Main Shelf', 'type' => 'shelf', 'is_sellable' => true]
        );

        $batch = \App\Models\Batch::create([
            'medicine_id' => $this->rxMedicine->id,
            'batch_number' => 'BATCH-RX-1',
            'expiry_date' => now()->addYear(),
            'status' => 'sellable',
        ]);

        StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $loc->id,
            'initial_base_qty' => 100,
            'remaining_base_qty' => 100,
            'cost_per_base_unit' => 15.00,
        ]);

        $this->customer = Customer::create([
            'name' => 'Patient Asad',
            'code' => 'CUST-002',
            'phone' => '03009876543',
            'is_active' => true,
        ]);
    }

    public function test_pharmacist_verification_sets_status_to_verified_not_dispensed(): void
    {
        $rx = Prescription::create([
            'prescription_number' => 'RX-VERIFY-001',
            'customer_id' => $this->customer->id,
            'patient_name' => 'Patient Asad',
            'doctor_name' => 'Dr. Khan',
            'prescription_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->pharmacist)->post(route('prescriptions.verify', $rx), [
            'instructions' => 'Take 1 capsule twice daily after meals.',
        ]);

        $response->assertSessionHasNoErrors();
        $rx->refresh();

        $this->assertEquals('verified', $rx->status, 'Prescription verification must set status to verified, not dispensed');
        $this->assertEquals($this->pharmacist->id, $rx->verified_by);
    }

    public function test_checkout_of_prescription_medicine_without_prescription_is_rejected(): void
    {
        $payload = [
            'idempotency_key' => 'idemp-rx-no-rx',
            'shift_id' => $this->shift->id,
            'payment_method' => 'cash',
            'received_paisa' => 50000,
            'items' => [
                [
                    'medicine_id' => $this->rxMedicine->id,
                    'medicine_package_id' => $this->package->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson(route('pos.checkout'), $payload);
        $response->assertStatus(422);
    }

    public function test_checkout_with_verified_prescription_dispenses_and_records_dispensing(): void
    {
        $rx = Prescription::create([
            'prescription_number' => 'RX-VERIFY-002',
            'customer_id' => $this->customer->id,
            'patient_name' => 'Patient Asad',
            'doctor_name' => 'Dr. Khan',
            'prescription_date' => now()->toDateString(),
            'status' => 'verified',
            'verified_by' => $this->pharmacist->id,
            'verified_at' => now(),
        ]);

        $payload = [
            'idempotency_key' => 'idemp-rx-valid',
            'shift_id' => $this->shift->id,
            'customer_id' => $this->customer->id,
            'prescription_id' => $rx->id,
            'payment_method' => 'cash',
            'received_paisa' => 50000,
            'items' => [
                [
                    'medicine_id' => $this->rxMedicine->id,
                    'medicine_package_id' => $this->package->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson(route('pos.checkout'), $payload);
        $response->assertStatus(200);

        $rx->refresh();
        $this->assertEquals('dispensed', $rx->status);

        $this->assertTrue(
            DispensingRecord::where('prescription_id', $rx->id)->exists(),
            'DispensingRecord must be linked to sale on checkout'
        );
    }

    public function test_checkout_cannot_reuse_already_dispensed_prescription(): void
    {
        $rx = Prescription::create([
            'prescription_number' => 'RX-VERIFY-003',
            'customer_id' => $this->customer->id,
            'patient_name' => 'Patient Asad',
            'doctor_name' => 'Dr. Khan',
            'prescription_date' => now()->toDateString(),
            'status' => 'dispensed',
        ]);

        $payload = [
            'idempotency_key' => 'idemp-rx-already-dispensed',
            'shift_id' => $this->shift->id,
            'customer_id' => $this->customer->id,
            'prescription_id' => $rx->id,
            'payment_method' => 'cash',
            'received_paisa' => 50000,
            'items' => [
                [
                    'medicine_id' => $this->rxMedicine->id,
                    'medicine_package_id' => $this->package->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson(route('pos.checkout'), $payload);
        $response->assertStatus(422);
    }

    public function test_unauthorized_staff_cannot_download_prescription_attachment(): void
    {
        $rx = Prescription::create([
            'prescription_number' => 'RX-ATTACH-001',
            'patient_name' => 'Patient Confidential',
            'doctor_name' => 'Dr. Khan',
            'prescription_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        Storage::disk('local')->put('prescriptions/confidential.pdf', 'private medical document');
        $attachment = PrescriptionAttachment::create([
            'prescription_id' => $rx->id,
            'file_path' => 'prescriptions/confidential.pdf',
            'file_name' => 'confidential.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1234,
        ]);

        // Cashier cannot download private medical attachment
        $this->actingAs($this->cashier)->get(route('prescriptions.attachment.download', $attachment))
            ->assertForbidden();

        // Pharmacist can download attachment
        $this->actingAs($this->pharmacist)->get(route('prescriptions.attachment.download', $attachment))
            ->assertOk();
    }
}
