<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisLayanan extends Model
{
    use HasFactory;

    protected $table = 'jenis_layanan';

    protected $fillable = [
        'kode', 'nama', 'slug', 'deskripsi', 'persyaratan', 'kolom_formulir',
        'sla_hari_kerja', 'format_nomor', 'templat', 'aktif', 'urutan',
    ];

    protected function casts(): array
    {
        return [
            'persyaratan' => 'array',
            'kolom_formulir' => 'array',
            'aktif' => 'boolean',
            'sla_hari_kerja' => 'integer',
        ];
    }

    public function permohonan(): HasMany
    {
        return $this->hasMany(Permohonan::class);
    }

    /**
     * Aturan validasi Laravel yang diturunkan dari definisi kolom dinamis (REQ-F-SRT-003, 004).
     *
     * @return array<string, string>
     */
    public function aturanValidasi(): array
    {
        $aturan = [];

        foreach ($this->kolom_formulir as $kolom) {
            $item = [$kolom['wajib'] ?? true ? 'required' : 'nullable'];

            $item[] = match ($kolom['tipe'] ?? 'teks') {
                'nik', 'kk' => 'digits:16',
                'angka' => 'numeric|min:0',
                'tanggal' => 'date|before_or_equal:today',
                'telepon' => 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/',
                'pilihan' => 'string|max:100',
                'teks_panjang' => 'string|max:2000',
                'centang' => 'accepted',
                default => 'string|max:255',
            };

            $aturan['data_formulir.'.$kolom['nama']] = implode('|', $item);
        }

        return $aturan;
    }
}
