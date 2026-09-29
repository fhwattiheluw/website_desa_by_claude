<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PermintaanDataPribadi;
use App\Services\DataPribadiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Penanganan permintaan hak subjek data oleh pengendali data desa
 * (REQ-F-USR-013).
 */
class PermintaanDataController extends Controller
{
    public function __construct(private readonly DataPribadiService $layanan) {}

    public function index(Request $request): JsonResponse
    {
        $data = PermintaanDataPribadi::with('pengguna:id,name,email,status_akun', 'petugas:id,name')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
                fn ($q) => $q->orderByRaw("CASE WHEN status = 'menunggu' THEN 0 ELSE 1 END"),
            )
            ->latest('id')
            ->paginate(20)
            ->through(fn (PermintaanDataPribadi $p) => [
                'id' => $p->id,
                'jenis' => $p->jenis,
                'status' => $p->status,
                'alasan' => $p->alasan,
                'catatan_petugas' => $p->catatan_petugas,
                'pemohon' => [
                    'id' => $p->pengguna?->id,
                    'nama' => $p->pengguna?->name,
                    'surel' => $p->pengguna?->email,
                    'status_akun' => $p->pengguna?->status_akun,
                ],
                'petugas' => $p->petugas?->name,
                'diajukan_pada' => $p->created_at?->toIso8601String(),
                'tenggat_jawaban' => $p->tenggat_jawaban?->toIso8601String(),
                'melampaui_tenggat' => $p->menunggu() && $p->tenggat_jawaban?->isPast(),
                'ditindak_pada' => $p->ditindak_pada?->toIso8601String(),
            ]);

        return response()->json($data);
    }

    public function tindak(Request $request, PermintaanDataPribadi $permintaan): JsonResponse
    {
        $data = $request->validate([
            'keputusan' => ['required', 'in:setujui,tolak'],
            // Penolakan wajib beralasan agar warga tahu dasar hukumnya.
            'catatan' => ['required_if:keputusan,tolak', 'nullable', 'string', 'min:10', 'max:1000'],
        ], [
            'catatan.required_if' => 'Alasan penolakan wajib dituliskan dan disampaikan kepada warga.',
        ]);

        $hasil = $data['keputusan'] === 'setujui'
            ? $this->layanan->setujui($permintaan, $request->user(), $data['catatan'] ?? null)
            : $this->layanan->tolak($permintaan, $request->user(), $data['catatan']);

        return response()->json([
            'pesan' => $hasil->status === PermintaanDataPribadi::DISETUJUI
                ? 'Data pribadi warga telah dihapus atau dianonimkan. Arsip surat yang wajib disimpan tetap ada.'
                : 'Permintaan ditolak beserta alasannya.',
            'status' => $hasil->status,
        ]);
    }
}
