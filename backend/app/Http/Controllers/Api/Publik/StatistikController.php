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
            // REQ-F-STA-004: pembanding dengan periode sebelumnya.
            'pembanding' => $this->pembanding($periode),
            'total_penduduk' => (int) $periode->item->where('kelompok', 'jenis_kelamin')->sum('jumlah'),
            'total_kk' => (int) $periode->item->where('kelompok', 'kepala_keluarga')->sum('jumlah'),
            'kelompok' => $kelompok,
            'catatan_privasi' => 'Kelompok dengan jumlah di bawah '.self::AMBANG_ANONIMITAS
                .' jiwa disamarkan untuk mencegah identifikasi ulang individu.',
        ]);
    }

    /**
     * Perubahan terhadap periode sebelumnya.
     *
     * Angka mentah statistik desa sulit dimaknai tanpa pembanding: 4.120 jiwa
     * baru berarti sesuatu bila diketahui tahun lalu 4.005. Kelompok yang
     * disamarkan karena BR-15 tidak ikut dibandingkan — selisihnya justru dapat
     * dipakai menyimpulkan angka yang sengaja disembunyikan.
     *
     * @return array<string, mixed>|null
     */
    private function pembanding(PeriodeStatistik $periode): ?array
    {
        $sebelumnya = PeriodeStatistik::where('tahun', '<', $periode->tahun)
            ->orderByDesc('tahun')
            ->first();

        if (! $sebelumnya) {
            return null;
        }

        $sebelumnya->load('item');

        $totalKini = (int) $periode->item->where('kelompok', 'jenis_kelamin')->sum('jumlah');
        $totalLalu = (int) $sebelumnya->item->where('kelompok', 'jenis_kelamin')->sum('jumlah');

        $lalu = $sebelumnya->item
            ->filter(fn ($i) => $i->jumlah >= self::AMBANG_ANONIMITAS)
            ->keyBy(fn ($i) => $i->kelompok.'|'.$i->label);

        $perKelompok = $periode->item
            ->filter(fn ($i) => $i->jumlah >= self::AMBANG_ANONIMITAS)
            ->map(function ($i) use ($lalu) {
                $pasangan = $lalu->get($i->kelompok.'|'.$i->label);

                return $pasangan ? [
                    'kelompok' => $i->kelompok,
                    'label' => $i->label,
                    'kini' => (int) $i->jumlah,
                    'sebelumnya' => (int) $pasangan->jumlah,
                    'selisih' => (int) $i->jumlah - (int) $pasangan->jumlah,
                ] : null;
            })
            ->filter()
            ->values();

        return [
            'periode' => ['id' => $sebelumnya->id, 'nama' => $sebelumnya->nama, 'tahun' => $sebelumnya->tahun],
            'total_penduduk' => $totalLalu,
            'selisih_total' => $totalKini - $totalLalu,
            'per_kelompok' => $perKelompok,
            'catatan' => 'Kelompok yang disamarkan tidak ikut dibandingkan, sebab selisihnya dapat dipakai '
                .'menyimpulkan angka yang sengaja disembunyikan.',
        ];
    }

    public function periode(): JsonResponse
    {
        return response()->json([
            'data' => PeriodeStatistik::orderByDesc('tahun')->get(['id', 'nama', 'tahun', 'aktif']),
        ]);
    }
}
