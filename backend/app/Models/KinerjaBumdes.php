<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Ringkasan kinerja tahunan BUMDes yang dipublikasikan (REQ-F-POT-003). */
class KinerjaBumdes extends Model
{
    use HasFactory;

    protected $table = 'kinerja_bumdes';

    protected $fillable = [
        'tahun', 'pendapatan', 'laba_bersih', 'kontribusi_pades', 'catatan', 'dipublikasikan',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'pendapatan' => 'decimal:2',
            'laba_bersih' => 'decimal:2',
            'kontribusi_pades' => 'decimal:2',
            'dipublikasikan' => 'boolean',
        ];
    }
}
