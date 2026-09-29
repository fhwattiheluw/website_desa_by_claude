<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Http\Resources\KontenResource;
use App\Models\ItemStatistik;
use App\Models\Konten;
use App\Models\Pengaturan;
use App\Models\PeriodeStatistik;
use Illuminate\Http\JsonResponse;

/** REQ-F-BRD-001: muatan halaman beranda dalam satu permintaan. */
class BerandaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $periode = PeriodeStatistik::where('aktif', true)->latest('tahun')->first();

        return response()->json([
            'desa' => Pengaturan::semua(),
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
}
