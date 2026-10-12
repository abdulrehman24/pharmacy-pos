<?php

namespace App\Http\Controllers;

use App\Actions\Returns\ProcessCustomerReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    public function __construct(
        protected ProcessCustomerReturn $processCustomerReturn
    ) {}

    public function index(): Response
    {
        $returns = SalesReturn::with(['sale', 'customer', 'cashier'])
            ->latest('created_at')
            ->paginate(15);

        $supplierReturns = SupplierReturn::with(['supplier', 'user'])
            ->latest('created_at')
            ->take(10)
            ->get();

        return Inertia::render('Returns/Index', [
            'customerReturns' => $returns,
            'supplierReturns' => $supplierReturns,
        ]);
    }

    /**
     * Search invoice to return items against
     */
    public function searchInvoice(Request $request): JsonResponse
    {
        $invoiceNumber = trim($request->get('invoice_number', ''));

        $sale = Sale::with(['lines.medicine', 'customer'])
            ->where('invoice_number', $invoiceNumber)
            ->first();

        if (!$sale) {
            return response()->json(['error' => 'Invoice not found.'], 404);
        }

        if ($sale->status !== 'posted') {
            return response()->json(['error' => "Invoice status is '{$sale->status}'. Only posted invoices can be returned."], 422);
        }

        $lines = $sale->lines->map(function ($line) {
            return [
                'id' => $line->id,
                'medicine_id' => $line->medicine_id,
                'medicine_name' => $line->medicine_name,
                'package_name' => $line->package_name,
                'sold_quantity' => $line->quantity,
                'already_returned' => $line->returnedQuantity(),
                'returnable_quantity' => $line->remainingReturnableQty(),
                'unit_price_paisa' => (int) round($line->total_paisa / $line->quantity),
            ];
        });

        return response()->json([
            'sale' => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'created_at' => $sale->created_at->format('d-M-Y h:i A'),
                'total_paisa' => $sale->total_paisa,
                'payment_method' => $sale->payment_method,
                'customer' => $sale->customer ? ['id' => $sale->customer->id, 'name' => $sale->customer->name] : null,
            ],
            'lines' => $lines,
        ]);
    }

    public function storeCustomerReturn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sale_id' => ['required', 'exists:sales,id'],
            'refund_method' => ['required', 'string', 'in:cash,credit_adjustment'],
            'reason' => ['required', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_line_id' => ['required', 'exists:sale_lines,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.disposition' => ['required', 'string', 'in:restock,quarantine,write_off'],
        ]);

        $return = $this->processCustomerReturn->execute($validated, $request->user());

        return redirect()->route('returns.index')
            ->with('success', "Customer return {$return->return_number} processed. Refund: PKR " . ($return->total_refund_paisa / 100));
    }

    public function storeSupplierReturn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'stock_lot_id' => ['required', 'exists:stock_lots,id'],
            'quantity_base_unit' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $supplier = Supplier::findOrFail($validated['supplier_id']);
        $lot = StockLot::with('batch.medicine')->findOrFail($validated['stock_lot_id']);
        $qty = $validated['quantity_base_unit'];

        if ($qty > $lot->remaining_base_qty) {
            return back()->withErrors(['quantity_base_unit' => "Cannot return more than available lot stock ({$lot->remaining_base_qty})."]);
        }

        $datePrefix = now()->format('Ymd');
        $returnNumber = 'SRET-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(3)));
        $unitCostPaisa = (float) $lot->cost_per_base_unit * 100;
        $totalCreditPaisa = (int) round($qty * $unitCostPaisa);

        DB::transaction(function () use ($supplier, $lot, $qty, $returnNumber, $unitCostPaisa, $totalCreditPaisa, $validated, $request) {
            $supReturn = SupplierReturn::create([
                'return_number' => $returnNumber,
                'supplier_id' => $supplier->id,
                'user_id' => $request->user()->id,
                'total_credit_paisa' => $totalCreditPaisa,
                'reason' => $validated['reason'],
                'status' => 'completed',
            ]);

            SupplierReturnLine::create([
                'supplier_return_id' => $supReturn->id,
                'stock_lot_id' => $lot->id,
                'medicine_id' => $lot->batch->medicine_id,
                'base_quantity' => $qty,
                'unit_cost_paisa' => $unitCostPaisa,
                'total_paisa' => $totalCreditPaisa,
            ]);

            $lot->decrement('remaining_base_qty', $qty);

            // Record movement
            StockMovement::create([
                'medicine_id' => $lot->batch->medicine_id,
                'batch_id' => $lot->batch_id,
                'stock_lot_id' => $lot->id,
                'location_id' => $lot->location_id,
                'movement_type' => 'supplier_return',
                'reference_type' => 'SupplierReturn',
                'reference_id' => $supReturn->id,
                'quantity_base_unit' => -$qty,
                'cost_per_base_unit' => $lot->cost_per_base_unit,
                'user_id' => $request->user()->id,
                'notes' => "Supplier return {$returnNumber}: {$validated['reason']}",
                'created_at' => now(),
            ]);

            // Decrement supplier payable balance
            $supplier->decrement('balance_paisa', $totalCreditPaisa);
        });

        return back()->with('success', "Supplier return {$returnNumber} recorded successfully.");
    }
}
