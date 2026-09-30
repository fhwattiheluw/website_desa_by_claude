<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\Umkm;
use App\Models\Wisata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-POT-001..006. */
class PotensiController extends Controller
{
    public function umkm(Request $request): JsonResponse
    {
        $data = Umkm::tayang()
            ->with('media')
            ->when($request->filled('kategori'), fn ($q) => $q->where('kategori', $request->string('kategori')))
            ->cariTeks(['nama_usaha', 'deskripsi'], $request->string('q')->toString())
            ->orderBy('nama_usaha')
            ->paginate(12)
            ->through(fn (Umkm $u) => [
                'nama_usaha' => $u->nama_usaha,
                'slug' => $u->slug,
                'pemilik' => $u->pemilik,
                'kategori' => $u->kategori,
                'deskripsi' => $u->deskripsi,
                'alamat' => $u->alamat,
                // BR-16: kontak hanya tampil bila pemilik menyetujui.
                'telepon' => $u->teleponPublik(),
                'foto' => $u->media?->url(),
            ]);

        return response()->json($data);
    }

    /** REQ-F-POT-002: pendaftaran mandiri yang menunggu verifikasi operator. */
    public function daftarUmkm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_usaha' => ['required', 'string', 'max:150'],
            'pemilik' => ['required', 'string', 'max:150'],
            'kategori' => ['required', 'string', 'max:60'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'telepon' => ['nullable', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'alamat' => ['nullable', 'string', 'max:250'],
            'consent_kontak' => ['boolean'],
        ]);

        $umkm = Umkm::create([
            ...$data,
            'slug' => str($data['nama_usaha'])->slug().'-'.str()->random(5),
            'diajukan_oleh' => $request->user()?->id,
            'status' => 'menunggu',
        ]);

        return response()->json([
            'pesan' => 'Pendaftaran usaha diterima dan akan ditinjau petugas desa sebelum ditayangkan.',
            'slug' => $umkm->slug,
        ], 201);
    }

    public function wisata(): JsonResponse
    {
        return response()->json([
            'data' => Wisata::with('media')->orderBy('nama')->get()->map(fn (Wisata $w) => [
                'nama' => $w->nama,
                'slug' => $w->slug,
                'deskripsi' => $w->deskripsi,
                'jam_operasional' => $w->jam_operasional,
                'tarif' => $w->tarif,
                'alamat' => $w->alamat,
                'koordinat' => $w->lat && $w->lng ? ['lat' => (float) $w->lat, 'lng' => (float) $w->lng] : null,
                'foto' => $w->media?->url(),
            ]),
        ]);
    }
}
