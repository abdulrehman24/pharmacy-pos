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
        if ($stockCount->status !== 'draft') {
            throw new InvalidArgumentException("Only draft stock counts can be approved.");
        }

        return DB::transaction(function () use ($stockCount, $approver) {
            foreach ($stockCount->lines as $line) {
                if ($line->variance_quantity == 0) {
                    continue;
                }

                $lot = StockLot::findOrFail($line->stock_lot_id);

                // Update physical stock balance
                $newQty = max(0, $lot->remaining_base_qty + $line->variance_quantity);
                $lot->remaining_base_qty = $newQty;
                $lot->save();

                // Record stock movement
                StockMovement::create([
                    'medicine_id' => $line->medicine_id,
                    'batch_id' => $line->batch_id,
                    'stock_lot_id' => $line->stock_lot_id,
                    'location_id' => $stockCount->location_id,
                    'movement_type' => 'adjustment',
                    'reference_type' => 'StockCount',
                    'reference_id' => $stockCount->id,
                    'quantity_base_unit' => $line->variance_quantity,
                    'cost_per_base_unit' => $line->cost_per_base_unit,
                    'user_id' => $approver->id,
                    'notes' => "Stock adjustment count {$stockCount->count_number}. Reason: {$line->reason}",
                    'created_at' => now(),
                ]);
            }

            $stockCount->status = 'approved';
            $stockCount->approved_by = $approver->id;
            $stockCount->approved_at = now();
            $stockCount->save();

            return $stockCount;
        });
    }
}
