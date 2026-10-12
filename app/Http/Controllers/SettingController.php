<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use App\Models\PrinterProfile;
use App\Models\Setting;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingController extends Controller
{
    public function index(): Response
    {
        $settings = [
            'shop_name' => Setting::get('shop_name', 'Al-Shifa Community Pharmacy'),
            'shop_address' => Setting::get('shop_address', 'Main Boulevard, Gulberg III, Lahore'),
            'shop_phone' => Setting::get('shop_phone', '+92 42 35789000'),
            'currency_symbol' => Setting::get('currency_symbol', 'PKR'),
            'tax_percentage' => Setting::get('tax_percentage', 0),
            'receipt_footer' => Setting::get('receipt_footer', 'Medicines once sold cannot be returned without original receipt.'),
            'expiry_alert_days' => Setting::get('expiry_alert_days', 90),
            'default_paper_width' => Setting::get('default_paper_width', 80),
        ];

        $printerProfiles = PrinterProfile::all();
        $users = User::all();
        $backups = BackupRun::with('initiator')->latest()->take(10)->get();

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
            'printerProfiles' => $printerProfiles,
            'users' => $users,
            'backups' => $backups,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'shop_address' => ['required', 'string', 'max:500'],
            'shop_phone' => ['required', 'string', 'max:100'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'tax_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'receipt_footer' => ['required', 'string', 'max:500'],
            'expiry_alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'default_paper_width' => ['required', 'integer', 'in:58,80'],
        ]);

        if (! $request->user()?->isAdmin()) {
            abort(403, 'Unauthorized. Modifying system settings requires administrator authorization.');
        }

        foreach ($validated as $key => $value) {
            $type = in_array($key, ['tax_percentage', 'expiry_alert_days', 'default_paper_width'], true)
                ? 'integer'
                : 'string';
            Setting::set($key, $value, 'general', $type);
        }

        \App\Models\AuditEvent::log(
            'settings_updated',
            'Setting',
            null,
            null,
            $validated
        );

        return back()->with('success', 'Settings updated successfully.');
    }

    public function storePrinter(Request $request): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Unauthorized. Adding printer profiles requires administrator authorization.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'connection_type' => ['required', 'string', 'in:browser,network,windows_raw'],
            'host' => ['nullable', 'string', 'max:100'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'columns' => ['required', 'integer', 'min:24', 'max:64'],
            'paper_width' => ['required', 'integer', 'in:58,80'],
            'has_cutter' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            PrinterProfile::where('id', '>', 0)->update(['is_default' => false]);
        }

        $profile = PrinterProfile::create([
            'name' => $validated['name'],
            'connection_type' => $validated['connection_type'],
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? 9100,
            'columns' => $validated['columns'],
            'paper_width' => $validated['paper_width'],
            'has_cutter' => $validated['has_cutter'] ?? true,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        \App\Models\AuditEvent::log(
            'printer_profile_created',
            'PrinterProfile',
            $profile->id,
            null,
            $validated
        );

        return back()->with('success', 'Printer profile added successfully.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Unauthorized. Creating staff accounts requires administrator authorization.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'in:admin,manager,pharmacist,cashier,inventory,accounts'],
            'pin_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'pin_code' => $validated['pin_code'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
        ]);

        \App\Models\AuditEvent::log(
            'user_created',
            'User',
            $newUser->id,
            null,
            ['name' => $newUser->name, 'email' => $newUser->email, 'role' => $newUser->role]
        );

        return back()->with('success', "User {$validated['name']} registered.");
    }

    /**
     * Trigger local backup creation
     */
    public function createBackup(Request $request, \App\Services\BackupService $backupService): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Unauthorized. Creating database backups requires administrator authorization.');
        }

        try {
            $backupRun = $backupService->createBackup($request->user()->id);
            return back()->with('success', "Complete system backup created successfully ({$backupRun->file_name}).");
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => "Backup failed: {$e->getMessage()}"]);
        }
    }

    /**
     * Download backup file
     */
    public function downloadBackup(BackupRun $backupRun): BinaryFileResponse
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Unauthorized. Downloading backups requires administrator authorization.');
        }

        if (!file_exists($backupRun->file_path)) {
            abort(404, 'Backup file not found.');
        }

        return response()->download($backupRun->file_path, $backupRun->file_name);
    }

    /**
     * Restore from a verified backup
     */
    public function restoreBackup(Request $request, \App\Services\BackupService $backupService): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Unauthorized. Restoring backups requires administrator authorization.');
        }

        $validated = $request->validate([
            'backup_id' => ['required', 'exists:backup_runs,id'],
        ]);

        $backupRun = BackupRun::findOrFail($validated['backup_id']);

        try {
            $backupService->restoreBackup($backupRun, $request->user()->id);
            return back()->with('success', "Backup restored successfully ({$backupRun->file_name}).");
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => "Backup restore failed: {$e->getMessage()}"]);
        }
    }
}
