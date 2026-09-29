<?php

namespace App\Services\TandaTangan;

use App\Models\User;

/** Kontrak penandatanganan dokumen surat (REQ-F-SRT-017, REQ-SW-004). */
interface PenandaTangan
{
    /** Pengenal metode yang tercatat pada surat terbit. */
    public function metode(): string;

    /** Nama penyedia untuk ditampilkan pada antarmuka dan laporan. */
    public function nama(): string;

    /**
     * Apakah gambar spesimen tanda tangan perlu disematkan ke dokumen sebelum
     * dicetak. Penyedia tersertifikasi membubuhkan tandanya sendiri.
     */
    public function menyematkanSpesimen(): bool;

    /** Apakah penyedia siap dipakai dengan konfigurasi saat ini. */
    public function siap(): bool;

    public function tandaTangani(string $pdf, User $penandatangan, string $nomorSurat): HasilTandaTangan;
}
