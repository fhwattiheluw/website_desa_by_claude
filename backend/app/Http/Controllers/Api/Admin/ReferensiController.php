<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProdukHukum;
use App\Models\Umkm;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pengelolaan produk hukum (REQ-F-PID-004..006) dan verifikasi UMKM (REQ-F-POT-002). */
class ReferensiController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function simpanProdukHukum(Request $request, ?ProdukHukum $produkHukum = null): JsonResponse
    {
        $data = $request->validate([
            'jenis' => ['required', 'in:perdes,perkades,sk_kades'],
            'nomor' => ['required', 'string', 'max:40'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'judul' => ['required', 'string', 'max:250'],
            'tentang' => ['nullable', 'string', 'max:250'],
            'berlaku' => ['boolean'],
            'dicabut_oleh_id' => ['nullable', 'exists:produk_hukum,id'],
            'media_id' => ['nullable', 'exists:media,id'],
        ]);

        $produk = $produkHukum?->exists
            ? tap($produkHukum)->update($data)
            : ProdukHukum::create($data);

        $this->audit->catatModel($produkHukum?->exists ? 'update' : 'create', $produk);

        return response()->json(['pesan' => 'Produk hukum tersimpan.', 'data' => $produk]);
    }

    public function hapusProdukHukum(ProdukHukum $produkHukum): JsonResponse
    {
        $this->audit->catatModel('delete', $produkHukum, $produkHukum->attributesToArray());
        $produkHukum->delete();

        return response()->json(['pesan' => 'Produk hukum dihapus.']);
    }

    public function umkmMenunggu(): JsonResponse
    {
        return response()->json([
            'data' => Umkm::where('status', 'menunggu')->latest()->get(),
        ]);
    }

    /** REQ-F-POT-002: pendaftaran mandiri baru tayang setelah diverifikasi operator. */
    public function verifikasiUmkm(Request $request, Umkm $umkm): JsonResponse
    {
        $data = $request->validate([
            'disetujui' => ['required', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $umkm->update(['status' => $data['disetujui'] ? 'disetujui' : 'ditolak']);
        $this->audit->catat('verifikasi_umkm', 'Umkm', $umkm->id, null, $data);

        return response()->json([
            'pesan' => $data['disetujui'] ? 'UMKM ditayangkan pada direktori.' : 'Pendaftaran UMKM ditolak.',
        ]);
    }
}
