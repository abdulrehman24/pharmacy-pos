<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Models\BackupRun;
use App\Models\Customer;
use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class A18BackupIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_cannot_create_download_or_restore_backup(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $backup = BackupRun::create([
            'file_name' => 'backup_test.zip',
            'file_path' => storage_path('app/backups/backup_test.zip'),
            'file_size_bytes' => 1024,
            'status' => 'completed',
        ]);

        $this->actingAs($cashier)->post(route('settings.backup.create'))
            ->assertForbidden();

        $this->actingAs($cashier)->get(route('settings.backup.download', $backup))
            ->assertForbidden();

        $this->actingAs($cashier)->post('/settings/backup/restore', ['backup_id' => $backup->id])
            ->assertForbidden();
    }

    public function test_admin_creates_complete_backup_archive_with_manifest_and_attachments(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // Seed a prescription with attachment
        $customer = Customer::create([
            'name' => 'Test Patient',
            'code' => 'CUST-001',
            'phone' => '03001234567',
            'is_active' => true,
        ]);

        $prescription = Prescription::create([
            'prescription_number' => 'RX-TEST-001',
            'customer_id' => $customer->id,
            'patient_name' => 'Test Patient',
            'doctor_name' => 'Dr. Ahmed',
            'prescription_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $fakeContent = 'fake prescription content';
        $relativeAttachmentPath = 'prescriptions/rx_test_123.pdf';
        Storage::disk('local')->put($relativeAttachmentPath, $fakeContent);

        PrescriptionAttachment::create([
            'prescription_id' => $prescription->id,
            'file_path' => $relativeAttachmentPath,
            'file_name' => 'rx_test_123.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($fakeContent),
        ]);

        $response = $this->actingAs($admin)->post(route('settings.backup.create'));
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $backup = BackupRun::latest()->first();
        $this->assertNotNull($backup);
        $this->assertEquals('completed', $backup->status);
        $this->assertFileExists($backup->file_path);
        $this->assertStringEndsWith('.zip', $backup->file_name);

        // Verify ZIP contents
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($backup->file_path) === true);

        $manifestIndex = $zip->locateName('manifest.json');
        $this->assertNotFalse($manifestIndex, 'manifest.json must exist in backup archive');

        $sqlIndex = $zip->locateName('database.sql');
        $this->assertNotFalse($sqlIndex, 'database.sql must exist in backup archive');

        $attachmentIndex = $zip->locateName('attachments/prescriptions/rx_test_123.pdf');
        $this->assertNotFalse($attachmentIndex, 'prescriptions attachment must exist in backup archive');

        // Check manifest contents
        $manifestJson = $zip->getFromName('manifest.json');
        $manifest = json_decode($manifestJson, true);
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('files', $manifest);
        $this->assertArrayHasKey('database.sql', $manifest['files']);
        $this->assertEquals(hash('sha256', $zip->getFromName('database.sql')), $manifest['files']['database.sql']['sha256']);

        $zip->close();
    }

    public function test_backup_restore_verifies_manifest_and_recovers_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // Create initial backup
        $backupService = app(BackupService::class);
        $backup = $backupService->createBackup($admin->id);
        $this->assertEquals('completed', $backup->status);

        // Perform restore
        $response = $this->actingAs($admin)->post('/settings/backup/restore', [
            'backup_id' => $backup->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Check audit event was logged
        $this->assertTrue(
            AuditEvent::where('event_type', 'backup_restored')->exists(),
            'AuditEvent::log must record backup_restored'
        );
    }

    public function test_tampered_backup_archive_fails_manifest_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $backupService = app(BackupService::class);
        $backup = $backupService->createBackup($admin->id);

        // Tamper with zip file by overwriting database.sql with altered content
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($backup->file_path) === true);
        $zip->addFromString('database.sql', '-- TAMPERED SQL CONTENT');
        $zip->close();

        // Attempt restore
        $response = $this->actingAs($admin)->post('/settings/backup/restore', [
            'backup_id' => $backup->id,
        ]);

        $response->assertSessionHasErrors('backup');
    }
}
