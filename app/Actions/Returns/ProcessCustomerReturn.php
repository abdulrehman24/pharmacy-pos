<?php

namespace App\Actions\Returns;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use App\Models\Shift;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ProcessCustomerReturn
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function execute(array $payload, User $user): SalesReturn
    {
        $saleId = (int) ($payload['sale_id'] ?? 0);
        $items = $payload['items'] ?? [];
        if (empty($items)) {
            throw new InvalidArgumentException("At least one item must be returned.");
        }

        $reason = $payload['reason'] ?? 'Customer request';
        $refundMethod = $payload['refund_method'] ?? 'cash'; // cash, credit_adjustment
        $shiftId = $payload['shift_id'] ?? null;

        if (!$shiftId) {
            $openShift = Shift::where('status', 'open')->where('user_id', $user->id)->latest()->first()
                ?? Shift::where('status', 'open')->latest()->first();
            $shiftId = $openShift?->id;
        }

        // Aggregate incoming items by sale_line_id to prevent duplicate item array bypassing (A06)
        $aggregatedItems = [];
        foreach ($items as $item) {
            $lineId = (int) ($item['sale_line_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            if ($lineId <= 0 || $qty <= 0) {
                continue;
            }

            $disposition = in_array($item['disposition'] ?? 'restock', ['restock', 'quarantine', 'write_off'], true)
                ? $item['disposition']
                : 'restock';

            if (!isset($aggregatedItems[$lineId])) {
                $aggregatedItems[$lineId] = [
                    'sale_line_id' => $lineId,
                    'quantity' => 0,
                    'disposition' => $disposition,
                ];
            }
            $aggregatedItems[$lineId]['quantity'] += $qty;
        }

        if (empty($aggregatedItems)) {
            throw new InvalidArgumentException("No valid return quantities specified.");
        }

        $datePrefix = now()->format('Ymd');
        $returnNumber = 'RET-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(3)));

        return DB::transaction(function () use (
            $saleId,
            $user,
            $returnNumber,
            $aggregatedItems,
            $reason,
            $refundMethod,
            $shiftId
        ) {
            // Pessimistic lock on Sale (A06)
            $sale = Sale::lockForUpdate()->with(['lines.allocations', 'customer'])->findOrFail($saleId);

            if ($sale->status !== 'posted') {
                throw new InvalidArgumentException("Only posted sales can be returned.");
            }

            // Lock all affected sale lines
            $saleLines = SaleLine::where('sale_id', $sale->id)
                ->whereIn('id', array_keys($aggregatedItems))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalRefundPaisa = 0;
            $linesToCreate = [];

            // Calculate invoice-level proportional discount & tax adjustment (A05)
            // Each line's realized net total = subtotal - line_discount - proportional_invoice_discount + proportional_invoice_tax
            $saleSubtotal = max(1, $sale->subtotal_paisa);

            foreach ($aggregatedItems as $lineId => $item) {
                $saleLine = $saleLines->get($lineId);
                if (!$saleLine) {
                    throw new InvalidArgumentException("Sale line #{$lineId} not found on this invoice.");
                }

                $returnQty = (int) $item['quantity'];

                // Compute exact remaining returnable qty under lock
                $priorReturnedQty = (int) SalesReturnLine::where('sale_line_id', $saleLine->id)
                    ->whereHas('salesReturn', fn($q) => $q->where('status', 'completed'))
                    ->sum('quantity');

                $maxReturnable = max(0, $saleLine->quantity - $priorReturnedQty);
                if ($returnQty > $maxReturnable) {
                    throw new RuntimeException(
                        "Cannot return {$returnQty} units of '{$saleLine->medicine_name}'. Only {$maxReturnable} remaining returnable."
                    );
                }

                $conversionFactor = max(1, $saleLine->conversion_factor);
                $totalReturnBaseQty = $returnQty * $conversionFactor;

                // Net line amount after proportional invoice discount & tax (A05)
                $lineShareOfInvoiceDiscount = (int) round(($sale->discount_paisa * $saleLine->total_paisa) / $saleSubtotal);
                $lineShareOfInvoiceTax = (int) round(($sale->tax_paisa * $saleLine->total_paisa) / $saleSubtotal);
                $lineRealizedNetTotal = max(0, $saleLine->total_paisa - $lineShareOfInvoiceDiscount + $lineShareOfInvoiceTax);

                $unitRefundPaisa = (int) round($lineRealizedNetTotal / $saleLine->quantity);
                $lineRefundPaisa = $returnQty * $unitRefundPaisa;
                $totalRefundPaisa += $lineRefundPaisa;

                $disposition = $item['disposition'];

                // Multi-batch return lot resolution (A07)
                // Distribute returned base quantity across original allocations in proportion to what was sold
                $allocations = $saleLine->allocations()->orderBy('id')->get();
                $remainingBaseToDistribute = $totalReturnBaseQty;

                foreach ($allocations as $alloc) {
                    if ($remainingBaseToDistribute <= 0) {
                        break;
                    }

                    // How much has already been returned from this specific allocation?
                    $alreadyReturnedFromAlloc = (int) SalesReturnLine::where('sale_line_id', $saleLine->id)
                        ->where('stock_lot_id', $alloc->stock_lot_id)
                        ->whereHas('salesReturn', fn($q) => $q->where('status', 'completed'))
                        ->sum('base_quantity');

                    $availableFromAlloc = max(0, $alloc->base_quantity - $alreadyReturnedFromAlloc);
                    if ($availableFromAlloc <= 0) {
                        continue;
                    }

                    $lotBaseToReturn = min($remainingBaseToDistribute, $availableFromAlloc);
                    $remainingBaseToDistribute -= $lotBaseToReturn;

                    $lotPackageFraction = $totalReturnBaseQty > 0 ? ($lotBaseToReturn / $totalReturnBaseQty) : 0;
                    $lotRefundPaisa = (int) round($lineRefundPaisa * $lotPackageFraction);

                    $linesToCreate[] = [
                        'sale_line_id' => $saleLine->id,
                        'medicine_id' => $saleLine->medicine_id,
                        'stock_lot_id' => $alloc->stock_lot_id,
                        'quantity' => (int) ceil($lotBaseToReturn / $conversionFactor),
                        'base_quantity' => $lotBaseToReturn,
                        'disposition' => $disposition,
                        'refund_paisa' => $lotRefundPaisa,
                    ];
                }

                // If allocations were missing or exhausted, fallback to first allocation or null
                if ($remainingBaseToDistribute > 0) {
                    $fallbackAlloc = $allocations->first();
                    $linesToCreate[] = [
                        'sale_line_id' => $saleLine->id,
                        'medicine_id' => $saleLine->medicine_id,
                        'stock_lot_id' => $fallbackAlloc?->stock_lot_id,
                        'quantity' => (int) ceil($remainingBaseToDistribute / $conversionFactor),
                        'base_quantity' => $remainingBaseToDistribute,
                        'disposition' => $disposition,
                        'refund_paisa' => (int) round($lineRefundPaisa * ($remainingBaseToDistribute / max(1, $totalReturnBaseQty))),
                    ];
                }
            }

            // Cap total refund to remaining un-refunded net invoice total (A05)
            $priorTotalRefunded = (int) SalesReturn::where('sale_id', $sale->id)
                ->where('status', 'completed')
                ->sum('total_refund_paisa');
            $remainingRefundCap = max(0, $sale->total_paisa - $priorTotalRefunded);
            $totalRefundPaisa = min($totalRefundPaisa, $remainingRefundCap);

            // Reconcile refund method with unpaid debt (A08)
            // A customer CANNOT receive cash refund for an unpaid credit sale!
            $cashSettledPaisa = max(0, $sale->total_paisa - $sale->balance_paisa);
            $priorCashRefunded = (int) SalesReturn::where('sale_id', $sale->id)
                ->where('status', 'completed')
                ->where('refund_method', 'cash')
                ->sum('total_refund_paisa');
            $maxCashRefundAllowed = max(0, $cashSettledPaisa - $priorCashRefunded);

            if ($refundMethod === 'cash' && $totalRefundPaisa > $maxCashRefundAllowed) {
                throw new InvalidArgumentException(
                    "Cannot refund in cash. Maximum settled cash refundable on this invoice is " .
                    number_format($maxCashRefundAllowed / 100, 2) . " PKR. Unpaid balance must be returned as credit adjustment."
                );
            }

            $salesReturn = SalesReturn::create([
                'return_number' => $returnNumber,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'cashier_id' => $user->id,
                'shift_id' => $shiftId,
                'total_refund_paisa' => $totalRefundPaisa,
                'refund_method' => $refundMethod,
                'reason' => $reason,
                'status' => 'completed',
            ]);

            foreach ($linesToCreate as $line) {
                SalesReturnLine::create([
                    'sales_return_id' => $salesReturn->id,
                    'sale_line_id' => $line['sale_line_id'],
                    'medicine_id' => $line['medicine_id'],
                    'stock_lot_id' => $line['stock_lot_id'],
                    'quantity' => $line['quantity'],
                    'base_quantity' => $line['base_quantity'],
                    'disposition' => $line['disposition'],
                    'refund_paisa' => $line['refund_paisa'],
                ]);

                if ($line['stock_lot_id']) {
                    $this->inventoryService->restockItem(
                        $line['stock_lot_id'],
                        $line['medicine_id'],
                        $line['base_quantity'],
                        $line['disposition'],
                        $user->id,
                        $salesReturn->id,
                        "Return {$returnNumber} reason: {$reason}"
                    );
                }
            }

            // Adjust invoice receivable and customer ledger balances (A08)
            $debtReduction = 0;
            if ($sale->balance_paisa > 0) {
                $debtReduction = min($sale->balance_paisa, $totalRefundPaisa);
                $sale->decrement('balance_paisa', $debtReduction);

                if ($sale->fresh()->balance_paisa === 0) {
                    $sale->update(['payment_status' => 'paid']);
                }

                if ($sale->customer_id) {
                    Customer::where('id', $sale->customer_id)->decrement('balance_paisa', $debtReduction);
                }
            }

            // If credit adjustment and customer exists, credit any excess beyond debt reduction
            if ($refundMethod === 'credit_adjustment' && $sale->customer_id) {
                $excessCredit = $totalRefundPaisa - $debtReduction;
                if ($excessCredit > 0) {
                    Customer::where('id', $sale->customer_id)->decrement('balance_paisa', $excessCredit);
                }
            }

            \App\Models\AuditEvent::log(
                'sale_returned',
                'SalesReturn',
                $salesReturn->id,
                null,
                [
                    'sale_id' => $sale->id,
                    'refund_paisa' => $totalRefundPaisa,
                    'refund_method' => $refundMethod,
                    'reason' => $reason,
                ]
            );

            return $salesReturn->load(['lines', 'sale']);
        });
    }
}
