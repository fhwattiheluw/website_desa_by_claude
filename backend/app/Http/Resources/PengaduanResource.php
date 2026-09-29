<?php

namespace App\Http\Resources;

use App\Models\Pengaduan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pengaduan */
class PengaduanResource extends JsonResource
{
    use KoleksiSeragam;

    public function toArray(Request $request): array
    {
        $petugas = (bool) $request->user()?->petugas();

        return [
            'id' => $this->id,
            'nomor_tiket' => $this->nomor_tiket,
            // Kode lacak hanya dikembalikan kepada petugas atau saat pembuatan.
            'kode_lacak' => $this->when($petugas, $this->kode_lacak),
            'kategori' => $this->kategori,
            'judul' => $this->judul,
            'uraian' => $this->uraian,
            'lokasi' => $this->lokasi,
            'tanggal_kejadian' => $this->tanggal_kejadian?->toDateString(),
            'status' => $this->status,
            'tenggat_tanggapan' => $this->tenggat_tanggapan?->toIso8601String(),
            'melampaui_sla' => $this->tenggat_tanggapan?->isPast()
                && ! in_array($this->status, [Pengaduan::SELESAI, Pengaduan::DITOLAK], true),
            'tampil_publik' => $this->tampil_publik,
            // BR-11: identitas pelapor tidak pernah tampil ke publik.
            'pelapor' => $petugas ? ($this->nama_pelapor ?? 'Anonim') : $this->pelaporTersamar(),
            'kontak_pelapor' => $this->when($petugas, $this->kontak_pelapor),
            'didisposisi_ke' => $this->whenLoaded('petugasDisposisi', fn () => $this->petugasDisposisi?->name),
            'dibuat_pada' => $this->created_at?->toIso8601String(),
            'tanggapan' => $this->whenLoaded('tanggapan', fn () => $this->tanggapan
                ->filter(fn ($t) => $petugas || ! $t->internal)
                ->values()
                ->map(fn ($t) => [
                    'isi' => $t->isi,
                    'internal' => $t->internal,
                    'oleh' => $t->aktor?->name ?? 'Petugas Desa',
                    'waktu' => $t->created_at?->toIso8601String(),
                ])),
        ];
    }
}
