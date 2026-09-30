<?php

namespace Tests\Feature;

use App\Services\DokumentasiOpenApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RuteAplikasi;
use Illuminate\Support\Str;
use Tests\TestCase;

/** REQ-API-004: dokumentasi OpenAPI yang selalu selaras dengan implementasi. */
class DokumentasiApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    public function test_dokumentasi_terbuka_tanpa_autentikasi(): void
    {
        $this->getJson('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonStructure(['info' => ['title', 'version'], 'servers', 'components', 'paths']);
    }

    /**
     * Penjaga utama kebutuhan ini.
     *
     * Dokumentasi yang ditulis terpisah selalu tertinggal dari kodenya, dan
     * selisihnya baru diketahui oleh pemakai API yang terlanjur memakainya.
     * Uji ini gagal begitu ada rute yang tidak terwakili, sehingga selisih itu
     * tidak pernah sempat sampai ke pemakai.
     */
    public function test_setiap_rute_api_terwakili_pada_dokumentasi(): void
    {
        $spesifikasi = app(DokumentasiOpenApi::class)->susun();
        $tertinggal = [];

        foreach (app(DokumentasiOpenApi::class)->rute() as $rute) {
            /** @var RuteAplikasi $rute */
            $alamat = '/'.Str::after($rute->uri(), 'api/v1/');

            foreach ($rute->methods() as $metode) {
                if (in_array($metode, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                if (! isset($spesifikasi['paths'][$alamat][strtolower($metode)])) {
                    $tertinggal[] = $metode.' '.$alamat;
                }
            }
        }

        $this->assertSame([], $tertinggal, 'Rute berikut belum terwakili pada dokumentasi OpenAPI.');
    }

    public function test_setiap_operasi_punya_ringkasan_dan_tanggapan(): void
    {
        $spesifikasi = app(DokumentasiOpenApi::class)->susun();
        $cacat = [];

        foreach ($spesifikasi['paths'] as $alamat => $operasi) {
            foreach ($operasi as $metode => $isi) {
                if (blank($isi['summary'] ?? null) || blank($isi['responses'] ?? null)) {
                    $cacat[] = strtoupper($metode).' '.$alamat;
                }
            }
        }

        $this->assertSame([], $cacat);
        $this->assertNotEmpty($spesifikasi['paths']);
    }

    /** Titik akhir yang dilindungi harus terlihat dilindungi pada dokumentasinya. */
    public function test_titik_akhir_panel_ditandai_menuntut_token_dan_izin(): void
    {
        $spesifikasi = app(DokumentasiOpenApi::class)->susun();
        $operasi = $spesifikasi['paths']['/admin/permohonan']['get'];

        $this->assertSame([['tokenSanctum' => []]], $operasi['security']);
        $this->assertStringContainsString('permohonan.lihat', $operasi['description']);
        $this->assertArrayHasKey('403', $operasi['responses']);
        $this->assertArrayHasKey('401', $operasi['responses']);
    }

    /** Titik akhir publik tidak boleh tampak menuntut token. */
    public function test_titik_akhir_publik_tidak_ditandai_menuntut_token(): void
    {
        $spesifikasi = app(DokumentasiOpenApi::class)->susun();

        foreach (['/beranda', '/menu', '/statistik'] as $alamat) {
            $this->assertArrayNotHasKey('security', $spesifikasi['paths'][$alamat]['get'], $alamat);
        }
    }

    /** Batas laju khusus disebut agar pemakai API dapat mengatur lajunya sendiri. */
    public function test_batas_laju_khusus_disebut(): void
    {
        $spesifikasi = app(DokumentasiOpenApi::class)->susun();

        $this->assertSame('masuk', $spesifikasi['paths']['/auth/masuk']['post']['x-batas-laju']);
        $this->assertSame('otp', $spesifikasi['paths']['/auth/otp']['post']['x-batas-laju']);
        $this->assertSame('formulir-publik', $spesifikasi['paths']['/pengaduan']['post']['x-batas-laju']);
    }
}
