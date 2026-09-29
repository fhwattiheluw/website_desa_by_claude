<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Unit usaha yang dikelola BUMDes (REQ-F-POT-003). */
class UnitUsaha extends Model
{
    use HasFactory;

    protected $table = 'unit_usaha';

    protected $fillable = [
        'media_id', 'nama', 'slug', 'deskripsi', 'penanggung_jawab', 'kontak', 'aktif', 'urutan',
    ];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
