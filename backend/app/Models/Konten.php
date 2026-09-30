<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Konten extends Model
{
    use HasFactory, SoftDeletes;

    public const TIPE = ['berita', 'artikel', 'pengumuman', 'agenda'];

    public const STATUS = ['draf', 'review', 'terbit', 'arsip'];

    protected $table = 'konten';

    protected $fillable = [
        'tipe', 'kategori_id', 'penulis_id', 'gambar_id', 'judul', 'slug', 'ringkasan',
        'isi', 'status', 'sorotan', 'tag', 'terbit_pada', 'kedaluwarsa_pada',
        'mulai_pada', 'selesai_pada', 'lokasi', 'penyelenggara',
    ];

    protected function casts(): array
    {
        return [
            'tag' => 'array',
            'sorotan' => 'boolean',
            'dibaca' => 'integer',
            'terbit_pada' => 'datetime',
            'kedaluwarsa_pada' => 'datetime',
            'mulai_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penulis_id');
    }

    public function gambar(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'gambar_id');
    }

    public function versi(): HasMany
    {
        return $this->hasMany(KontenVersi::class);
    }

    /** Hanya konten yang benar-benar tayang bagi publik (REQ-F-KNT-003, 004, BR-09). */
    /** REQ-F-KNT-013: komentar pembaca, tayang hanya setelah dimoderasi. */
    public function komentar(): HasMany
    {
        return $this->hasMany(Komentar::class);
    }

    public function scopeTayang(Builder $query): Builder
    {
        return $query->where('status', 'terbit')
            ->where(fn (Builder $q) => $q->whereNull('terbit_pada')->orWhere('terbit_pada', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('kedaluwarsa_pada')->orWhere('kedaluwarsa_pada', '>', now()));
    }
}
