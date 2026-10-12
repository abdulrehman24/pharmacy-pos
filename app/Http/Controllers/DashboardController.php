<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StockLot;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::today('Asia/Karachi');
        $expiryDays = (int) Setting::get('expiry_alert_days', 90);
        $expiryThreshold = $today->copy()->addDays($expiryDays)->toDateString();

        // Today's Sales
        $todaySales = Sale::where('status', 'posted')
            ->whereDate('created_at', $today)
            ->get();

        $grossSalesPaisa = (int) $todaySales->sum('subtotal_paisa');
        $discountPaisa = (int) $todaySales->sum('discount_paisa');
        $taxPaisa = (int) $todaySales->sum('tax_paisa');
        $totalSalesPaisa = (int) $todaySales->sum('total_paisa');

        // Today's Returns
        $todayReturns = SalesReturn::whereDate('created_at', $today)->get();
        $returnsPaisa = (int) $todayReturns->sum('total_refund_paisa');

        $netSalesPaisa = $totalSalesPaisa - $returnsPaisa;

        // Payment collections split for today
        $cashCollectionsPaisa = (int) $todaySales->where('payment_method', 'cash')->sum('received_paisa')
            - (int) $todaySales->where('payment_method', 'cash')->sum('change_paisa');
        $cardCollectionsPaisa = (int) $todaySales->where('payment_method', 'card')->sum('total_paisa');
        $digitalCollectionsPaisa = (int) $todaySales->whereIn('payment_method', ['jazzcash', 'easypaisa', 'bank_transfer'])->sum('total_paisa');
        $creditSalesPaisa = (int) $todaySales->where('payment_method', 'credit')->sum('total_paisa');

        // True Cost of Goods Sold for today's sales
        $todayAllocations = \App\Models\SaleAllocation::whereIn('sale_id', $todaySales->pluck('id'))->get();
        $cogsPaisa = (int) round($todayAllocations->sum(function ($alloc) {
            return $alloc->base_quantity * (float) $alloc->cost_per_base_unit_paisa;
        }));

        $grossMarginPaisa = max(0, ($netSalesPaisa - $taxPaisa) - $cogsPaisa);

        // Customer outstanding total & Supplier payable total
        $totalCustomerReceivablesPaisa = (int) Customer::where('balance_paisa', '>', 0)->sum('balance_paisa');
        $totalSupplierPayablesPaisa = (int) Supplier::where('balance_paisa', '>', 0)->sum('balance_paisa');

        // Low stock medicines
        $lowStockMedicines = Medicine::where('is_active', true)
            ->get()
            ->filter(function ($med) {
                return $med->totalSellableStockBaseUnit() <= $med->reorder_level;
            })
            ->map(function ($med) {
                return [
                    'id' => $med->id,
                    'code' => $med->code,
                    'brand_name' => $med->brand_name,
                    'generic_name' => $med->generic_name,
                    'strength' => $med->strength,
                    'base_unit' => $med->base_unit,
                    'reorder_level' => $med->reorder_level,
                    'current_stock' => $med->totalSellableStockBaseUnit(),
                    'rack_shelf' => $med->rack_shelf,
                ];
            })
            ->values()
            ->take(8);

        // Medicines expiring within alert threshold
        $expiringBatches = Batch::with('medicine')
            ->where('status', 'sellable')
            ->whereDate('expiry_date', '<=', $expiryThreshold)
            ->whereHas('stockLots', fn($q) => $q->where('remaining_base_qty', '>', 0))
            ->orderBy('expiry_date', 'asc')
            ->take(8)
            ->get()
            ->map(function ($batch) {
                $remaining = (int) $batch->stockLots()->sum('remaining_base_qty');
                return [
                    'id' => $batch->id,
                    'medicine_name' => $batch->medicine->brand_name . ' ' . $batch->medicine->strength,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $batch->expiry_date->format('d-M-Y'),
                    'is_expired' => $batch->expiry_date->isPast(),
                    'remaining_base_qty' => $remaining,
                    'base_unit' => $batch->medicine->base_unit,
                ];
            });

        // Fast moving medicines (top sold in last 30 days)
        $fastMovers = \App\Models\SaleLine::selectRaw('medicine_name, SUM(total_base_quantity) as total_units, SUM(total_paisa) as total_revenue_paisa')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('medicine_name')
            ->orderByDesc('total_units')
            ->take(5)
            ->get();

        // Active open shift for current user
        $currentShift = Shift::with('counter')
            ->where('status', 'open')
            ->where('user_id', $request->user()->id)
            ->first();

        return Inertia::render('Dashboard', [
            'stats' => [
                'gross_sales_paisa' => $grossSalesPaisa,
                'discount_paisa' => $discountPaisa,
                'tax_paisa' => $taxPaisa,
                'returns_paisa' => $returnsPaisa,
                'net_sales_paisa' => $netSalesPaisa,
                'gross_margin_paisa' => $grossMarginPaisa,
                'cash_collections_paisa' => $cashCollectionsPaisa,
                'card_collections_paisa' => $cardCollectionsPaisa,
                'digital_collections_paisa' => $digitalCollectionsPaisa,
                'credit_sales_paisa' => $creditSalesPaisa,
                'customer_receivables_paisa' => $totalCustomerReceivablesPaisa,
                'supplier_payables_paisa' => $totalSupplierPayablesPaisa,
                'invoices_count' => $todaySales->count(),
            ],
            'lowStock' => $lowStockMedicines,
            'expiringBatches' => $expiringBatches,
            'fastMovers' => $fastMovers,
            'currentShift' => $currentShift,
        ]);
    }
}
