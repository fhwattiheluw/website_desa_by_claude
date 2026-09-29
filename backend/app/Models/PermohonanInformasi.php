<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PermohonanInformasi extends Model
{
    protected $table = 'permohonan_informasi';

    protected $fillable = [
        'nomor_tiket', 'pemohon_id', 'nama', 'kontak', 'informasi_diminta',
        'tujuan_penggunaan', 'status', 'jawaban', 'tenggat_jawaban',
    ];

    protected function casts(): array
    {
        return ['tenggat_jawaban' => 'datetime'];
    }

    public function setKontakAttribute(?string $nilai): void
    {
        $this->attributes['kontak'] = blank($nilai) ? null : Crypt::encryptString($nilai);
    }

    public function getKontakAttribute(?string $nilai): ?string
    {
        if (blank($nilai)) {
            return null;
        }

        try {
            return Crypt::decryptString($nilai);
        } catch (\Throwable) {
            return null;
        }
    }
}
