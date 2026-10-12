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

        foreach ($validated as $key => $value) {
            $type = in_array($key, ['tax_percentage', 'expiry_alert_days', 'default_paper_width'], true)
                ? 'integer'
                : 'string';
            Setting::set($key, $value, 'general', $type);
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    public function storePrinter(Request $request): RedirectResponse
    {
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

        PrinterProfile::create([
            'name' => $validated['name'],
            'connection_type' => $validated['connection_type'],
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? 9100,
            'columns' => $validated['columns'],
            'paper_width' => $validated['paper_width'],
            'has_cutter' => $validated['has_cutter'] ?? true,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return back()->with('success', 'Printer profile added successfully.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'in:admin,manager,pharmacist,cashier,inventory,accounts'],
            'pin_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'pin_code' => $validated['pin_code'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', "User {$validated['name']} registered.");
    }

    /**
     * Trigger local backup creation
     */
    public function createBackup(Request $request): RedirectResponse
    {
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');
        $dbHost = config('database.connections.mysql.host');
        $dbPort = config('database.connections.mysql.port', 3306);

        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $fileName = 'backup_' . date('Y-m-d_His') . '.sql';
        $filePath = $backupDir . '/' . $fileName;

        $passArg = $dbPass ? "-p\"{$dbPass}\"" : '';
        $cmd = "mysqldump -h {$dbHost} -P {$dbPort} -u {$dbUser} {$passArg} {$dbName} > \"{$filePath}\" 2>&1";

        exec($cmd, $output, $returnCode);

        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
        $status = ($returnCode === 0 && $fileSize > 0) ? 'completed' : 'failed';
        $errorMsg = ($status === 'failed') ? implode("\n", $output) : null;

        BackupRun::create([
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size_bytes' => $fileSize,
            'status' => $status,
            'error_message' => $errorMsg,
            'initiated_by' => $request->user()->id,
        ]);

        if ($status === 'completed') {
            return back()->with('success', "Database backup created successfully ({$fileName}).");
        }

        return back()->withErrors(['backup' => "Backup failed: {$errorMsg}"]);
    }

    /**
     * Download backup file
     */
    public function downloadBackup(BackupRun $backupRun): BinaryFileResponse
    {
        if (!file_exists($backupRun->file_path)) {
            abort(404, 'Backup file not found.');
        }

        return response()->download($backupRun->file_path, $backupRun->file_name);
    }
}
