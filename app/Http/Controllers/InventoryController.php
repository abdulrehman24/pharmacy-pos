<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\PostStockAdjustment;
use App\Models\Batch;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\WriteOff;
use App\Models\WriteOffLine;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        protected PostStockAdjustment $postAdjustment
    ) {}

    public function index(Request $request): Response
    {
        $query = Batch::with(['medicine', 'stockLots.location'])
            ->orderBy('expiry_date', 'asc');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('medicine', function ($mq) use ($search) {
                      $mq->where('brand_name', 'LIKE', "%{$search}%")
                         ->orWhere('generic_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $batches = $query->paginate(20)->withQueryString();

        $batches->getCollection()->transform(function ($batch) {
            $remaining = (int) $batch->stockLots->sum('remaining_base_qty');
            $costPaisa = (int) round($batch->stockLots->avg('cost_per_base_unit') * 100);
            return [
                'id' => $batch->id,
                'medicine_id' => $batch->medicine_id,
                'medicine_name' => $batch->medicine->brand_name . ($batch->medicine->strength ? " ({$batch->medicine->strength})" : ''),
                'base_unit' => $batch->medicine->base_unit,
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date->format('d-M-Y'),
                'is_expired' => $batch->expiry_date->isPast(),
                'days_to_expiry' => (int) now()->diffInDays($batch->expiry_date, false),
                'status' => $batch->status,
                'remaining_base_qty' => $remaining,
                'unit_cost_paisa' => $costPaisa,
            ];
        });

        return Inertia::render('Inventory/Index', [
            'batches' => $batches,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Stock Movement Audit Ledger
     */
    public function ledger(Request $request): Response
    {
        $query = StockMovement::with(['medicine', 'batch', 'user', 'location'])
            ->latest('created_at');

        if ($medicineId = $request->get('medicine_id')) {
            $query->where('medicine_id', $medicineId);
        }

        if ($type = $request->get('movement_type')) {
            $query->where('movement_type', $type);
        }

        $movements = $query->paginate(25)->withQueryString();
        $medicines = Medicine::where('is_active', true)->select('id', 'brand_name', 'code')->get();

        return Inertia::render('Inventory/Ledger', [
            'movements' => $movements,
            'medicines' => $medicines,
            'filters' => $request->only(['medicine_id', 'movement_type']),
        ]);
    }

    /**
     * Physical stock counts & adjustments
     */
    public function counts(): Response
    {
        $counts = StockCount::with(['conductor', 'approver', 'location'])
            ->latest()
            ->paginate(15);

        $locations = Location::all();
        $medicines = Medicine::with(['batches.stockLots'])
            ->where('is_active', true)
            ->get();

        return Inertia::render('Inventory/Counts', [
            'counts' => $counts,
            'locations' => $locations,
            'medicines' => $medicines,
        ]);
    }

    public function storeCount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.medicine_id' => ['required', 'exists:medicines,id'],
            'lines.*.batch_id' => ['required', 'exists:batches,id'],
            'lines.*.stock_lot_id' => ['required', 'exists:stock_lots,id'],
            'lines.*.system_quantity' => ['required', 'integer'],
            'lines.*.counted_quantity' => ['required', 'integer', 'min:0'],
            'lines.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $datePrefix = now()->format('Ymd');
        $countNumber = 'CNT-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(3)));

        DB::transaction(function () use ($validated, $countNumber, $request) {
            $stockCount = StockCount::create([
                'count_number' => $countNumber,
                'location_id' => $validated['location_id'],
                'status' => 'draft',
                'conducted_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['lines'] as $line) {
                $lot = StockLot::where('id', $line['stock_lot_id'])->lockForUpdate()->firstOrFail();
                // Authoritative system quantity derived strictly server-side
                $serverSystemQty = (int) $lot->remaining_base_qty;
                $countedQty = (int) $line['counted_quantity'];
                $variance = $countedQty - $serverSystemQty;

                StockCountLine::create([
                    'stock_count_id' => $stockCount->id,
                    'medicine_id' => $lot->batch->medicine_id,
                    'batch_id' => $lot->batch_id,
                    'stock_lot_id' => $lot->id,
                    'system_quantity' => $serverSystemQty,
                    'counted_quantity' => $countedQty,
                    'variance_quantity' => $variance,
                    'cost_per_base_unit' => $lot->cost_per_base_unit,
                    'reason' => $line['reason'] ?? 'Physical audit',
                ]);
            }
        });

        return redirect()->route('inventory.counts')
            ->with('success', "Stock count {$countNumber} logged as draft. Awaiting manager approval.");
    }

    public function approveCount(StockCount $stockCount, Request $request): RedirectResponse
    {
        $this->postAdjustment->execute($stockCount, $request->user());

        \App\Models\AuditEvent::log(
            'stock_count_approved',
            'StockCount',
            $stockCount->id,
            ['status' => 'draft'],
            ['status' => 'approved', 'count_number' => $stockCount->count_number]
        );

        return redirect()->route('inventory.counts')
            ->with('success', "Stock count {$stockCount->count_number} approved and stock balances adjusted.");
    }

    /**
     * Write-off expired or damaged stock
     */
    public function writeOff(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stock_lot_id' => ['required', 'exists:stock_lots,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:expired,damaged,lost'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $qty = $validated['quantity'];

        try {
            DB::transaction(function () use ($validated, $qty, $request) {
                $lot = StockLot::with('batch.medicine')
                    ->where('id', $validated['stock_lot_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($qty > $lot->remaining_base_qty) {
                    throw new \InvalidArgumentException("Cannot write off more than remaining quantity ({$lot->remaining_base_qty}).");
                }

                $lossPaisa = (int) round($qty * ((float) $lot->cost_per_base_unit * 100));

                $datePrefix = now()->format('Ymd');
                $writeOffNumber = 'WO-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(3)));

                $writeOff = WriteOff::create([
                    'write_off_number' => $writeOffNumber,
                    'user_id' => $request->user()->id,
                    'approved_by' => $request->user()->id,
                    'total_loss_paisa' => $lossPaisa,
                    'type' => $validated['type'],
                    'reason' => $validated['reason'],
                ]);

                WriteOffLine::create([
                    'write_off_id' => $writeOff->id,
                    'stock_lot_id' => $lot->id,
                    'medicine_id' => $lot->batch->medicine_id,
                    'base_quantity' => $qty,
                    'unit_cost_paisa' => (float) $lot->cost_per_base_unit * 100,
                    'total_loss_paisa' => $lossPaisa,
                ]);

                $lot->decrement('remaining_base_qty', $qty);

                // Record stock movement
                StockMovement::create([
                    'medicine_id' => $lot->batch->medicine_id,
                    'batch_id' => $lot->batch_id,
                    'stock_lot_id' => $lot->id,
                    'location_id' => $lot->location_id,
                    'movement_type' => 'write_off',
                    'reference_type' => 'WriteOff',
                    'reference_id' => $writeOff->id,
                    'quantity_base_unit' => -$qty,
                    'cost_per_base_unit' => $lot->cost_per_base_unit,
                    'user_id' => $request->user()->id,
                    'notes' => "Stock write-off {$writeOffNumber}: {$validated['reason']}",
                    'created_at' => now(),
                ]);

                \App\Models\AuditEvent::log(
                    'stock_written_off',
                    'WriteOff',
                    $writeOff->id,
                    null,
                    ['total_loss_paisa' => $lossPaisa, 'type' => $validated['type'], 'reason' => $validated['reason']]
                );
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()->route('inventory.index')
            ->with('success', "Stock write-off successfully posted.");
    }
}
