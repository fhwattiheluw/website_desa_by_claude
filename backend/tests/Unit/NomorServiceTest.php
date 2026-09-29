<?php

namespace Tests\Unit;

use App\Models\JenisLayanan;
use App\Services\NomorService;
use Database\Seeders\JenisLayananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** REQ-F-SRT-008, 014: penomoran berurutan tanpa duplikasi. */
class NomorServiceTest extends TestCase
{
    use RefreshDatabase;

    private NomorService $nomor;

    private JenisLayanan $layanan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JenisLayananSeeder::class);
        $this->nomor = app(NomorService::class);
        $this->layanan = JenisLayanan::where('kode', 'SKTM')->firstOrFail();
    }

    public function test_nomor_tiket_mengikuti_format_dan_berurutan(): void
    {
        $waktu = Carbon::parse('2026-09-29 09:00:00');

        $this->assertSame('DESA/SKTM/202609/00001', $this->nomor->nomorTiket($this->layanan, $waktu));
        $this->assertSame('DESA/SKTM/202609/00002', $this->nomor->nomorTiket($this->layanan, $waktu));
    }

    public function test_urutan_tiket_dimulai_ulang_pada_periode_baru(): void
    {
        $this->nomor->nomorTiket($this->layanan, Carbon::parse('2026-09-29'));

        $this->assertSame(
            'DESA/SKTM/202610/00001',
            $this->nomor->nomorTiket($this->layanan, Carbon::parse('2026-10-01')),
        );
    }

    public function test_nomor_surat_memakai_bulan_romawi_dan_tidak_duplikat(): void
    {
        $waktu = Carbon::parse('2026-09-29');

        $this->assertSame('001/SKTM/IX/2026', $this->nomor->nomorSurat($this->layanan, $waktu));
        $this->assertSame('002/SKTM/IX/2026', $this->nomor->nomorSurat($this->layanan, $waktu));

        // Bulan berbeda pada tahun yang sama tetap melanjutkan urutan tahunan.
        $this->assertSame('003/SKTM/X/2026', $this->nomor->nomorSurat($this->layanan, Carbon::parse('2026-10-05')));
    }

    public function test_seratus_nomor_berurutan_tetap_unik(): void
    {
        $nomor = collect(range(1, 100))->map(fn () => $this->nomor->nomorSurat($this->layanan));

        $this->assertCount(100, $nomor->unique());
    }

    public function test_kode_verifikasi_dan_kode_lacak_acak(): void
    {
        $this->assertNotSame($this->nomor->kodeVerifikasi(), $this->nomor->kodeVerifikasi());
        $this->assertSame(16, strlen($this->nomor->kodeVerifikasi()));
        $this->assertSame(10, strlen($this->nomor->kodeLacak()));
    }
}
