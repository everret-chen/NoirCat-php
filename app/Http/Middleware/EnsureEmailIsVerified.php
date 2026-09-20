<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests from accounts whose email address is not verified yet.
 *
 * Registered as the "verified" alias, and intentionally stricter than the
 * framework middleware: API calls answer with the project envelope (code 1005)
 * instead of a generic 403 with an untranslated message, while browser
 * requests are sent to the profile page where the resend button lives.
 *
 * The gate can be switched off locally (NOIRCAT_REQUIRE_VERIFIED_EMAIL=false)
 * for a machine that has no way to receive the confirmation mail. Production
 * ignores that switch: a deployment must never accept unverified writers.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->verificationRequired()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                throw new BusinessException(ErrorCode::EMAIL_NOT_VERIFIED);
            }

            // The API-only routes in the mail notification can not help a
            // browser session, so point the user at the resend form instead.
            return redirect()
                ->route('profile.edit')
                ->with('error', __('auth_ui.verify_email_notice'));
        }

        return $next($request);
    }

    private function verificationRequired(): bool
    {
        // Production is deliberately not configurable here, so a copied .env
        // cannot silently disable the gate on a public deployment.
        if (app()->isProduction()) {
            return true;
        }

        return (bool) config('noircat.auth.require_verified_email', true);
    }
}
