<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // POS & Counter Billing
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/search', [PosController::class, 'search'])->name('pos.search');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/switch-cashier', [PosController::class, 'switchCashier'])->name('pos.switch-cashier');

    // Thermal & Browser Receipts
    Route::get('/sales/{sale}/receipt', [ReceiptController::class, 'show'])->name('sales.receipt');
    Route::post('/sales/{sale}/print', [ReceiptController::class, 'print'])->name('sales.print');

    // Medicines Catalogue & Master Data
    Route::post('/categories/quick-store', [MedicineController::class, 'storeCategory'])->name('categories.quick-store');
    Route::delete('/categories/{category}', [MedicineController::class, 'destroyCategory'])->name('categories.destroy');
    Route::post('/manufacturers/quick-store', [MedicineController::class, 'storeManufacturer'])->name('manufacturers.quick-store');
    Route::delete('/manufacturers/{manufacturer}', [MedicineController::class, 'destroyManufacturer'])->name('manufacturers.destroy');
    Route::resource('medicines', MedicineController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/medicines/{medicine}/toggle-status', [MedicineController::class, 'toggleStatus'])->name('medicines.toggle-status');

    // Purchases & Receiving
    Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show']);

    // Inventory, Batches, Stock Movements, Physical Counts & Write-Offs
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/ledger', [InventoryController::class, 'ledger'])->name('inventory.ledger');
    Route::get('/inventory/counts', [InventoryController::class, 'counts'])->name('inventory.counts');
    Route::post('/inventory/counts', [InventoryController::class, 'storeCount'])->name('inventory.counts.store');

    // Suppliers & Supplier Payments
    Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'show']);
    Route::post('/suppliers/{supplier}/payment', [SupplierController::class, 'recordPayment'])->name('suppliers.payment');

    // Customers & Credit Khata
    Route::post('/customers/quick-store', [CustomerController::class, 'quickStore'])->name('customers.quick-store');
    Route::resource('customers', CustomerController::class)->only(['index', 'store', 'show']);
    Route::post('/customers/{customer}/payment', [CustomerController::class, 'recordPayment'])->name('customers.payment');

    // Returns (Customer & Supplier)
    Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/search-invoice', [ReturnController::class, 'searchInvoice'])->name('returns.search-invoice');
    Route::post('/returns/customer', [ReturnController::class, 'storeCustomerReturn'])->name('returns.customer.store');
    Route::post('/returns/supplier', [ReturnController::class, 'storeSupplierReturn'])->name('returns.supplier.store');
    Route::post('/returns/supplier/{supplierReturn}/confirm-credit', [ReturnController::class, 'confirmSupplierCredit'])->name('returns.supplier.confirm-credit');

    // Cashier Shifts & Operating Expenses
    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('/shifts/open', [ShiftController::class, 'open'])->name('shifts.open');
    Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
    Route::post('/shifts/{shift}/cash-movement', [ShiftController::class, 'cashMovement'])->name('shifts.cash-movement');
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

    // Reports & Exports (Admin & Manager)
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export-sales', [ReportController::class, 'exportSalesCsv'])->name('reports.export-sales');
        Route::get('/reports/export-stock', [ReportController::class, 'exportStockCsv'])->name('reports.export-stock');
        Route::post('/inventory/counts/{stockCount}/approve', [InventoryController::class, 'approveCount'])->name('inventory.counts.approve');
        Route::post('/inventory/write-off', [InventoryController::class, 'writeOff'])->name('inventory.write-off');
    });

    // Prescriptions & Dispensing Records
    Route::get('/prescriptions', [PrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::post('/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
    Route::post('/prescriptions/{prescription}/verify', [PrescriptionController::class, 'verify'])
        ->middleware('role:admin,manager,pharmacist')
        ->name('prescriptions.verify');
    Route::get('/prescriptions/attachment/{attachment}/download', [PrescriptionController::class, 'downloadAttachment'])
        ->middleware('role:admin,manager,pharmacist')
        ->name('prescriptions.attachment.download');

    // Settings, Hardware Profiles, Backups & Users (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/printer', [SettingController::class, 'storePrinter'])->name('settings.printer.store');
        Route::post('/settings/user', [SettingController::class, 'storeUser'])->name('settings.user.store');
        Route::post('/settings/backup', [SettingController::class, 'createBackup'])->name('settings.backup.create');
        Route::get('/settings/backup/{backupRun}/download', [SettingController::class, 'downloadBackup'])->name('settings.backup.download');
        Route::post('/settings/backup/restore', [SettingController::class, 'restoreBackup'])->name('settings.backup.restore');
    });

    // User Profile & Account Settings
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/pin', [ProfileController::class, 'updatePin'])->name('profile.pin.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
