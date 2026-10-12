<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Supplier::query()->orderBy('name');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        $suppliers = $query->paginate(15)->withQueryString();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:suppliers,code'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'balance_paisa' => ['nullable', 'integer'],
        ]);

        Supplier::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'address' => $validated['address'],
            'tax_number' => $validated['tax_number'],
            'balance_paisa' => $validated['balance_paisa'] ?? 0,
            'is_active' => true,
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully.');
    }

    public function show(Supplier $supplier): Response
    {
        $purchases = $supplier->purchases()->latest('invoice_date')->take(20)->get();
        $payments = $supplier->payments()->latest('created_at')->take(20)->get();

        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'purchases' => $purchases,
            'payments' => $payments,
        ]);
    }

    public function recordPayment(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'amount_paisa' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,cheque'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $openShift = Shift::where('status', 'open')->latest()->first();

        DB::transaction(function () use ($validated, $supplier, $request, $openShift) {
            SupplierPayment::create([
                'supplier_id' => $supplier->id,
                'user_id' => $request->user()->id,
                'shift_id' => $openShift?->id,
                'amount_paisa' => $validated['amount_paisa'],
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'],
                'notes' => $validated['notes'],
            ]);

            // Decrement liability balance
            $supplier->decrement('balance_paisa', $validated['amount_paisa']);
        });

        return back()->with('success', 'Payment recorded and supplier balance updated.');
    }
}
