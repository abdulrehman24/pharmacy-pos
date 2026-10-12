<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(): Response
    {
        $expenses = Expense::with(['user', 'shift'])
            ->latest('created_at')
            ->paginate(15);

        $categories = [
            'utilities' => 'Utilities (Electricity, Water, Gas)',
            'tea_refreshment' => 'Tea & Refreshment',
            'maintenance' => 'Shop Maintenance & Repairs',
            'supplies' => 'Counter Supplies & Paper Rolls',
            'cleaning' => 'Janitorial & Cleaning',
            'salary' => 'Daily Wage / Staff Advance',
            'other' => 'Other Expense',
        ];

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'amount_paisa' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'receipt_ref' => ['nullable', 'string', 'max:100'],
        ]);

        $openShift = Shift::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first() ?? Shift::where('status', 'open')->latest()->first();

        Expense::create([
            'shift_id' => $openShift?->id,
            'user_id' => $request->user()->id,
            'category' => $validated['category'],
            'amount_paisa' => $validated['amount_paisa'],
            'description' => $validated['description'],
            'receipt_ref' => $validated['receipt_ref'],
        ]);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded successfully.');
    }
}
