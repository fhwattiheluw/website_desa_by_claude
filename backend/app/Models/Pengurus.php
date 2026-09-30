<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pengurus extends Model
{
    use HasFactory;

    protected $table = 'pengurus';

    protected $fillable = [
        'lembaga_id', 'atasan_id', 'foto_id', 'nama', 'jabatan', 'wilayah',
        'masa_jabatan_mulai', 'masa_jabatan_selesai', 'tugas_pokok', 'urutan',
    ];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function foto(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'foto_id');
    }

    /** REQ-F-BRD-003: hubungan atasan–bawahan yang membentuk bagan organisasi. */
    public function atasan(): BelongsTo
    {
        return $this->belongsTo(self::class, 'atasan_id');
    }

    public function bawahan(): HasMany
    {
        return $this->hasMany(self::class, 'atasan_id')->orderBy('urutan');
    }

    public function masaJabatan(): ?string
    {
        return $this->masa_jabatan_mulai
            ? $this->masa_jabatan_mulai.'–'.($this->masa_jabatan_selesai ?? 'sekarang')
            : null;
    }
}
