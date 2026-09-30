<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\InformasiPublik;
use App\Models\PermohonanInformasi;
use App\Models\ProdukHukum;
use App\Services\AuditLogger;
use App\Services\KalenderKerja;
use App\Services\NomorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-PID-001..006: PPID, daftar informasi publik, dan produk hukum. */
class PustakaController extends Controller
{
    /** Batas waktu jawaban permohonan informasi publik sesuai UU 14/2008. */
    public const SLA_JAWABAN_HARI_KERJA = 10;

    public function produkHukum(Request $request): JsonResponse
    {
        $data = ProdukHukum::query()
            ->with('dicabutOleh:id,jenis,nomor,tahun,judul', 'media')
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->string('jenis')))
            ->when($request->filled('tahun'), fn ($q) => $q->where('tahun', $request->integer('tahun')))
            ->cariTeks(['judul', 'tentang', 'nomor'], $request->string('q')->toString())
            ->orderByDesc('tahun')
            ->orderByDesc('nomor')
            ->paginate(15)
            ->through(fn (ProdukHukum $p) => [
                'id' => $p->id,
                'jenis' => $p->jenis,
                'nomor' => $p->nomor,
                'tahun' => $p->tahun,
                'judul' => $p->judul,
                'tentang' => $p->tentang,
                'berlaku' => $p->berlaku,
                // REQ-F-PID-006: produk hukum yang dicabut tetap dapat diakses (BR-14).
                'keterangan' => $p->berlaku ? 'Berlaku' : 'Tidak berlaku',
                'dicabut_oleh' => $p->dicabutOleh?->only(['jenis', 'nomor', 'tahun', 'judul']),
                'berkas' => $p->media?->url(),
            ]);

        return response()->json($data);
    }

    public function informasiPublik(): JsonResponse
    {
        $data = InformasiPublik::with('media')->get()->groupBy('klasifikasi')->map(
            fn ($items) => $items->map(fn (InformasiPublik $i) => [
                'judul' => $i->judul,
                'ringkasan' => $i->ringkasan,
                'penanggung_jawab' => $i->penanggung_jawab,
                'periode_terbit' => $i->periode_terbit,
                'berkas' => $i->media?->url(),
            ])->values()
        );

        return response()->json([
            'berkala' => $data['berkala'] ?? [],
            'serta_merta' => $data['serta_merta'] ?? [],
            'setiap_saat' => $data['setiap_saat'] ?? [],
        ]);
    }

    /** REQ-F-PID-003: permohonan informasi publik daring. */
    public function ajukanInformasi(
        Request $request,
        NomorService $nomor,
        KalenderKerja $kalender,
        AuditLogger $audit,
    ): JsonResponse {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'kontak' => ['required', 'string', 'max:150'],
            'informasi_diminta' => ['required', 'string', 'min:10', 'max:2000'],
            'tujuan_penggunaan' => ['nullable', 'string', 'max:250'],
        ]);

        $permohonan = PermohonanInformasi::create([
            ...$data,
            'nomor_tiket' => $nomor->nomorPermohonanInformasi(),
            // REQ-F-PID-007: pemohon memerlukan kode untuk memeriksa status dan
            // mengajukan keberatan tanpa harus berakun.
            'kode_lacak' => strtoupper(str()->random(8)),
            'pemohon_id' => $request->user()?->id,
            'tenggat_jawaban' => $kalender->tenggat(self::SLA_JAWABAN_HARI_KERJA),
        ]);

        $audit->catat('create', 'PermohonanInformasi', $permohonan->id);

        return response()->json([
            'pesan' => 'Permohonan informasi diterima dan akan dijawab paling lambat '
                .self::SLA_JAWABAN_HARI_KERJA.' hari kerja.',
            'nomor_tiket' => $permohonan->nomor_tiket,
            'kode_lacak' => $permohonan->kode_lacak,
            'tenggat_jawaban' => $permohonan->tenggat_jawaban?->toIso8601String(),
        ], 201);
    }
}
