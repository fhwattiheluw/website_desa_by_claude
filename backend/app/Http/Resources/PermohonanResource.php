<?php

namespace App\Http\Resources;

use App\Models\Permohonan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Permohonan */
class PermohonanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $petugas = (bool) $request->user()?->petugas();

        return [
            'id' => $this->id,
            'nomor_tiket' => $this->nomor_tiket,
            'status' => $this->status,
            'kanal' => $this->kanal,
            'alasan' => $this->alasan,
            'melampaui_sla' => $this->melampauiSla(),
            'tenggat_sla' => $this->tenggat_sla?->toIso8601String(),
            'diajukan_pada' => $this->diajukan_pada?->toIso8601String(),
            'selesai_pada' => $this->selesai_pada?->toIso8601String(),
            'kepuasan' => $this->kepuasan,
            'layanan' => $this->whenLoaded('jenisLayanan', fn () => [
                'kode' => $this->jenisLayanan->kode,
                'nama' => $this->jenisLayanan->nama,
                'slug' => $this->jenisLayanan->slug,
                'sla_hari_kerja' => $this->jenisLayanan->sla_hari_kerja,
                'kolom_formulir' => $this->jenisLayanan->kolom_formulir,
            ]),
            // Data formulir memuat data pribadi: hanya pemohon dan petugas yang berhak.
            'data_formulir' => $this->data_formulir,
            'pemohon' => $this->whenLoaded('pemohon', fn () => [
                'nama' => $this->pemohon->name,
                'nik_tersamar' => $petugas ? $this->pemohon->nikTersamar() : null,
                'telepon' => $petugas ? $this->pemohon->telepon : null,
            ]),
            'lampiran' => $this->whenLoaded('lampiran', fn () => $this->lampiran->map(fn ($l) => [
                'id' => $l->id,
                'label' => $l->label,
                'nama' => $l->media?->nama_asli,
                'ukuran' => $l->media?->ukuran,
            ])),
            'riwayat' => $this->whenLoaded('riwayat', fn () => $this->riwayat->map(fn ($r) => [
                'dari' => $r->dari,
                'ke' => $r->ke,
                'catatan' => $r->catatan,
                'aktor' => $r->aktor?->name,
                'waktu' => $r->created_at?->toIso8601String(),
            ])),
            'surat' => $this->whenLoaded('surat', fn () => $this->surat ? [
                'nomor_surat' => $this->surat->nomor_surat,
                'tanggal_terbit' => $this->surat->tanggal_terbit?->toDateString(),
                'kode_verifikasi' => $this->surat->kode_verifikasi,
                'status_keabsahan' => $this->surat->status_keabsahan,
            ] : null),
        ];
    }
}
