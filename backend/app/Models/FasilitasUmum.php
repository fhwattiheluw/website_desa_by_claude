<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** REQ-F-BRD-004: fasilitas umum utama yang ditandai pada peta wilayah desa. */
class FasilitasUmum extends Model
{
    public const JENIS = ['kantor', 'pendidikan', 'kesehatan', 'ibadah', 'olahraga', 'ekonomi', 'lainnya'];

    protected $table = 'fasilitas_umum';

    protected $fillable = ['nama', 'jenis', 'alamat', 'keterangan', 'lat', 'lng', 'urutan'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }
}
