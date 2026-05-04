<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
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
    public function boot()
{
    // Rate limit login per email
    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(10)->by($request->input('email') ?: $request->ip());
    });

    // Rate limit register per email
    RateLimiter::for('register', function (Request $request) {
        return Limit::perMinute(10)->by($request->input('email') ?: $request->ip());
    });

    // Rate limit create class per user_id
    RateLimiter::for('create-class', function (Request $request) {
        return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
    });

    // Rate limit create announcement per user_id
    RateLimiter::for('create-announcement', function (Request $request) {
        return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
    });

    // Rate limit add comment per user_id
    RateLimiter::for('add-comment', function (Request $request) {
        return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
    });

    // Rate limit batch grade per user_id
    RateLimiter::for('batch-grade', function (Request $request) {
        return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
    });
}
}
