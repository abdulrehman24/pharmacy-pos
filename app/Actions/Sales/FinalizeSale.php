<?php

namespace App\Actions\Sales;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleAllocation;
use App\Models\SaleLine;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class FinalizeSale
{
    public function __construct(
        protected PricingService $pricingService,
        protected InventoryService $inventoryService
    ) {}

    public function execute(array $payload, User $cashier): Sale
    {
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        if (!$idempotencyKey) {
            $idempotencyKey = 'sale_' . bin2hex(random_bytes(16));
        }

        // 1. Check idempotency: if already processed, return existing sale
        $existing = Sale::with(['lines', 'allocations'])->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        // 2. Validate shift: Cashier must have an open shift
        $shiftId = $payload['shift_id'] ?? null;
        if ($shiftId) {
            $shift = Shift::find($shiftId);
            if (!$shift || $shift->status !== 'open') {
                throw new InvalidArgumentException("Specified shift is not currently open.");
            }
        } else {
            // Find cashier's currently open shift
            $shift = Shift::where('user_id', $cashier->id)->where('status', 'open')->latest()->first();
            if (!$shift) {
                // If cashier has no open shift, check if counter has an open shift or auto-create/require open shift
                $shift = Shift::where('status', 'open')->latest()->first();
            }
            $shiftId = $shift?->id;
        }

        $counterId = $payload['counter_id'] ?? $shift?->counter_id;
        $customerId = isset($payload['customer_id']) && $payload['customer_id'] ? (int) $payload['customer_id'] : null;

        $rawItems = $payload['items'] ?? [];
        if (empty($rawItems)) {
            throw new InvalidArgumentException("Sale cannot be empty. Please add items.");
        }

        // 3. Resolve prices & totals authoritatively
        $invoiceDiscountPaisa = (int) ($payload['discount_paisa'] ?? 0);
        $resolvedItems = $this->pricingService->resolveItems($rawItems);
        $totals = $this->pricingService->computeTotals($resolvedItems, $invoiceDiscountPaisa);

        $paymentMethod = $payload['payment_method'] ?? 'cash'; // cash, card, credit, etc.
        $receivedPaisa = (int) ($payload['received_paisa'] ?? 0);

        // Calculate change and balance
        $changePaisa = 0;
        $balancePaisa = 0;

        if ($paymentMethod === 'credit') {
            if (!$customerId) {
                throw new InvalidArgumentException("Credit sales require a registered customer.");
            }
            $customer = Customer::findOrFail($customerId);
            if (!$customer->is_active) {
                throw new InvalidArgumentException("Customer account is inactive.");
            }

            $currentBalance = $customer->balance_paisa;
            $newBalance = $currentBalance + $totals['total_paisa'];
            if ($customer->credit_limit_paisa > 0 && $newBalance > $customer->credit_limit_paisa) {
                $limitRs = $customer->credit_limit_paisa / 100;
                $newRs = $newBalance / 100;
                throw new RuntimeException("Credit limit exceeded. Limit: PKR {$limitRs}, Projected Balance: PKR {$newRs}.");
            }

            $receivedPaisa = 0;
            $changePaisa = 0;
            $balancePaisa = $totals['total_paisa'];
            $paymentStatus = 'unpaid';
        } else {
            if ($receivedPaisa >= $totals['total_paisa']) {
                $changePaisa = $receivedPaisa - $totals['total_paisa'];
                $balancePaisa = 0;
                $paymentStatus = 'paid';
            } else {
                // Partial payment
                $balancePaisa = $totals['total_paisa'] - $receivedPaisa;
                if ($balancePaisa > 0 && !$customerId) {
                    throw new InvalidArgumentException("Partial payments with remaining balance require a customer account.");
                }
                $paymentStatus = ($receivedPaisa > 0) ? 'partial' : 'unpaid';
            }
        }

        // Sort items by medicine_id ASC to strictly prevent cross-transaction database deadlocks
        usort($resolvedItems, fn($a, $b) => $a['medicine_id'] <=> $b['medicine_id']);

        // Concurrency-safe invoice numbering
        $datePrefix = now()->format('Ymd');
        $invoiceNumber = 'INV-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(4)));

        // 4. Execute atomic database transaction
        return DB::transaction(function () use (
            $cashier,
            $shiftId,
            $counterId,
            $customerId,
            $invoiceNumber,
            $idempotencyKey,
            $totals,
            $receivedPaisa,
            $changePaisa,
            $balancePaisa,
            $paymentMethod,
            $paymentStatus,
            $resolvedItems,
            $payload
        ) {
            // Save Sale record
            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'idempotency_key' => $idempotencyKey,
                'shift_id' => $shiftId,
                'counter_id' => $counterId,
                'cashier_id' => $cashier->id,
                'customer_id' => $customerId,
                'subtotal_paisa' => $totals['subtotal_paisa'],
                'discount_paisa' => $totals['discount_paisa'],
                'tax_paisa' => $totals['tax_paisa'],
                'total_paisa' => $totals['total_paisa'],
                'received_paisa' => $receivedPaisa,
                'change_paisa' => $changePaisa,
                'balance_paisa' => $balancePaisa,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'status' => 'posted',
                'notes' => $payload['notes'] ?? null,
            ]);

            // Save Lines & Allocate Stock
            foreach ($resolvedItems as $item) {
                $saleLine = SaleLine::create([
                    'sale_id' => $sale->id,
                    'medicine_id' => $item['medicine_id'],
                    'medicine_package_id' => $item['medicine_package_id'],
                    'medicine_name' => $item['medicine_name'],
                    'package_name' => $item['package_name'],
                    'conversion_factor' => $item['conversion_factor'],
                    'quantity' => $item['quantity'],
                    'total_base_quantity' => $item['total_base_quantity'],
                    'unit_price_paisa' => $item['unit_price_paisa'],
                    'subtotal_paisa' => $item['subtotal_paisa'],
                    'discount_paisa' => $item['discount_paisa'],
                    'tax_paisa' => $item['tax_paisa'],
                    'total_paisa' => $item['total_paisa'],
                ]);

                // Allocate stock via FEFO with row locks
                $allocations = $this->inventoryService->allocateFefoStock(
                    $item['medicine_id'],
                    $item['total_base_quantity']
                );

                foreach ($allocations as $alloc) {
                    SaleAllocation::create([
                        'sale_id' => $sale->id,
                        'sale_line_id' => $saleLine->id,
                        'stock_lot_id' => $alloc['stock_lot_id'],
                        'base_quantity' => $alloc['base_quantity'],
                        'cost_per_base_unit_paisa' => $alloc['cost_per_base_unit_paisa'],
                    ]);

                    // Record outbound stock movement
                    StockMovement::create([
                        'medicine_id' => $item['medicine_id'],
                        'batch_id' => $alloc['batch_id'],
                        'stock_lot_id' => $alloc['stock_lot_id'],
                        'location_id' => $alloc['location_id'],
                        'movement_type' => 'sale',
                        'reference_type' => 'Sale',
                        'reference_id' => $sale->id,
                        'quantity_base_unit' => -$alloc['base_quantity'],
                        'cost_per_base_unit' => $alloc['cost_per_base_unit_paisa'] / 100,
                        'user_id' => $cashier->id,
                        'notes' => "Sale invoice {$invoiceNumber}",
                        'created_at' => now(),
                    ]);
                }
            }

            // If credit or remaining balance, increase customer outstanding balance
            if ($customerId && $balancePaisa > 0) {
                Customer::where('id', $customerId)->increment('balance_paisa', $balancePaisa);
            }

            return $sale->load(['lines', 'allocations', 'customer', 'cashier']);
        });
    }
}
