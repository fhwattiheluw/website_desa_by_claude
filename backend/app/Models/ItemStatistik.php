<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemStatistik extends Model
{
    use HasFactory;

    protected $table = 'item_statistik';

    protected $fillable = ['periode_statistik_id', 'kelompok', 'label', 'jumlah', 'urutan'];

    protected function casts(): array
    {
        return ['jumlah' => 'integer'];
    }
}
