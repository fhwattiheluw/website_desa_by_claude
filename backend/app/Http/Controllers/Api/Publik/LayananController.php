<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\JenisLayanan;
use Illuminate\Http\JsonResponse;

/** REQ-F-SRT-001: katalog layanan lengkap dengan persyaratan, biaya, dan SLA. */
class LayananController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => JenisLayanan::where('aktif', true)
                ->orderBy('urutan')
                ->get()
                ->map(fn (JenisLayanan $l) => [
                    'kode' => $l->kode,
                    'nama' => $l->nama,
                    'slug' => $l->slug,
                    'deskripsi' => $l->deskripsi,
                    'persyaratan' => $l->persyaratan,
                    'sla_hari_kerja' => $l->sla_hari_kerja,
                    'biaya' => 0,
                ]),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $layanan = JenisLayanan::where('slug', $slug)->where('aktif', true)->firstOrFail();

        return response()->json([
            'kode' => $layanan->kode,
            'nama' => $layanan->nama,
            'slug' => $layanan->slug,
            'deskripsi' => $layanan->deskripsi,
            'persyaratan' => $layanan->persyaratan,
            'kolom_formulir' => $layanan->kolom_formulir,
            'sla_hari_kerja' => $layanan->sla_hari_kerja,
            'biaya' => 0,
        ]);
    }
}
