<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Http\Resources\PengaduanResource;
use App\Models\Pengaduan;
use App\Services\PengaduanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-ADU-001..003, 008, 010. */
class PengaduanController extends Controller
{
    public const KATEGORI = ['infrastruktur', 'pelayanan', 'sosial', 'lingkungan', 'keamanan', 'lainnya'];

    public function store(Request $request, PengaduanService $layanan): JsonResponse
    {
        $data = $request->validate([
            'nama_pelapor' => ['nullable', 'string', 'max:150'],
            // REQ-F-ADU-002: anonim diizinkan, tetapi satu kanal kontak tetap wajib.
            'kontak_pelapor' => ['required', 'string', 'max:150'],
            'kategori' => ['required', 'in:'.implode(',', self::KATEGORI)],
            'judul' => ['required', 'string', 'min:5', 'max:200'],
            'uraian' => ['required', 'string', 'min:20', 'max:5000'],
            'lokasi' => ['nullable', 'string', 'max:200'],
            'tanggal_kejadian' => ['nullable', 'date', 'before_or_equal:today'],
            'anonim' => ['boolean'],
        ]);

        $pengaduan = $layanan->buat($data, $request->user());

        return response()->json([
            'pesan' => 'Pengaduan Anda telah diterima. Simpan kode lacak untuk memantau tindak lanjut.',
            'nomor_tiket' => $pengaduan->nomor_tiket,
            'kode_lacak' => $pengaduan->kode_lacak,
            'tenggat_tanggapan' => $pengaduan->tenggat_tanggapan?->toIso8601String(),
        ], 201);
    }

    /** REQ-F-ADU-003: pelacakan mandiri dengan kode lacak. */
    public function lacak(string $kode): PengaduanResource
    {
        $pengaduan = Pengaduan::where('kode_lacak', strtoupper($kode))
            ->with('tanggapan.aktor')
            ->firstOrFail();

        return new PengaduanResource($pengaduan);
    }

    /** REQ-F-ADU-008: pengaduan yang dipublikasikan tampil dengan identitas disamarkan. */
    public function publik(): JsonResponse
    {
        $data = Pengaduan::where('tampil_publik', true)
            ->whereIn('status', [Pengaduan::PROSES, Pengaduan::SELESAI])
            ->with('tanggapan')
            ->latest()
            ->paginate(10)
            // Dipetakan eksplisit, bukan lewat resource bersama: laman publik tidak
            // boleh menampilkan identitas maupun catatan internal, sekalipun yang
            // membukanya kebetulan petugas yang sedang masuk (BR-11).
            ->through(fn (Pengaduan $pengaduan) => [
                'nomor_tiket' => $pengaduan->nomor_tiket,
                'kategori' => $pengaduan->kategori,
                'judul' => $pengaduan->judul,
                'uraian' => $pengaduan->uraian,
                'lokasi' => $pengaduan->lokasi,
                'status' => $pengaduan->status,
                'pelapor' => $pengaduan->pelaporTersamar(),
                'dibuat_pada' => $pengaduan->created_at?->toIso8601String(),
                'tanggapan' => $pengaduan->tanggapan->where('internal', false)->values()->map(fn ($t) => [
                    'isi' => $t->isi,
                    'waktu' => $t->created_at?->toIso8601String(),
                ]),
            ]);

        return response()->json($data);
    }

    public function kategori(): JsonResponse
    {
        return response()->json(['data' => self::KATEGORI]);
    }
}
