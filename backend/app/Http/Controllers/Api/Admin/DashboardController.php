<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Konten;
use App\Models\Pengaduan;
use App\Models\Permohonan;
use App\Models\User;
use App\Services\KalenderKerja;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** REQ-F-ADM-001, REQ-F-SRT-020: ringkasan kinerja layanan bagi pimpinan. */
class DashboardController extends Controller
{
    public function __invoke(KalenderKerja $kalender): JsonResponse
    {
        $permohonan = Permohonan::query()
            ->select('status', DB::raw('count(*) as jumlah'))
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $selesai = Permohonan::whereNotNull('selesai_pada')
            ->where('selesai_pada', '>=', now()->subDays(30))
            ->whereNotNull('diajukan_pada')
            ->get(['diajukan_pada', 'selesai_pada', 'tenggat_sla']);

        $rataHariKerja = $selesai->isEmpty() ? 0 : round(
            $selesai->sum(fn ($p) => $kalender->selisihHariKerja($p->diajukan_pada, $p->selesai_pada)) / $selesai->count(),
            2,
        );

        $patuhSla = $selesai->isEmpty() ? 100 : round(
            $selesai->filter(fn ($p) => $p->tenggat_sla === null || $p->selesai_pada->lessThanOrEqualTo($p->tenggat_sla))
                ->count() / $selesai->count() * 100,
            1,
        );

        return response()->json([
            'permohonan' => [
                'per_status' => $permohonan,
                'total' => (int) $permohonan->sum(),
                'melampaui_sla' => Permohonan::terlambat()->count(),
                'perlu_tindakan' => Permohonan::whereIn('status', [
                    Permohonan::DIAJUKAN, Permohonan::DIVERIFIKASI, Permohonan::DISETUJUI,
                ])->count(),
            ],
            'kinerja_30_hari' => [
                'selesai' => $selesai->count(),
                'rata_hari_kerja' => $rataHariKerja,
                'kepatuhan_sla_persen' => $patuhSla,
            ],
            'pengaduan' => [
                'terbuka' => Pengaduan::whereNotIn('status', [Pengaduan::SELESAI, Pengaduan::DITOLAK])->count(),
                'melampaui_sla' => Pengaduan::whereNotIn('status', [Pengaduan::SELESAI, Pengaduan::DITOLAK])
                    ->where('tenggat_tanggapan', '<', now())->count(),
                'per_kategori' => Pengaduan::select('kategori', DB::raw('count(*) as jumlah'))
                    ->groupBy('kategori')->pluck('jumlah', 'kategori'),
            ],
            'konten' => [
                'menunggu_review' => Konten::where('status', 'review')->count(),
                'terbit_30_hari' => Konten::tayang()->where('terbit_pada', '>=', now()->subDays(30))->count(),
            ],
            'pengguna' => [
                'warga_menunggu_verifikasi' => User::where('status_akun', User::BELUM_VERIFIKASI)->count(),
                'total_warga' => User::whereHas('role', fn ($q) => $q->where('kode', 'warga'))->count(),
            ],
        ]);
    }
}
