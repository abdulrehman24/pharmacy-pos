<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use App\Models\AuditEvent;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile and settings form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'profileUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
                'has_pin' => !empty($user->pin_code),
                'pin_code' => (string) ($user->pin_code ?? ''),
                'created_at' => $user->created_at?->format('d M Y'),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        if (array_key_exists('pin_code', $validated)) {
            $user->pin_code = $validated['pin_code'] ?: null;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        AuditEvent::log(
            'user_profile_updated',
            'User',
            $user->id,
            null,
            ['name' => $user->name, 'email' => $user->email, 'has_pin' => !empty($user->pin_code)]
        );

        return Redirect::route('profile.edit')->with('success', 'Profile information updated successfully.');
    }

    /**
     * Update the user's Quick 4-Digit PIN.
     */
    public function updatePin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin_code' => ['nullable', 'string', 'regex:/^[0-9]{4}$/'],
        ], [
            'pin_code.regex' => 'The Quick PIN must be exactly 4 numeric digits (e.g. 1234).',
        ]);

        $user = $request->user();
        $user->pin_code = $validated['pin_code'] ?: null;
        $user->save();

        AuditEvent::log(
            'user_pin_updated',
            'User',
            $user->id,
            null,
            ['has_pin' => !empty($user->pin_code)]
        );

        return Redirect::route('profile.edit')->with('success', 'Quick 4-Digit PIN updated successfully.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $hasFinancialRecords = $user->sales()->exists()
            || $user->shifts()->exists()
            || \App\Models\Purchase::where('created_by', $user->id)->exists()
            || \App\Models\SalesReturn::where('cashier_id', $user->id)->exists()
            || \App\Models\StockCount::where('conducted_by', $user->id)->exists();

        if ($hasFinancialRecords) {
            abort(403, 'Account deletion is prohibited for staff with posted financial or transaction records. Please contact an administrator to deactivate your account.');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
