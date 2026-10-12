<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicineBarcode;
use App\Models\MedicinePackage;
use App\Models\Permission;
use App\Models\PrinterProfile;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Settings
        $settings = [
            ['key' => 'shop_name', 'value' => 'Al-Shifa Community Pharmacy', 'type' => 'string', 'group' => 'general'],
            ['key' => 'shop_address', 'value' => 'Plot 42-B, Main Boulevard, Gulberg III, Lahore, Pakistan', 'type' => 'string', 'group' => 'general'],
            ['key' => 'shop_phone', 'value' => '+92 42 35789000 / 0300-8456789', 'type' => 'string', 'group' => 'general'],
            ['key' => 'currency_symbol', 'value' => 'PKR', 'type' => 'string', 'group' => 'general'],
            ['key' => 'currency_code', 'value' => 'PKR', 'type' => 'string', 'group' => 'general'],
            ['key' => 'tax_percentage', 'value' => '0', 'type' => 'integer', 'group' => 'tax'],
            ['key' => 'receipt_footer', 'value' => 'Thank you! Strictly no return without original receipt within 7 days. Cold chain items non-returnable.', 'type' => 'string', 'group' => 'receipt'],
            ['key' => 'expiry_alert_days', 'value' => '90', 'type' => 'integer', 'group' => 'inventory'],
            ['key' => 'default_paper_width', 'value' => '80', 'type' => 'integer', 'group' => 'receipt'],
        ];

        foreach ($settings as $setting) {
            Setting::set($setting['key'], $setting['value'], $setting['group'], $setting['type']);
        }

        // 2. Hardware: Default Location, Counter, Printer Profiles
        $location = Location::firstOrCreate(
            ['code' => 'MAIN-01'],
            ['name' => 'Main Pharmacy Floor', 'is_default' => true]
        );

        $counter = Counter::firstOrCreate(
            ['code' => 'POS-01'],
            ['name' => 'Billing Counter 1', 'ip_address' => '127.0.0.1', 'is_active' => true]
        );

        PrinterProfile::firstOrCreate(
            ['name' => 'Browser Print (80mm)'],
            [
                'connection_type' => 'browser',
                'columns' => 42,
                'paper_width' => 80,
                'has_cutter' => true,
                'is_default' => true,
            ]
        );

        PrinterProfile::firstOrCreate(
            ['name' => 'LAN Thermal Printer (80mm)'],
            [
                'connection_type' => 'network',
                'host' => '192.168.1.200',
                'port' => 9100,
                'columns' => 42,
                'paper_width' => 80,
                'has_cutter' => true,
                'is_default' => false,
            ]
        );

        // 3. Permissions and Roles
        $permissions = [
            ['name' => 'pos.billing', 'display_name' => 'Access POS & Create Invoices', 'group' => 'pos'],
            ['name' => 'pos.discount', 'display_name' => 'Apply Manual Discounts', 'group' => 'pos'],
            ['name' => 'pos.reprint', 'display_name' => 'Reprint Receipts', 'group' => 'pos'],
            ['name' => 'pos.cancel', 'display_name' => 'Cancel / Void Invoices', 'group' => 'pos'],
            ['name' => 'catalogue.manage', 'display_name' => 'Manage Medicines & Packaging', 'group' => 'catalogue'],
            ['name' => 'inventory.view', 'display_name' => 'View Stock & Batches', 'group' => 'inventory'],
            ['name' => 'inventory.adjust', 'display_name' => 'Perform Stock Adjustments & Counts', 'group' => 'inventory'],
            ['name' => 'purchases.manage', 'display_name' => 'Manage Purchases & Suppliers', 'group' => 'purchases'],
            ['name' => 'returns.process', 'display_name' => 'Process Customer & Supplier Returns', 'group' => 'returns'],
            ['name' => 'prescriptions.manage', 'display_name' => 'Review & Dispense Prescriptions', 'group' => 'prescriptions'],
            ['name' => 'shifts.manage', 'display_name' => 'Open/Close Shifts & Record Cash Movements', 'group' => 'finance'],
            ['name' => 'reports.view', 'display_name' => 'View Business & Financial Reports', 'group' => 'reports'],
            ['name' => 'settings.manage', 'display_name' => 'Manage System Settings & Backups', 'group' => 'admin'],
        ];

        foreach ($permissions as $p) {
            $perm = Permission::firstOrCreate(['name' => $p['name']], $p);
            // Assign to manager by default
            RolePermission::firstOrCreate(['role' => 'manager', 'permission_id' => $perm->id]);
            // Assign POS, shifts, returns to cashier
            if (in_array($p['name'], ['pos.billing', 'pos.reprint', 'shifts.manage', 'returns.process', 'inventory.view'], true)) {
                RolePermission::firstOrCreate(['role' => 'cashier', 'permission_id' => $perm->id]);
            }
            // Assign to pharmacist
            if (in_array($p['name'], ['pos.billing', 'prescriptions.manage', 'inventory.view', 'catalogue.manage'], true)) {
                RolePermission::firstOrCreate(['role' => 'pharmacist', 'permission_id' => $perm->id]);
            }
        }

        // 4. Default Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@pharmacy.local'],
            [
                'name' => 'Haji Tariq (Admin)',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
                'pin_code' => '1234',
                'phone' => '0300-8456789',
            ]
        );

        $manager = User::firstOrCreate(
            ['email' => 'manager@pharmacy.local'],
            [
                'name' => 'Usman Ahmed (Manager)',
                'password' => Hash::make('manager123'),
                'role' => 'manager',
                'is_active' => true,
                'pin_code' => '2345',
                'phone' => '0321-4567890',
            ]
        );

        $cashier = User::firstOrCreate(
            ['email' => 'cashier@pharmacy.local'],
            [
                'name' => 'Bilal Khan (Cashier)',
                'password' => Hash::make('cashier123'),
                'role' => 'cashier',
                'is_active' => true,
                'pin_code' => '4567',
                'phone' => '0333-1234567',
            ]
        );

        $pharmacist = User::firstOrCreate(
            ['email' => 'pharmacist@pharmacy.local'],
            [
                'name' => 'Dr. Ayesha Malik (PharmD)',
                'password' => Hash::make('pharma123'),
                'role' => 'pharmacist',
                'is_active' => true,
                'pin_code' => '3456',
                'phone' => '0345-9876543',
            ]
        );

        // 5. Customers & Suppliers
        $customer = Customer::firstOrCreate(
            ['code' => 'CUST-001'],
            [
                'name' => 'Muhammad Zubair',
                'phone' => '03001234567',
                'cnic' => '35201-1234567-1',
                'address' => 'House 14, Street 3, Cavalry Ground, Lahore',
                'credit_limit_paisa' => 2000000, // PKR 20,000 credit limit
                'balance_paisa' => 0,
                'is_active' => true,
            ]
        );

        $supplier = Supplier::firstOrCreate(
            ['code' => 'SUP-001'],
            [
                'name' => 'Lahore Pharma Distributors',
                'contact_person' => 'Rana Khalid',
                'phone' => '0321-9876543',
                'email' => 'orders@lahoredistributors.pk',
                'address' => 'Circular Road, Medicine Market, Lahore',
                'tax_number' => 'NTN-8765432-1',
                'balance_paisa' => 0,
                'is_active' => true,
            ]
        );

        // 6. Categories & Manufacturers
        $analgesics = Category::firstOrCreate(['slug' => 'analgesics'], ['name' => 'Analgesics & Antipyretics']);
        $antibiotics = Category::firstOrCreate(['slug' => 'antibiotics'], ['name' => 'Antibiotics']);
        $antacids = Category::firstOrCreate(['slug' => 'gastrointestinal'], ['name' => 'Gastrointestinal & Antacids']);
        $respiratory = Category::firstOrCreate(['slug' => 'respiratory'], ['name' => 'Respiratory & Cough']);

        $gsk = Manufacturer::firstOrCreate(['name' => 'GlaxoSmithKline (GSK) Pakistan']);
        $abbott = Manufacturer::firstOrCreate(['name' => 'Abbott Laboratories Pakistan']);
        $getz = Manufacturer::firstOrCreate(['name' => 'Getz Pharma']);
        $searle = Manufacturer::firstOrCreate(['name' => 'The Searle Company']);

        // 7. Medicines, Packages, Barcodes & Initial Stock
        $medicinesData = [
            [
                'code' => 'MED-001',
                'brand_name' => 'Panadol',
                'generic_name' => 'Paracetamol',
                'strength' => '500mg',
                'dosage_form' => 'Tablet',
                'category_id' => $analgesics->id,
                'manufacturer_id' => $gsk->id,
                'base_unit' => 'Tablet',
                'rack_shelf' => 'A-01',
                'reorder_level' => 200,
                'prescription_required' => false,
                'packages' => [
                    ['name' => 'Tablet', 'base_quantity' => 1, 'sell_paisa' => 350, 'cost_paisa' => 280, 'barcode' => '896400010011', 'is_sale' => false],
                    ['name' => 'Strip of 10', 'base_quantity' => 10, 'sell_paisa' => 3500, 'cost_paisa' => 2800, 'barcode' => '896400010012', 'is_sale' => true],
                    ['name' => 'Box of 200', 'base_quantity' => 200, 'sell_paisa' => 70000, 'cost_paisa' => 56000, 'barcode' => '896400010013', 'is_sale' => false],
                ],
                'batch' => 'B-PND-991',
                'expiry' => now()->addMonths(18)->format('Y-m-d'),
                'stock_base_qty' => 600, // 3 boxes = 600 tablets
                'unit_cost' => 2.80,
            ],
            [
                'code' => 'MED-002',
                'brand_name' => 'Augmentin',
                'generic_name' => 'Amoxicillin + Clavulanic Acid',
                'strength' => '625mg',
                'dosage_form' => 'Tablet',
                'category_id' => $antibiotics->id,
                'manufacturer_id' => $gsk->id,
                'base_unit' => 'Tablet',
                'rack_shelf' => 'B-02',
                'reorder_level' => 50,
                'prescription_required' => true,
                'packages' => [
                    ['name' => 'Tablet', 'base_quantity' => 1, 'sell_paisa' => 3800, 'cost_paisa' => 3100, 'barcode' => '896400020021', 'is_sale' => false],
                    ['name' => 'Strip of 7', 'base_quantity' => 7, 'sell_paisa' => 26600, 'cost_paisa' => 21700, 'barcode' => '896400020022', 'is_sale' => true],
                    ['name' => 'Box of 14', 'base_quantity' => 14, 'sell_paisa' => 53200, 'cost_paisa' => 43400, 'barcode' => '896400020023', 'is_sale' => false],
                ],
                'batch' => 'B-AUG-442',
                'expiry' => now()->addMonths(12)->format('Y-m-d'),
                'stock_base_qty' => 140, // 10 boxes = 140 tablets
                'unit_cost' => 31.00,
            ],
            [
                'code' => 'MED-003',
                'brand_name' => 'Brufen',
                'generic_name' => 'Ibuprofen',
                'strength' => '400mg',
                'dosage_form' => 'Tablet',
                'category_id' => $analgesics->id,
                'manufacturer_id' => $abbott->id,
                'base_unit' => 'Tablet',
                'rack_shelf' => 'A-03',
                'reorder_level' => 100,
                'prescription_required' => false,
                'packages' => [
                    ['name' => 'Tablet', 'base_quantity' => 1, 'sell_paisa' => 400, 'cost_paisa' => 320, 'barcode' => '896400030031', 'is_sale' => false],
                    ['name' => 'Strip of 10', 'base_quantity' => 10, 'sell_paisa' => 4000, 'cost_paisa' => 3200, 'barcode' => '896400030032', 'is_sale' => true],
                    ['name' => 'Box of 300', 'base_quantity' => 300, 'sell_paisa' => 120000, 'cost_paisa' => 96000, 'barcode' => '896400030033', 'is_sale' => false],
                ],
                'batch' => 'B-BRF-711',
                'expiry' => now()->addMonths(20)->format('Y-m-d'),
                'stock_base_qty' => 600, // 2 boxes = 600 tablets
                'unit_cost' => 3.20,
            ],
            [
                'code' => 'MED-004',
                'brand_name' => 'Risek',
                'generic_name' => 'Omeprazole',
                'strength' => '20mg',
                'dosage_form' => 'Capsule',
                'category_id' => $antacids->id,
                'manufacturer_id' => $getz->id,
                'base_unit' => 'Capsule',
                'rack_shelf' => 'C-01',
                'reorder_level' => 70,
                'prescription_required' => false,
                'packages' => [
                    ['name' => 'Capsule', 'base_quantity' => 1, 'sell_paisa' => 2600, 'cost_paisa' => 2050, 'barcode' => '896400040041', 'is_sale' => false],
                    ['name' => 'Strip of 7', 'base_quantity' => 7, 'sell_paisa' => 18200, 'cost_paisa' => 14350, 'barcode' => '896400040042', 'is_sale' => true],
                    ['name' => 'Box of 14', 'base_quantity' => 14, 'sell_paisa' => 36400, 'cost_paisa' => 28700, 'barcode' => '896400040043', 'is_sale' => false],
                ],
                'batch' => 'B-RSK-105',
                'expiry' => now()->addMonths(15)->format('Y-m-d'),
                'stock_base_qty' => 280, // 20 boxes = 280 capsules
                'unit_cost' => 20.50,
            ],
            [
                'code' => 'MED-005',
                'brand_name' => 'Hydryllin',
                'generic_name' => 'Aminophylline + Diphenhydramine',
                'strength' => '120ml',
                'dosage_form' => 'Syrup',
                'category_id' => $respiratory->id,
                'manufacturer_id' => $searle->id,
                'base_unit' => 'Bottle',
                'rack_shelf' => 'D-01',
                'reorder_level' => 15,
                'prescription_required' => false,
                'packages' => [
                    ['name' => 'Bottle of 120ml', 'base_quantity' => 1, 'sell_paisa' => 14500, 'cost_paisa' => 11800, 'barcode' => '896400050051', 'is_sale' => true],
                ],
                'batch' => 'B-HYD-502',
                'expiry' => now()->addMonths(24)->format('Y-m-d'),
                'stock_base_qty' => 50, // 50 bottles
                'unit_cost' => 118.00,
            ],
        ];

        foreach ($medicinesData as $m) {
            $med = Medicine::firstOrCreate(
                ['code' => $m['code']],
                [
                    'brand_name' => $m['brand_name'],
                    'generic_name' => $m['generic_name'],
                    'strength' => $m['strength'],
                    'dosage_form' => $m['dosage_form'],
                    'category_id' => $m['category_id'],
                    'manufacturer_id' => $m['manufacturer_id'],
                    'base_unit' => $m['base_unit'],
                    'rack_shelf' => $m['rack_shelf'],
                    'reorder_level' => $m['reorder_level'],
                    'prescription_required' => $m['prescription_required'],
                    'is_active' => true,
                ]
            );

            foreach ($m['packages'] as $pkg) {
                $package = MedicinePackage::firstOrCreate(
                    ['medicine_id' => $med->id, 'name' => $pkg['name']],
                    [
                        'base_quantity' => $pkg['base_quantity'],
                        'default_sell_price_paisa' => $pkg['sell_paisa'],
                        'default_purchase_price_paisa' => $pkg['cost_paisa'],
                        'is_default_sale' => $pkg['is_sale'],
                        'is_default_purchase' => ($pkg['base_quantity'] > 1),
                    ]
                );

                if (!empty($pkg['barcode'])) {
                    MedicineBarcode::firstOrCreate(
                        ['barcode' => $pkg['barcode']],
                        [
                            'medicine_id' => $med->id,
                            'medicine_package_id' => $package->id,
                        ]
                    );
                }
            }

            // Create Batch
            $batch = Batch::firstOrCreate(
                ['medicine_id' => $med->id, 'batch_number' => $m['batch']],
                [
                    'expiry_date' => $m['expiry'],
                    'status' => 'sellable',
                ]
            );

            // Create Stock Lot
            $stockLot = StockLot::firstOrCreate(
                ['batch_id' => $batch->id, 'location_id' => $location->id],
                [
                    'receipt_reference' => 'OPENING-STOCK',
                    'purchase_price_paisa' => (int) round($m['unit_cost'] * 100),
                    'cost_per_base_unit' => $m['unit_cost'],
                    'received_base_qty' => $m['stock_base_qty'],
                    'remaining_base_qty' => $m['stock_base_qty'],
                ]
            );

            // Create Opening Stock Movement
            StockMovement::firstOrCreate(
                [
                    'medicine_id' => $med->id,
                    'batch_id' => $batch->id,
                    'stock_lot_id' => $stockLot->id,
                    'movement_type' => 'opening',
                ],
                [
                    'location_id' => $location->id,
                    'reference_type' => 'OpeningStock',
                    'reference_id' => $stockLot->id,
                    'quantity_base_unit' => $m['stock_base_qty'],
                    'cost_per_base_unit' => $m['unit_cost'],
                    'user_id' => $admin->id,
                    'notes' => 'Initial opening inventory setup',
                    'created_at' => now(),
                ]
            );
        }
    }
}
