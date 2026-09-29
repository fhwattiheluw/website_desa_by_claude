<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REQ-F-ADM-005: audit log bersifat hanya-baca bagi seluruh peran. */
class AuditLog extends Model
{
    protected $table = 'audit_log';

    public $timestamps = false;

    protected $fillable = [
        'aktor_id', 'aksi', 'entitas', 'entitas_id', 'sebelum', 'sesudah',
        'alamat_ip', 'agen', 'created_at',
    ];

    protected function casts(): array
    {
        return ['sebelum' => 'array', 'sesudah' => 'array', 'created_at' => 'datetime'];
    }

    public function aktor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aktor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException('Audit log tidak dapat diubah.'));
        static::deleting(fn () => throw new \RuntimeException('Audit log tidak dapat dihapus.'));
    }
}
