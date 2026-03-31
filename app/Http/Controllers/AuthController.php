<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $loginInput = trim((string) ($credentials['login'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');

        $user = User::query()
            ->where(function ($query) use ($loginInput): void {
                $query->where('email', $loginInput)
                    ->orWhere('username', $loginInput);
            })
            ->first();

        $attemptCredentials = [
            'email' => $user?->email ?? $loginInput,
            'password' => $password,
        ];

        if (! Auth::attempt($attemptCredentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'login' => __('ui.invalid_login_credentials'),
            ]);
        }

        $request->session()->regenerate();
        $this->auditLogService->log(
            'auth.login',
            null,
            __('ui.audit_desc_user_logged_in', ['email' => (string) ($user?->email ?? $loginInput)]),
            $request
        );

        $intended = (string) $request->session()->pull('url.intended', '');
        $intendedPath = parse_url($intended, PHP_URL_PATH) ?: '';
        if ($intended !== '' && ! in_array($intendedPath, ['/login', '/logout'], true)) {
            return redirect()->to($intended);
        }

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auditLogService->log('auth.logout', null, __('ui.audit_desc_user_logged_out'), $request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
