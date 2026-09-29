<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Crypt;

class Pengaduan extends Model
{
    use HasFactory;

    public const BARU = 'baru';

    public const DIVERIFIKASI = 'diverifikasi';

    public const DIDISPOSISI = 'didisposisi';

    public const PROSES = 'proses';

    public const SELESAI = 'selesai';

    public const DITOLAK = 'ditolak';

    protected $table = 'pengaduan';

    protected $fillable = [
        'nomor_tiket', 'kode_lacak', 'pelapor_id', 'nama_pelapor', 'kontak_pelapor',
        'kategori', 'judul', 'uraian', 'lokasi', 'tanggal_kejadian', 'status',
        'didisposisi_ke', 'catatan_disposisi', 'tenggat_tanggapan', 'tampil_publik', 'anonim',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_kejadian' => 'date',
            'tenggat_tanggapan' => 'datetime',
            'tampil_publik' => 'boolean',
            'anonim' => 'boolean',
        ];
    }

    /** Kontak pelapor tergolong data pribadi sehingga disimpan terenkripsi (REQ-NF-SEC-006). */
    public function setKontakPelaporAttribute(?string $nilai): void
    {
        $this->attributes['kontak_pelapor'] = blank($nilai) ? null : Crypt::encryptString($nilai);
    }

    public function getKontakPelaporAttribute(?string $nilai): ?string
    {
        if (blank($nilai)) {
            return null;
        }

        try {
            return Crypt::decryptString($nilai);
        } catch (\Throwable) {
            return null;
        }
    }

    public function tanggapan(): HasMany
    {
        return $this->hasMany(Tanggapan::class)->oldest();
    }

    public function petugasDisposisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'didisposisi_ke');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiranable');
    }

    /** BR-11: identitas pelapor tidak pernah tampil ke publik. */
    public function pelaporTersamar(): string
    {
        $nama = $this->anonim ? null : $this->nama_pelapor;

        if (blank($nama)) {
            return 'Warga';
        }

        $bagian = preg_split('/\s+/', trim($nama)) ?: [];

        return $bagian[0].' '.strtoupper(substr($bagian[count($bagian) - 1] ?? '', 0, 1)).'.';
    }
}
