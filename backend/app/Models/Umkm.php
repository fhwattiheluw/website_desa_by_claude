<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Umkm extends Model
{
    use HasFactory;

    protected $table = 'umkm';

    protected $fillable = [
        'media_id', 'diajukan_oleh', 'nama_usaha', 'slug', 'pemilik', 'kategori',
        'deskripsi', 'telepon', 'alamat', 'consent_kontak', 'status',
    ];

    protected function casts(): array
    {
        return ['consent_kontak' => 'boolean'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function scopeTayang(Builder $query): Builder
    {
        return $query->where('status', 'disetujui');
    }

    /** BR-16: kontak hanya tampil bila pelaku usaha menyetujui publikasi. */
    public function teleponPublik(): ?string
    {
        return $this->consent_kontak ? $this->telepon : null;
    }
}
