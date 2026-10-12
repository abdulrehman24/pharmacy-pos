<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\Counter;
use App\Models\Expense;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Active open shift
        $activeShift = Shift::with(['counter', 'user', 'cashMovements', 'expenses'])
            ->where('status', 'open')
            ->where('user_id', $user->id)
            ->first();

        $expectedCashPaisa = $activeShift ? $activeShift->calculateExpectedCashPaisa() : 0;

        $pastShifts = Shift::with(['counter', 'user'])
            ->latest('opened_at')
            ->paginate(15);

        $counters = Counter::where('is_active', true)->get();

        return Inertia::render('Shifts/Index', [
            'activeShift' => $activeShift,
            'expectedCashPaisa' => $expectedCashPaisa,
            'pastShifts' => $pastShifts,
            'counters' => $counters,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'counter_id' => ['required', 'exists:counters,id'],
            'opening_float_paisa' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        // Check if user already has an open shift
        $existing = Shift::where('user_id', $user->id)->where('status', 'open')->first();
        if ($existing) {
            return back()->withErrors(['shift' => 'You already have an active shift open. Please close it before opening a new one.']);
        }

        Shift::create([
            'counter_id' => $validated['counter_id'],
            'user_id' => $user->id,
            'opening_float_paisa' => $validated['opening_float_paisa'],
            'opened_at' => now(),
            'status' => 'open',
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('shifts.index')->with('success', 'Cashier shift opened successfully.');
    }

    public function close(Request $request, Shift $shift): RedirectResponse
    {
        if ($shift->status !== 'open') {
            return back()->withErrors(['shift' => 'Shift is already closed.']);
        }

        $validated = $request->validate([
            'counted_cash_paisa' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $expectedCashPaisa = $shift->calculateExpectedCashPaisa();
        $countedCashPaisa = (int) $validated['counted_cash_paisa'];
        $variancePaisa = $countedCashPaisa - $expectedCashPaisa;

        $shift->closed_at = now();
        $shift->counted_cash_paisa = $countedCashPaisa;
        $shift->expected_cash_paisa = $expectedCashPaisa;
        $shift->variance_paisa = $variancePaisa;
        $shift->status = 'closed';
        if (!empty($validated['notes'])) {
            $shift->notes = ($shift->notes ? $shift->notes . " | " : '') . "Close note: " . $validated['notes'];
        }
        $shift->save();

        return redirect()->route('shifts.index')
            ->with('success', "Shift closed. Variance: PKR " . ($variancePaisa / 100));
    }

    public function cashMovement(Request $request, Shift $shift): RedirectResponse
    {
        if ($shift->status !== 'open') {
            return back()->withErrors(['shift' => 'Cannot record cash movement on a closed shift.']);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:cash_in,cash_out'],
            'amount_paisa' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        CashMovement::create([
            'shift_id' => $shift->id,
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'amount_paisa' => $validated['amount_paisa'],
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Cash movement logged successfully.');
    }
}
