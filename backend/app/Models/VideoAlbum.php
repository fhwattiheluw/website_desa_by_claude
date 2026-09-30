<?php

namespace App\Models;

use App\Services\PenyematVideo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Video yang disematkan pada album galeri (REQ-F-GAL-006). */
class VideoAlbum extends Model
{
    protected $table = 'video_album';

    protected $fillable = ['album_id', 'judul', 'penyedia', 'id_video', 'url_asli', 'thumbnail_path', 'urutan'];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function urlSematan(): string
    {
        return PenyematVideo::urlSematan($this->penyedia, $this->id_video);
    }

    /**
     * Gambar sampul yang dilayani dari server desa sendiri.
     *
     * Sengaja bukan alamat gambar milik penyedia: memuatnya berarti peramban
     * setiap pengunjung menghubungi penyedia sebelum ia memutuskan menonton.
     */
    public function urlThumbnail(): ?string
    {
        return $this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : null;
    }
}
