<?php

namespace App\Services;

use App\Models\HariLibur;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Perhitungan hari kerja untuk SLA layanan (BR-06): Sabtu, Minggu,
 * dan hari libur nasional yang terdaftar tidak dihitung.
 */
class KalenderKerja
{
    public function tenggat(int $hariKerja, ?Carbon $mulai = null): Carbon
    {
        $tanggal = ($mulai ?? now())->copy();
        $sisa = max($hariKerja, 0);

        while ($sisa > 0) {
            $tanggal->addDay();

            if ($this->hariKerja($tanggal)) {
                $sisa--;
            }
        }

        return $tanggal->endOfDay();
    }

    public function hariKerja(Carbon $tanggal): bool
    {
        return ! $tanggal->isWeekend() && ! in_array($tanggal->toDateString(), $this->hariLibur(), true);
    }

    /** Selisih hari kerja antara dua waktu — dipakai pada laporan kinerja layanan. */
    public function selisihHariKerja(Carbon $dari, Carbon $sampai): int
    {
        if ($sampai->lessThan($dari)) {
            return 0;
        }

        $kursor = $dari->copy()->startOfDay();
        $akhir = $sampai->copy()->startOfDay();
        $jumlah = 0;

        while ($kursor->lessThan($akhir)) {
            $kursor->addDay();

            if ($this->hariKerja($kursor)) {
                $jumlah++;
            }
        }

        return $jumlah;
    }

    /** @return array<int, string> */
    private function hariLibur(): array
    {
        return Cache::remember(
            'hari_libur.daftar',
            3600,
            fn () => HariLibur::query()->pluck('tanggal')->map(fn ($t) => Carbon::parse($t)->toDateString())->all(),
        );
    }
}
