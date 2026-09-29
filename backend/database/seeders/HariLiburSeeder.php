<?php

namespace Database\Seeders;

use App\Models\HariLibur;
use Illuminate\Database\Seeder;

/**
 * Hari libur nasional yang dikecualikan dari perhitungan SLA (BR-06).
 * Daftar ini wajib dimutakhirkan setiap awal tahun oleh administrator.
 */
class HariLiburSeeder extends Seeder
{
    public function run(): void
    {
        $tahun = now()->year;

        $libur = [
            ["{$tahun}-01-01", 'Tahun Baru Masehi'],
            ["{$tahun}-05-01", 'Hari Buruh Internasional'],
            ["{$tahun}-06-01", 'Hari Lahir Pancasila'],
            ["{$tahun}-08-17", 'Hari Kemerdekaan Republik Indonesia'],
            ["{$tahun}-12-25", 'Hari Raya Natal'],
        ];

        foreach ($libur as [$tanggal, $keterangan]) {
            HariLibur::updateOrCreate(['tanggal' => $tanggal], ['keterangan' => $keterangan]);
        }
    }
}
