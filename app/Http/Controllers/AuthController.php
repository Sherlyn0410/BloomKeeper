<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->withInput($request->only('email'));
        }

        if (! Auth::user()->hasVerifiedEmail()) {
            $request->session()->regenerate();

            return redirect()->route('verification.notice');
        }

        if (Auth::user()->approval_status !== 'approved') {
            Auth::logout();

            return back()
                ->withErrors(['email' => 'Your account is waiting for administrator approval, has been declined, or has been suspended.'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return redirect()->to($this->pageAfterLogin($request));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:staff,supplier'],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
        ]);

        $user = User::create([
            ...$validated,
            'approval_status' => 'pending',
        ]);

        Auth::login($user);

        return redirect()->route('verification.notice')->with('status', $user->trySendingVerificationEmail()
            ? 'Verify your email while your account waits for administrator approval.'
            : "Your account was created, but we couldn't send the verification email just now. Please use Resend verification email.");
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($validated);

        if ($status !== Password::RESET_LINK_SENT) {
            return redirect()->route('password.request')
                ->withErrors(['email' => __($status)])
                ->withInput();
        }

        return redirect()->route('password.request')->with('status', 'We sent a password reset link to your email address.');
    }

    public function showResetPassword(string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request()->string('email')->toString(),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withErrors(['email' => __($status)])
                ->withInput($request->only('email'));
        }

        return redirect()->route('login')->with('status', __($status));
    }

    /**
     * Send the user back to the page they were trying to open, but only if their role can open it;
     * otherwise a staff member could land on an admin page left over from an earlier sign-in attempt.
     */
    private function pageAfterLogin(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');
        $path = '/'.ltrim((string) parse_url((string) $intended, PHP_URL_PATH), '/');

        $isAllowed = match (true) {
            $intended === null => false,
            str_starts_with($path, '/admin') => $request->user()->can('manage-users'),
            str_starts_with($path, '/staff') => $request->user()->can('manage-customers'),
            default => true,
        };

        return $isAllowed ? $intended : route('dashboard');
    }
}
