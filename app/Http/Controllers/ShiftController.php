<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\Counter;
use App\Models\Expense;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Counter must be active
        $counter = Counter::where('id', $validated['counter_id'])->where('is_active', true)->first();
        if (!$counter) {
            return back()->withErrors(['counter_id' => 'The selected counter is inactive or unavailable.']);
        }

        // Check if user already has an open shift
        $existing = Shift::where('user_id', $user->id)->where('status', 'open')->first();
        if ($existing) {
            return back()->withErrors(['shift' => 'You already have an active shift open. Please close it before opening a new one.']);
        }

        // Check if counter already has an open shift
        $counterOpenShift = Shift::where('counter_id', $counter->id)->where('status', 'open')->first();
        if ($counterOpenShift) {
            return back()->withErrors(['shift' => 'This counter already has an active open shift.', 'counter_id' => 'This counter already has an active open shift.']);
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
        $user = $request->user();

        // Enforce ownership: user must own the shift or be admin/manager
        if ($shift->user_id !== $user->id && !$user->isAdmin() && !$user->isManager()) {
            abort(403, 'Unauthorized to close another cashier\'s shift.');
        }

        $validated = $request->validate([
            'counted_cash_paisa' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $countedCashPaisa = (int) $validated['counted_cash_paisa'];
        $variancePaisa = 0;

        try {
            DB::transaction(function () use ($shift, $validated, $countedCashPaisa, &$variancePaisa) {
                $lockedShift = Shift::where('id', $shift->id)->lockForUpdate()->firstOrFail();

                if ($lockedShift->status !== 'open') {
                    throw new \InvalidArgumentException('Shift is already closed.');
                }

                $expectedCashPaisa = $lockedShift->calculateExpectedCashPaisa();
                $variancePaisa = $countedCashPaisa - $expectedCashPaisa;

                $lockedShift->closed_at = now();
                $lockedShift->counted_cash_paisa = $countedCashPaisa;
                $lockedShift->expected_cash_paisa = $expectedCashPaisa;
                $lockedShift->variance_paisa = $variancePaisa;
                $lockedShift->status = 'closed';
                if (!empty($validated['notes'])) {
                    $lockedShift->notes = ($lockedShift->notes ? $lockedShift->notes . ' | ' : '') . 'Close note: ' . $validated['notes'];
                }
                $lockedShift->save();
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['shift' => $e->getMessage()]);
        }

        return redirect()->route('shifts.index')
            ->with('success', 'Shift closed. Variance: PKR ' . ($variancePaisa / 100));
    }

    public function cashMovement(Request $request, Shift $shift): RedirectResponse
    {
        $user = $request->user();

        // Enforce ownership: user must own the shift or be admin/manager
        if ($shift->user_id !== $user->id && !$user->isAdmin() && !$user->isManager()) {
            abort(403, 'Unauthorized to record cash movement on another cashier\'s shift.');
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:cash_in,cash_out'],
            'amount_paisa' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($shift, $validated, $user) {
                $lockedShift = Shift::where('id', $shift->id)->lockForUpdate()->firstOrFail();

                if ($lockedShift->status !== 'open') {
                    throw new \InvalidArgumentException('Cannot record cash movement on a closed shift.');
                }

                CashMovement::create([
                    'shift_id' => $lockedShift->id,
                    'user_id' => $user->id,
                    'type' => $validated['type'],
                    'amount_paisa' => $validated['amount_paisa'],
                    'reason' => $validated['reason'],
                ]);
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['shift' => $e->getMessage()]);
        }

        return back()->with('success', 'Cash movement logged successfully.');
    }
}
