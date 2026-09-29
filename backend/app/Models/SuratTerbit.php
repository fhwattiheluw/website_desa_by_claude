<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratTerbit extends Model
{
    protected $table = 'surat_terbit';

    protected $fillable = [
        'permohonan_id', 'penandatangan_id', 'nomor_surat', 'tanggal_terbit',
        'path_pdf', 'hash_dokumen', 'kode_verifikasi', 'status_keabsahan', 'alasan_pembatalan',
        'metode_tanda_tangan', 'bukti_tte',
    ];

    protected function casts(): array
    {
        return ['tanggal_terbit' => 'date', 'bukti_tte' => 'array'];
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    public function penandatangan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penandatangan_id');
    }

    public function sah(): bool
    {
        return $this->status_keabsahan === 'sah';
    }

    /** Apakah surat ditandatangani memakai sertifikat elektronik tersertifikasi. */
    public function tersertifikasi(): bool
    {
        return $this->metode_tanda_tangan === 'tte';
    }
}
