<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\Lembaga;
use Illuminate\Http\JsonResponse;

/**
 * Profil lembaga dan aparatur desa (REQ-F-LMB-001..004).
 * REQ-F-LMB-004: data pribadi sensitif aparatur tidak pernah ditampilkan publik.
 */
class LembagaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Lembaga::with('pengurus.foto')->orderBy('urutan')->get()->map(fn (Lembaga $l) => [
                'nama' => $l->nama,
                'slug' => $l->slug,
                'jenis' => $l->jenis,
                'deskripsi' => $l->deskripsi,
                'pengurus' => $l->pengurus->map(fn ($p) => [
                    'nama' => $p->nama,
                    'jabatan' => $p->jabatan,
                    'wilayah' => $p->wilayah,
                    'masa_jabatan' => $p->masa_jabatan_mulai
                        ? $p->masa_jabatan_mulai.'–'.($p->masa_jabatan_selesai ?? 'sekarang')
                        : null,
                    'tugas_pokok' => $p->tugas_pokok,
                    'foto' => $p->foto?->url(),
                ]),
            ]),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $lembaga = Lembaga::where('slug', $slug)->with('pengurus.foto')->firstOrFail();

        return response()->json([
            'nama' => $lembaga->nama,
            'jenis' => $lembaga->jenis,
            'deskripsi' => $lembaga->deskripsi,
            'pengurus' => $lembaga->pengurus->map(fn ($p) => [
                'nama' => $p->nama,
                'jabatan' => $p->jabatan,
                'wilayah' => $p->wilayah,
                'foto' => $p->foto?->url(),
            ]),
        ]);
    }
}
