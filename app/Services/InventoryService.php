<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockLot;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InventoryService
{
    /**
     * Allocate stock using FEFO (First Expiry, First Out) with row-level locks
     * Returns an array of allocation records:
     * [['stock_lot_id' => int, 'base_quantity' => int, 'cost_per_base_unit_paisa' => float]]
     */
    public function allocateFefoStock(int $medicineId, int $requiredBaseQty, ?int $locationId = null): array
    {
        if ($requiredBaseQty <= 0) {
            throw new InvalidArgumentException("Allocation quantity must be greater than zero.");
        }

        $medicine = Medicine::findOrFail($medicineId);
        $today = now()->toDateString();

        // 1. Query eligible lots joined with batches for FEFO sorting
        $query = StockLot::query()
            ->select('stock_lots.*')
            ->join('batches', 'stock_lots.batch_id', '=', 'batches.id')
            ->where('batches.medicine_id', $medicineId)
            ->where('batches.status', 'sellable')
            ->whereDate('batches.expiry_date', '>=', $today)
            ->where('stock_lots.remaining_base_qty', '>', 0)
            ->orderBy('batches.expiry_date', 'asc')
            ->orderBy('stock_lots.id', 'asc')
            ->lockForUpdate();

        if ($locationId) {
            $query->where('stock_lots.location_id', $locationId);
        }

        $eligibleLots = $query->get();

        $totalAvailable = $eligibleLots->sum('remaining_base_qty');

        if ($totalAvailable < $requiredBaseQty) {
            throw new RuntimeException(
                "Insufficient stock for '{$medicine->brand_name}'. Requested {$requiredBaseQty} {$medicine->base_unit}(s), available: {$totalAvailable}."
            );
        }

        $allocations = [];
        $remainingToAllocate = $requiredBaseQty;

        foreach ($eligibleLots as $lot) {
            if ($remainingToAllocate <= 0) {
                break;
            }

            $deductQty = min($lot->remaining_base_qty, $remainingToAllocate);

            // Deduct directly from remaining_base_qty
            $lot->remaining_base_qty -= $deductQty;
            $lot->save();

            $allocations[] = [
                'stock_lot_id' => $lot->id,
                'batch_id' => $lot->batch_id,
                'location_id' => $lot->location_id,
                'base_quantity' => $deductQty,
                'cost_per_base_unit_paisa' => (float) $lot->cost_per_base_unit * 100, // paisa
            ];

            $remainingToAllocate -= $deductQty;
        }

        return $allocations;
    }

    /**
     * Record inventory receiving for a purchase line
     */
    public function receiveStock(
        int $medicineId,
        string $batchNumber,
        string $expiryDate,
        int $receivedBaseQty,
        float $unitCostPaisa,
        int $locationId,
        int $userId,
        string $receiptReference,
        ?string $notes = null,
        ?int $referenceId = null
    ): StockLot {
        if ($receivedBaseQty <= 0) {
            throw new InvalidArgumentException("Received quantity must be positive.");
        }

        // 1. Find or create Batch with strict expiry consistency check (A14)
        $batch = Batch::where('medicine_id', $medicineId)
            ->where('batch_number', trim($batchNumber))
            ->first();

        if ($batch) {
            if ($batch->expiry_date->format('Y-m-d') !== $expiryDate) {
                throw new InvalidArgumentException("Batch {$batchNumber} already exists for this medicine with a different expiry date ({$batch->expiry_date->format('Y-m-d')}). Inconsistent expiry dates are not allowed.");
            }
        } else {
            $batch = Batch::create([
                'medicine_id' => $medicineId,
                'batch_number' => trim($batchNumber),
                'expiry_date' => $expiryDate,
                'status' => 'sellable',
            ]);
        }

        $costInRupees = $unitCostPaisa / 100;

        // 2. Create StockLot to preserve distinct receipt cost layers
        $stockLot = StockLot::create([
            'batch_id' => $batch->id,
            'location_id' => $locationId,
            'receipt_reference' => $receiptReference,
            'purchase_price_paisa' => (int) round($unitCostPaisa),
            'cost_per_base_unit' => $costInRupees,
            'received_base_qty' => $receivedBaseQty,
            'remaining_base_qty' => $receivedBaseQty,
        ]);

        // 3. Record stock movement
        StockMovement::create([
            'medicine_id' => $medicineId,
            'batch_id' => $batch->id,
            'stock_lot_id' => $stockLot->id,
            'location_id' => $locationId,
            'movement_type' => 'purchase',
            'reference_type' => 'Purchase',
            'reference_id' => $referenceId ?? $stockLot->id,
            'quantity_base_unit' => $receivedBaseQty,
            'cost_per_base_unit' => $costInRupees,
            'user_id' => $userId,
            'notes' => $notes ?? "Received from purchase {$receiptReference}",
            'created_at' => now(),
        ]);

        return $stockLot;
    }

    /**
     * Return to stock from a customer return or restock
     */
    public function restockItem(
        int $stockLotId,
        int $medicineId,
        int $baseQty,
        string $disposition,
        int $userId,
        int $salesReturnId,
        ?string $notes = null
    ): void {
        $lot = StockLot::findOrFail($stockLotId);

        if ($disposition === 'restock') {
            $lot->increment('remaining_base_qty', $baseQty);

            StockMovement::create([
                'medicine_id' => $medicineId,
                'batch_id' => $lot->batch_id,
                'stock_lot_id' => $lot->id,
                'location_id' => $lot->location_id,
                'movement_type' => 'customer_return',
                'reference_type' => 'SalesReturn',
                'reference_id' => $salesReturnId,
                'quantity_base_unit' => $baseQty,
                'cost_per_base_unit' => $lot->cost_per_base_unit,
                'user_id' => $userId,
                'notes' => $notes ?? "Restocked from customer return",
                'created_at' => now(),
            ]);
        } elseif ($disposition === 'quarantine') {
            StockMovement::create([
                'medicine_id' => $medicineId,
                'batch_id' => $lot->batch_id,
                'stock_lot_id' => $lot->id,
                'location_id' => $lot->location_id,
                'movement_type' => 'adjustment',
                'reference_type' => 'SalesReturn',
                'reference_id' => $salesReturnId,
                'quantity_base_unit' => $baseQty, // Record physical quantity returned to quarantine
                'cost_per_base_unit' => $lot->cost_per_base_unit,
                'user_id' => $userId,
                'notes' => $notes ?? "Placed in quarantine after return",
                'created_at' => now(),
            ]);
        } else {
            // Write-off immediately
            StockMovement::create([
                'medicine_id' => $medicineId,
                'batch_id' => $lot->batch_id,
                'stock_lot_id' => $lot->id,
                'location_id' => $lot->location_id,
                'movement_type' => 'write_off',
                'reference_type' => 'SalesReturn',
                'reference_id' => $salesReturnId,
                'quantity_base_unit' => $baseQty, // Record physical quantity written off
                'cost_per_base_unit' => $lot->cost_per_base_unit,
                'user_id' => $userId,
                'notes' => $notes ?? "Written off upon customer return",
                'created_at' => now(),
            ]);
        }
    }
}
