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
 * framework middleware: it answers with the project envelope (code 1005)
 * instead of a generic 403 with an untranslated message.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            throw new BusinessException(ErrorCode::EMAIL_NOT_VERIFIED);
        }

        return $next($request);
    }
}
