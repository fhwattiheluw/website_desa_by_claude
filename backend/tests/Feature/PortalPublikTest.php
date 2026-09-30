<?php

namespace Tests\Feature;

use App\Models\ItemStatistik;
use App\Models\Konten;
use App\Models\PeriodeStatistik;
use App\Models\TahunAnggaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** REQ-F-BRD-001, REQ-F-KNT-003/008, REQ-F-APB-001, REQ-F-STA-002, REQ-F-SRC-001; BR-08, BR-09, BR-15. */
class PortalPublikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    private function buatKonten(array $atribut = []): Konten
    {
        return Konten::create([
            'tipe' => 'berita',
            'judul' => $atribut['judul'] ?? 'Berita Uji Coba Portal Desa',
            'slug' => str($atribut['judul'] ?? 'Berita Uji Coba Portal Desa')->slug()->toString(),
            'ringkasan' => 'Ringkasan berita uji coba.',
            'isi' => '<p>Isi berita uji coba.</p>',
            'status' => 'terbit',
            'terbit_pada' => now()->subDay(),
            ...$atribut,
        ]);
    }

    public function test_beranda_menyajikan_profil_dan_sorotan(): void
    {
        $this->buatKonten(['sorotan' => true]);

        $this->getJson('/api/v1/beranda')
            ->assertOk()
            ->assertJsonPath('desa.nama_desa', 'Sukamaju')
            ->assertJsonCount(1, 'sorotan')
            ->assertJsonStructure(['desa', 'sorotan', 'berita', 'pengumuman', 'agenda']);
    }

    public function test_konten_draf_dan_terjadwal_tidak_tampil_ke_publik(): void
    {
        $this->buatKonten(['judul' => 'Berita Terbit', 'status' => 'terbit']);
        $this->buatKonten(['judul' => 'Berita Draf', 'status' => 'draf']);
        $this->buatKonten(['judul' => 'Berita Terjadwal', 'terbit_pada' => now()->addWeek()]);

        $respons = $this->getJson('/api/v1/konten/berita')->assertOk();

        $this->assertSame(['Berita Terbit'], collect($respons->json('data'))->pluck('judul')->all());
    }

    /** BR-09: pengumuman kedaluwarsa berhenti tampil. */
    public function test_pengumuman_kedaluwarsa_tidak_tampil(): void
    {
        $this->buatKonten([
            'tipe' => 'pengumuman',
            'judul' => 'Pengumuman Lama',
            'kedaluwarsa_pada' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/konten/pengumuman')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_membuka_berita_menambah_penghitung_dibaca(): void
    {
        $konten = $this->buatKonten();

        $this->getJson("/api/v1/konten/berita/{$konten->slug}")
            ->assertOk()
            ->assertJsonPath('data.judul', $konten->judul);

        $this->assertSame(1, $konten->refresh()->dibaca);
    }

    /** BR-08: APBDes yang belum dipublikasikan tidak boleh terlihat publik. */
    public function test_apbdes_belum_dipublikasikan_tidak_dapat_diakses(): void
    {
        $anggaran = TahunAnggaran::create(['tahun' => 2026, 'dipublikasikan' => false]);
        $anggaran->item()->create(['jenis' => 'pendapatan', 'bidang' => 'Dana Desa', 'pagu' => 1000, 'realisasi' => 900]);

        $this->getJson('/api/v1/apbdes/2026')->assertNotFound();
        $this->getJson('/api/v1/apbdes')->assertOk()->assertJsonCount(0, 'data');

        $anggaran->update(['dipublikasikan' => true, 'dipublikasikan_pada' => now()]);

        $this->getJson('/api/v1/apbdes/2026')
            ->assertOk()
            ->assertJsonPath('ringkasan.pendapatan.pagu', 1000)
            ->assertJsonPath('item.0.penyerapan', 90);
    }

    /** BR-15: kelompok statistik di bawah 5 jiwa disamarkan. */
    public function test_statistik_menyamarkan_kelompok_sangat_kecil(): void
    {
        $periode = PeriodeStatistik::create(['nama' => 'Uji', 'tahun' => 2026, 'aktif' => true]);

        ItemStatistik::create(['periode_statistik_id' => $periode->id, 'kelompok' => 'agama', 'label' => 'Islam', 'jumlah' => 4100]);
        ItemStatistik::create(['periode_statistik_id' => $periode->id, 'kelompok' => 'agama', 'label' => 'Buddha', 'jumlah' => 3]);

        $respons = $this->getJson('/api/v1/statistik')->assertOk();

        $this->assertSame(4100, $respons->json('kelompok.agama.0.jumlah'));
        $this->assertNull($respons->json('kelompok.agama.1.jumlah'));
        $this->assertTrue($respons->json('kelompok.agama.1.disamarkan'));
    }

    public function test_katalog_layanan_menampilkan_syarat_dan_sla(): void
    {
        $this->getJson('/api/v1/layanan')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.1.kode', 'SKTM')
            ->assertJsonPath('data.1.biaya', 0)
            ->assertJsonPath('data.1.sla_hari_kerja', 2);

        $this->getJson('/api/v1/layanan/surat-keterangan-tidak-mampu')
            ->assertOk()
            ->assertJsonStructure(['kolom_formulir', 'persyaratan']);
    }

    public function test_pencarian_global_menjangkau_berita_dan_layanan(): void
    {
        $this->buatKonten(['judul' => 'Pembangunan Jembatan Dusun Mekar']);

        $this->getJson('/api/v1/pencarian?q=jembatan')
            ->assertOk()
            ->assertJsonPath('data.0.jenis', 'berita');

        $this->getJson('/api/v1/pencarian?q=domisili')
            ->assertOk()
            ->assertJsonPath('data.0.jenis', 'layanan');

        $this->getJson('/api/v1/pencarian?q=ab')->assertStatus(422);
    }

    /**
     * REQ-F-SRC-001: pencarian tidak boleh membedakan huruf besar dan kecil.
     *
     * `LIKE` mengabaikan besar kecil huruf di SQLite dan MySQL tetapi tidak di
     * PostgreSQL, sehingga tanpa penyeragaman `cariTeks` pencarian gagal
     * diam-diam begitu desa berpindah mesin basis data.
     */
    public function test_pencarian_tidak_membedakan_huruf_besar_kecil(): void
    {
        $this->buatKonten(['judul' => 'Pembangunan Jembatan Dusun Mekar']);

        foreach (['jembatan', 'JEMBATAN', 'JeMbAtAn'] as $kunci) {
            $this->getJson('/api/v1/pencarian?q='.$kunci)
                ->assertOk()
                ->assertJsonPath('data.0.judul', 'Pembangunan Jembatan Dusun Mekar');
        }

        // Penyaringan pada daftar konten memakai jalur yang sama.
        $this->getJson('/api/v1/konten/berita?q=JEMBATAN')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * REQ-API-002: seluruh daftar berhalaman memakai bentuk respons yang sama,
     * baik yang berasal dari koleksi resource maupun paginator biasa.
     */
    public function test_bentuk_respons_berhalaman_seragam(): void
    {
        $this->buatKonten();

        $kunciWajib = ['data', 'current_page', 'last_page', 'per_page', 'total'];

        foreach (['/api/v1/konten/berita', '/api/v1/umkm', '/api/v1/produk-hukum'] as $jalur) {
            $respons = $this->getJson($jalur)->assertOk();

            foreach ($kunciWajib as $kunci) {
                $this->assertArrayHasKey($kunci, $respons->json(), "{$jalur} kehilangan kunci {$kunci}.");
            }

            $this->assertArrayNotHasKey('meta', $respons->json(), "{$jalur} masih menyarangkan info halaman di meta.");
        }
    }

    public function test_tajuk_keamanan_terpasang_pada_respons(): void
    {
        $this->getJson('/api/v1/beranda')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
    }
}
