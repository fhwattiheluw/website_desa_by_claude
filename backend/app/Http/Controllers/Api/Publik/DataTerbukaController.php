<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\ItemStatistik;
use App\Models\JenisLayanan;
use App\Models\PeriodeStatistik;
use App\Models\ProdukHukum;
use App\Models\TahunAnggaran;
use Illuminate\Http\JsonResponse;

/**
 * API data terbuka hanya-baca (REQ-API-005).
 *
 * Hanya menyajikan data yang memang sudah terbuka bagi publik: statistik
 * agregat, APBDes yang telah dipublikasikan, katalog layanan, dan produk
 * hukum. Tidak ada data pribadi yang disajikan di sini, dan kuota permintaan
 * dibatasi melalui pembatas laju "terbuka".
 */
class DataTerbukaController extends Controller
{
    /** Ambang k-anonimitas mengikuti laman statistik publik (BR-15). */
    public const AMBANG_ANONIMITAS = 5;

    public function indeks(): JsonResponse
    {
        return response()->json([
            'nama' => 'API Data Terbuka Desa',
            'versi' => '1.0',
            'lisensi' => [
                'nama' => 'Creative Commons Attribution 4.0',
                'url' => 'https://creativecommons.org/licenses/by/4.0/deed.id',
                'syarat' => 'Cantumkan sumber data sebagai Pemerintah Desa saat menggunakan atau menyebarkan data ini.',
            ],
            'kuota' => 'Maksimal 60 permintaan per menit dan 2.000 permintaan per hari untuk setiap alamat IP.',
            'catatan_privasi' => 'Seluruh data bersifat agregat. Data pribadi warga tidak tersedia melalui API ini.',
            'kumpulan_data' => [
                ['nama' => 'Statistik kependudukan agregat', 'jalur' => '/api/v1/terbuka/statistik'],
                ['nama' => 'Daftar tahun anggaran terpublikasi', 'jalur' => '/api/v1/terbuka/apbdes'],
                ['nama' => 'Rincian APBDes per tahun', 'jalur' => '/api/v1/terbuka/apbdes/{tahun}'],
                ['nama' => 'Katalog layanan administrasi', 'jalur' => '/api/v1/terbuka/layanan'],
                ['nama' => 'Produk hukum desa', 'jalur' => '/api/v1/terbuka/produk-hukum'],
            ],
        ]);
    }

    public function statistik(): JsonResponse
    {
        $periode = PeriodeStatistik::where('aktif', true)->latest('tahun')->first();

        if (! $periode) {
            return response()->json(['pesan' => 'Belum ada periode statistik yang dipublikasikan.'], 404);
        }

        $item = ItemStatistik::where('periode_statistik_id', $periode->id)
            ->orderBy('kelompok')
            ->orderBy('urutan')
            ->get();

        return response()->json([
            'periode' => $periode->nama,
            'tahun' => $periode->tahun,
            'sumber_data' => $periode->sumber_data,
            'total_penduduk' => (int) $item->where('kelompok', 'jenis_kelamin')->sum('jumlah'),
            'kelompok' => $item->groupBy('kelompok')->map(
                fn ($baris) => $baris->map(fn (ItemStatistik $i) => [
                    'label' => $i->label,
                    // Kelompok sangat kecil disamarkan agar tidak dapat dipakai
                    // mengidentifikasi individu (BR-15).
                    'jumlah' => $i->jumlah < self::AMBANG_ANONIMITAS ? null : $i->jumlah,
                ])->values()
            ),
        ]);
    }

    public function daftarApbdes(): JsonResponse
    {
        return response()->json([
            'data' => TahunAnggaran::where('dipublikasikan', true)
                ->orderByDesc('tahun')
                ->get()
                ->map(fn (TahunAnggaran $t) => [
                    'tahun' => $t->tahun,
                    'dipublikasikan_pada' => $t->dipublikasikan_pada?->toIso8601String(),
                    'jalur_rincian' => "/api/v1/terbuka/apbdes/{$t->tahun}",
                ]),
        ]);
    }

    public function apbdes(int $tahun): JsonResponse
    {
        $anggaran = TahunAnggaran::where('tahun', $tahun)
            ->where('dipublikasikan', true)
            ->with('item')
            ->first();

        if (! $anggaran) {
            return response()->json(['pesan' => "APBDes tahun {$tahun} belum dipublikasikan."], 404);
        }

        return response()->json([
            'tahun' => $anggaran->tahun,
            'diperbarui_pada' => $anggaran->updated_at?->toIso8601String(),
            'ringkasan' => $anggaran->ringkasan(),
            'item' => $anggaran->item->map(fn ($i) => [
                'jenis' => $i->jenis,
                'bidang' => $i->bidang,
                'kegiatan' => $i->kegiatan,
                'pagu' => (float) $i->pagu,
                'realisasi' => (float) $i->realisasi,
            ]),
        ]);
    }

    public function layanan(): JsonResponse
    {
        return response()->json([
            'data' => JenisLayanan::where('aktif', true)
                ->orderBy('urutan')
                ->get()
                ->map(fn (JenisLayanan $l) => [
                    'kode' => $l->kode,
                    'nama' => $l->nama,
                    'deskripsi' => $l->deskripsi,
                    'persyaratan' => $l->persyaratan,
                    'sla_hari_kerja' => $l->sla_hari_kerja,
                    'biaya' => 0,
                ]),
        ]);
    }

    public function produkHukum(): JsonResponse
    {
        return response()->json([
            'data' => ProdukHukum::orderByDesc('tahun')
                ->get()
                ->map(fn (ProdukHukum $p) => [
                    'jenis' => $p->jenis,
                    'nomor' => $p->nomor,
                    'tahun' => $p->tahun,
                    'judul' => $p->judul,
                    'tentang' => $p->tentang,
                    'berlaku' => $p->berlaku,
                ]),
        ]);
    }
}
