<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale from ?lang=, the X-Locale header or
 * Accept-Language, restricted to the locales listed in config/noircat.php.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolveLocale(Request $request): string
    {
        $supported = array_keys((array) config('noircat.locales', []));

        foreach ([$request->query('lang'), $request->header('X-Locale')] as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        foreach ($request->getLanguages() as $language) {
            if (in_array($language, $supported, true)) {
                return $language;
            }

            $short = substr($language, 0, 2);

            foreach ($supported as $locale) {
                if (str_starts_with($locale, $short)) {
                    return $locale;
                }
            }
        }

        // Neither an explicit nor an acceptable locale matched: keep the
        // application default. app.fallback_locale stays reserved for missing
        // translation keys.
        return (string) config('app.locale', 'en');
    }
}
