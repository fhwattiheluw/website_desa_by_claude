<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemApbdes extends Model
{
    use HasFactory;

    protected $table = 'item_apbdes';

    protected $fillable = ['tahun_anggaran_id', 'jenis', 'bidang', 'kegiatan', 'pagu', 'realisasi', 'urutan'];

    protected function casts(): array
    {
        return ['pagu' => 'decimal:2', 'realisasi' => 'decimal:2'];
    }

    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class);
    }
}
