<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tanggapan extends Model
{
    protected $table = 'tanggapan';

    protected $fillable = ['pengaduan_id', 'aktor_id', 'isi', 'internal'];

    protected function casts(): array
    {
        return ['internal' => 'boolean'];
    }

    public function aktor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aktor_id');
    }
}
