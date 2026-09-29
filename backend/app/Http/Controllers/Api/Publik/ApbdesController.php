<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\TahunAnggaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-APB-001..008; BR-08: hanya tahun anggaran terpublikasi yang tampil. */
class ApbdesController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => TahunAnggaran::where('dipublikasikan', true)
                ->orderByDesc('tahun')
                ->get(['id', 'tahun', 'dipublikasikan_pada']),
        ]);
    }

    public function show(Request $request, int $tahun): JsonResponse
    {
        $anggaran = TahunAnggaran::where('tahun', $tahun)
            ->where('dipublikasikan', true)
            ->with(['item' => fn ($q) => $q->orderBy('jenis')->orderBy('urutan'), 'dokumen.media'])
            ->firstOrFail();

        $ringkasan = $anggaran->ringkasan();

        return response()->json([
            'tahun' => $anggaran->tahun,
            // REQ-F-APB-006: publik perlu tahu kapan data terakhir dimutakhirkan.
            'diperbarui_pada' => $anggaran->updated_at?->toIso8601String(),
            'dipublikasikan_pada' => $anggaran->dipublikasikan_pada?->toIso8601String(),
            'ringkasan' => [
                'pendapatan' => $ringkasan['pendapatan'] ?? ['pagu' => 0, 'realisasi' => 0],
                'belanja' => $ringkasan['belanja'] ?? ['pagu' => 0, 'realisasi' => 0],
                'pembiayaan' => $ringkasan['pembiayaan'] ?? ['pagu' => 0, 'realisasi' => 0],
            ],
            'item' => $anggaran->item->map(fn ($i) => [
                'jenis' => $i->jenis,
                'bidang' => $i->bidang,
                'kegiatan' => $i->kegiatan,
                'pagu' => (float) $i->pagu,
                'realisasi' => (float) $i->realisasi,
                'penyerapan' => (float) $i->pagu > 0
                    ? round(((float) $i->realisasi / (float) $i->pagu) * 100, 2)
                    : 0,
            ]),
            'dokumen' => $anggaran->dokumen->map(fn ($d) => [
                'judul' => $d->judul,
                'jenis' => $d->jenis_dokumen,
                'url' => $d->media?->url(),
            ]),
        ]);
    }

    /** REQ-F-APB-008: unduhan data anggaran dalam format terbuka. */
    public function csv(int $tahun)
    {
        $anggaran = TahunAnggaran::where('tahun', $tahun)
            ->where('dipublikasikan', true)
            ->with('item')
            ->firstOrFail();

        $baris = ['jenis,bidang,kegiatan,pagu,realisasi'];

        foreach ($anggaran->item as $item) {
            $baris[] = sprintf(
                '%s,"%s","%s",%s,%s',
                $item->jenis,
                str_replace('"', '""', $item->bidang),
                str_replace('"', '""', (string) $item->kegiatan),
                $item->pagu,
                $item->realisasi,
            );
        }

        return response(implode("\n", $baris), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=apbdes-{$tahun}.csv",
        ]);
    }
}
