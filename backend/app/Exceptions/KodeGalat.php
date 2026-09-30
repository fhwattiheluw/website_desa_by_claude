<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Kode galat yang stabil untuk respons API (REQ-API-006).
 *
 * Kode ini bagian dari kontrak API: klien boleh mengambil keputusan
 * berdasarkannya, sedangkan teks pesan boleh berubah sewaktu-waktu.
 */
final class KodeGalat
{
    public static function untuk(Throwable $galat, int $status): string
    {
        return match (true) {
            $galat instanceof ValidationException => 'VALIDASI_GAGAL',
            $galat instanceof AuthenticationException => 'TIDAK_TERAUTENTIKASI',
            $galat instanceof AuthorizationException => 'AKSES_DITOLAK',
            $galat instanceof ThrottleRequestsException => 'TERLALU_BANYAK_PERMINTAAN',
            $galat instanceof ModelNotFoundException,
            $galat instanceof NotFoundHttpException => 'TIDAK_DITEMUKAN',
            default => self::menurutStatus($status),
        };
    }

    private static function menurutStatus(int $status): string
    {
        return match ($status) {
            400 => 'PERMINTAAN_TIDAK_SAH',
            401 => 'TIDAK_TERAUTENTIKASI',
            403 => 'AKSES_DITOLAK',
            404 => 'TIDAK_DITEMUKAN',
            405 => 'METODE_TIDAK_DIIZINKAN',
            409 => 'KONFLIK',
            413 => 'MUATAN_TERLALU_BESAR',
            422 => 'VALIDASI_GAGAL',
            429 => 'TERLALU_BANYAK_PERMINTAAN',
            503 => 'LAYANAN_TIDAK_TERSEDIA',
            default => $status >= 500 ? 'GALAT_SERVER' : 'PERMINTAAN_GAGAL',
        };
    }
}
