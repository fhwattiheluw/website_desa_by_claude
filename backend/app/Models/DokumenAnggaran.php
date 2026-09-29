<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenAnggaran extends Model
{
    protected $table = 'dokumen_anggaran';

    protected $fillable = ['tahun_anggaran_id', 'media_id', 'judul', 'jenis_dokumen'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
