<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\KeberatanInformasi;
use App\Models\PermohonanInformasi;
use App\Services\AuditLogger;
use App\Services\KalenderKerja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Pelacakan permohonan informasi publik dan pengajuan keberatan atas
 * penolakannya (REQ-F-PID-003, REQ-F-PID-007).
 *
 * UU 14/2008 Pasal 35 memberi pemohon hak mengajukan keberatan, antara lain
 * bila permohonannya ditolak atau tidak dijawab dalam tenggat. Tanpa jalur ini,
 * satu-satunya pilihan pemohon adalah datang ke kantor desa.
 */
class KeberatanController extends Controller
{
    public function lacak(string $kode): JsonResponse
    {
        $permohonan = $this->cari($kode);

        return response()->json([
            'nomor_tiket' => $permohonan->nomor_tiket,
            'informasi_diminta' => $permohonan->informasi_diminta,
            'status' => $permohonan->status,
            'jawaban' => $permohonan->jawaban,
            'diajukan_pada' => $permohonan->created_at?->toIso8601String(),
            'tenggat_jawaban' => $permohonan->tenggat_jawaban?->toIso8601String(),
            'melampaui_tenggat' => $permohonan->status === 'diajukan' && $permohonan->tenggat_jawaban?->isPast(),
            'dapat_mengajukan_keberatan' => $permohonan->dapatDiajukanKeberatan()
                && $permohonan->keberatan()->count() === 0,
            'keberatan' => $permohonan->keberatan()->oldest('id')->get()->map(
                fn (KeberatanInformasi $k) => [
                    'alasan' => $k->alasan,
                    'status' => $k->status,
                    'tanggapan' => $k->tanggapan,
                    'diajukan_pada' => $k->created_at?->toIso8601String(),
                    'tenggat_tanggapan' => $k->tenggat_tanggapan?->toIso8601String(),
                    'ditanggapi_pada' => $k->ditanggapi_pada?->toIso8601String(),
                ],
            ),
        ]);
    }

    public function ajukan(Request $request, string $kode, KalenderKerja $kalender, AuditLogger $audit): JsonResponse
    {
        $permohonan = $this->cari($kode);

        $data = $request->validate([
            'alasan' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        if (! $permohonan->dapatDiajukanKeberatan()) {
            throw ValidationException::withMessages([
                'alasan' => 'Keberatan baru dapat diajukan setelah permohonan dijawab, ditolak, '
                    .'atau melewati tenggat jawaban.',
            ]);
        }

        if ($permohonan->keberatan()->exists()) {
            throw ValidationException::withMessages([
                'alasan' => 'Keberatan atas permohonan ini sudah pernah diajukan.',
            ]);
        }

        $keberatan = $permohonan->keberatan()->create([
            'alasan' => $data['alasan'],
            'status' => KeberatanInformasi::DIAJUKAN,
            'tenggat_tanggapan' => $kalender->tenggat(KeberatanInformasi::SLA_HARI_KERJA),
        ]);

        $audit->catat('ajukan_keberatan_informasi', 'KeberatanInformasi', $keberatan->id);

        return response()->json([
            'pesan' => 'Keberatan Anda tercatat dan akan ditanggapi atasan PPID paling lambat '
                .KeberatanInformasi::SLA_HARI_KERJA.' hari kerja.',
            'tenggat_tanggapan' => $keberatan->tenggat_tanggapan?->toIso8601String(),
        ], 201);
    }

    private function cari(string $kode): PermohonanInformasi
    {
        return PermohonanInformasi::where('kode_lacak', strtoupper($kode))->firstOrFail();
    }
}
