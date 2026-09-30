<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermohonanResource;
use App\Models\JenisLayanan;
use App\Models\Permohonan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\KalenderKerja;
use App\Services\MediaService;
use App\Services\PermohonanService;
use App\Services\SuratService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Antrean kerja petugas dan alur persetujuan permohonan
 * (REQ-F-SRT-019..025).
 */
class PermohonanController extends Controller
{
    public function __construct(
        private readonly PermohonanService $layanan,
        private readonly SuratService $surat,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = Permohonan::query()
            ->with('jenisLayanan', 'pemohon', 'surat')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('layanan'), fn ($q) => $q->whereHas(
                'jenisLayanan',
                fn ($l) => $l->where('kode', $request->string('layanan'))
            ))
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('sampai')))
            ->when($request->boolean('terlambat'), fn ($q) => $q->terlambat())
            ->when($request->filled('q'), fn ($q) => $q->where(
                fn ($w) => $w->cariTeks('nomor_tiket', $request->string('q')->toString())
                    ->orWhereHas('pemohon', fn ($p) => $p->cariTeks('name', $request->string('q')->toString()))
            ))
            // Antrean diurutkan berdasarkan tenggat SLA terdekat (REQ-F-SRT-019).
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END', [Permohonan::SELESAI, Permohonan::DITOLAK])
            ->orderBy('tenggat_sla')
            ->paginate(min($request->integer('per_halaman', 15), 100))
            ->withQueryString();

        return PermohonanResource::collection($data);
    }

    public function show(Permohonan $permohonan): PermohonanResource
    {
        return new PermohonanResource(
            $permohonan->load('jenisLayanan', 'pemohon', 'lampiran.media', 'riwayat.aktor', 'surat.penandatangan')
        );
    }

    public function verifikasi(Request $request, Permohonan $permohonan): JsonResponse
    {
        $catatan = $request->validate(['catatan' => ['nullable', 'string', 'max:1000']])['catatan'] ?? null;

        return $this->hasil($this->layanan->transisi(
            $permohonan, Permohonan::DIVERIFIKASI, $request->user(), $catatan ?? 'Berkas diperiksa dan dinyatakan lengkap.'
        ), 'Permohonan telah diverifikasi.');
    }

    public function kembalikan(Request $request, Permohonan $permohonan): JsonResponse
    {
        // REQ-F-SRT-010: alasan pengembalian wajib dan panjang minimalnya dijaga service.
        $data = $request->validate(['alasan' => ['required', 'string', 'min:20', 'max:1000']]);

        return $this->hasil($this->layanan->transisi(
            $permohonan, Permohonan::DIKEMBALIKAN, $request->user(), $data['alasan']
        ), 'Permohonan dikembalikan kepada pemohon untuk diperbaiki.');
    }

    public function tolak(Request $request, Permohonan $permohonan): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:20', 'max:1000']]);

        return $this->hasil($this->layanan->transisi(
            $permohonan, Permohonan::DITOLAK, $request->user(), $data['alasan']
        ), 'Permohonan ditolak.');
    }

    public function setujui(Request $request, Permohonan $permohonan): JsonResponse
    {
        $catatan = $request->validate(['catatan' => ['nullable', 'string', 'max:1000']])['catatan'] ?? null;

        return $this->hasil($this->layanan->transisi(
            $permohonan, Permohonan::DISETUJUI, $request->user(), $catatan ?? 'Permohonan disetujui.'
        ), 'Permohonan disetujui dan masuk antrean tanda tangan.');
    }

    /** REQ-F-SRT-017: penandatanganan sekaligus penerbitan dokumen. */
    public function tandaTangani(Request $request, Permohonan $permohonan): JsonResponse
    {
        $surat = $this->surat->terbitkan($permohonan, $request->user());

        return response()->json([
            'pesan' => 'Surat berhasil diterbitkan dan dapat diunduh pemohon.',
            'nomor_surat' => $surat->nomor_surat,
            'kode_verifikasi' => $surat->kode_verifikasi,
            'data' => new PermohonanResource($permohonan->fresh(['jenisLayanan', 'pemohon', 'riwayat.aktor', 'surat'])),
        ]);
    }

    public function batalkanSurat(Request $request, Permohonan $permohonan): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:20', 'max:1000']]);

        abort_if($permohonan->surat === null, 404, 'Permohonan ini belum memiliki surat terbit.');

        $this->surat->batalkan($permohonan->surat, $request->user(), $data['alasan']);

        return response()->json(['pesan' => 'Surat dinyatakan batal dan tidak berlaku.']);
    }

    /** REQ-F-SRT-024: petugas loket membuat permohonan atas nama warga. */
    public function buatkan(Request $request, MediaService $media): JsonResponse
    {
        $data = $request->validate([
            'pemohon_id' => ['required', 'exists:users,id'],
            'layanan' => ['required', 'exists:jenis_layanan,slug'],
            'data_formulir' => ['required', 'array'],
        ]);

        $layanan = JenisLayanan::where('slug', $data['layanan'])->firstOrFail();
        $request->validate($layanan->aturanValidasi());

        $permohonan = $this->layanan->buat(
            pemohon: User::findOrFail($data['pemohon_id']),
            layanan: $layanan,
            dataFormulir: $data['data_formulir'],
            kanal: 'loket',
            dibuatOleh: $request->user(),
        );

        return response()->json([
            'pesan' => 'Permohonan loket berhasil dibuat.',
            'data' => new PermohonanResource($permohonan->load('jenisLayanan', 'pemohon')),
        ], 201);
    }

    /** REQ-F-SRT-025: rekapitulasi kinerja layanan per periode. */
    public function laporan(Request $request, KalenderKerja $kalender): JsonResponse
    {
        $dari = $request->date('dari') ?? now()->startOfMonth();
        $sampai = $request->date('sampai') ?? now()->endOfMonth();

        $permohonan = Permohonan::with('jenisLayanan')
            ->whereBetween('created_at', [$dari, $sampai])
            ->get();

        $selesai = $permohonan->whereNotNull('selesai_pada')->whereNotNull('diajukan_pada');

        return response()->json([
            'periode' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
            'total' => $permohonan->count(),
            'per_status' => $permohonan->groupBy('status')->map->count(),
            'per_layanan' => $permohonan->groupBy(fn ($p) => $p->jenisLayanan->nama)->map->count(),
            'per_kanal' => $permohonan->groupBy('kanal')->map->count(),
            'rata_hari_kerja' => $selesai->isEmpty() ? 0 : round(
                $selesai->sum(fn ($p) => $kalender->selisihHariKerja($p->diajukan_pada, $p->selesai_pada)) / $selesai->count(), 2
            ),
            'kepatuhan_sla_persen' => $selesai->isEmpty() ? 100 : round(
                $selesai->filter(fn ($p) => $p->tenggat_sla === null
                    || $p->selesai_pada->lessThanOrEqualTo($p->tenggat_sla))->count() / $selesai->count() * 100, 1
            ),
            'rata_kepuasan' => round((float) $permohonan->whereNotNull('kepuasan')->avg('kepuasan'), 2),
        ]);
    }

    /**
     * Ekspor rekapitulasi layanan untuk pelaporan (REQ-F-ADM-009).
     * Kolom identitas sengaja dibatasi agar berkas laporan tidak menjadi
     * salinan data pribadi yang beredar bebas (REQ-NF-CMP-002).
     */
    public function eksporCsv(Request $request): StreamedResponse
    {
        $dari = $request->date('dari') ?? now()->startOfMonth();
        $sampai = $request->date('sampai') ?? now()->endOfMonth();

        $permohonan = Permohonan::with('jenisLayanan', 'pemohon', 'surat')
            ->whereBetween('created_at', [$dari, $sampai])
            ->orderBy('created_at')
            ->get();

        $nama = 'laporan-layanan-'.$dari->format('Ymd').'-'.$sampai->format('Ymd').'.csv';

        /*
         * Ekspor massal adalah jalur keluar data pribadi yang paling mudah
         * disalahgunakan, jadi setiap pengambilan dicatat lengkap dengan
         * rentang dan jumlah barisnya (REQ-NF-CMP-005).
         */
        $this->audit->catat('ekspor_laporan', 'Permohonan', null, null, [
            'dari' => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),
            'jumlah_baris' => $permohonan->count(),
        ]);

        return response()->streamDownload(function () use ($permohonan) {
            $keluaran = fopen('php://output', 'wb');

            fputcsv($keluaran, [
                'nomor_tiket', 'jenis_layanan', 'pemohon', 'kanal', 'status',
                'diajukan_pada', 'selesai_pada', 'tenggat_sla', 'melampaui_sla',
                'nomor_surat', 'kepuasan',
            ]);

            foreach ($permohonan as $baris) {
                fputcsv($keluaran, [
                    $baris->nomor_tiket,
                    $baris->jenisLayanan->nama,
                    $baris->pemohon?->name,
                    $baris->kanal,
                    $baris->status,
                    $baris->diajukan_pada?->format('Y-m-d H:i'),
                    $baris->selesai_pada?->format('Y-m-d H:i'),
                    $baris->tenggat_sla?->format('Y-m-d H:i'),
                    $baris->melampauiSla() ? 'ya' : 'tidak',
                    $baris->surat?->nomor_surat,
                    $baris->kepuasan,
                ]);
            }

            fclose($keluaran);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function hasil(Permohonan $permohonan, string $pesan): JsonResponse
    {
        return response()->json([
            'pesan' => $pesan,
            'data' => new PermohonanResource($permohonan->load('jenisLayanan', 'pemohon', 'riwayat.aktor')),
        ]);
    }
}
