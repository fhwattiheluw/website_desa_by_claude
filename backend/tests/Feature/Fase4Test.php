<?php

namespace Tests\Feature;

use App\Models\KinerjaBumdes;
use App\Models\Konten;
use App\Models\PeriodeStatistik;
use App\Models\Permohonan;
use App\Models\SpesimenTandaTangan;
use App\Models\SuratTerbit;
use App\Models\TahunAnggaran;
use App\Models\UnitUsaha;
use App\Models\User;
use App\Services\SuratService;
use App\Services\TandaTangan\ManajerTandaTangan;
use App\Services\TandaTangan\TandaTanganPsre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** REQ-F-SRT-017, REQ-SW-004, REQ-F-POT-003, REQ-API-005, REQ-F-SRC-006. */
class Fase4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
        Storage::fake('local');
    }

    private function permohonanDisetujui(User $warga): Permohonan
    {
        $layanan = $this->layanan('SKD');

        $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'data_formulir' => $this->dataFormulir($layanan),
        ])->assertCreated();

        $permohonan = Permohonan::firstOrFail();

        $this->actingAs($this->buatPengguna('operator'))
            ->postJson("/api/v1/admin/permohonan/{$permohonan->id}/verifikasi")->assertOk();
        $this->actingAs($this->buatPengguna('verifikator'))
            ->postJson("/api/v1/admin/permohonan/{$permohonan->id}/setujui")->assertOk();

        return $permohonan->refresh();
    }

    /* ------------------------------------------------ Tanda tangan elektronik */

    public function test_metode_bawaan_adalah_tanda_tangan_dalam_sistem(): void
    {
        config(['tte.driver' => 'internal']);

        $permohonan = $this->permohonanDisetujui($this->buatWarga());
        $kades = $this->buatPengguna('kades');

        $this->actingAs($kades)
            ->postJson("/api/v1/admin/permohonan/{$permohonan->id}/tanda-tangani")
            ->assertOk();

        $surat = SuratTerbit::firstOrFail();

        $this->assertSame('internal', $surat->metode_tanda_tangan);
        $this->assertNull($surat->bukti_tte);
        $this->assertTrue(Storage::disk(SuratService::DISK)->exists($surat->path_pdf));
    }

    public function test_hanya_pejabat_penanda_tangan_yang_dapat_mengelola_spesimen(): void
    {
        $this->actingAs($this->buatPengguna('operator'))
            ->getJson('/api/v1/admin/tanda-tangan')
            ->assertForbidden();

        $this->actingAs($this->buatPengguna('kades'))
            ->getJson('/api/v1/admin/tanda-tangan')
            ->assertOk()
            ->assertJsonPath('metode_aktif', 'internal')
            ->assertJsonPath('spesimen_terpasang', false);
    }

    public function test_spesimen_tersimpan_privat_dan_dipakai_pada_surat(): void
    {
        $kades = $this->buatPengguna('kades');

        $this->actingAs($kades)->post('/api/v1/admin/tanda-tangan', [
            'berkas' => UploadedFile::fake()->image('ttd.png', 300, 120),
        ])->assertOk();

        $spesimen = SpesimenTandaTangan::firstOrFail();

        $this->assertSame($kades->id, $spesimen->user_id);
        $this->assertSame('local', $spesimen->disk, 'Spesimen wajib berada pada disk privat.');
        $this->assertTrue(Storage::disk('local')->exists($spesimen->path));

        $this->actingAs($kades)->getJson('/api/v1/admin/tanda-tangan')
            ->assertOk()
            ->assertJsonPath('spesimen_terpasang', true);

        // Spesimen milik pejabat lain tidak dapat diintip.
        $this->actingAs($this->buatPengguna('sekdes'))
            ->get('/api/v1/admin/tanda-tangan/pratinjau')
            ->assertNotFound();
    }

    public function test_berkas_selain_gambar_ditolak_sebagai_spesimen(): void
    {
        $this->actingAs($this->buatPengguna('kades'))
            ->post('/api/v1/admin/tanda-tangan', [
                'berkas' => UploadedFile::fake()->create('skrip.php', 10, 'application/x-php'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('berkas');
    }

    public function test_penyedia_tersertifikasi_dipakai_saat_dikonfigurasi(): void
    {
        config([
            'tte.driver' => 'psre',
            'tte.psre.url' => 'https://psre.contoh.id/api/sign',
            'tte.psre.token' => 'token-uji',
            'tte.psre.passphrase' => 'rahasia',
        ]);

        Http::fake(['psre.contoh.id/*' => Http::response('%PDF-1.7 dokumen tertandatangani', 200)]);

        $permohonan = $this->permohonanDisetujui($this->buatWarga());
        $kades = $this->buatPengguna('kades', ['nik' => '3203010101800001']);

        $this->actingAs($kades)
            ->postJson("/api/v1/admin/permohonan/{$permohonan->id}/tanda-tangani")
            ->assertOk();

        $surat = SuratTerbit::firstOrFail();

        $this->assertSame('tte', $surat->metode_tanda_tangan);
        $this->assertSame('Balai Sertifikasi Elektronik', $surat->bukti_tte['penyedia']);
        $this->assertArrayHasKey('hash_dokumen', $surat->bukti_tte);
        $this->assertSame(
            '%PDF-1.7 dokumen tertandatangani',
            Storage::disk(SuratService::DISK)->get($surat->path_pdf),
        );
    }

    /**
     * REQ-NF-REL-005: kegagalan penyedia eksternal tidak boleh menghentikan
     * pelayanan. Surat tetap terbit dengan metode dalam sistem.
     */
    public function test_kegagalan_penyedia_tte_tidak_menggagalkan_penerbitan(): void
    {
        config([
            'tte.driver' => 'psre',
            'tte.psre.url' => 'https://psre.contoh.id/api/sign',
            'tte.psre.token' => 'token-uji',
        ]);

        Http::fake(['psre.contoh.id/*' => Http::response('layanan sedang gangguan', 503)]);

        $permohonan = $this->permohonanDisetujui($this->buatWarga());
        $kades = $this->buatPengguna('kades', ['nik' => '3203010101800001']);

        $this->actingAs($kades)
            ->postJson("/api/v1/admin/permohonan/{$permohonan->id}/tanda-tangani")
            ->assertOk();

        $surat = SuratTerbit::firstOrFail();

        $this->assertSame('internal', $surat->metode_tanda_tangan);
        $this->assertSame(Permohonan::SELESAI, $permohonan->refresh()->status);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'tte_gagal']);
    }

    public function test_driver_psre_tanpa_konfigurasi_kembali_ke_metode_internal(): void
    {
        config(['tte.driver' => 'psre', 'tte.psre.url' => null, 'tte.psre.token' => null]);

        $this->assertFalse(app(TandaTanganPsre::class)->siap());
        $this->assertSame('internal', app(ManajerTandaTangan::class)->aktif()->metode());
    }

    /* ------------------------------------------------------------ BUMDes */

    public function test_laman_bumdes_menyajikan_unit_usaha_dan_kinerja_terpublikasi(): void
    {
        UnitUsaha::create(['nama' => 'Toko Desa', 'slug' => 'toko-desa', 'aktif' => true]);
        UnitUsaha::create(['nama' => 'Unit Nonaktif', 'slug' => 'unit-nonaktif', 'aktif' => false]);

        KinerjaBumdes::create(['tahun' => 2025, 'pendapatan' => 100, 'dipublikasikan' => true]);
        KinerjaBumdes::create(['tahun' => 2026, 'pendapatan' => 200, 'dipublikasikan' => false]);

        $respons = $this->getJson('/api/v1/bumdes')->assertOk();

        $this->assertSame(['Toko Desa'], collect($respons->json('unit_usaha'))->pluck('nama')->all());
        $this->assertSame([2025], collect($respons->json('kinerja'))->pluck('tahun')->all());
    }

    public function test_kinerja_bumdes_baru_tampil_setelah_dipublikasikan(): void
    {
        $kinerja = KinerjaBumdes::create(['tahun' => 2026, 'pendapatan' => 500]);

        $this->getJson('/api/v1/bumdes')->assertOk()->assertJsonCount(0, 'kinerja');

        $this->actingAs($this->buatPengguna('operator'))
            ->postJson("/api/v1/admin/bumdes/kinerja/{$kinerja->id}/publikasi", ['dipublikasikan' => true])
            ->assertForbidden();

        $this->actingAs($this->buatPengguna('sekdes'))
            ->postJson("/api/v1/admin/bumdes/kinerja/{$kinerja->id}/publikasi", ['dipublikasikan' => true])
            ->assertOk();

        $this->getJson('/api/v1/bumdes')->assertOk()->assertJsonCount(1, 'kinerja');
    }

    /* ------------------------------------------------- API data terbuka & RSS */

    public function test_api_data_terbuka_menyajikan_lisensi_dan_kumpulan_data(): void
    {
        $this->getJson('/api/v1/terbuka')
            ->assertOk()
            ->assertJsonPath('lisensi.nama', 'Creative Commons Attribution 4.0')
            ->assertJsonStructure(['kuota', 'catatan_privasi', 'kumpulan_data']);
    }

    public function test_api_data_terbuka_menyamarkan_kelompok_kecil(): void
    {
        $periode = PeriodeStatistik::create(['nama' => 'Uji', 'tahun' => 2026, 'aktif' => true]);

        $periode->item()->create(['kelompok' => 'agama', 'label' => 'Islam', 'jumlah' => 4000]);
        $periode->item()->create(['kelompok' => 'agama', 'label' => 'Buddha', 'jumlah' => 2]);

        $respons = $this->getJson('/api/v1/terbuka/statistik')->assertOk();

        $this->assertSame(4000, $respons->json('kelompok.agama.0.jumlah'));
        $this->assertNull($respons->json('kelompok.agama.1.jumlah'));
    }

    public function test_api_data_terbuka_tidak_membocorkan_apbdes_yang_belum_terbit(): void
    {
        TahunAnggaran::create(['tahun' => 2026, 'dipublikasikan' => false]);

        $this->getJson('/api/v1/terbuka/apbdes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/terbuka/apbdes/2026')->assertNotFound();
    }

    public function test_umpan_rss_memuat_berita_tayang_saja(): void
    {
        Konten::create([
            'tipe' => 'berita', 'judul' => 'Berita Tayang', 'slug' => 'berita-tayang',
            'ringkasan' => 'Ringkasan berita.', 'status' => 'terbit', 'terbit_pada' => now()->subDay(),
        ]);
        Konten::create([
            'tipe' => 'berita', 'judul' => 'Berita Draf', 'slug' => 'berita-draf', 'status' => 'draf',
        ]);

        $isi = $this->get('/rss/berita.xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/rss+xml; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('<title>Berita Tayang</title>', $isi);
        $this->assertStringNotContainsString('Berita Draf', $isi);
        $this->assertStringContainsString('<language>id-ID</language>', $isi);
    }

    public function test_umpan_rss_jenis_tak_dikenal_ditolak(): void
    {
        $this->get('/rss/rahasia.xml')->assertNotFound();
    }
}
