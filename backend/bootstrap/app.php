<?php

use App\Exceptions\KodeGalat;
use App\Http\Middleware\PastikanIzin;
use App\Http\Middleware\PengenalKorelasi;
use App\Http\Middleware\PeriksaCaptcha;
use App\Http\Middleware\TajukKeamanan;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'izin' => PastikanIzin::class,
            'captcha' => PeriksaCaptcha::class,
        ]);

        // Tajuk keamanan wajib pada seluruh respons (REQ-NF-SEC-005).
        $middleware->append(TajukKeamanan::class);

        /*
         * Pengenal korelasi dipasang paling awal agar seluruh log permintaan —
         * termasuk yang berasal dari middleware lain — sudah membawanya
         * (REQ-API-006, REQ-NF-MNT-007).
         */
        $middleware->prepend(PengenalKorelasi::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * REQ-API-006: setiap respons galat memuat kode galat, pesan ringkas,
         * dan pengenal korelasi.
         *
         * Respons bawaan Laravel dilengkapi, bukan diganti: kunci `message` dan
         * `errors` tetap seperti semula agar klien yang sudah ada tidak rusak.
         */
        $exceptions->respond(function (Response $jawaban, Throwable $galat, Request $request) {
            if (! $jawaban instanceof JsonResponse) {
                return $jawaban;
            }

            $isi = $jawaban->getData(true);

            if (! is_array($isi)) {
                return $jawaban;
            }

            /*
             * Nilai bawaan ditaruh lebih dahulu agar isi respons yang sudah
             * ada menimpanya: pengecualian domain seperti AlurTidakValid
             * menetapkan kode dan pesannya sendiri, dan itu lebih tepat
             * daripada kode umum menurut status HTTP.
             */
            $jawaban->setData([
                'kode' => KodeGalat::untuk($galat, $jawaban->getStatusCode()),
                'pesan' => $isi['message'] ?? 'Permintaan gagal diproses.',
                ...$isi,
                'korelasi' => (string) Context::get('korelasi', ''),
            ]);

            return $jawaban;
        });
    })->create();
