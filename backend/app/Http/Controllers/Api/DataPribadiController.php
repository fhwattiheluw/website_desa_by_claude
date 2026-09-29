<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PermintaanDataPribadi;
use App\Services\DataPribadiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** REQ-F-USR-013: hak subjek data bagi pemilik akun sendiri. */
class DataPribadiController extends Controller
{
    public function __construct(private readonly DataPribadiService $layanan) {}

    /** Ringkasan hak dan status permintaan yang pernah diajukan. */
    public function ringkasan(Request $request): JsonResponse
    {
        $permintaan = PermintaanDataPribadi::where('pengguna_id', $request->user()->id)
            ->latest('id')
            ->get()
            ->map(fn (PermintaanDataPribadi $p) => [
                'id' => $p->id,
                'jenis' => $p->jenis,
                'status' => $p->status,
                'alasan' => $p->alasan,
                'catatan_petugas' => $p->catatan_petugas,
                'diajukan_pada' => $p->created_at?->toIso8601String(),
                'tenggat_jawaban' => $p->tenggat_jawaban?->toIso8601String(),
                'ditindak_pada' => $p->ditindak_pada?->toIso8601String(),
            ]);

        return response()->json([
            'tenggat_jawaban_hari' => DataPribadiService::TENGGAT_JAWABAN_HARI,
            'ada_permintaan_tertunda' => $permintaan->contains('status', PermintaanDataPribadi::MENUNGGU),
            'data' => $permintaan,
        ]);
    }

    /** Hak memperoleh salinan data pribadi, diunduh sebagai berkas JSON. */
    public function unduh(Request $request): StreamedResponse
    {
        $berkas = $this->layanan->berkas($request->user());
        $nama = 'data-pribadi-'.$request->user()->id.'-'.now()->format('Ymd').'.json';

        return response()->streamDownload(
            fn () => print json_encode($berkas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $nama,
            ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store'],
        );
    }

    /** Hak menghapus data pribadi; dijalankan setelah ditinjau petugas. */
    public function ajukanPenghapusan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'alasan' => ['nullable', 'string', 'max:1000'],
            // Penghapusan tidak dapat dibatalkan, jadi warga menegaskannya dahulu.
            'konfirmasi' => ['accepted'],
        ], [
            'konfirmasi.accepted' => 'Centang pernyataan bahwa Anda memahami akibat penghapusan data.',
        ]);

        $permintaan = $this->layanan->ajukanPenghapusan($request->user(), $data['alasan'] ?? null);

        return response()->json([
            'pesan' => 'Permintaan penghapusan data Anda diterima dan akan dijawab paling lambat '
                .DataPribadiService::TENGGAT_JAWABAN_HARI.' hari.',
            'tenggat_jawaban' => $permintaan->tenggat_jawaban?->toIso8601String(),
        ], 201);
    }
}
