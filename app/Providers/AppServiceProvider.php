<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureEloquentStrictness();
    }

    /**
     * Silently discarded attributes are a real bug class here: a derived column
     * (like posts.content_html) missing from $fillable would otherwise vanish
     * without a trace. Outside production this becomes a loud error instead.
     */
    private function configureEloquentStrictness(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }

    /**
     * Named limiters backing the rate limiting matrix in config/noircat.php.
     *
     * The "api" limiter is applied automatically by the api middleware group;
     * the other limiters are attached per route with throttle:<name>.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(
            (int) config('noircat.rate_limits.api.per_minute', 120)
        )->by($this->limiterKey($request)));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute((int) config('noircat.rate_limits.login.per_minute', 5))->by($request->ip()),
            Limit::perDay((int) config('noircat.rate_limits.login.per_day', 50))->by($request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(
            (int) config('noircat.rate_limits.register.per_hour', 3)
        )->by($request->ip()));

        RateLimiter::for('posts', fn (Request $request) => Limit::perMinute(
            (int) config('noircat.rate_limits.posts.per_minute', 10)
        )->by($this->limiterKey($request)));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(
            (int) config('noircat.rate_limits.uploads.per_hour', 20)
        )->by($this->limiterKey($request)));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(
            (int) config('noircat.rate_limits.search.per_minute', 60)
        )->by($request->ip()));

        RateLimiter::for('password_reset', fn (Request $request) => Limit::perHour(
            (int) config('noircat.rate_limits.password_reset.per_hour', 3)
        )->by($request->ip()));

        RateLimiter::for('email_verification', fn (Request $request) => Limit::perMinute(
            (int) config('noircat.rate_limits.email_verification.per_minute', 3)
        )->by($this->limiterKey($request)));
    }

    /**
     * Authenticated users are limited per account, guests per IP address.
     */
    private function limiterKey(Request $request): string
    {
        $user = $request->user();

        return $user !== null
            ? 'user:'.$user->getAuthIdentifier()
            : 'ip:'.$request->ip();
    }
}
