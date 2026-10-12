<?php

namespace App\Http\Controllers;

use App\Actions\Sales\FinalizeSale;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\MedicineBarcode;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function __construct(
        protected FinalizeSale $finalizeSale
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Check if there is an active shift for this user or counter
        $currentShift = Shift::with('counter')
            ->where('status', 'open')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->latest()
            ->first();

        // If no shift is open for current user, check if any open shift exists on counter
        if (!$currentShift) {
            $currentShift = Shift::with('counter')->where('status', 'open')->latest()->first();
        }

        $counters = Counter::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)
            ->select('id', 'code', 'name', 'phone', 'credit_limit_paisa', 'balance_paisa')
            ->get();

        $shiftSalesTotalPaisa = 0;
        $shiftSalesCount = 0;
        if ($currentShift) {
            $shiftSalesCount = Sale::where('shift_id', $currentShift->id)->count();
            $shiftSalesTotalPaisa = (int) Sale::where('shift_id', $currentShift->id)->sum('total_paisa');
        }

        $quickMedicines = Medicine::with('packages')
            ->where('is_active', true)
            ->take(16)
            ->get()
            ->map(function ($med) {
                return [
                    'id' => $med->id,
                    'code' => $med->code,
                    'brand_name' => $med->brand_name,
                    'generic_name' => $med->generic_name,
                    'strength' => $med->strength,
                    'dosage_form' => $med->dosage_form,
                    'base_unit' => $med->base_unit,
                    'rack_shelf' => $med->rack_shelf,
                    'prescription_required' => $med->prescription_required,
                    'sellable_stock_base' => $med->totalSellableStockBaseUnit(),
                    'packages' => $med->packages->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'base_quantity' => $p->base_quantity,
                        'sell_price_paisa' => $p->default_sell_price_paisa,
                        'is_default_sale' => $p->is_default_sale,
                    ]),
                ];
            });

        $taxPercentage = (int) Setting::get('tax_percentage', 0);
        $currencySymbol = (string) Setting::get('currency_symbol', 'PKR');

        return Inertia::render('Pos/Index', [
            'currentShift' => $currentShift,
            'counters' => $counters,
            'customers' => $customers,
            'quickMedicines' => $quickMedicines,
            'shiftStats' => [
                'sales_count' => $shiftSalesCount,
                'sales_total_paisa' => $shiftSalesTotalPaisa,
            ],
            'config' => [
                'tax_percentage' => $taxPercentage,
                'currency_symbol' => $currencySymbol,
            ],
        ]);
    }

    /**
     * Search medicines by brand, generic, barcode or code with live sellable stock
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->get('q', ''));
        $today = now()->toDateString();

        if (strlen($query) === 0) {
            return response()->json([]);
        }

        // 1. Direct barcode match first
        $barcodeMatch = MedicineBarcode::with(['medicine.packages', 'medicinePackage'])
            ->where('barcode', $query)
            ->first();

        if ($barcodeMatch && $barcodeMatch->medicine && $barcodeMatch->medicine->is_active) {
            $med = $barcodeMatch->medicine;
            $stock = $med->totalSellableStockBaseUnit();

            return response()->json([[
                'id' => $med->id,
                'code' => $med->code,
                'brand_name' => $med->brand_name,
                'generic_name' => $med->generic_name,
                'strength' => $med->strength,
                'dosage_form' => $med->dosage_form,
                'base_unit' => $med->base_unit,
                'rack_shelf' => $med->rack_shelf,
                'prescription_required' => $med->prescription_required,
                'sellable_stock_base' => $stock,
                'selected_package_id' => $barcodeMatch->medicine_package_id,
                'packages' => $med->packages->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'base_quantity' => $p->base_quantity,
                    'sell_price_paisa' => $p->default_sell_price_paisa,
                    'is_default_sale' => $p->is_default_sale,
                ]),
                'matched_barcode' => true,
            ]]);
        }

        // 2. Search brand name, generic name or internal code
        $medicines = Medicine::with('packages')
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('brand_name', 'LIKE', "%{$query}%")
                  ->orWhere('generic_name', 'LIKE', "%{$query}%")
                  ->orWhere('code', 'LIKE', "%{$query}%");
            })
            ->take(15)
            ->get()
            ->map(function ($med) {
                return [
                    'id' => $med->id,
                    'code' => $med->code,
                    'brand_name' => $med->brand_name,
                    'generic_name' => $med->generic_name,
                    'strength' => $med->strength,
                    'dosage_form' => $med->dosage_form,
                    'base_unit' => $med->base_unit,
                    'rack_shelf' => $med->rack_shelf,
                    'prescription_required' => $med->prescription_required,
                    'sellable_stock_base' => $med->totalSellableStockBaseUnit(),
                    'packages' => $med->packages->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'base_quantity' => $p->base_quantity,
                        'sell_price_paisa' => $p->default_sell_price_paisa,
                        'is_default_sale' => $p->is_default_sale,
                    ]),
                ];
            });

        return response()->json($medicines);
    }

    /**
     * Finalize checkout
     */
    public function checkout(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'shift_id' => ['nullable', 'integer'],
            'counter_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer'],
            'items.*.medicine_package_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_paisa' => ['nullable', 'integer', 'min:0'],
            'discount_paisa' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['required', 'string', 'in:cash,card,jazzcash,easypaisa,bank_transfer,credit'],
            'received_paisa' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sale = $this->finalizeSale->execute($validated, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'sale' => $sale,
                'receipt_url' => route('sales.receipt', $sale->id),
            ]);
        }

        return redirect()->route('sales.receipt', $sale->id);
    }
}
