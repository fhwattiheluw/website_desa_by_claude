<?php

namespace App\Services\TandaTangan;

use Illuminate\Support\Facades\Log;

/**
 * Memilih metode penandatanganan yang aktif.
 *
 * Selama penyedia tersertifikasi belum dikonfigurasi, sistem kembali ke
 * metode dalam sistem agar pelayanan surat tetap berjalan (REQ-F-SRT-017,
 * DEP-03).
 */
class ManajerTandaTangan
{
    public function __construct(
        private readonly TandaTanganInternal $internal,
        private readonly TandaTanganPsre $psre,
    ) {}

    public function aktif(): PenandaTangan
    {
        if (config('tte.driver') !== 'psre') {
            return $this->internal;
        }

        if (! $this->psre->siap()) {
            Log::warning('Driver TTE disetel ke psre namun konfigurasinya belum lengkap; memakai metode internal.');

            return $this->internal;
        }

        return $this->psre;
    }

    /** Metode cadangan saat penandatanganan tersertifikasi gagal di tengah jalan. */
    public function cadangan(): PenandaTangan
    {
        return $this->internal;
    }
}
