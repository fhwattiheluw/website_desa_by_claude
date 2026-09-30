<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\KeberatanInformasi;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** REQ-F-PID-007: penanganan keberatan oleh atasan PPID desa. */
class KeberatanController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $data = KeberatanInformasi::with('permohonan:id,nomor_tiket,informasi_diminta,status', 'petugas:id,name')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
                fn ($q) => $q->orderByRaw("CASE WHEN status = 'diajukan' THEN 0 ELSE 1 END"),
            )
            ->latest('id')
            ->paginate(20)
            ->through(fn (KeberatanInformasi $k) => [
                'id' => $k->id,
                'alasan' => $k->alasan,
                'status' => $k->status,
                'tanggapan' => $k->tanggapan,
                'petugas' => $k->petugas?->name,
                'permohonan' => $k->permohonan ? [
                    'nomor_tiket' => $k->permohonan->nomor_tiket,
                    'informasi_diminta' => $k->permohonan->informasi_diminta,
                    'status' => $k->permohonan->status,
                ] : null,
                'diajukan_pada' => $k->created_at?->toIso8601String(),
                'tenggat_tanggapan' => $k->tenggat_tanggapan?->toIso8601String(),
                'melampaui_tenggat' => $k->status === KeberatanInformasi::DIAJUKAN
                    && $k->tenggat_tanggapan?->isPast(),
                'ditanggapi_pada' => $k->ditanggapi_pada?->toIso8601String(),
            ]);

        return response()->json($data);
    }

    public function tanggapi(Request $request, KeberatanInformasi $keberatan): JsonResponse
    {
        if ($keberatan->status !== KeberatanInformasi::DIAJUKAN) {
            throw ValidationException::withMessages(['tanggapan' => 'Keberatan ini sudah pernah ditanggapi.']);
        }

        $data = $request->validate([
            // Tanggapan atas keberatan wajib beralasan: pemohon berhak tahu
            // dasar keputusannya, dan ia dapat membawanya ke Komisi Informasi.
            'tanggapan' => ['required', 'string', 'min:20', 'max:5000'],
        ]);

        $keberatan->update([
            'tanggapan' => $data['tanggapan'],
            'status' => KeberatanInformasi::DITANGGAPI,
            'ditanggapi_oleh' => $request->user()->id,
            'ditanggapi_pada' => now(),
        ]);

        $this->audit->catat('tanggapi_keberatan_informasi', 'KeberatanInformasi', $keberatan->id);

        return response()->json(['pesan' => 'Tanggapan atas keberatan tersimpan dan dapat dibaca pemohon.']);
    }
}
