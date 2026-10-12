<?php

namespace App\Actions\Purchases;

use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostPurchase
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function execute(array $payload, User $user): Purchase
    {
        $supplierId = (int) ($payload['supplier_id'] ?? 0);
        $supplier = Supplier::findOrFail($supplierId);

        $linesData = $payload['lines'] ?? [];
        if (empty($linesData)) {
            throw new InvalidArgumentException("Purchase must contain at least one line item.");
        }

        $locationId = (int) ($payload['location_id'] ?? 1);
        $invoiceDate = $payload['invoice_date'] ?? now()->toDateString();
        $supplierInvoiceNo = $payload['supplier_invoice_number'] ?? null;
        $discountPaisa = (int) ($payload['discount_paisa'] ?? 0);
        $taxPaisa = (int) ($payload['tax_paisa'] ?? 0);
        $paidPaisa = (int) ($payload['paid_paisa'] ?? 0);
        $paymentMethod = $payload['payment_method'] ?? 'cash';

        $datePrefix = now()->format('Ymd');
        $invoiceNumber = 'PUR-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(3)));

        return DB::transaction(function () use (
            $supplier,
            $user,
            $invoiceNumber,
            $supplierInvoiceNo,
            $invoiceDate,
            $locationId,
            $linesData,
            $discountPaisa,
            $taxPaisa,
            $paidPaisa,
            $paymentMethod,
            $payload
        ) {
            $subtotalPaisa = 0;
            $preparedLines = [];

            // Step 1: Validate lines and calculate gross subtotal
            foreach ($linesData as $item) {
                $medicine = Medicine::findOrFail($item['medicine_id']);
                $package = null;
                if (!empty($item['medicine_package_id'])) {
                    $package = MedicinePackage::where('id', $item['medicine_package_id'])
                        ->where('medicine_id', $medicine->id)
                        ->first();
                    if (!$package) {
                        throw new InvalidArgumentException("Package does not belong to medicine {$medicine->brand_name}.");
                    }
                }

                $conversionFactor = $package ? $package->base_quantity : 1;
                $packageQty = (int) ($item['package_quantity'] ?? 1);
                $bonusBaseQty = (int) ($item['bonus_base_quantity'] ?? 0);
                $packageCostPaisa = (int) ($item['package_cost_paisa'] ?? 0);

                if ($packageQty <= 0) {
                    throw new InvalidArgumentException("Package quantity must be greater than zero.");
                }

                $totalBaseQty = ($packageQty * $conversionFactor) + $bonusBaseQty;
                $lineTotalCostPaisa = $packageQty * $packageCostPaisa;

                $subtotalPaisa += $lineTotalCostPaisa;

                $preparedLines[] = [
                    'medicine' => $medicine,
                    'package' => $package,
                    'batch_number' => trim($item['batch_number']),
                    'expiry_date' => $item['expiry_date'],
                    'package_quantity' => $packageQty,
                    'bonus_base_quantity' => $bonusBaseQty,
                    'total_base_quantity' => $totalBaseQty,
                    'package_cost_paisa' => $packageCostPaisa,
                    'line_total_cost_paisa' => $lineTotalCostPaisa,
                ];
            }

            // Step 2: Allocate invoice discount proportionally across lines for accurate stock valuation (A15)
            $processedLines = [];
            foreach ($preparedLines as $p) {
                $lineDiscount = 0;
                if ($subtotalPaisa > 0 && $discountPaisa > 0) {
                    $lineDiscount = (int) round(($p['line_total_cost_paisa'] / $subtotalPaisa) * $discountPaisa);
                }
                $netLineCostPaisa = max(0, $p['line_total_cost_paisa'] - $lineDiscount);

                // Effective net unit cost per base unit
                $unitCostPaisa = $p['total_base_quantity'] > 0
                    ? $netLineCostPaisa / $p['total_base_quantity']
                    : 0.0;

                $processedLines[] = array_merge($p, [
                    'discount_paisa' => $lineDiscount,
                    'net_line_cost_paisa' => $netLineCostPaisa,
                    'unit_cost_paisa' => $unitCostPaisa,
                ]);
            }

            $totalPaisa = max(0, $subtotalPaisa - $discountPaisa + $taxPaisa);

            $purchase = Purchase::create([
                'invoice_number' => $invoiceNumber,
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'supplier_invoice_number' => $supplierInvoiceNo,
                'invoice_date' => $invoiceDate,
                'subtotal_paisa' => $subtotalPaisa,
                'discount_paisa' => $discountPaisa,
                'tax_paisa' => $taxPaisa,
                'total_paisa' => $totalPaisa,
                'paid_paisa' => $paidPaisa,
                'payment_method' => $paymentMethod,
                'status' => 'posted',
                'notes' => $payload['notes'] ?? null,
            ]);

            foreach ($processedLines as $pLine) {
                $purchaseLine = PurchaseLine::create([
                    'purchase_id' => $purchase->id,
                    'medicine_id' => $pLine['medicine']->id,
                    'medicine_package_id' => $pLine['package']?->id,
                    'batch_number' => $pLine['batch_number'],
                    'expiry_date' => $pLine['expiry_date'],
                    'package_quantity' => $pLine['package_quantity'],
                    'bonus_base_quantity' => $pLine['bonus_base_quantity'],
                    'total_base_quantity' => $pLine['total_base_quantity'],
                    'package_cost_paisa' => $pLine['package_cost_paisa'],
                    'unit_cost_paisa' => $pLine['unit_cost_paisa'],
                    'total_cost_paisa' => $pLine['line_total_cost_paisa'],
                ]);

                // Post stock & create lot with net cost and purchase reference (A15)
                $this->inventoryService->receiveStock(
                    $pLine['medicine']->id,
                    $pLine['batch_number'],
                    $pLine['expiry_date'],
                    $pLine['total_base_quantity'],
                    $pLine['unit_cost_paisa'],
                    $locationId,
                    $user->id,
                    $invoiceNumber,
                    "Purchase {$invoiceNumber}",
                    $purchase->id
                );
            }

            // Upfront cash payment links to open shift if cashier has an open shift (A11)
            if ($paidPaisa > 0) {
                $openShift = Shift::where('user_id', $user->id)->where('status', 'open')->latest()->first();
                SupplierPayment::create([
                    'supplier_id' => $supplier->id,
                    'user_id' => $user->id,
                    'shift_id' => $openShift?->id,
                    'amount_paisa' => $paidPaisa,
                    'payment_method' => $paymentMethod,
                    'reference' => $invoiceNumber,
                    'notes' => "Upfront payment on purchase {$invoiceNumber}",
                ]);
            }

            // Update Supplier Balance: Net unpaid balance increases supplier payable
            $netPayableIncrease = $totalPaisa - $paidPaisa;
            if ($netPayableIncrease != 0) {
                $supplier->increment('balance_paisa', $netPayableIncrease);
            }

            return $purchase->load(['lines', 'supplier']);
        });
    }
}
