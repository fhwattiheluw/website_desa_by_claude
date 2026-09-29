<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahunAnggaran extends Model
{
    use HasFactory;

    protected $table = 'tahun_anggaran';

    protected $fillable = ['tahun', 'dipublikasikan', 'disetujui_oleh', 'dipublikasikan_pada', 'catatan'];

    protected function casts(): array
    {
        return ['dipublikasikan' => 'boolean', 'dipublikasikan_pada' => 'datetime', 'tahun' => 'integer'];
    }

    public function item(): HasMany
    {
        return $this->hasMany(ItemApbdes::class);
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenAnggaran::class);
    }

    /** @return array<string, float> */
    public function ringkasan(): array
    {
        return $this->item->groupBy('jenis')->map(fn ($g) => [
            'pagu' => (float) $g->sum('pagu'),
            'realisasi' => (float) $g->sum('realisasi'),
        ])->all();
    }
}
