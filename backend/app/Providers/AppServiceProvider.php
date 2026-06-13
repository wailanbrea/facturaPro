<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $this->configureRateLimiters();
    }

    /**
     * Define the application's rate limiters.
     */
    private function configureRateLimiters(): void
    {
        // Throttle login attempts per email + IP to mitigate brute-force attacks.
        RateLimiter::for('login', function (Request $request): Limit {
            $email = (string) $request->input('email');
            $key = Str::transliterate(Str::lower($email).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });
    }
}
