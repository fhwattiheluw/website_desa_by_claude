<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-NOT-006: pengaturan kanal notifikasi oleh pemilik akun. */
class PreferensiNotifikasiController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function tampil(Request $request): JsonResponse
    {
        return response()->json([
            'preferensi' => $request->user()->preferensiNotifikasi(),
            'catatan' => 'Pemberitahuan mengenai permohonan Anda tetap dikirim lewat surel meskipun kedua kanal '
                .'dimatikan, agar Anda tidak kehilangan kabar surat yang sudah selesai.',
        ]);
    }

    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'boolean'],
            'whatsapp' => ['required', 'boolean'],
            'pengumuman' => ['required', 'boolean'],
        ]);

        $pengguna = $request->user();
        $sebelum = $pengguna->preferensiNotifikasi();

        $pengguna->update(['preferensi_notifikasi' => $data]);
        $this->audit->catat('ubah_preferensi_notifikasi', 'User', $pengguna->id, $sebelum, $data);

        return response()->json([
            'pesan' => 'Preferensi notifikasi tersimpan.',
            'preferensi' => $pengguna->refresh()->preferensiNotifikasi(),
        ]);
    }
}
