<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-ADM-002, REQ-F-BRD-005: profil desa dapat disunting tanpa ubah kode. */
class PengaturanController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => Pengaturan::orderBy('grup')->get()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pengaturan' => ['required', 'array', 'min:1'],
            'pengaturan.*.kunci' => ['required', 'string', 'max:80'],
            'pengaturan.*.nilai' => ['nullable', 'string', 'max:5000'],
        ]);

        $sebelum = Pengaturan::semua();

        foreach ($data['pengaturan'] as $item) {
            Pengaturan::simpan($item['kunci'], $item['nilai'] ?? null);
        }

        $this->audit->catat('update', 'Pengaturan', null, $sebelum, Pengaturan::semua());

        return response()->json(['pesan' => 'Pengaturan situs diperbarui.', 'data' => Pengaturan::semua()]);
    }
}
