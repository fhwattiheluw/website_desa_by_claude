<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REQ-F-USR-013: permintaan hak subjek data beserta penanganannya. */
class PermintaanDataPribadi extends Model
{
    use HasFactory;

    public const MENUNGGU = 'menunggu';

    public const DISETUJUI = 'disetujui';

    public const DITOLAK = 'ditolak';

    public const HAPUS = 'hapus';

    protected $table = 'permintaan_data_pribadi';

    protected $fillable = [
        'pengguna_id', 'jenis', 'status', 'alasan', 'catatan_petugas',
        'ditindak_oleh', 'ditindak_pada', 'tenggat_jawaban',
    ];

    protected function casts(): array
    {
        return ['ditindak_pada' => 'datetime', 'tenggat_jawaban' => 'datetime'];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditindak_oleh');
    }

    public function menunggu(): bool
    {
        return $this->status === self::MENUNGGU;
    }
}
