<?php

namespace App\Services\Pemindai;

use Illuminate\Support\Facades\Log;

/** Memilih pemindai berkas yang aktif (REQ-NF-SEC-008). */
class ManajerPemindai
{
    public function __construct(
        private readonly PemindaiNihil $nihil,
        private readonly PemindaiClamav $clamav,
    ) {}

    public function aktif(): PemindaiBerkas
    {
        if (config('pemindai.driver') !== 'clamav') {
            return $this->nihil;
        }

        if (! $this->clamav->aktif()) {
            Log::warning('Driver pemindai disetel ke clamav namun alamatnya belum diisi; pemindaian dilewati.');

            return $this->nihil;
        }

        return $this->clamav;
    }
}
