<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Komentar;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-KNT-013: moderasi komentar sebelum tayang. */
class KomentarController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $data = Komentar::with('konten:id,tipe,judul,slug', 'moderator:id,name')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
                // Tanpa saringan, yang menunggu moderasi tampil lebih dahulu.
                fn ($q) => $q->orderByRaw("CASE WHEN status = 'menunggu' THEN 0 ELSE 1 END"),
            )
            ->latest('id')
            ->paginate(20)
            ->through(fn (Komentar $k) => [
                'id' => $k->id,
                'nama' => $k->nama,
                'isi' => $k->isi,
                'status' => $k->status,
                'berakun' => $k->penulis_id !== null,
                'konten' => $k->konten ? [
                    'judul' => $k->konten->judul,
                    'tautan' => "/{$k->konten->tipe}/{$k->konten->slug}",
                ] : null,
                'moderator' => $k->moderator?->name,
                'alasan_penolakan' => $k->alasan_penolakan,
                'dikirim_pada' => $k->created_at?->toIso8601String(),
                'dimoderasi_pada' => $k->dimoderasi_pada?->toIso8601String(),
            ]);

        return response()->json($data);
    }

    public function moderasi(Request $request, Komentar $komentar): JsonResponse
    {
        $data = $request->validate([
            'keputusan' => ['required', 'in:setujui,tolak'],
            // Alasan penolakan dicatat agar keputusan moderasi dapat ditinjau,
            // bukan sekadar hilang tanpa jejak.
            'alasan' => ['required_if:keputusan,tolak', 'nullable', 'string', 'min:5', 'max:250'],
        ], [
            'alasan.required_if' => 'Alasan penolakan wajib dituliskan.',
        ]);

        $disetujui = $data['keputusan'] === 'setujui';

        $komentar->update([
            'status' => $disetujui ? Komentar::DISETUJUI : Komentar::DITOLAK,
            'alasan_penolakan' => $disetujui ? null : $data['alasan'],
            'dimoderasi_oleh' => $request->user()->id,
            'dimoderasi_pada' => now(),
        ]);

        $this->audit->catat('moderasi_komentar', 'Komentar', $komentar->id, null, ['status' => $komentar->status]);

        return response()->json([
            'pesan' => $disetujui ? 'Komentar ditayangkan.' : 'Komentar ditolak dan tidak ditayangkan.',
            'status' => $komentar->status,
        ]);
    }
}
