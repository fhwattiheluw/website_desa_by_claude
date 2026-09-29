<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PengaduanResource;
use App\Models\Pengaduan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PengaduanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** REQ-F-ADU-004..009, 011. */
class PengaduanController extends Controller
{
    public function __construct(
        private readonly PengaduanService $layanan,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = Pengaduan::with('petugasDisposisi')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('kategori'), fn ($q) => $q->where('kategori', $request->string('kategori')))
            ->when($request->boolean('terlambat'), fn ($q) => $q
                ->whereNotIn('status', [Pengaduan::SELESAI, Pengaduan::DITOLAK])
                ->where('tenggat_tanggapan', '<', now()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return PengaduanResource::collection($data);
    }

    public function show(Pengaduan $pengaduan): PengaduanResource
    {
        return new PengaduanResource($pengaduan->load('tanggapan.aktor', 'petugasDisposisi'));
    }

    public function ubahStatus(Request $request, Pengaduan $pengaduan): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                Pengaduan::DIVERIFIKASI, Pengaduan::DIDISPOSISI, Pengaduan::PROSES,
                Pengaduan::SELESAI, Pengaduan::DITOLAK,
            ])],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->layanan->ubahStatus($pengaduan, $data['status'], $request->user(), $data['catatan'] ?? null);

        return response()->json([
            'pesan' => "Status pengaduan diubah menjadi {$data['status']}.",
            'data' => new PengaduanResource($pengaduan->fresh(['tanggapan.aktor'])),
        ]);
    }

    public function disposisi(Request $request, Pengaduan $pengaduan): JsonResponse
    {
        $data = $request->validate([
            'petugas_id' => ['required', 'exists:users,id'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->layanan->disposisi(
            $pengaduan,
            $request->user(),
            User::findOrFail($data['petugas_id']),
            $data['catatan'] ?? null,
        );

        return response()->json(['pesan' => 'Pengaduan telah didisposisi.']);
    }

    public function tanggapi(Request $request, Pengaduan $pengaduan): JsonResponse
    {
        $data = $request->validate([
            'isi' => ['required', 'string', 'min:10', 'max:5000'],
            'internal' => ['boolean'],
        ]);

        $this->layanan->tanggapi($pengaduan, $request->user(), $data['isi'], (bool) ($data['internal'] ?? false));

        return response()->json([
            'pesan' => 'Tanggapan tersimpan.',
            'data' => new PengaduanResource($pengaduan->fresh(['tanggapan.aktor'])),
        ]);
    }

    /** REQ-F-ADU-008: publikasi pengaduan memerlukan persetujuan moderator. */
    public function publikasi(Request $request, Pengaduan $pengaduan): JsonResponse
    {
        $data = $request->validate(['tampil_publik' => ['required', 'boolean']]);

        $pengaduan->update($data);
        $this->audit->catat('moderasi', 'Pengaduan', $pengaduan->id, null, $data);

        return response()->json([
            'pesan' => $data['tampil_publik']
                ? 'Pengaduan ditampilkan ke publik dengan identitas pelapor disamarkan.'
                : 'Pengaduan disembunyikan dari publik.',
        ]);
    }
}
