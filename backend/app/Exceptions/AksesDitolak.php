<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Dilempar saat pengguna terautentikasi tidak berhak atas suatu tindakan
 * (REQ-F-USR-012).
 *
 * Dilempar, bukan dikembalikan sebagai respons, agar bentuk badan galat —
 * termasuk kode dan pengenal korelasi — tetap disusun di satu tempat
 * (REQ-API-006).
 */
class AksesDitolak extends RuntimeException
{
    /** @param array<int, string> $izinDibutuhkan */
    public function __construct(string $pesan, private readonly array $izinDibutuhkan = [])
    {
        parent::__construct($pesan);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'pesan' => $this->getMessage(),
            'kode' => 'AKSES_DITOLAK',
            'izin_dibutuhkan' => $this->izinDibutuhkan ?: null,
        ]), 403);
    }
}
