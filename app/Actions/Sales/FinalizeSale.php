<?php

namespace App\Actions\Sales;

use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\DispensingRecord;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\Sale;
use App\Models\SaleAllocation;
use App\Models\SaleLine;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\PricingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinalizeSale
{
    public function __construct(
        protected PricingService $pricingService,
        protected InventoryService $inventoryService
    ) {}

    public function computePayloadHash(array $payload, int $cashierId): string
    {
        $rawItems = $payload['items'] ?? [];
        $normalizedItems = array_map(function ($it) {
            return [
                'medicine_id' => (int) ($it['medicine_id'] ?? 0),
                'medicine_package_id' => isset($it['medicine_package_id']) ? (int) $it['medicine_package_id'] : null,
                'quantity' => (int) ($it['quantity'] ?? 0),
                'unit_price_paisa' => isset($it['unit_price_paisa']) ? (int) $it['unit_price_paisa'] : null,
                'discount_paisa' => (int) ($it['discount_paisa'] ?? 0),
            ];
        }, $rawItems);

        usort($normalizedItems, function ($a, $b) {
            return ($a['medicine_id'] <=> $b['medicine_id']) ?: ($a['quantity'] <=> $b['quantity']);
        });

        $canonical = [
            'cashier_id' => $cashierId,
            'customer_id' => isset($payload['customer_id']) && $payload['customer_id'] ? (int) $payload['customer_id'] : null,
            'payment_method' => (string) ($payload['payment_method'] ?? 'cash'),
            'received_paisa' => (int) ($payload['received_paisa'] ?? 0),
            'discount_paisa' => (int) ($payload['discount_paisa'] ?? 0),
            'items' => $normalizedItems,
        ];

        return hash('sha256', json_encode($canonical));
    }

    public function execute(array $payload, User $cashier): Sale
    {
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        if (!$idempotencyKey) {
            $idempotencyKey = 'sale_' . bin2hex(random_bytes(16));
        }

        $payloadHash = $this->computePayloadHash($payload, $cashier->id);

        // 1. Check idempotency: if already processed, verify hash and cashier
        $existing = Sale::with(['lines', 'allocations', 'customer', 'cashier'])
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            if ($existing->cashier_id !== $cashier->id || ($existing->payload_hash && $existing->payload_hash !== $payloadHash)) {
                throw new ConflictHttpException("Idempotency key collision: checkout payload or cashier does not match existing record.");
            }
            return $existing;
        }

        // 2. Resolve shift: Shifts are optional/not compulsory.
        // If an explicit shift_id is passed, validate that it belongs to the cashier and is open.
        // Otherwise, automatically associate with cashier's active open shift if one exists.
        $shiftId = $payload['shift_id'] ?? null;
        $shift = null;
        if ($shiftId) {
            $shift = Shift::find($shiftId);
            if (!$shift || $shift->status !== 'open' || $shift->user_id !== $cashier->id) {
                throw new InvalidArgumentException("Specified shift is not open or does not belong to the current cashier.");
            }
        } else {
            // Find cashier's currently open shift if one exists (optional)
            $shift = Shift::where('user_id', $cashier->id)->where('status', 'open')->latest()->first();
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

        $paymentMethod = $payload['payment_method'] ?? 'cash';
        $receivedPaisa = (int) ($payload['received_paisa'] ?? 0);

        // Calculate change and balance
        $changePaisa = 0;
        $balancePaisa = 0;

        if ($paymentMethod === 'credit') {
            if (!$customerId) {
                throw new InvalidArgumentException("Credit sales require a registered customer.");
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

        // 3b. Verify prescription requirements
        $rxMedicineNames = [];
        foreach ($resolvedItems as $item) {
            $med = Medicine::find($item['medicine_id']);
            if ($med && ($med->prescription_required || $med->is_narcotic)) {
                $rxMedicineNames[$item['medicine_id']] = $med->brand_name ?? $med->code;
            }
        }

        $prescription = null;
        if (!empty($rxMedicineNames)) {
            $prescriptionId = $payload['prescription_id'] ?? null;
            if (!$prescriptionId) {
                $names = implode(', ', $rxMedicineNames);
                throw new InvalidArgumentException("A verified prescription is required for restricted items: {$names}.");
            }

            $prescription = Prescription::find($prescriptionId);
            if (!$prescription) {
                throw new InvalidArgumentException("Prescription not found.");
            }

            if ($prescription->status === 'pending') {
                throw new InvalidArgumentException("Prescription {$prescription->prescription_number} is pending pharmacist verification.");
            }

            if ($prescription->status === 'dispensed') {
                throw new InvalidArgumentException("Prescription {$prescription->prescription_number} has already been dispensed.");
            }

            if ($prescription->status !== 'verified') {
                throw new InvalidArgumentException("Prescription {$prescription->prescription_number} is not approved for dispensing.");
            }

            if ($prescription->customer_id && $customerId && $prescription->customer_id !== $customerId) {
                throw new InvalidArgumentException("Prescription customer does not match sale customer.");
            }
        }

        // Sort items by medicine_id ASC to strictly prevent cross-transaction database deadlocks
        usort($resolvedItems, fn($a, $b) => $a['medicine_id'] <=> $b['medicine_id']);

        // Concurrency-safe invoice numbering
        $datePrefix = now()->format('Ymd');
        $invoiceNumber = 'INV-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(4)));

        // 4. Execute atomic database transaction
        try {
            return DB::transaction(function () use (
                $cashier,
                $shiftId,
                $counterId,
                $customerId,
                $invoiceNumber,
                $idempotencyKey,
                $payloadHash,
                $totals,
                $receivedPaisa,
                $changePaisa,
                $balancePaisa,
                $paymentMethod,
                $paymentStatus,
                $resolvedItems,
                $prescription,
                $payload
            ) {
                // Lock shift and verify still open (if checkout is associated with a shift)
                if ($shiftId) {
                    $lockedShift = Shift::where('id', $shiftId)->lockForUpdate()->first();
                    if (!$lockedShift || $lockedShift->status !== 'open' || $lockedShift->user_id !== $cashier->id) {
                        throw new InvalidArgumentException("Shift was closed or is invalid during checkout.");
                    }
                }

                // If balance is outstanding, lock customer and verify credit limit
                if ($customerId && $balancePaisa > 0) {
                    $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();
                    if (!$customer->is_active) {
                        throw new InvalidArgumentException("Customer account is inactive.");
                    }
                    $projectedBalance = $customer->balance_paisa + $balancePaisa;
                    if ($customer->credit_limit_paisa > 0 && $projectedBalance > $customer->credit_limit_paisa) {
                        $limitRs = $customer->credit_limit_paisa / 100;
                        $newRs = $projectedBalance / 100;
                        throw new RuntimeException("Credit limit exceeded. Limit: PKR {$limitRs}, Projected Balance: PKR {$newRs}.");
                    }
                    $customer->increment('balance_paisa', $balancePaisa);
                }

                // Save Sale record
                $sale = Sale::create([
                    'invoice_number' => $invoiceNumber,
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
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

                // If prescription required, atomically lock and dispense
                if ($prescription) {
                    $lockedRx = Prescription::where('id', $prescription->id)->lockForUpdate()->first();
                    if (!$lockedRx || $lockedRx->status !== 'verified') {
                        throw new InvalidArgumentException("Prescription is no longer in verified status.");
                    }
                    $lockedRx->status = 'dispensed';
                    $lockedRx->save();

                    DispensingRecord::create([
                        'prescription_id' => $lockedRx->id,
                        'sale_id' => $sale->id,
                        'pharmacist_id' => $lockedRx->verified_by ?? $cashier->id,
                        'instructions' => "Dispensed for invoice {$invoiceNumber}",
                    ]);

                    AuditEvent::log(
                        'prescription_dispensed',
                        'Prescription',
                        $lockedRx->id,
                        ['status' => 'verified'],
                        ['status' => 'dispensed', 'sale_id' => $sale->id, 'invoice_number' => $invoiceNumber]
                    );
                }

                return $sale->load(['lines', 'allocations', 'customer', 'cashier']);
            });
        } catch (QueryException $e) {
            // Concurrent race condition: another process committed with the exact same idempotency key
            $committed = Sale::with(['lines', 'allocations', 'customer', 'cashier'])
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($committed) {
                if ($committed->cashier_id !== $cashier->id || ($committed->payload_hash && $committed->payload_hash !== $payloadHash)) {
                    throw new ConflictHttpException("Idempotency key collision: checkout payload or cashier does not match existing record.");
                }
                return $committed;
            }

            throw $e;
        }
    }
}
