<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeStatistik extends Model
{
    use HasFactory;

    protected $table = 'periode_statistik';

    protected $fillable = ['nama', 'tahun', 'sumber_data', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'tahun' => 'integer'];
    }

    public function item(): HasMany
    {
        return $this->hasMany(ItemStatistik::class);
    }
}
