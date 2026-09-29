<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Services\AnalitikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** REQ-SW-006: analitik web tanpa data pribadi. */
class AnalitikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    public function test_kunjungan_dihitung_agregat_per_jalur_dan_tanggal(): void
    {
        $this->postJson('/api/v1/kunjungan', ['jalur' => '/berita'])->assertAccepted();
        $this->postJson('/api/v1/kunjungan', ['jalur' => '/berita'])->assertAccepted();
        $this->postJson('/api/v1/kunjungan', ['jalur' => '/layanan'])->assertAccepted();

        $this->assertDatabaseCount('kunjungan_harian', 2);
        $this->assertDatabaseHas('kunjungan_harian', ['jalur' => '/berita', 'jumlah' => 2]);
        $this->assertDatabaseHas('kunjungan_harian', ['jalur' => '/layanan', 'jumlah' => 1]);
    }

    public function test_tidak_ada_kolom_yang_menunjuk_pengunjung(): void
    {
        $this->postJson('/api/v1/kunjungan', ['jalur' => '/beranda'])->assertAccepted();

        $kolom = array_keys((array) DB::table('kunjungan_harian')->first());

        // Yang tersimpan hanya tanggal, jalur, dan jumlah.
        foreach (['alamat_ip', 'agen', 'pengguna_id', 'sesi', 'perujuk'] as $terlarang) {
            $this->assertNotContains($terlarang, $kolom);
        }
    }

    public function test_parameter_kueri_dan_jalur_tidak_wajar_tidak_dicatat(): void
    {
        $analitik = app(AnalitikService::class);

        // Parameter kueri dapat memuat data pribadi, jadi dipangkas sebelum
        // disimpan: yang tercatat hanya jalur lamannya.
        $this->assertTrue($analitik->catat('/pengaduan/lacak?kode=ABC123&nik=3203014507840003'));
        $this->assertDatabaseHas('kunjungan_harian', ['jalur' => '/pengaduan/lacak']);
        $this->assertSame(0, DB::table('kunjungan_harian')->where('jalur', 'like', '%3203014507840003%')->count());

        // Jalur dengan karakter di luar pola jalur laman, dan jalur yang terlalu
        // panjang, diabaikan seluruhnya.
        $this->assertFalse($analitik->catat('/berita/<script>alert(1)</script>'));
        $this->assertFalse($analitik->catat('/berita judul dengan spasi'));
        $this->assertFalse($analitik->catat('/'.str_repeat('a', 200)));

        $this->assertSame(1, DB::table('kunjungan_harian')->count());
    }

    public function test_laman_akun_dan_panel_petugas_tidak_dihitung(): void
    {
        $analitik = app(AnalitikService::class);

        foreach (['/akun', '/akun/permohonan/42', '/admin', '/admin/pengguna', '/masuk'] as $jalur) {
            $this->assertFalse($analitik->catat($jalur), "Jalur {$jalur} seharusnya tidak dihitung.");
        }

        $this->assertDatabaseCount('kunjungan_harian', 0);
        // Laman publik dengan awalan mirip tetap dihitung.
        $this->assertTrue($analitik->catat('/akuntabilitas'));
    }

    public function test_jalur_akar_dicatat_sebagai_garis_miring(): void
    {
        app(AnalitikService::class)->catat('/');

        $this->assertDatabaseHas('kunjungan_harian', ['jalur' => '/', 'jumlah' => 1]);
    }

    public function test_analitik_dapat_dimatikan_sepenuhnya(): void
    {
        config()->set('analitik.aktif', false);

        $this->postJson('/api/v1/kunjungan', ['jalur' => '/berita'])->assertAccepted();

        $this->assertDatabaseCount('kunjungan_harian', 0);
        // Portal diberi tahu agar tidak lagi mengirim hitungan.
        $this->getJson('/api/v1/profil-desa')->assertOk()->assertJson(['analitik_aktif' => false]);
    }

    public function test_ringkasan_hanya_untuk_petugas_berizin_laporan(): void
    {
        app(AnalitikService::class)->catat('/berita');

        $this->getJson('/api/v1/admin/analitik')->assertUnauthorized();

        $this->actingAs($this->buatPengguna(Role::OPERATOR))
            ->getJson('/api/v1/admin/analitik')
            ->assertForbidden();

        $this->actingAs($this->buatPengguna(Role::SEKDES))
            ->getJson('/api/v1/admin/analitik')
            ->assertOk()
            ->assertJson(['total_kunjungan' => 1, 'rentang_hari' => 30])
            ->assertJsonPath('laman_teratas.0.jalur', '/berita');
    }

    public function test_rentang_ringkasan_dibatasi_pada_nilai_wajar(): void
    {
        $sekdes = $this->buatPengguna(Role::SEKDES);

        $this->actingAs($sekdes)->getJson('/api/v1/admin/analitik?hari=1')
            ->assertOk()->assertJson(['rentang_hari' => 7]);

        $this->actingAs($sekdes)->getJson('/api/v1/admin/analitik?hari=9999')
            ->assertOk()->assertJson(['rentang_hari' => 180]);
    }
}
