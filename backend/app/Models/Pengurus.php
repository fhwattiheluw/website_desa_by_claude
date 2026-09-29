<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengurus extends Model
{
    use HasFactory;

    protected $table = 'pengurus';

    protected $fillable = [
        'lembaga_id', 'foto_id', 'nama', 'jabatan', 'wilayah',
        'masa_jabatan_mulai', 'masa_jabatan_selesai', 'tugas_pokok', 'urutan',
    ];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function foto(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'foto_id');
    }
}
