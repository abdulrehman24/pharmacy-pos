<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicineBarcode;
use App\Models\MedicinePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MedicineController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Medicine::with(['category', 'manufacturer', 'packages.barcodes'])
            ->orderBy('brand_name');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('brand_name', 'LIKE', "%{$search}%")
                  ->orWhere('generic_name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        if ($category = $request->get('category_id')) {
            $query->where('category_id', $category);
        }

        if ($manufacturer = $request->get('manufacturer_id')) {
            $query->where('manufacturer_id', $manufacturer);
        }

        $medicines = $query->paginate(15)->withQueryString();

        // Attach live sellable stock to each item
        $medicines->getCollection()->transform(function ($med) {
            $med->total_stock_base = $med->totalSellableStockBaseUnit();
            return $med;
        });

        $categories = Category::all();
        $manufacturers = Manufacturer::all();

        return Inertia::render('Medicines/Index', [
            'medicines' => $medicines,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
            'filters' => $request->only(['search', 'category_id', 'manufacturer_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Medicines/Create', [
            'categories' => Category::all(),
            'manufacturers' => Manufacturer::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:medicines,code'],
            'brand_name' => ['required', 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:50'],
            'dosage_form' => ['required', 'string', 'max:50'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'manufacturer_id' => ['nullable', 'exists:manufacturers,id'],
            'base_unit' => ['required', 'string', 'max:30'],
            'rack_shelf' => ['nullable', 'string', 'max:50'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'prescription_required' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'packages' => ['required', 'array', 'min:1'],
            'packages.*.name' => ['required', 'string', 'max:50'],
            'packages.*.base_quantity' => ['required', 'integer', 'min:1'],
            'packages.*.default_sell_price_paisa' => ['required', 'integer', 'min:0'],
            'packages.*.default_purchase_price_paisa' => ['nullable', 'integer', 'min:0'],
            'packages.*.barcode' => ['nullable', 'string', 'max:100', 'unique:medicine_barcodes,barcode'],
            'packages.*.is_default_sale' => ['boolean'],
        ]);

        $medicine = Medicine::create([
            'code' => $validated['code'],
            'brand_name' => $validated['brand_name'],
            'generic_name' => $validated['generic_name'],
            'strength' => $validated['strength'],
            'dosage_form' => $validated['dosage_form'],
            'category_id' => $validated['category_id'],
            'manufacturer_id' => $validated['manufacturer_id'],
            'base_unit' => $validated['base_unit'],
            'rack_shelf' => $validated['rack_shelf'],
            'reorder_level' => $validated['reorder_level'],
            'prescription_required' => $validated['prescription_required'] ?? false,
            'is_active' => true,
            'notes' => $validated['notes'],
        ]);

        foreach ($validated['packages'] as $pkg) {
            $package = MedicinePackage::create([
                'medicine_id' => $medicine->id,
                'name' => $pkg['name'],
                'base_quantity' => $pkg['base_quantity'],
                'default_sell_price_paisa' => $pkg['default_sell_price_paisa'],
                'default_purchase_price_paisa' => $pkg['default_purchase_price_paisa'] ?? 0,
                'is_default_sale' => $pkg['is_default_sale'] ?? false,
            ]);

            if (!empty($pkg['barcode'])) {
                MedicineBarcode::create([
                    'medicine_id' => $medicine->id,
                    'medicine_package_id' => $package->id,
                    'barcode' => trim($pkg['barcode']),
                ]);
            }
        }

        return redirect()->route('medicines.index')
            ->with('success', "Medicine '{$medicine->brand_name}' created successfully.");
    }

    public function show(Medicine $medicine): Response
    {
        $medicine->load([
            'category',
            'manufacturer',
            'packages.barcodes',
            'batches.stockLots',
        ]);

        $stockMovements = $medicine->stockMovements()
            ->with(['batch', 'user'])
            ->latest('created_at')
            ->take(20)
            ->get();

        return Inertia::render('Medicines/Show', [
            'medicine' => $medicine,
            'total_stock_base' => $medicine->totalSellableStockBaseUnit(),
            'recentMovements' => $stockMovements,
        ]);
    }

    /**
     * Quick AJAX store for category from medicine form
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $slug = Str::slug($validated['name']);
        if (Category::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::lower(Str::random(4));
        }

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'category' => $category,
        ], 201);
    }

    /**
     * Quick AJAX store for manufacturer from medicine form
     */
    public function storeManufacturer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:manufacturers,name'],
            'contact_info' => ['nullable', 'string', 'max:255'],
        ]);

        $manufacturer = Manufacturer::create([
            'name' => $validated['name'],
            'contact_info' => $validated['contact_info'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'manufacturer' => $manufacturer,
        ], 201);
    }

    /**
     * Delete an unused category
     */
    public function destroyCategory(Category $category): JsonResponse
    {
        $count = $category->medicines()->count();
        if ($count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete category '{$category->name}'. It is currently assigned to {$count} medicine(s).",
            ], 422);
        }

        $categoryName = $category->name;
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => "Category '{$categoryName}' deleted successfully.",
        ]);
    }

    /**
     * Delete an unused manufacturer
     */
    public function destroyManufacturer(Manufacturer $manufacturer): JsonResponse
    {
        $count = $manufacturer->medicines()->count();
        if ($count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete manufacturer '{$manufacturer->name}'. It is currently assigned to {$count} medicine(s).",
            ], 422);
        }

        $mfgName = $manufacturer->name;
        $manufacturer->delete();

        return response()->json([
            'success' => true,
            'message' => "Manufacturer '{$mfgName}' deleted successfully.",
        ]);
    }
}
