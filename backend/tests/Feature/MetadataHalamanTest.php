<?php

namespace Tests\Feature;

use App\Models\Konten;
use App\Services\MetadataHalaman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * REQ-F-SRC-004, REQ-F-SRC-005: kerangka halaman disajikan lengkap dengan
 * judul unik, deskripsi, URL kanonik, Open Graph, dan data terstruktur bagi
 * perayap yang tidak menjalankan JavaScript.
 */
class MetadataHalamanTest extends TestCase
{
    use RefreshDatabase;

    private string $dist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();

        // Kerangka tiruan yang meniru keluaran build: sudah punya judul dan
        // deskripsi bawaan yang harus tergantikan.
        $this->dist = storage_path('framework/testing/dist-uji');
        File::ensureDirectoryExists($this->dist);
        File::put($this->dist.'/index.html', <<<'HTML'
            <!doctype html><html lang="id"><head>
            <meta charset="UTF-8">
            <title>Portal Desa</title>
            <meta name="description" content="Deskripsi bawaan">
            </head><body><div id="root"></div></body></html>
            HTML);

        config()->set('app.dist', $this->dist);
        config()->set('app.frontend_url', 'https://sukamaju.desa.id');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dist);
        parent::tearDown();
    }

    private function buatKonten(array $ubah = []): Konten
    {
        return Konten::create([
            'tipe' => 'berita',
            'judul' => 'Musyawarah Desa Bahas Prioritas Pembangunan',
            'slug' => 'musyawarah-desa-bahas-prioritas-pembangunan',
            'ringkasan' => 'Pemerintah desa bersama BPD menyusun prioritas pembangunan tahun anggaran berikutnya.',
            'isi' => '<p>Isi lengkap berita musyawarah desa.</p>',
            'status' => 'terbit',
            'terbit_pada' => now()->subDay(),
            ...$ubah,
        ]);
    }

    public function test_halaman_statis_mendapat_judul_dan_kanonik_sendiri(): void
    {
        $isi = $this->get('/profil')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Profil Desa — Desa Sukamaju</title>', $isi);
        $this->assertStringContainsString('<link rel="canonical" href="https://sukamaju.desa.id/profil">', $isi);
        $this->assertStringContainsString('property="og:title"', $isi);
        $this->assertStringContainsString('content="id_ID"', $isi);

        // Judul dan deskripsi bawaan kerangka harus tergantikan, bukan
        // berdampingan: peramban memakai <title> pertama yang ditemuinya.
        $this->assertSame(1, substr_count($isi, '<title>'));
        $this->assertStringNotContainsString('Deskripsi bawaan', $isi);
    }

    public function test_halaman_berita_memakai_judul_dan_ringkasan_kontennya(): void
    {
        $konten = $this->buatKonten();

        $isi = $this->get("/berita/{$konten->slug}")->assertOk()->getContent();

        $this->assertStringContainsString($konten->judul, $isi);
        $this->assertStringContainsString($konten->ringkasan, $isi);
        $this->assertStringContainsString('content="article"', $isi);

        // REQ-F-SRC-005: data terstruktur schema.org untuk halaman berita.
        $this->assertStringContainsString('application/ld+json', $isi);
        $this->assertStringContainsString('"@type":"NewsArticle"', $isi);
        $this->assertStringContainsString('"datePublished"', $isi);
        // Properti kosong tidak boleh ikut terbawa.
        $this->assertStringNotContainsString('null', $isi);
    }

    public function test_konten_belum_tayang_tidak_bocor_lewat_metadata(): void
    {
        $draf = $this->buatKonten([
            'judul' => 'Rancangan yang belum boleh dibaca publik',
            'slug' => 'rancangan-rahasia',
            'status' => 'draf',
            'terbit_pada' => null,
        ]);

        $jawab = $this->get("/berita/{$draf->slug}")->assertNotFound();

        $this->assertStringNotContainsString('Rancangan yang belum boleh dibaca publik', $jawab->getContent());
    }

    public function test_halaman_rinci_yang_tidak_ada_dijawab_404(): void
    {
        // Perayap perlu tahu halaman itu memang tidak ada, bukan menerima
        // kerangka kosong berstatus 200 lalu mengindeksnya.
        $this->get('/berita/tidak-pernah-ada')->assertNotFound();
        $this->get('/layanan/tidak-pernah-ada')->assertNotFound();

        // Alamat lain tetap dilayani; aplikasi yang menampilkan halaman 404-nya.
        $this->get('/entah-apa')->assertOk();
    }

    public function test_area_pribadi_ditandai_tidak_untuk_diindeks(): void
    {
        foreach (['/akun', '/akun/permohonan/12', '/admin/pengguna', '/masuk'] as $jalur) {
            $isi = $this->get($jalur)->assertOk()->getContent();

            $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $isi, $jalur);
        }

        $this->assertStringNotContainsString('noindex', $this->get('/profil')->getContent());
    }

    public function test_beranda_menyertakan_data_terstruktur_lembaga_pemerintah(): void
    {
        $isi = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"GovernmentOrganization"', $isi);
        $this->assertStringContainsString('"addressCountry":"ID"', $isi);
    }

    public function test_rute_api_dan_berkas_tidak_ikut_tertangkap(): void
    {
        // Rute API dan berkas statis tidak boleh dijawab kerangka aplikasi.
        $this->getJson('/api/v1/profil-desa')->assertOk()->assertJsonStructure(['data']);
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_alamat_tak_dikenal_tidak_menggelembungkan_singgahan(): void
    {
        $metadata = app(MetadataHalaman::class);

        $metadata->untuk('/profil');
        $this->assertNotNull(cache()->get('metadata-halaman:/profil'));

        // Perayap kerap mencoba alamat acak; alamat semacam itu tidak boleh
        // meninggalkan jejak pada singgahan.
        foreach (['/entah-apa', '/wp-admin', '/akun/permohonan/12345'] as $jalur) {
            $metadata->untuk($jalur);
            $this->assertNull(cache()->get('metadata-halaman:'.$jalur), $jalur);
        }
    }

    public function test_pengembangan_tanpa_hasil_build_dialihkan_ke_server_antarmuka(): void
    {
        config()->set('app.dist', storage_path('framework/testing/tidak-ada'));

        $this->get('/profil')->assertRedirect('https://sukamaju.desa.id');
    }
}
