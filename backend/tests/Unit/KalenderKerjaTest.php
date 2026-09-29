<?php

namespace Tests\Unit;

use App\Models\HariLibur;
use App\Services\KalenderKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** BR-06: SLA dihitung dalam hari kerja. */
class KalenderKerjaTest extends TestCase
{
    use RefreshDatabase;

    private KalenderKerja $kalender;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->kalender = app(KalenderKerja::class);
    }

    public function test_tenggat_melewati_akhir_pekan(): void
    {
        // Jumat + 1 hari kerja seharusnya jatuh pada Senin, bukan Sabtu.
        $jumat = Carbon::parse('2026-10-02 10:00:00');

        $this->assertSame('2026-10-05', $this->kalender->tenggat(1, $jumat)->toDateString());
    }

    public function test_tenggat_melewati_hari_libur_nasional(): void
    {
        HariLibur::create(['tanggal' => '2026-10-06', 'keterangan' => 'Libur Uji Coba']);
        Cache::flush();

        $jumat = Carbon::parse('2026-10-02 10:00:00');

        // Senin (5) dihitung satu hari kerja, Selasa (6) libur, sehingga jatuh Rabu (7).
        $this->assertSame('2026-10-07', $this->kalender->tenggat(2, $jumat)->toDateString());
    }

    public function test_akhir_pekan_bukan_hari_kerja(): void
    {
        $this->assertFalse($this->kalender->hariKerja(Carbon::parse('2026-10-03'))); // Sabtu
        $this->assertFalse($this->kalender->hariKerja(Carbon::parse('2026-10-04'))); // Minggu
        $this->assertTrue($this->kalender->hariKerja(Carbon::parse('2026-10-05')));  // Senin
    }

    public function test_selisih_hari_kerja_mengabaikan_akhir_pekan(): void
    {
        $this->assertSame(3, $this->kalender->selisihHariKerja(
            Carbon::parse('2026-10-01 08:00:00'),   // Kamis
            Carbon::parse('2026-10-06 16:00:00'),   // Selasa
        ));
    }
}
