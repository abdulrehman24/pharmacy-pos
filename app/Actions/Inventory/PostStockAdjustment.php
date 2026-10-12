<?php

namespace App\Actions\Inventory;

use App\Models\StockCount;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostStockAdjustment
{
    public function execute(StockCount $stockCount, User $approver): StockCount
    {
        return DB::transaction(function () use ($stockCount, $approver) {
            $lockedCount = StockCount::with('lines')->where('id', $stockCount->id)->lockForUpdate()->firstOrFail();

            if ($lockedCount->status !== 'draft') {
                throw new InvalidArgumentException("Only draft stock counts can be approved.");
            }

            foreach ($lockedCount->lines as $line) {
                if ($line->variance_quantity == 0) {
                    continue;
                }

                $lot = StockLot::where('id', $line->stock_lot_id)->lockForUpdate()->firstOrFail();

                $originalQty = (int) $lot->remaining_base_qty;
                // Physical stock balance clamped at 0
                $newQty = max(0, $originalQty + (int) $line->variance_quantity);
                $lot->remaining_base_qty = $newQty;
                $lot->save();

                // Actual applied movement delta (matches exact change to stock balance)
                $appliedMovementDelta = $newQty - $originalQty;

                if ($appliedMovementDelta !== 0) {
                    // Record stock movement
                    StockMovement::create([
                        'medicine_id' => $line->medicine_id,
                        'batch_id' => $line->batch_id,
                        'stock_lot_id' => $line->stock_lot_id,
                        'location_id' => $lockedCount->location_id,
                        'movement_type' => 'adjustment',
                        'reference_type' => 'StockCount',
                        'reference_id' => $lockedCount->id,
                        'quantity_base_unit' => $appliedMovementDelta,
                        'cost_per_base_unit' => $line->cost_per_base_unit,
                        'user_id' => $approver->id,
                        'notes' => "Stock adjustment count {$lockedCount->count_number}. Reason: {$line->reason}",
                        'created_at' => now(),
                    ]);
                }
            }

            $lockedCount->status = 'approved';
            $lockedCount->approved_by = $approver->id;
            $lockedCount->approved_at = now();
            $lockedCount->save();

            return $lockedCount;
        });
    }
}
