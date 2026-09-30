<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REQ-F-PID-007: keberatan atas penolakan permohonan informasi publik. */
class KeberatanInformasi extends Model
{
    public const DIAJUKAN = 'diajukan';

    public const DITANGGAPI = 'ditanggapi';

    /**
     * UU 14/2008 Pasal 36 ayat (2): atasan PPID wajib menanggapi keberatan
     * paling lambat 30 hari kerja sejak keberatan dicatat.
     */
    public const SLA_HARI_KERJA = 30;

    protected $table = 'keberatan_informasi';

    protected $fillable = [
        'permohonan_informasi_id', 'alasan', 'status',
        'tanggapan', 'ditanggapi_oleh', 'ditanggapi_pada', 'tenggat_tanggapan',
    ];

    protected function casts(): array
    {
        return ['ditanggapi_pada' => 'datetime', 'tenggat_tanggapan' => 'datetime'];
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(PermohonanInformasi::class, 'permohonan_informasi_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditanggapi_oleh');
    }
}
