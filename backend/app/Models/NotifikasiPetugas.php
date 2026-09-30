<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REQ-F-NOT-005: pekerjaan baru yang masuk ke antrean petugas. */
class NotifikasiPetugas extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'notifikasi_petugas';

    protected $fillable = [
        'pengguna_id', 'jenis', 'judul', 'ringkasan', 'tautan',
        'entitas', 'entitas_id', 'dibaca_pada', 'created_at',
    ];

    protected function casts(): array
    {
        return ['dibaca_pada' => 'datetime', 'created_at' => 'datetime'];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    public function scopeBelumDibaca(Builder $kueri): Builder
    {
        return $kueri->whereNull('dibaca_pada');
    }
}
