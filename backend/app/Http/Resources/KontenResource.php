<?php

namespace App\Http\Resources;

use App\Models\Konten;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Konten */
class KontenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipe' => $this->tipe,
            'judul' => $this->judul,
            'slug' => $this->slug,
            'ringkasan' => $this->ringkasan,
            'isi' => $this->when($request->routeIs('*.show') || $request->boolean('lengkap'), $this->isi),
            'status' => $this->status,
            'sorotan' => $this->sorotan,
            'dibaca' => $this->dibaca,
            'tag' => $this->tag ?? [],
            'gambar' => $this->whenLoaded('gambar', fn () => [
                'url' => $this->gambar?->url(),
                'alt' => $this->gambar?->alt,
            ]),
            'kategori' => $this->whenLoaded('kategori', fn () => $this->kategori ? [
                'nama' => $this->kategori->nama,
                'slug' => $this->kategori->slug,
            ] : null),
            'penulis' => $this->whenLoaded('penulis', fn () => $this->penulis?->name),
            'terbit_pada' => $this->terbit_pada?->toIso8601String(),
            'kedaluwarsa_pada' => $this->kedaluwarsa_pada?->toIso8601String(),
            'mulai_pada' => $this->mulai_pada?->toIso8601String(),
            'selesai_pada' => $this->selesai_pada?->toIso8601String(),
            'lokasi' => $this->lokasi,
            'penyelenggara' => $this->penyelenggara,
        ];
    }
}
