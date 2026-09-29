<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $fillable = ['kunci', 'nilai', 'grup', 'tipe', 'label'];

    public static function ambil(string $kunci, mixed $bawaan = null): mixed
    {
        return static::semua()[$kunci] ?? $bawaan;
    }

    /** @return array<string, mixed> */
    public static function semua(): array
    {
        return Cache::remember('pengaturan.semua', 3600, fn () => static::query()
            ->pluck('nilai', 'kunci')
            ->all());
    }

    public static function simpan(string $kunci, mixed $nilai, string $grup = 'umum'): self
    {
        $row = static::updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai, 'grup' => $grup]);
        Cache::forget('pengaturan.semua');

        return $row;
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('pengaturan.semua'));
        static::deleted(fn () => Cache::forget('pengaturan.semua'));
    }
}
