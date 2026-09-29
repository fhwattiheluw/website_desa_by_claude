<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\SuratTerbit;
use Illuminate\Http\JsonResponse;

/**
 * Verifikasi keabsahan surat oleh pihak ketiga (REQ-F-SRT-016).
 * Halaman ini tidak boleh membocorkan data pribadi pemohon.
 */
class SuratController extends Controller
{
    public function verifikasi(string $kode): JsonResponse
    {
        $surat = SuratTerbit::where('kode_verifikasi', strtoupper($kode))
            ->with('permohonan.jenisLayanan', 'penandatangan')
            ->first();

        if (! $surat) {
            return response()->json([
                'ditemukan' => false,
                'pesan' => 'Kode verifikasi tidak dikenali. Pastikan kode disalin dengan benar.',
            ], 404);
        }

        return response()->json([
            'ditemukan' => true,
            'nomor_surat' => $surat->nomor_surat,
            'jenis_surat' => $surat->permohonan->jenisLayanan->nama,
            'tanggal_terbit' => $surat->tanggal_terbit->toDateString(),
            'ditandatangani_oleh' => $surat->penandatangan->name,
            'status_keabsahan' => $surat->status_keabsahan,
            'sah' => $surat->sah(),
            'pesan' => $surat->sah()
                ? 'Surat ini tercatat sah dan diterbitkan oleh Pemerintah Desa.'
                : 'Surat ini telah dibatalkan dan tidak berlaku.',
        ]);
    }
}
