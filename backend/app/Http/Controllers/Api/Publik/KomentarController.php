<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\Komentar;
use App\Models\Konten;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Komentar pembaca pada berita dan artikel (REQ-F-KNT-013).
 *
 * Seluruh komentar wajib melewati moderasi sebelum tayang. Desa memikul
 * tanggung jawab atas apa yang terbit di lamannya, sehingga tidak ada jalur
 * yang membuat tulisan orang lain langsung tampil.
 */
class KomentarController extends Controller
{
    /** Hanya tulisan yang memang mengundang tanggapan. */
    private const TIPE_DIIZINKAN = ['berita', 'artikel'];

    public function index(string $tipe, string $slug): JsonResponse
    {
        $konten = $this->konten($tipe, $slug);

        return response()->json([
            'data' => $konten->komentar()
                ->tayang()
                ->oldest('dimoderasi_pada')
                ->get()
                ->map(fn (Komentar $k) => [
                    'nama' => $k->nama,
                    'isi' => $k->isi,
                    // Waktu tayang, bukan waktu kirim: itu yang dilihat pembaca.
                    'waktu' => $k->dimoderasi_pada?->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request, string $tipe, string $slug, AuditLogger $audit): JsonResponse
    {
        $konten = $this->konten($tipe, $slug);
        $pengguna = $request->user();

        $data = $request->validate([
            // Pengguna yang sudah masuk memakai namanya sendiri.
            'nama' => [$pengguna ? 'nullable' : 'required', 'string', 'min:3', 'max:150'],
            'isi' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $komentar = $konten->komentar()->create([
            'penulis_id' => $pengguna?->id,
            'nama' => $pengguna?->name ?? $data['nama'],
            'isi' => $data['isi'],
            'status' => Komentar::MENUNGGU,
            'alamat_ip' => $request->ip(),
        ]);

        $audit->catat('kirim_komentar', 'Komentar', $komentar->id);

        return response()->json([
            'pesan' => 'Terima kasih. Komentar Anda akan tayang setelah ditinjau petugas desa.',
        ], 201);
    }

    private function konten(string $tipe, string $slug): Konten
    {
        abort_unless(in_array($tipe, self::TIPE_DIIZINKAN, true), 404);

        return Konten::tayang()->where('tipe', $tipe)->where('slug', $slug)->firstOrFail();
    }
}
