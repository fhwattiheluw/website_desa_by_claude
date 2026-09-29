<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Services\Captcha\ManajerCaptcha;
use Illuminate\Http\JsonResponse;

/** REQ-SW-007: bahan tantangan untuk formulir publik. */
class CaptchaController extends Controller
{
    public function __invoke(ManajerCaptcha $manajer): JsonResponse
    {
        // Respons tidak boleh disinggahkan: setiap formulir memperoleh
        // tantangan sendiri yang sekali pakai.
        return response()
            ->json($manajer->aktif()->tantangan())
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
