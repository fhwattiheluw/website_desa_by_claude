<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Permohonan extends Model
{
    use HasFactory;

    public const DRAF = 'draf';

    public const DIAJUKAN = 'diajukan';

    public const DIVERIFIKASI = 'diverifikasi';

    public const DISETUJUI = 'disetujui';

    public const DITANDATANGANI = 'ditandatangani';

    public const SELESAI = 'selesai';

    public const DIKEMBALIKAN = 'dikembalikan';

    public const DITOLAK = 'ditolak';

    /** Status yang masih berjalan — dipakai untuk mencegah pengajuan ganda (REQ-F-SRT-022). */
    public const AKTIF = [
        self::DIAJUKAN, self::DIVERIFIKASI, self::DISETUJUI, self::DITANDATANGANI, self::DIKEMBALIKAN,
    ];

    public const FINAL = [self::SELESAI, self::DITOLAK];

    protected $table = 'permohonan';

    protected $fillable = [
        'nomor_tiket', 'pemohon_id', 'jenis_layanan_id', 'dibuat_oleh', 'verifikator_id',
        'penyetuju_id', 'data_formulir', 'status', 'kanal', 'alasan', 'tenggat_sla',
        'diajukan_pada', 'selesai_pada', 'kepuasan',
    ];

    protected function casts(): array
    {
        return [
            'data_formulir' => 'array',
            'tenggat_sla' => 'datetime',
            'diajukan_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'lampiran_dibersihkan_pada' => 'datetime',
        ];
    }

    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pemohon_id');
    }

    public function jenisLayanan(): BelongsTo
    {
        return $this->belongsTo(JenisLayanan::class);
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifikator_id');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penyetuju_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatStatus::class)->oldest();
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiranable');
    }

    public function surat(): HasOne
    {
        return $this->hasOne(SuratTerbit::class);
    }

    public function scopeTerlambat(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::FINAL)->where('tenggat_sla', '<', now());
    }

    public function melampauiSla(): bool
    {
        return $this->tenggat_sla !== null
            && ! in_array($this->status, self::FINAL, true)
            && $this->tenggat_sla->isPast();
    }
}
