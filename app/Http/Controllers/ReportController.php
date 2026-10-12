<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleAllocation;
use App\Models\SaleLine;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use App\Models\Shift;
use App\Models\StockLot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        // 1. Sales Summary for period
        $sales = Sale::where('status', 'posted')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->get();

        $grossSalesPaisa = (int) $sales->sum('subtotal_paisa');
        $discountPaisa = (int) $sales->sum('discount_paisa');
        $taxPaisa = (int) $sales->sum('tax_paisa');
        $totalSalesPaisa = (int) $sales->sum('total_paisa');

        $returns = SalesReturn::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->get();
        $returnsPaisa = (int) $returns->sum('total_refund_paisa');
        $netSalesPaisa = $totalSalesPaisa - $returnsPaisa;

        // True COGS for period (subtracting returned stock COGS)
        $allocations = SaleAllocation::whereIn('sale_id', $sales->pluck('id'))->get();
        $grossCogsPaisa = (int) round($allocations->sum(function ($alloc) {
            return $alloc->base_quantity * (float) $alloc->cost_per_base_unit_paisa;
        }));

        $returnedStockCostPaisa = (int) round(
            SalesReturnLine::whereIn('sales_return_id', $returns->pluck('id'))
                ->where('disposition', 'restock')
                ->whereNotNull('stock_lot_id')
                ->with('stockLot')
                ->get()
                ->sum(function ($rLine) {
                    $costPerBaseUnit = $rLine->stockLot ? (float) $rLine->stockLot->cost_per_base_unit : 0.0;
                    return $rLine->base_quantity * ($costPerBaseUnit * 100);
                })
        );
        $cogsPaisa = max(0, $grossCogsPaisa - $returnedStockCostPaisa);
        $grossMarginPaisa = ($netSalesPaisa - $taxPaisa) - $cogsPaisa;

        // Payment method breakdown
        $paymentMethods = [
            'cash' => (int) $sales->where('payment_method', 'cash')->sum('total_paisa'),
            'card' => (int) $sales->where('payment_method', 'card')->sum('total_paisa'),
            'jazzcash' => (int) $sales->where('payment_method', 'jazzcash')->sum('total_paisa'),
            'easypaisa' => (int) $sales->where('payment_method', 'easypaisa')->sum('total_paisa'),
            'bank_transfer' => (int) $sales->where('payment_method', 'bank_transfer')->sum('total_paisa'),
            'credit' => (int) $sales->where('payment_method', 'credit')->sum('total_paisa'),
        ];

        // 2. Top medicines by revenue & margin (group by medicine_id and sum base quantity)
        $itemSales = SaleLine::selectRaw('medicine_id, medicine_name, SUM(total_base_quantity) as total_qty, SUM(total_paisa) as total_revenue_paisa')
            ->whereIn('sale_id', $sales->pluck('id'))
            ->groupBy('medicine_id', 'medicine_name')
            ->orderByDesc('total_revenue_paisa')
            ->take(10)
            ->get();

        // 3. Stock Valuation Summary
        $stockLots = StockLot::where('remaining_base_qty', '>', 0)->get();
        $totalStockUnits = (int) $stockLots->sum('remaining_base_qty');
        $totalStockValuationPaisa = (int) round($stockLots->sum(function ($lot) {
            return $lot->remaining_base_qty * ((float) $lot->cost_per_base_unit * 100);
        }));

        // 4. Expiry Risk Summary (at risk in <= 90 days)
        $expiryLimit = now()->addDays(90)->toDateString();
        $expiringLots = StockLot::whereHas('batch', function ($q) use ($expiryLimit) {
            $q->where('status', 'sellable')->whereDate('expiry_date', '<=', $expiryLimit);
        })->where('remaining_base_qty', '>', 0)->get();

        $expiryAtRiskUnits = (int) $expiringLots->sum('remaining_base_qty');
        $expiryAtRiskCostPaisa = (int) round($expiringLots->sum(function ($lot) {
            return $lot->remaining_base_qty * ((float) $lot->cost_per_base_unit * 100);
        }));

        return Inertia::render('Reports/Index', [
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'salesSummary' => [
                'gross_sales_paisa' => $grossSalesPaisa,
                'discount_paisa' => $discountPaisa,
                'tax_paisa' => $taxPaisa,
                'returns_paisa' => $returnsPaisa,
                'net_sales_paisa' => $netSalesPaisa,
                'cogs_paisa' => $cogsPaisa,
                'gross_margin_paisa' => $grossMarginPaisa,
                'invoices_count' => $sales->count(),
                'payment_methods' => $paymentMethods,
            ],
            'itemSales' => $itemSales,
            'inventorySummary' => [
                'total_units' => $totalStockUnits,
                'valuation_paisa' => $totalStockValuationPaisa,
                'expiry_at_risk_units' => $expiryAtRiskUnits,
                'expiry_at_risk_cost_paisa' => $expiryAtRiskCostPaisa,
            ],
        ]);
    }

    /**
     * Export detailed sales report as CSV
     */
    public function exportSalesCsv(Request $request): StreamedResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $sales = Sale::with(['cashier', 'customer', 'lines'])
            ->where('status', 'posted')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->orderBy('created_at', 'desc')
            ->get();

        $fileName = "sales_report_{$startDate}_to_{$endDate}.csv";

        return response()->streamDownload(function () use ($sales) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice No', 'Date Time', 'Cashier', 'Customer', 'Items Count', 'Subtotal PKR', 'Discount PKR', 'Tax PKR', 'Total PKR', 'Payment Method']);

            foreach ($sales as $sale) {
                fputcsv($handle, [
                    $sale->invoice_number,
                    $sale->created_at->format('Y-m-d H:i:s'),
                    $sale->cashier?->name ?? 'N/A',
                    $sale->customer?->name ?? 'Walk-in',
                    $sale->lines->count(),
                    number_format($sale->subtotal_paisa / 100, 2),
                    number_format($sale->discount_paisa / 100, 2),
                    number_format($sale->tax_paisa / 100, 2),
                    number_format($sale->total_paisa / 100, 2),
                    strtoupper($sale->payment_method),
                ]);
            }
            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    /**
     * Export stock valuation report as CSV
     */
    public function exportStockCsv(): StreamedResponse
    {
        $lots = StockLot::with(['batch.medicine', 'location'])
            ->where('remaining_base_qty', '>', 0)
            ->get();

        $fileName = "stock_valuation_" . now()->format('Ymd_His') . ".csv";

        return response()->streamDownload(function () use ($lots) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Medicine Code', 'Medicine Brand', 'Dosage Form', 'Base Unit', 'Batch No', 'Expiry Date', 'Status', 'Remaining Qty', 'Unit Cost PKR', 'Valuation PKR']);

            foreach ($lots as $lot) {
                $med = $lot->batch->medicine;
                $cost = (float) $lot->cost_per_base_unit;
                $val = $lot->remaining_base_qty * $cost;

                fputcsv($handle, [
                    $med->code,
                    $med->brand_name . ' ' . $med->strength,
                    $med->dosage_form,
                    $med->base_unit,
                    $lot->batch->batch_number,
                    $lot->batch->expiry_date->format('Y-m-d'),
                    $lot->batch->status,
                    $lot->remaining_base_qty,
                    number_format($cost, 2),
                    number_format($val, 2),
                ]);
            }
            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
