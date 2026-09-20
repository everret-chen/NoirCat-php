<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(private readonly SessionService $sessionService)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        \assert($user instanceof User);

        return view('profile.sessions', [
            'sessions' => $this->sessionService->list($user),
            // The browser session is cookie based and is not one of these tokens.
            'currentTokenId' => null,
        ]);
    }

    public function destroy(Request $request, int|string $session): RedirectResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        $this->sessionService->revoke($user, $session);

        return back()->with('status', __('api.messages.session_revoked'));
    }

    public function revokeOthers(Request $request): RedirectResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        $revoked = $this->sessionService->revokeOthers($user);

        return back()->with('status', __('api.messages.sessions_revoked').' ('.$revoked.')');
    }
}
