<?php

namespace App\Services\Captcha;

use Illuminate\Support\Facades\Log;

/**
 * Memilih penyedia CAPTCHA yang aktif.
 *
 * Bila penyedia pihak ketiga dipilih namun kuncinya belum lengkap, sistem
 * kembali ke tantangan bawaan alih-alih membiarkan formulir tanpa pelindung.
 */
class ManajerCaptcha
{
    public function __construct(
        private readonly CaptchaBawaan $bawaan,
        private readonly CaptchaTurnstile $turnstile,
        private readonly CaptchaNihil $nihil,
    ) {}

    public function aktif(): Captcha
    {
        return match ((string) config('captcha.driver')) {
            'nihil' => $this->nihil,
            'turnstile' => $this->turnstileAtauBawaan(),
            default => $this->bawaan,
        };
    }

    private function turnstileAtauBawaan(): Captcha
    {
        if ($this->turnstile->siap()) {
            return $this->turnstile;
        }

        Log::warning('Driver CAPTCHA disetel ke turnstile namun kuncinya belum lengkap; memakai tantangan bawaan.');

        return $this->bawaan;
    }
}
