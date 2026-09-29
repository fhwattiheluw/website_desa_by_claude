<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\KinerjaBumdes;
use App\Models\UnitUsaha;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pengelolaan unit usaha dan kinerja BUMDes (REQ-F-POT-003). */
class BumdesController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'unit_usaha' => UnitUsaha::with('media')->orderBy('urutan')->get(),
            'kinerja' => KinerjaBumdes::orderByDesc('tahun')->get(),
        ]);
    }

    public function simpanUnit(Request $request, ?UnitUsaha $unitUsaha = null): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'penanggung_jawab' => ['nullable', 'string', 'max:150'],
            'kontak' => ['nullable', 'string', 'max:60'],
            'media_id' => ['nullable', 'exists:media,id'],
            'aktif' => ['boolean'],
            'urutan' => ['nullable', 'integer', 'min:0'],
        ]);

        $unit = $unitUsaha?->exists
            ? tap($unitUsaha)->update($data)
            : UnitUsaha::create([...$data, 'slug' => str($data['nama'])->slug().'-'.str()->random(4)]);

        $this->audit->catatModel($unitUsaha?->exists ? 'update' : 'create', $unit);

        return response()->json(['pesan' => 'Unit usaha tersimpan.', 'data' => $unit]);
    }

    public function hapusUnit(UnitUsaha $unitUsaha): JsonResponse
    {
        $this->audit->catatModel('delete', $unitUsaha, $unitUsaha->attributesToArray());
        $unitUsaha->delete();

        return response()->json(['pesan' => 'Unit usaha dihapus.']);
    }

    public function simpanKinerja(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'min:2015', 'max:2100'],
            'pendapatan' => ['required', 'numeric', 'min:0'],
            'laba_bersih' => ['required', 'numeric'],
            'kontribusi_pades' => ['required', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $kinerja = KinerjaBumdes::updateOrCreate(['tahun' => $data['tahun']], $data);

        $this->audit->catatModel('update', $kinerja);

        return response()->json(['pesan' => "Kinerja BUMDes tahun {$data['tahun']} tersimpan.", 'data' => $kinerja]);
    }

    /**
     * Angka kinerja baru tampil publik setelah disetujui, mengikuti perlakuan
     * yang sama dengan data APBDes (BR-08).
     */
    public function publikasikanKinerja(Request $request, KinerjaBumdes $kinerjaBumdes): JsonResponse
    {
        $data = $request->validate(['dipublikasikan' => ['required', 'boolean']]);

        $kinerjaBumdes->update($data);
        $this->audit->catat('publikasi', 'KinerjaBumdes', $kinerjaBumdes->id, null, $data);

        return response()->json([
            'pesan' => $data['dipublikasikan']
                ? "Kinerja BUMDes tahun {$kinerjaBumdes->tahun} kini tampil di portal publik."
                : "Kinerja BUMDes tahun {$kinerjaBumdes->tahun} disembunyikan dari publik.",
        ]);
    }

    public function hapusKinerja(KinerjaBumdes $kinerjaBumdes): JsonResponse
    {
        $this->audit->catatModel('delete', $kinerjaBumdes, $kinerjaBumdes->attributesToArray());
        $kinerjaBumdes->delete();

        return response()->json(['pesan' => 'Data kinerja dihapus.']);
    }
}
