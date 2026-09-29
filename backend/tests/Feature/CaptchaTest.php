<?php

namespace Tests\Feature;

use App\Services\Captcha\CaptchaBawaan;
use App\Services\Captcha\CaptchaTurnstile;
use App\Services\Captcha\ManajerCaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * REQ-SW-007 dan REQ-F-ADU-010: tantangan formulir publik diverifikasi di sisi
 * server. Uji lain berjalan dengan driver `nihil`, jadi berkas ini menyalakan
 * driver bawaan secara eksplisit.
 */
class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
        config()->set('captcha.driver', 'bawaan');
    }

    /** @return array{token: string, jawaban: int} */
    private function ambilTantangan(): array
    {
        $tantangan = $this->getJson('/api/v1/captcha')->assertOk()->json();

        return ['token' => $tantangan['token'], 'jawaban' => (int) Cache::get('captcha:'.$tantangan['token'])];
    }

    private function muatanPengaduan(array $ubah = []): array
    {
        return [
            'nama_pelapor' => 'Agus Priyanto',
            'kontak_pelapor' => 'agus@contoh.id',
            'kategori' => 'infrastruktur',
            'judul' => 'Lampu penerangan jalan mati',
            'uraian' => 'Lampu penerangan jalan di RT 003 RW 002 sudah mati sejak dua pekan lalu dan rawan kecelakaan.',
            ...$ubah,
        ];
    }

    public function test_tantangan_berisi_pertanyaan_tanpa_membocorkan_kunci_jawaban(): void
    {
        $jawab = $this->getJson('/api/v1/captcha')->assertOk();

        $jawab->assertJson(['aktif' => true, 'metode' => 'bawaan']);
        $this->assertNotEmpty($jawab->json('pertanyaan'));

        // Kunci jawaban hanya boleh ada di singgahan sisi server.
        $this->assertArrayNotHasKey('jawaban', $jawab->json());
        $this->assertNotNull(Cache::get('captcha:'.$jawab->json('token')));
        $this->assertStringContainsString('no-store', (string) $jawab->headers->get('Cache-Control'));
    }

    public function test_formulir_publik_ditolak_tanpa_jawaban_tantangan(): void
    {
        $this->postJson('/api/v1/pengaduan', $this->muatanPengaduan())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('captcha_jawaban');

        $this->assertDatabaseCount('pengaduan', 0);
    }

    public function test_formulir_publik_ditolak_bila_jawaban_keliru(): void
    {
        $tantangan = $this->ambilTantangan();

        $this->postJson('/api/v1/pengaduan', $this->muatanPengaduan([
            'captcha_token' => $tantangan['token'],
            'captcha_jawaban' => (string) ($tantangan['jawaban'] + 1),
        ]))->assertUnprocessable()->assertJsonValidationErrors('captcha_jawaban');

        $this->assertDatabaseCount('pengaduan', 0);
    }

    public function test_formulir_publik_diterima_dengan_jawaban_benar(): void
    {
        $tantangan = $this->ambilTantangan();

        $this->postJson('/api/v1/pengaduan', $this->muatanPengaduan([
            'captcha_token' => $tantangan['token'],
            'captcha_jawaban' => (string) $tantangan['jawaban'],
        ]))->assertCreated();

        $this->assertDatabaseCount('pengaduan', 1);
    }

    public function test_satu_tantangan_hanya_dapat_dipakai_sekali(): void
    {
        $tantangan = $this->ambilTantangan();

        $muatan = $this->muatanPengaduan([
            'captcha_token' => $tantangan['token'],
            'captcha_jawaban' => (string) $tantangan['jawaban'],
        ]);

        $this->postJson('/api/v1/pengaduan', $muatan)->assertCreated();
        // Pengiriman ulang dengan token yang sama tidak boleh lolos.
        $this->postJson('/api/v1/pengaduan', $muatan)->assertUnprocessable();

        $this->assertDatabaseCount('pengaduan', 1);
    }

    public function test_jawaban_salah_menghanguskan_tantangan(): void
    {
        $tantangan = $this->ambilTantangan();

        $this->postJson('/api/v1/pengaduan', $this->muatanPengaduan([
            'captcha_token' => $tantangan['token'],
            'captcha_jawaban' => (string) ($tantangan['jawaban'] + 3),
        ]))->assertUnprocessable();

        // Tidak boleh ada kesempatan menebak berulang pada token yang sama.
        $this->postJson('/api/v1/pengaduan', $this->muatanPengaduan([
            'captcha_token' => $tantangan['token'],
            'captcha_jawaban' => (string) $tantangan['jawaban'],
        ]))->assertUnprocessable();
    }

    public function test_permohonan_informasi_dan_pendaftaran_umkm_ikut_dilindungi(): void
    {
        $this->postJson('/api/v1/permohonan-informasi', [
            'nama' => 'Siti Aminah',
            'kontak' => 'siti@contoh.id',
            'informasi_diminta' => 'Salinan laporan realisasi APBDes tahun anggaran berjalan.',
        ])->assertUnprocessable()->assertJsonValidationErrors('captcha_jawaban');

        $this->postJson('/api/v1/umkm/daftar', [
            'nama_usaha' => 'Keripik Mekar',
            'pemilik' => 'Rahmat Hidayat',
            'kategori' => 'Makanan Olahan',
        ])->assertUnprocessable()->assertJsonValidationErrors('captcha_jawaban');
    }

    public function test_jawaban_berupa_kata_diterima(): void
    {
        $captcha = new CaptchaBawaan;
        $tantangan = $captcha->tantangan();
        $jawaban = (int) Cache::get('captcha:'.$tantangan['token']);

        $kata = [
            0 => 'nol', 1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat', 5 => 'lima', 6 => 'enam',
            7 => 'tujuh', 8 => 'delapan', 9 => 'sembilan', 10 => 'sepuluh', 11 => 'sebelas',
            12 => 'dua belas', 13 => 'tiga belas', 14 => 'empat belas', 15 => 'lima belas',
            16 => 'enam belas', 17 => 'tujuh belas', 18 => 'delapan belas',
        ];

        $this->assertTrue($captcha->periksa($tantangan['token'], strtoupper($kata[$jawaban])));
    }

    public function test_driver_pihak_ketiga_tanpa_kunci_kembali_ke_tantangan_bawaan(): void
    {
        config()->set('captcha.driver', 'turnstile');
        config()->set('captcha.turnstile.kunci_situs', null);
        config()->set('captcha.turnstile.kunci_rahasia', null);

        $aktif = app(ManajerCaptcha::class)->aktif();

        // Yang penting: formulir tidak pernah berakhir tanpa pelindung.
        $this->assertInstanceOf(CaptchaBawaan::class, $aktif);
        $this->assertTrue($aktif->aktif());

        config()->set('captcha.turnstile.kunci_situs', '1x00000000000000000000AA');
        config()->set('captcha.turnstile.kunci_rahasia', '1x0000000000000000000000000000000AA');

        $this->assertInstanceOf(CaptchaTurnstile::class, app(ManajerCaptcha::class)->aktif());
    }

    public function test_driver_nihil_membiarkan_formulir_lewat_tanpa_tantangan(): void
    {
        config()->set('captcha.driver', 'nihil');

        $this->getJson('/api/v1/captcha')->assertOk()->assertJson(['aktif' => false]);
        $this->postJson('/api/v1/pengaduan', $this->muatanPengaduan())->assertCreated();
    }
}
