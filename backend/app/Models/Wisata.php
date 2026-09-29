<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wisata extends Model
{
    protected $table = 'wisata';

    protected $fillable = ['media_id', 'nama', 'slug', 'deskripsi', 'jam_operasional', 'tarif', 'alamat', 'lat', 'lng'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
