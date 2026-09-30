<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\JenisLayanan;
use App\Models\Konten;
use App\Models\ProdukHukum;
use App\Models\Umkm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-SRC-001, 002: pencarian global lintas jenis konten. */
class PencarianController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:3', 'max:100']]);

        $kunci = $request->string('q')->toString();

        $hasil = collect();

        $hasil = $hasil->concat(
            Konten::tayang()
                ->cariTeks(['judul', 'isi'], $kunci)
                ->limit(10)->get()
                ->map(fn (Konten $k) => [
                    'jenis' => $k->tipe,
                    'judul' => $k->judul,
                    'cuplikan' => $this->cuplikan(strip_tags((string) ($k->ringkasan ?: $k->isi)), $kunci),
                    'tautan' => "/{$k->tipe}/{$k->slug}",
                    'tanggal' => $k->terbit_pada?->toDateString(),
                ])
        );

        $hasil = $hasil->concat(
            JenisLayanan::where('aktif', true)
                ->cariTeks(['nama', 'deskripsi'], $kunci)
                ->limit(5)->get()
                ->map(fn (JenisLayanan $l) => [
                    'jenis' => 'layanan',
                    'judul' => $l->nama,
                    'cuplikan' => $this->cuplikan((string) $l->deskripsi, $kunci),
                    'tautan' => "/layanan/{$l->slug}",
                    'tanggal' => null,
                ])
        );

        $hasil = $hasil->concat(
            ProdukHukum::query()
                ->cariTeks(['judul', 'tentang'], $kunci)
                ->limit(5)->get()
                ->map(fn (ProdukHukum $p) => [
                    'jenis' => 'produk_hukum',
                    'judul' => "{$p->jenis} Nomor {$p->nomor} Tahun {$p->tahun}",
                    'cuplikan' => $this->cuplikan((string) ($p->tentang ?: $p->judul), $kunci),
                    'tautan' => '/produk-hukum?q='.urlencode($p->nomor),
                    'tanggal' => null,
                ])
        );

        $hasil = $hasil->concat(
            Umkm::tayang()->cariTeks('nama_usaha', $kunci)->limit(5)->get()
                ->map(fn (Umkm $u) => [
                    'jenis' => 'umkm',
                    'judul' => $u->nama_usaha,
                    'cuplikan' => $this->cuplikan((string) $u->deskripsi, $kunci),
                    'tautan' => '/potensi/umkm',
                    'tanggal' => null,
                ])
        );

        return response()->json(['kata_kunci' => $kunci, 'jumlah' => $hasil->count(), 'data' => $hasil->values()]);
    }

    private function cuplikan(string $teks, string $kunci, int $panjang = 160): string
    {
        $posisi = stripos($teks, $kunci);
        $mulai = max(0, $posisi === false ? 0 : $posisi - 40);

        return trim(($mulai > 0 ? '…' : '').mb_substr($teks, $mulai, $panjang)).(mb_strlen($teks) > $mulai + $panjang ? '…' : '');
    }
}
