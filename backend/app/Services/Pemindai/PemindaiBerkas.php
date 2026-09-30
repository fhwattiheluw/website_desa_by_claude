<?php

namespace App\Services\Pemindai;

use Illuminate\Http\UploadedFile;

/** Kontrak pemindai perangkat lunak berbahaya pada berkas unggahan (REQ-NF-SEC-008). */
interface PemindaiBerkas
{
    public function nama(): string;

    /** Apakah pemindaian benar-benar dijalankan dengan konfigurasi saat ini. */
    public function aktif(): bool;

    /**
     * @return HasilPindai hasil pemeriksaan; berkas hanya boleh disimpan bila bersih
     */
    public function pindai(UploadedFile $berkas): HasilPindai;
}
