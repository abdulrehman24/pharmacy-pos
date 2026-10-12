<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Customer::query()->orderBy('name');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        $customers = $query->paginate(15)->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:customers,code'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'cnic' => ['nullable', 'string', 'max:25'],
            'address' => ['nullable', 'string', 'max:500'],
            'credit_limit_paisa' => ['nullable', 'integer', 'min:0'],
        ]);

        Customer::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'cnic' => $validated['cnic'],
            'address' => $validated['address'],
            'credit_limit_paisa' => $validated['credit_limit_paisa'] ?? 0,
            'balance_paisa' => 0,
            'is_active' => true,
        ]);

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }

    public function quickStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'cnic' => ['nullable', 'string', 'max:25'],
            'credit_limit_paisa' => ['nullable', 'integer', 'min:0'],
        ]);

        $code = 'CUST-' . strtoupper(substr(uniqid(), -5));

        $customer = Customer::create([
            'code' => $code,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'cnic' => $validated['cnic'] ?? null,
            'credit_limit_paisa' => $validated['credit_limit_paisa'] ?? 1000000, // PKR 10,000 default limit
            'balance_paisa' => 0,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'code' => $customer->code,
                'phone' => $customer->phone,
                'credit_limit_paisa' => $customer->credit_limit_paisa,
                'balance_paisa' => $customer->balance_paisa,
            ],
        ]);
    }

    public function show(Customer $customer): Response
    {
        $sales = $customer->sales()->latest('created_at')->take(20)->get();
        $payments = $customer->payments()->latest('created_at')->take(20)->get();

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'sales' => $sales,
            'payments' => $payments,
        ]);
    }

    public function recordPayment(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'amount_paisa' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:cash,card,jazzcash,easypaisa,bank_transfer'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $openShift = Shift::where('user_id', $user->id)->where('status', 'open')->latest()->first();

        DB::transaction(function () use ($validated, $customer, $user, $openShift) {
            CustomerPayment::create([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'shift_id' => $openShift?->id,
                'amount_paisa' => $validated['amount_paisa'],
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Decrement customer outstanding debt
            $customer->decrement('balance_paisa', $validated['amount_paisa']);

            // Allocate payment FIFO across unpaid customer sales (A16)
            $unpaidSales = Sale::where('customer_id', $customer->id)
                ->where('status', 'posted')
                ->where('balance_paisa', '>', 0)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            $remaining = (int) $validated['amount_paisa'];
            foreach ($unpaidSales as $unpaidSale) {
                if ($remaining <= 0) break;
                $alloc = min($remaining, (int) $unpaidSale->balance_paisa);
                $unpaidSale->decrement('balance_paisa', $alloc);
                $unpaidSale->increment('received_paisa', $alloc);
                if ($unpaidSale->fresh()->balance_paisa == 0) {
                    $unpaidSale->payment_status = 'paid';
                } else {
                    $unpaidSale->payment_status = 'partial';
                }
                $unpaidSale->save();
                $remaining -= $alloc;
            }
        });

        return back()->with('success', 'Payment recorded and customer balance updated.');
    }
}
