<?php

namespace App\Services\TandaTangan;

use App\Models\User;

/**
 * Metode bawaan saat penyedia TTE tersertifikasi belum tersedia: dokumen
 * memuat gambar spesimen tanda tangan pejabat beserta kode QR verifikasi
 * (REQ-F-SRT-017, kalimat kedua).
 *
 * Keaslian dokumen dijamin melalui halaman verifikasi publik dan hash
 * dokumen yang tersimpan, bukan melalui sertifikat digital.
 */
class TandaTanganInternal implements PenandaTangan
{
    public function metode(): string
    {
        return 'internal';
    }

    public function nama(): string
    {
        return 'Tanda tangan dalam sistem dengan verifikasi kode QR';
    }

    public function menyematkanSpesimen(): bool
    {
        return true;
    }

    public function siap(): bool
    {
        return true;
    }

    public function tandaTangani(string $pdf, User $penandatangan, string $nomorSurat): HasilTandaTangan
    {
        // Spesimen sudah tersemat saat dokumen dirender, sehingga berkas
        // diteruskan apa adanya.
        return new HasilTandaTangan($pdf, $this->metode());
    }
}
