<?php

namespace App\Http\Controllers;

use App\Actions\Sales\FinalizeSale;
use App\Models\AuditEvent;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\MedicineBarcode;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

        // Check if there is an active shift strictly for this user
        $currentShift = Shift::with('counter')
            ->where('status', 'open')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

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
            'activeStaff' => User::where('is_active', true)
                ->whereNotNull('pin_code')
                ->where('pin_code', '!=', '')
                ->select('id', 'name', 'role')
                ->orderBy('name')
                ->get(),
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
            'prescription_id' => ['nullable', 'integer', 'exists:prescriptions,id'],
        ]);

        try {
            $sale = $this->finalizeSale->execute($validated, $request->user());
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 409);
            }
            throw $e;
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'sale' => $sale,
                'receipt_url' => route('sales.receipt', $sale->id),
            ]);
        }

        return redirect()->route('sales.receipt', $sale->id);
    }

    /**
     * Quick Cashier Switch via PIN code at POS terminal
     */
    public function switchCashier(Request $request): JsonResponse
    {
        $request->validate([
            'pin_code' => ['required', 'string', 'max:10'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $throttleKey = 'pin_switch|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'message' => "Too many PIN attempts. Please wait {$seconds} seconds.",
            ], 429);
        }

        $targetUser = null;
        if ($request->filled('user_id')) {
            $user = User::where('id', $request->user_id)->where('is_active', true)->first();
            if ($user && !empty($user->pin_code) && hash_equals((string) $user->pin_code, (string) $request->pin_code)) {
                $targetUser = $user;
            }
        } else {
            // Direct 4-digit PIN lookup across active users
            $matches = User::where('is_active', true)
                ->where('pin_code', (string) $request->pin_code)
                ->get();
            if ($matches->count() === 1) {
                $targetUser = $matches->first();
            }
        }

        if (!$targetUser) {
            RateLimiter::hit($throttleKey, 60);
            return response()->json([
                'success' => false,
                'message' => 'Invalid PIN code. Cashier not verified.',
            ], 422);
        }

        RateLimiter::clear($throttleKey);

        $previousUser = $request->user();
        Auth::login($targetUser);
        $request->session()->regenerate();

        AuditEvent::log(
            'cashier_switched',
            'User',
            $targetUser->id,
            ['previous_user_id' => $previousUser?->id, 'previous_user' => $previousUser?->name],
            ['new_user_id' => $targetUser->id, 'new_user' => $targetUser->name]
        );

        return response()->json([
            'success' => true,
            'message' => "Switched active cashier to {$targetUser->name}.",
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'role' => $targetUser->role,
            ],
        ]);
    }
}
