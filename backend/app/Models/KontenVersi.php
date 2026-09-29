<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KontenVersi extends Model
{
    protected $table = 'konten_versi';

    protected $fillable = ['konten_id', 'dibuat_oleh', 'versi', 'judul', 'isi'];

    public function konten(): BelongsTo
    {
        return $this->belongsTo(Konten::class);
    }
}
