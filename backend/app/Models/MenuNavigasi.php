<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Butir menu navigasi utama (REQ-F-ADM-003).
 *
 * Penyarangan sengaja dibatasi dua tingkat, sesuai REQ-UI-003. Menu yang lebih
 * dalam pada layar sentuh menuntut ketepatan tekan yang tidak dimiliki sebagian
 * besar warga desa, dan menyembunyikan halaman yang justru paling dicari.
 */
class MenuNavigasi extends Model
{
    protected $table = 'menu_navigasi';

    /** Batas butir tingkat pertama (REQ-UI-003). */
    public const MAKS_TINGKAT_SATU = 7;

    protected $fillable = ['induk_id', 'label', 'tautan', 'urutan', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'urutan' => 'integer'];
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id')->orderBy('urutan');
    }
}
