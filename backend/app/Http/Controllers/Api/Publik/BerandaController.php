<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Http\Resources\KontenResource;
use App\Models\ItemStatistik;
use App\Models\Konten;
use App\Models\Lembaga;
use App\Models\Pengaturan;
use App\Models\Pengurus;
use App\Models\PeriodeStatistik;
use Illuminate\Http\JsonResponse;

/** REQ-F-BRD-001: muatan halaman beranda dalam satu permintaan. */
class BerandaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $periode = PeriodeStatistik::where('aktif', true)->latest('tahun')->first();
        $pengaturan = Pengaturan::semua();

        return response()->json([
            'desa' => $pengaturan,
            // REQ-F-BRD-007: sambutan kepala desa beserta foto dan kutipannya.
            'sambutan' => $this->sambutan($pengaturan),
            'sorotan' => KontenResource::collection(
                Konten::tayang()->where('sorotan', true)->with('gambar', 'kategori')
                    ->latest('terbit_pada')->take(5)->get()
            ),
            'berita' => KontenResource::collection(
                Konten::tayang()->where('tipe', 'berita')->with('gambar', 'kategori')
                    ->latest('terbit_pada')->take(6)->get()
            ),
            'pengumuman' => KontenResource::collection(
                Konten::tayang()->where('tipe', 'pengumuman')->latest('terbit_pada')->take(5)->get()
            ),
            'agenda' => KontenResource::collection(
                Konten::tayang()->where('tipe', 'agenda')->where('mulai_pada', '>=', now()->startOfDay())
                    ->orderBy('mulai_pada')->take(4)->get()
            ),
            'statistik' => $periode ? [
                'periode' => $periode->nama,
                'sumber' => $periode->sumber_data,
                'total_penduduk' => (int) ItemStatistik::where('periode_statistik_id', $periode->id)
                    ->where('kelompok', 'jenis_kelamin')->sum('jumlah'),
                'ringkas' => ItemStatistik::where('periode_statistik_id', $periode->id)
                    ->where('kelompok', 'jenis_kelamin')->get(['label', 'jumlah']),
            ] : null,
        ]);
    }

    /**
     * Penyambut diambil dari puncak bagan pemerintah desa, bukan dari
     * pencocokan teks jabatan: bila kepala desa berganti atau jabatannya
     * dituliskan berbeda, sambutan tetap menunjuk orang yang benar.
     *
     * @param  array<string, mixed>  $pengaturan
     * @return array<string, mixed>|null
     */
    private function sambutan(array $pengaturan): ?array
    {
        $kutipan = $pengaturan['sambutan_kepala_desa'] ?? null;

        if (blank($kutipan)) {
            return null;
        }

        $lembaga = Lembaga::where('jenis', 'pemerintah_desa')->first();

        $kepala = $lembaga
            ? Pengurus::with('foto')
                ->where('lembaga_id', $lembaga->id)
                ->whereNull('atasan_id')
                ->orderBy('urutan')
                ->first()
            : null;

        return [
            'nama' => $kepala?->nama,
            'jabatan' => $kepala?->jabatan ?? 'Kepala Desa',
            'foto' => $kepala?->foto?->url(),
            'kutipan' => $kutipan,
        ];
    }
}
