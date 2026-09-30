<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengenal korelasi untuk setiap permintaan (REQ-API-006, REQ-NF-MNT-007).
 *
 * Satu keluhan warga biasanya hanya menyebut "tadi gagal". Dengan pengenal ini,
 * petugas cukup meminta kode yang tampil pada pesan galat, lalu seluruh baris
 * log permintaan tersebut dapat ditemukan sekaligus — termasuk yang berasal
 * dari proses latar.
 */
class PengenalKorelasi
{
    public const TAJUK = 'X-Request-Id';

    /** Batas panjang dan bentuk nilai kiriman luar, agar log tidak dapat disusupi. */
    private const POLA = '/^[A-Za-z0-9\-_]{8,64}$/';

    public function handle(Request $request, Closure $lanjut): Response
    {
        $dikirim = (string) $request->header(self::TAJUK);

        $korelasi = preg_match(self::POLA, $dikirim) === 1 ? $dikirim : (string) Str::uuid();

        // Context ikut tersemat pada setiap baris log selama permintaan ini,
        // termasuk dari layanan yang tidak tahu-menahu soal HTTP.
        Context::add('korelasi', $korelasi);
        $request->attributes->set('korelasi', $korelasi);

        $jawaban = $lanjut($request);
        $jawaban->headers->set(self::TAJUK, $korelasi);

        return $jawaban;
    }
}
