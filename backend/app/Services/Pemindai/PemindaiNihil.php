<?php

namespace App\Services\Pemindai;

use Illuminate\Http\UploadedFile;

/**
 * Tanpa pemindai antivirus.
 *
 * Dipakai saat pengembangan dan pada pemasangan yang belum menyediakan ClamAV.
 * Pemeriksaan bawaan MediaService — tipe asli berkas, batas ukuran, dan
 * penolakan berkas berisi skrip — tetap berlaku, sehingga unggahan tidak pernah
 * lolos tanpa pemeriksaan sama sekali.
 */
class PemindaiNihil implements PemindaiBerkas
{
    public function nama(): string
    {
        return 'nihil';
    }

    public function aktif(): bool
    {
        return false;
    }

    public function pindai(UploadedFile $berkas): HasilPindai
    {
        return HasilPindai::bersih($this->nama());
    }
}
