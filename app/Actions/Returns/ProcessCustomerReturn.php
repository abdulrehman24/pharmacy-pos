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
        $sale = Sale::with(['lines.allocations', 'customer'])->findOrFail($saleId);

        if ($sale->status !== 'posted') {
            throw new InvalidArgumentException("Only posted sales can be returned.");
        }

        $items = $payload['items'] ?? [];
        if (empty($items)) {
            throw new InvalidArgumentException("At least one item must be returned.");
        }

        $reason = $payload['reason'] ?? 'Customer request';
        $refundMethod = $payload['refund_method'] ?? 'cash'; // cash, credit_adjustment
        $shiftId = $payload['shift_id'] ?? null;

        if (!$shiftId) {
            $openShift = Shift::where('status', 'open')->latest()->first();
            $shiftId = $openShift?->id;
        }

        $datePrefix = now()->format('Ymd');
        $returnNumber = 'RET-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(3)));

        return DB::transaction(function () use (
            $sale,
            $user,
            $returnNumber,
            $items,
            $reason,
            $refundMethod,
            $shiftId
        ) {
            $totalRefundPaisa = 0;
            $returnLinesData = [];

            foreach ($items as $item) {
                $saleLineId = (int) ($item['sale_line_id'] ?? 0);
                $saleLine = SaleLine::where('sale_id', $sale->id)->where('id', $saleLineId)->firstOrFail();

                $returnQty = (int) ($item['quantity'] ?? 0);
                if ($returnQty <= 0) {
                    continue;
                }

                $maxReturnable = $saleLine->remainingReturnableQty();
                if ($returnQty > $maxReturnable) {
                    throw new RuntimeException(
                        "Cannot return {$returnQty} units of '{$saleLine->medicine_name}'. Only {$maxReturnable} remaining returnable."
                    );
                }

                $conversionFactor = $saleLine->conversion_factor;
                $returnBaseQty = $returnQty * $conversionFactor;

                // Refund calculated on original line price after discount
                $effectiveUnitPricePaisa = (int) round($saleLine->total_paisa / $saleLine->quantity);
                $lineRefundPaisa = $returnQty * $effectiveUnitPricePaisa;
                $totalRefundPaisa += $lineRefundPaisa;

                $disposition = in_array($item['disposition'] ?? 'restock', ['restock', 'quarantine', 'write_off'], true)
                    ? $item['disposition']
                    : 'restock';

                // Locate the first stock lot allocation for this line
                $allocation = $saleLine->allocations->first();
                $stockLotId = $allocation?->stock_lot_id;

                $returnLinesData[] = [
                    'sale_line_id' => $saleLine->id,
                    'medicine_id' => $saleLine->medicine_id,
                    'stock_lot_id' => $stockLotId,
                    'quantity' => $returnQty,
                    'base_quantity' => $returnBaseQty,
                    'disposition' => $disposition,
                    'refund_paisa' => $lineRefundPaisa,
                ];
            }

            if (empty($returnLinesData)) {
                throw new InvalidArgumentException("No valid return quantities specified.");
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

            foreach ($returnLinesData as $line) {
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

                // Update stock inventory according to chosen disposition
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

            // If customer credit adjustment, reduce customer's outstanding balance
            if ($refundMethod === 'credit_adjustment' && $sale->customer_id) {
                Customer::where('id', $sale->customer_id)->decrement('balance_paisa', $totalRefundPaisa);
            }

            return $salesReturn->load(['lines', 'sale']);
        });
    }
}
