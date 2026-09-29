<?php

namespace Tests\Feature;

use App\Models\Konten;
use App\Models\Permohonan;
use App\Services\PermohonanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** REQ-F-SRC-003, REQ-F-ADM-006, REQ-F-ADM-009, REQ-F-SRT-007. */
class OperasionalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    private function buatKontenTayang(string $judul): Konten
    {
        return Konten::create([
            'tipe' => 'berita',
            'judul' => $judul,
            'slug' => str($judul)->slug()->toString(),
            'isi' => '<p>Isi.</p>',
            'status' => 'terbit',
            'terbit_pada' => now()->subDay(),
        ]);
    }

    public function test_sitemap_memuat_halaman_tetap_dan_konten_tayang(): void
    {
        $this->buatKontenTayang('Berita Yang Tayang');
        Konten::create([
            'tipe' => 'berita',
            'judul' => 'Berita Draf',
            'slug' => 'berita-draf',
            'status' => 'draf',
        ]);

        $respons = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8');

        $isi = $respons->getContent();

        $this->assertStringContainsString('/berita/berita-yang-tayang', $isi);
        $this->assertStringContainsString('/layanan/surat-keterangan-tidak-mampu', $isi);
        $this->assertStringContainsString('/transparansi/apbdes', $isi);

        // Konten yang belum terbit tidak boleh bocor lewat sitemap.
        $this->assertStringNotContainsString('berita-draf', $isi);
    }

    public function test_robots_melarang_area_pribadi_dan_menunjuk_sitemap(): void
    {
        $isi = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Disallow: /admin', $isi);
        $this->assertStringContainsString('Disallow: /akun', $isi);
        $this->assertStringContainsString('Sitemap:', $isi);
    }

    public function test_perintah_pencadangan_membuat_berkas_dan_menghormati_retensi(): void
    {
        $direktori = storage_path('app/private/cadangan');
        File::deleteDirectory($direktori);

        $this->artisan('sidesa:cadangkan', ['--jenis' => 'media'])->assertSuccessful();

        $berkas = File::files($direktori);
        $this->assertCount(1, $berkas);
        $this->assertStringStartsWith('media-', $berkas[0]->getFilename());

        // Berkas lama melewati retensi harus terhapus pada pencadangan berikutnya.
        $usang = "{$direktori}/media-lama.zip";
        File::put($usang, 'usang');
        touch($usang, now()->subDays(40)->getTimestamp());

        $this->artisan('sidesa:cadangkan', ['--jenis' => 'media', '--retensi' => 30])->assertSuccessful();

        $this->assertFileDoesNotExist($usang);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'cadangan']);

        File::deleteDirectory($direktori);
    }

    public function test_warga_dapat_menyimpan_dan_melanjutkan_draf(): void
    {
        $warga = $this->buatWarga();
        $layanan = $this->layanan('SKD');

        $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'draf' => true,
            'data_formulir' => ['nama_lengkap' => 'Warga Uji'],
        ])->assertCreated()->assertJsonPath('data.status', Permohonan::DRAF);

        $permohonan = Permohonan::firstOrFail();

        $this->assertNull($permohonan->tenggat_sla, 'Draf belum memulai perhitungan SLA.');

        $this->actingAs($warga)->putJson("/api/v1/permohonan/{$permohonan->id}/draf", [
            'data_formulir' => ['nama_lengkap' => 'Warga Uji Diperbarui'],
        ])->assertOk();

        $this->assertSame('Warga Uji Diperbarui', $permohonan->refresh()->data_formulir['nama_lengkap']);
        $this->assertSame(Permohonan::DRAF, $permohonan->status);

        // Draf dikirimkan melalui alur perbaikan sehingga SLA mulai dihitung.
        $this->actingAs($warga)->postJson("/api/v1/permohonan/{$permohonan->id}/kirim-ulang", [
            'data_formulir' => $this->dataFormulir($layanan),
        ])->assertOk();

        $this->assertSame(Permohonan::DIAJUKAN, $permohonan->refresh()->status);
        $this->assertNotNull($permohonan->tenggat_sla);
    }

    public function test_draf_tidak_dapat_disunting_pemilik_lain(): void
    {
        $warga = $this->buatWarga();
        $layanan = $this->layanan('SKD');

        $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'draf' => true,
            'data_formulir' => ['nama_lengkap' => 'Warga Uji'],
        ])->assertCreated();

        $this->actingAs($this->buatWarga())
            ->putJson('/api/v1/permohonan/'.Permohonan::firstOrFail()->id.'/draf', [
                'data_formulir' => ['nama_lengkap' => 'Disusupi'],
            ])->assertForbidden();
    }

    public function test_draf_kedaluwarsa_dibersihkan_terjadwal(): void
    {
        $warga = $this->buatWarga();
        $layanan = $this->layanan('SKD');

        $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'draf' => true,
            'data_formulir' => ['nama_lengkap' => 'Warga Uji'],
        ])->assertCreated();

        Permohonan::query()->update([
            'updated_at' => now()->subDays(PermohonanService::BATAS_DRAF_HARI + 1),
        ]);

        $this->artisan('sidesa:bersihkan-draf')->assertSuccessful();

        $this->assertDatabaseCount('permohonan', 0);
    }

    public function test_ekspor_laporan_layanan_dalam_format_csv(): void
    {
        $warga = $this->buatWarga();
        $layanan = $this->layanan('SKD');

        $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'data_formulir' => $this->dataFormulir($layanan),
        ])->assertCreated();

        $respons = $this->actingAs($this->buatPengguna('sekdes'))
            ->get('/api/v1/admin/permohonan/laporan/csv')
            ->assertOk();

        $isi = $respons->streamedContent();

        $this->assertStringContainsString('nomor_tiket,jenis_layanan', $isi);
        $this->assertStringContainsString(Permohonan::firstOrFail()->nomor_tiket, $isi);
    }

    public function test_ekspor_laporan_butuh_izin_laporan(): void
    {
        $this->actingAs($this->buatPengguna('operator'))
            ->get('/api/v1/admin/permohonan/laporan/csv')
            ->assertForbidden();
    }
}
