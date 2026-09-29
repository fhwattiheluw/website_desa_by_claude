<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdukHukum extends Model
{
    use HasFactory;

    protected $table = 'produk_hukum';

    protected $fillable = ['jenis', 'nomor', 'tahun', 'judul', 'tentang', 'berlaku', 'dicabut_oleh_id', 'media_id'];

    protected function casts(): array
    {
        return ['berlaku' => 'boolean', 'tahun' => 'integer'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** REQ-F-PID-006: rujukan ke produk hukum pengganti. */
    public function dicabutOleh(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dicabut_oleh_id');
    }
}
