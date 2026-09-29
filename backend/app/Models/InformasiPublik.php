<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformasiPublik extends Model
{
    protected $table = 'informasi_publik';

    protected $fillable = ['klasifikasi', 'judul', 'ringkasan', 'penanggung_jawab', 'periode_terbit', 'media_id'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
