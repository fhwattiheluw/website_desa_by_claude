<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

/**
 * Menyeragamkan bentuk respons daftar berhalaman (REQ-API-002).
 *
 * Secara bawaan, koleksi resource menaruh informasi halaman di dalam kunci
 * "meta", sedangkan paginator biasa menaruhnya di tingkat atas. Perbedaan itu
 * membuat klien harus membaca dua bentuk berbeda, sehingga di sini seluruh
 * koleksi resource diratakan mengikuti bentuk paginator biasa.
 */
trait KoleksiSeragam
{
    public static function collection($resource): AnonymousResourceCollection
    {
        return new class($resource, static::class) extends AnonymousResourceCollection
        {
            public function paginationInformation($request, $paginated, $default): array
            {
                return Arr::only($default['meta'] ?? [], ['current_page', 'last_page', 'per_page', 'total', 'from', 'to']);
            }
        };
    }
}
