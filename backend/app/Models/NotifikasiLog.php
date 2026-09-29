<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotifikasiLog extends Model
{
    protected $table = 'notifikasi_log';

    protected $fillable = ['kanal', 'tujuan', 'templat', 'data', 'status', 'percobaan', 'galat', 'terkirim_pada'];

    protected function casts(): array
    {
        return ['data' => 'array', 'terkirim_pada' => 'datetime'];
    }
}
