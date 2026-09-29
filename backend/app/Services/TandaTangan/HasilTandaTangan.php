<?php

namespace App\Services\TandaTangan;

/**
 * Hasil penandatanganan sebuah dokumen: berkas akhir, metode yang dipakai,
 * dan bukti penandatanganan bila berasal dari penyedia tersertifikasi.
 */
final readonly class HasilTandaTangan
{
    public function __construct(
        public string $pdf,
        public string $metode,
        public ?array $bukti = null,
    ) {}
}
