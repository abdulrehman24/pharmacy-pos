<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        $staff = User::where('is_active', true)
            ->whereNotNull('pin_code')
            ->where('pin_code', '!=', '')
            ->select('id', 'name', 'role')
            ->orderBy('name')
            ->get();

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'staff' => $staff,
        ]);
    }

    /**
     * Handle an authentication request via Quick PIN.
     */
    public function loginWithPin(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'pin_code' => ['required', 'string', 'max:10'],
        ]);

        $throttleKey = 'pin_login|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'pin_code' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $user = User::where('id', $request->user_id)->where('is_active', true)->first();

        if (! $user || empty($user->pin_code) || ! hash_equals((string) $user->pin_code, (string) $request->pin_code)) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'pin_code' => 'Invalid PIN code.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user);
        $request->session()->regenerate();

        AuditEvent::log(
            'user_login_pin',
            'User',
            $user->id,
            null,
            ['user_id' => $user->id, 'role' => $user->role]
        );

        return redirect()->intended(route('pos.index', absolute: false));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
