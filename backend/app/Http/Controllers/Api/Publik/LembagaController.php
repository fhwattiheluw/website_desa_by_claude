<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\Lembaga;
use App\Models\Pengurus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

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
                'pengurus' => $l->pengurus->map(fn (Pengurus $p) => $this->ringkas($p)),
                // REQ-F-BRD-003: susunan berjenjang untuk ditampilkan sebagai bagan.
                'bagan' => $this->bagan($l->pengurus),
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
            'pengurus' => $lembaga->pengurus->map(fn (Pengurus $p) => $this->ringkas($p)),
            'bagan' => $this->bagan($lembaga->pengurus),
        ]);
    }

    /** @return array<string, mixed> */
    private function ringkas(Pengurus $pengurus): array
    {
        return [
            'id' => $pengurus->id,
            'nama' => $pengurus->nama,
            'jabatan' => $pengurus->jabatan,
            'wilayah' => $pengurus->wilayah,
            'masa_jabatan' => $pengurus->masaJabatan(),
            'tugas_pokok' => $pengurus->tugas_pokok,
            'foto' => $pengurus->foto?->url(),
        ];
    }

    /**
     * Menyusun pengurus menjadi pohon atasan–bawahan.
     *
     * Dibentuk dari koleksi yang sudah dimuat, bukan kueri berulang per
     * tingkat, agar bagan sedalam apa pun tetap satu kueri (REQ-NF-PRF-002).
     * Pengurus tanpa atasan — atau yang atasannya tidak berada pada lembaga
     * yang sama — menjadi akar, sehingga tidak ada yang hilang dari bagan.
     *
     * @param  Collection<int, Pengurus>  $pengurus
     * @return array<int, array<string, mixed>>
     */
    private function bagan(Collection $pengurus): array
    {
        $perAtasan = $pengurus->groupBy('atasan_id');
        $id = $pengurus->pluck('id')->all();

        $susun = function (Pengurus $satu) use (&$susun, $perAtasan): array {
            return [
                ...$this->ringkas($satu),
                'bawahan' => ($perAtasan[$satu->id] ?? collect())
                    ->map(fn (Pengurus $anak) => $susun($anak))
                    ->values()
                    ->all(),
            ];
        };

        return $pengurus
            ->filter(fn (Pengurus $satu) => $satu->atasan_id === null || ! in_array($satu->atasan_id, $id, true))
            ->map(fn (Pengurus $akar) => $susun($akar))
            ->values()
            ->all();
    }
}
