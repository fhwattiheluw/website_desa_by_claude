<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REQ-F-KNT-013: komentar pembaca yang wajib dimoderasi sebelum tayang. */
class Komentar extends Model
{
    public const MENUNGGU = 'menunggu';

    public const DISETUJUI = 'disetujui';

    public const DITOLAK = 'ditolak';

    protected $table = 'komentar';

    protected $fillable = [
        'konten_id', 'penulis_id', 'nama', 'isi', 'status',
        'dimoderasi_oleh', 'dimoderasi_pada', 'alasan_penolakan', 'alamat_ip',
    ];

    protected $hidden = ['alamat_ip'];

    protected function casts(): array
    {
        return ['dimoderasi_pada' => 'datetime'];
    }

    public function konten(): BelongsTo
    {
        return $this->belongsTo(Konten::class);
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penulis_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dimoderasi_oleh');
    }

    public function scopeTayang(Builder $kueri): Builder
    {
        return $kueri->where('status', self::DISETUJUI);
    }
}
