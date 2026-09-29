<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lembaga extends Model
{
    use HasFactory;

    protected $table = 'lembaga';

    protected $fillable = ['nama', 'slug', 'jenis', 'deskripsi', 'urutan'];

    public function pengurus(): HasMany
    {
        return $this->hasMany(Pengurus::class)->orderBy('urutan');
    }
}
