<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\AuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Session based authentication for the Blade pages.
 *
 * The credential checks are delegated to AuthService, so the web and the API
 * entry points share the same hashing, anti-enumeration and audit behaviour.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly AuditLogService $auditLogs,
    ) {
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        try {
            $user = $this->authService->authenticate($credentials['username'], $credentials['password']);
        } catch (BusinessException $exception) {
            // Same generic message as the API: never reveal which part failed.
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => $exception->getMessage()]);
        }

        Auth::login($user, $request->boolean('remember'));

        // New session id after a privilege change (session fixation defence).
        $request->session()->regenerate();

        return redirect()->intended(route('forum.index'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = $this->authService->register($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('forum.index')
            ->with('status', __('api.messages.registered'));
    }

    /**
     * GET /email/verify/{id}/{hash}
     *
     * The mail link lands here; the "signed" middleware has already proven the
     * URL integrity, and AuthService re-checks the email hash.
     */
    public function verifyEmail(Request $request, int|string $id, string $hash): RedirectResponse
    {
        // AuthService performs the hash check and records the audit entry.
        $this->authService->verifyEmail($id, $hash);

        // A visitor who clicked the link in another browser is not logged in
        // yet, so send them to the sign-in form instead of a protected page.
        $target = $request->user() === null
            ? redirect()->route('login')
            : redirect()->route('profile.edit');

        return $target->with('status', __('api.messages.email_verified'));
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        if ($user->hasVerifiedEmail()) {
            return back()->with('status', __('api.messages.already_verified'));
        }

        $this->authService->sendEmailVerification($user);

        return back()->with('status', __('auth_ui.verification_resent'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user !== null) {
            // Token revocation is not involved here: the cookie session is gone.
            $this->auditLogs->record('auth.logout', ['channel' => 'web'], AuditLog::RESULT_SUCCESS, null, $user->id);
        }

        return redirect()->route('home');
    }
}
