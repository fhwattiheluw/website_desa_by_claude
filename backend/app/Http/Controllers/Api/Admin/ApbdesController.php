<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemApbdes;
use App\Models\TahunAnggaran;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** REQ-F-APB-003..005, 009; BR-08. */
class ApbdesController extends Controller
{
    public const JENIS = ['pendapatan', 'belanja', 'pembiayaan'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => TahunAnggaran::withCount('item')->orderByDesc('tahun')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'min:2015', 'max:2100', 'unique:tahun_anggaran,tahun'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $tahun = TahunAnggaran::create($data);
        $this->audit->catatModel('create', $tahun);

        return response()->json(['pesan' => 'Tahun anggaran dibuat.', 'data' => $tahun], 201);
    }

    public function show(TahunAnggaran $tahunAnggaran): JsonResponse
    {
        return response()->json([
            'data' => $tahunAnggaran->load(['item' => fn ($q) => $q->orderBy('jenis')->orderBy('urutan'), 'dokumen.media']),
            'ringkasan' => $tahunAnggaran->ringkasan(),
        ]);
    }

    public function simpanItem(Request $request, TahunAnggaran $tahunAnggaran): JsonResponse
    {
        $data = $request->validate([
            'item' => ['required', 'array', 'min:1'],
            'item.*.jenis' => ['required', Rule::in(self::JENIS)],
            'item.*.bidang' => ['required', 'string', 'max:150'],
            'item.*.kegiatan' => ['nullable', 'string', 'max:200'],
            'item.*.pagu' => ['required', 'numeric', 'min:0'],
            'item.*.realisasi' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $tahunAnggaran) {
            $tahunAnggaran->item()->delete();

            foreach ($data['item'] as $urutan => $item) {
                $tahunAnggaran->item()->create([...$item, 'urutan' => $urutan]);
            }

            $tahunAnggaran->touch();
        });

        $this->audit->catat('update', 'TahunAnggaran', $tahunAnggaran->id, null, ['jumlah_item' => count($data['item'])]);

        return response()->json(['pesan' => 'Rincian anggaran tersimpan.', 'jumlah' => count($data['item'])]);
    }

    /** REQ-F-APB-005: impor CSV dengan laporan baris gagal. */
    public function impor(Request $request, TahunAnggaran $tahunAnggaran): JsonResponse
    {
        $request->validate(['berkas' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $baris = array_filter(array_map('str_getcsv', file($request->file('berkas')->getRealPath())));
        $judul = array_map(fn ($k) => strtolower(trim((string) $k)), array_shift($baris) ?: []);
        $wajib = ['jenis', 'bidang', 'pagu', 'realisasi'];

        if (array_diff($wajib, $judul)) {
            return response()->json([
                'pesan' => 'Judul kolom CSV tidak sesuai templat.',
                'kolom_wajib' => $wajib,
                'kolom_ditemukan' => $judul,
            ], 422);
        }

        $berhasil = 0;
        $gagal = [];

        foreach ($baris as $nomor => $kolom) {
            $item = array_combine($judul, array_pad($kolom, count($judul), null));

            if (! in_array($item['jenis'] ?? '', self::JENIS, true) || blank($item['bidang'] ?? null)) {
                $gagal[] = ['baris' => $nomor + 2, 'alasan' => 'Jenis atau bidang tidak valid.'];

                continue;
            }

            ItemApbdes::create([
                'tahun_anggaran_id' => $tahunAnggaran->id,
                'jenis' => $item['jenis'],
                'bidang' => $item['bidang'],
                'kegiatan' => $item['kegiatan'] ?? null,
                'pagu' => (float) str_replace(['.', ','], ['', '.'], (string) $item['pagu']),
                'realisasi' => (float) str_replace(['.', ','], ['', '.'], (string) $item['realisasi']),
                'urutan' => $nomor,
            ]);

            $berhasil++;
        }

        $this->audit->catat('impor', 'TahunAnggaran', $tahunAnggaran->id, null, [
            'berhasil' => $berhasil,
            'gagal' => count($gagal),
        ]);

        return response()->json([
            'pesan' => "Impor selesai: {$berhasil} baris tersimpan, ".count($gagal).' baris gagal.',
            'berhasil' => $berhasil,
            'gagal' => $gagal,
        ]);
    }

    /** BR-08 & REQ-F-APB-009: publikasi memerlukan persetujuan Sekretaris Desa. */
    public function publikasikan(Request $request, TahunAnggaran $tahunAnggaran): JsonResponse
    {
        $data = $request->validate(['dipublikasikan' => ['required', 'boolean']]);

        $tahunAnggaran->update([
            'dipublikasikan' => $data['dipublikasikan'],
            'disetujui_oleh' => $data['dipublikasikan'] ? $request->user()->id : null,
            'dipublikasikan_pada' => $data['dipublikasikan'] ? now() : null,
        ]);

        $this->audit->catat('publikasi', 'TahunAnggaran', $tahunAnggaran->id, null, $data);

        return response()->json([
            'pesan' => $data['dipublikasikan']
                ? "APBDes {$tahunAnggaran->tahun} kini tampil di portal publik."
                : "APBDes {$tahunAnggaran->tahun} disembunyikan dari publik.",
        ]);
    }
}
