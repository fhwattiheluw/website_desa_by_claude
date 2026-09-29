<?php

namespace App\Services;

use App\Models\JenisLayanan;
use App\Models\UrutanNomor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penomoran tiket dan surat.
 *
 * Nomor surat wajib berurutan tanpa duplikasi meski diakses bersamaan
 * (REQ-F-SRT-014): pencacah dikunci di dalam transaksi basis data.
 */
class NomorService
{
    private const ROMAWI = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /** Format: DESA/<KODE>/<YYYYMM>/<URUT 5 DIGIT> (REQ-F-SRT-008). */
    public function nomorTiket(JenisLayanan $layanan, ?Carbon $waktu = null): string
    {
        $waktu ??= now();
        $periode = $waktu->format('Ym');
        $urut = $this->urutBerikutnya("tiket:{$layanan->kode}:{$periode}");

        return sprintf('DESA/%s/%s/%05d', $layanan->kode, $periode, $urut);
    }

    /** Nomor surat resmi mengikuti pola tata naskah yang dikonfigurasi per layanan. */
    public function nomorSurat(JenisLayanan $layanan, ?Carbon $waktu = null): string
    {
        $waktu ??= now();
        $urut = $this->urutBerikutnya("surat:{$layanan->kode}:{$waktu->year}");

        return str_replace(
            ['{urut}', '{kode}', '{romawi_bulan}', '{bulan}', '{tahun}'],
            [sprintf('%03d', $urut), $layanan->kode, self::ROMAWI[$waktu->month], $waktu->format('m'), $waktu->year],
            $layanan->format_nomor,
        );
    }

    public function nomorPengaduan(?Carbon $waktu = null): string
    {
        $waktu ??= now();
        $periode = $waktu->format('Ym');

        return sprintf('ADU/%s/%05d', $periode, $this->urutBerikutnya("aduan:{$periode}"));
    }

    public function nomorPermohonanInformasi(?Carbon $waktu = null): string
    {
        $waktu ??= now();
        $periode = $waktu->format('Ym');

        return sprintf('PPID/%s/%05d', $periode, $this->urutBerikutnya("ppid:{$periode}"));
    }

    public function kodeLacak(): string
    {
        return strtoupper(Str::random(10));
    }

    public function kodeVerifikasi(): string
    {
        return strtoupper(Str::random(16));
    }

    private function urutBerikutnya(string $kunci): int
    {
        return DB::transaction(function () use ($kunci): int {
            $baris = UrutanNomor::query()->where('kunci', $kunci)->lockForUpdate()->first()
                ?? UrutanNomor::create(['kunci' => $kunci, 'urut' => 0]);

            $baris->increment('urut');

            return (int) $baris->refresh()->urut;
        });
    }
}
