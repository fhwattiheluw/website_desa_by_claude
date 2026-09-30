<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use HasFactory;

    protected $table = 'album';

    protected $fillable = ['nama', 'slug', 'deskripsi', 'tanggal_kegiatan'];

    protected function casts(): array
    {
        return ['tanggal_kegiatan' => 'date'];
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /** REQ-F-GAL-006: video dari penyedia eksternal yang disematkan pada album. */
    public function video(): HasMany
    {
        return $this->hasMany(VideoAlbum::class)->orderBy('urutan');
    }
}
