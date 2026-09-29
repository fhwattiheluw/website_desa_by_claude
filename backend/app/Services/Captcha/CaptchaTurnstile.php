<?php

namespace App\Services\Captcha;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Verifikasi token Cloudflare Turnstile di sisi server (REQ-SW-007).
 *
 * Widget dirender oleh peramban; yang menentukan lolos atau tidak tetap jawaban
 * titik akhir resmi penyedia, bukan keberadaan token itu sendiri.
 */
class CaptchaTurnstile implements Captcha
{
    public function metode(): string
    {
        return 'turnstile';
    }

    public function aktif(): bool
    {
        return true;
    }

    public function siap(): bool
    {
        return filled(config('captcha.turnstile.kunci_situs')) && filled(config('captcha.turnstile.kunci_rahasia'));
    }

    public function tantangan(): array
    {
        return [
            'aktif' => true,
            'metode' => $this->metode(),
            'kunci_situs' => config('captcha.turnstile.kunci_situs'),
        ];
    }

    public function periksa(?string $token, ?string $jawaban): bool
    {
        // Widget mengisi jawaban; token sesi tidak dipakai penyedia ini.
        $muatan = $jawaban ?: $token;

        if (blank($muatan)) {
            return false;
        }

        try {
            $jawab = Http::asForm()
                ->timeout((int) config('captcha.turnstile.timeout', 5))
                ->post((string) config('captcha.turnstile.titik_akhir'), [
                    'secret' => config('captcha.turnstile.kunci_rahasia'),
                    'response' => $muatan,
                    'remoteip' => Request::ip(),
                ]);

            return $jawab->successful() && $jawab->json('success') === true;
        } catch (\Throwable $galat) {
            /*
             * Penyedia tidak dapat dihubungi. Formulir ditolak, bukan
             * diloloskan: pelindung yang gagal terbuka sama dengan tidak ada
             * pelindung. Warga diminta mencoba lagi beberapa saat kemudian.
             */
            Log::warning('Verifikasi CAPTCHA gagal dihubungi.', ['galat' => $galat->getMessage()]);

            return false;
        }
    }
}
