<?php

namespace App\Http\Controllers;

use App\Actions\Purchases\PostPurchase;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        protected PostPurchase $postPurchase
    ) {}

    public function index(Request $request): Response
    {
        $query = Purchase::with(['supplier', 'user', 'lines'])
            ->latest('invoice_date');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                  ->orWhere('supplier_invoice_number', 'LIKE', "%{$search}%");
            });
        }

        if ($supplierId = $request->get('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        $purchases = $query->paginate(15)->withQueryString();
        $suppliers = Supplier::where('is_active', true)->get();

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'supplier_id']),
        ]);
    }

    public function create(): Response
    {
        $suppliers = Supplier::where('is_active', true)->get();
        $medicines = Medicine::with('packages')
            ->where('is_active', true)
            ->orderBy('brand_name')
            ->get();

        return Inertia::render('Purchases/Create', [
            'suppliers' => $suppliers,
            'medicines' => $medicines,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:50'],
            'invoice_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,credit'],
            'paid_paisa' => ['nullable', 'integer', 'min:0'],
            'discount_paisa' => ['nullable', 'integer', 'min:0'],
            'tax_paisa' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.medicine_id' => ['required', 'exists:medicines,id'],
            'lines.*.medicine_package_id' => ['nullable', 'exists:medicine_packages,id'],
            'lines.*.batch_number' => ['required', 'string', 'max:50'],
            'lines.*.expiry_date' => ['required', 'date', 'after:today'],
            'lines.*.package_quantity' => ['required', 'integer', 'min:1'],
            'lines.*.bonus_base_quantity' => ['nullable', 'integer', 'min:0'],
            'lines.*.package_cost_paisa' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['lines'] as $index => $line) {
            if (!empty($line['medicine_package_id'])) {
                $pkgExists = \App\Models\MedicinePackage::where('id', $line['medicine_package_id'])
                    ->where('medicine_id', $line['medicine_id'])
                    ->exists();
                if (!$pkgExists) {
                    return back()->withErrors(["lines.{$index}.medicine_package_id" => "Selected package does not belong to the selected medicine."]);
                }
            }
        }

        $purchase = $this->postPurchase->execute($validated, $request->user());

        return redirect()->route('purchases.show', $purchase->id)
            ->with('success', "Purchase invoice {$purchase->invoice_number} posted successfully.");
    }

    public function show(Purchase $purchase): Response
    {
        $purchase->load(['supplier', 'user', 'lines.medicine', 'lines.medicinePackage']);

        return Inertia::render('Purchases/Show', [
            'purchase' => $purchase,
        ]);
    }
}
