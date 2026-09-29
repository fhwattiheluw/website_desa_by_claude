<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->daftarkanPembatasLaju();
    }

    /**
     * Pembatasan laju pada titik akhir sensitif dan formulir publik
     * (REQ-NF-SEC-009, REQ-F-ADU-010, REQ-API-003).
     */
    private function daftarkanPembatasLaju(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('masuk', fn (Request $request) => [
            Limit::perMinute(5)->by('masuk:'.$request->ip()),
            Limit::perMinute(5)->by('masuk:'.strtolower((string) $request->input('email'))),
        ]);

        RateLimiter::for('registrasi', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        RateLimiter::for('formulir-publik', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);

        RateLimiter::for('unggah', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
