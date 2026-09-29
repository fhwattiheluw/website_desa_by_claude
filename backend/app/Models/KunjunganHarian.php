<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** REQ-SW-006: hitungan kunjungan agregat per laman per hari. */
class KunjunganHarian extends Model
{
    protected $table = 'kunjungan_harian';

    protected $fillable = ['tanggal', 'jalur', 'jumlah'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'jumlah' => 'integer'];
    }
}
