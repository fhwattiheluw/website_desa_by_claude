<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotifikasiPetugas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-NOT-005: lonceng pekerjaan baru bagi petugas. */
class NotifikasiController extends Controller
{
    /** Batas tampilan lonceng; riwayat penuh ada pada antrean masing-masing. */
    private const BATAS = 20;

    public function index(Request $request): JsonResponse
    {
        $milik = NotifikasiPetugas::where('pengguna_id', $request->user()->id);

        return response()->json([
            'jumlah_belum_dibaca' => (clone $milik)->belumDibaca()->count(),
            'data' => (clone $milik)->latest('id')->limit(self::BATAS)->get()->map(
                fn (NotifikasiPetugas $satu) => [
                    'id' => $satu->id,
                    'jenis' => $satu->jenis,
                    'judul' => $satu->judul,
                    'ringkasan' => $satu->ringkasan,
                    'tautan' => $satu->tautan,
                    'dibaca' => $satu->dibaca_pada !== null,
                    'waktu' => $satu->created_at?->toIso8601String(),
                ],
            ),
        ]);
    }

    public function baca(Request $request, NotifikasiPetugas $notifikasi): JsonResponse
    {
        // Notifikasi milik petugas lain tidak boleh disentuh, sekalipun
        // pengenalnya ditebak dengan benar.
        abort_unless($notifikasi->pengguna_id === $request->user()->id, 404);

        $notifikasi->update(['dibaca_pada' => $notifikasi->dibaca_pada ?? now()]);

        return response()->json(['pesan' => 'Notifikasi ditandai telah dibaca.']);
    }

    public function bacaSemua(Request $request): JsonResponse
    {
        $jumlah = NotifikasiPetugas::where('pengguna_id', $request->user()->id)
            ->belumDibaca()
            ->update(['dibaca_pada' => now()]);

        return response()->json(['pesan' => "{$jumlah} notifikasi ditandai telah dibaca."]);
    }
}
