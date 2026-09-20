<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the "sanctum" guard the default one for API requests.
 *
 * Public endpoints (post lists, post detail, comments) must still be able to
 * recognise a token when one is present, otherwise $request->user() resolves
 * through the session guard and policies treat the author as a guest.
 */
class UseSanctumGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
