<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gambar tanda tangan pejabat yang dipakai saat penyedia TTE tersertifikasi
 * belum tersedia (REQ-F-SRT-017).
 *
 * Berkas disimpan pada disk privat dan tidak pernah memiliki URL publik.
 */
class SpesimenTandaTangan extends Model
{
    protected $table = 'spesimen_tanda_tangan';

    protected $fillable = ['user_id', 'path', 'disk', 'hash_berkas', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function pemilik(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
