<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\PeriodeStatistik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-STA-001..006; BR-15: kelompok < 5 jiwa disamarkan. */
class StatistikController extends Controller
{
    public const AMBANG_ANONIMITAS = 5;

    public function index(Request $request): JsonResponse
    {
        $periode = $request->filled('periode')
            ? PeriodeStatistik::where('id', $request->integer('periode'))->firstOrFail()
            : PeriodeStatistik::where('aktif', true)->latest('tahun')->firstOrFail();

        $periode->load(['item' => fn ($q) => $q->orderBy('kelompok')->orderBy('urutan')]);

        $kelompok = $periode->item->groupBy('kelompok')->map(
            fn ($item) => $item->map(fn ($i) => [
                'label' => $i->label,
                // BR-15: cegah identifikasi ulang pada kelompok sangat kecil.
                'jumlah' => $i->jumlah < self::AMBANG_ANONIMITAS ? null : $i->jumlah,
                'disamarkan' => $i->jumlah < self::AMBANG_ANONIMITAS,
            ])->values()
        );

        return response()->json([
            'periode' => [
                'id' => $periode->id,
                'nama' => $periode->nama,
                'tahun' => $periode->tahun,
                'sumber_data' => $periode->sumber_data,
            ],
            'total_penduduk' => (int) $periode->item->where('kelompok', 'jenis_kelamin')->sum('jumlah'),
            'total_kk' => (int) $periode->item->where('kelompok', 'kepala_keluarga')->sum('jumlah'),
            'kelompok' => $kelompok,
            'catatan_privasi' => 'Kelompok dengan jumlah di bawah '.self::AMBANG_ANONIMITAS
                .' jiwa disamarkan untuk mencegah identifikasi ulang individu.',
        ]);
    }

    public function periode(): JsonResponse
    {
        return response()->json([
            'data' => PeriodeStatistik::orderByDesc('tahun')->get(['id', 'nama', 'tahun', 'aktif']),
        ]);
    }
}
