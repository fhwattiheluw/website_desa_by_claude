<?php

namespace App\Http\Middleware;

use App\Services\Captcha\ManajerCaptcha;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memberlakukan tantangan CAPTCHA pada seluruh formulir publik
 * (REQ-F-ADU-010, REQ-SW-007).
 *
 * Dipasang pada grup rute, bukan di masing-masing pengendali, agar formulir
 * publik yang ditambahkan kemudian ikut terlindungi tanpa perlu diingat.
 */
class PeriksaCaptcha
{
    public function __construct(private readonly ManajerCaptcha $manajer) {}

    public function handle(Request $request, Closure $lanjut): Response
    {
        $captcha = $this->manajer->aktif();

        if ($captcha->aktif() && ! $captcha->periksa(
            $request->string('captcha_token')->toString() ?: null,
            $request->string('captcha_jawaban')->toString() ?: null,
        )) {
            throw ValidationException::withMessages([
                'captcha_jawaban' => 'Jawaban verifikasi tidak tepat. Silakan coba tantangan baru.',
            ]);
        }

        return $lanjut($request);
    }
}
