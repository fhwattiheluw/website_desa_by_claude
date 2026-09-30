<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PermohonanInformasi extends Model
{
    protected $table = 'permohonan_informasi';

    protected $fillable = [
        'nomor_tiket', 'kode_lacak', 'pemohon_id', 'nama', 'kontak', 'informasi_diminta',
        'tujuan_penggunaan', 'status', 'jawaban', 'tenggat_jawaban',
    ];

    protected function casts(): array
    {
        return ['tenggat_jawaban' => 'datetime'];
    }

    /** REQ-F-PID-007: keberatan atas penolakan permohonan ini. */
    public function keberatan(): HasMany
    {
        return $this->hasMany(KeberatanInformasi::class);
    }

    /** Status yang membuka jalan keberatan (UU 14/2008 Pasal 35). */
    public function dapatDiajukanKeberatan(): bool
    {
        return in_array($this->status, ['ditolak', 'selesai'], true)
            || ($this->status === 'diajukan' && $this->tenggat_jawaban?->isPast());
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
