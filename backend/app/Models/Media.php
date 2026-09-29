<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'album_id', 'uploader_id', 'nama_asli', 'path', 'disk',
        'mime', 'ukuran', 'alt', 'koleksi', 'privat',
    ];

    protected function casts(): array
    {
        return ['privat' => 'boolean', 'ukuran' => 'integer'];
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /** Berkas privat tidak pernah diberi URL langsung (REQ-NF-SEC-007). */
    public function url(): ?string
    {
        return $this->privat ? null : Storage::disk($this->disk)->url($this->path);
    }
}
