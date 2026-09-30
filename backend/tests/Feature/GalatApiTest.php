<?php

namespace Tests\Feature;

use App\Http\Middleware\PengenalKorelasi;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * REQ-API-006 dan REQ-NF-MNT-007: setiap respons galat memuat kode galat, pesan
 * ringkas, dan pengenal korelasi yang sama dengan yang tercatat pada log.
 */
class GalatApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    public function test_galat_validasi_memuat_kode_pesan_dan_korelasi(): void
    {
        $jawab = $this->postJson('/api/v1/auth/masuk', ['email' => 'bukan-surel'])->assertUnprocessable();

        $jawab->assertJsonPath('kode', 'VALIDASI_GAGAL');
        $this->assertNotEmpty($jawab->json('pesan'));
        $this->assertNotEmpty($jawab->json('korelasi'));

        // Bentuk lama tetap ada agar klien yang sudah jalan tidak rusak.
        $jawab->assertJsonValidationErrors('email');
    }

    public function test_kode_galat_mengikuti_jenis_kegagalan(): void
    {
        $this->getJson('/api/v1/admin/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('kode', 'TIDAK_TERAUTENTIKASI');

        $this->actingAs($this->buatWarga())
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('kode', 'AKSES_DITOLAK');

        $this->actingAs($this->buatPengguna(Role::OPERATOR))
            ->getJson('/api/v1/admin/permohonan/999999')
            ->assertNotFound()
            ->assertJsonPath('kode', 'TIDAK_DITEMUKAN');
    }

    public function test_kode_galat_domain_tidak_tertimpa_kode_umum(): void
    {
        $warga = $this->buatWarga(['verifikasi_nik_at' => null]);
        $layanan = $this->layanan('SKTM');

        // Kegagalan aturan bisnis lebih menjelaskan daripada "validasi gagal",
        // jadi kodenya sendiri yang harus bertahan.
        $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'data_formulir' => $this->dataFormulir($layanan),
        ])->assertUnprocessable()->assertJsonPath('kode', 'ALUR_TIDAK_VALID');
    }

    public function test_pengenal_korelasi_dikembalikan_pada_tajuk_setiap_respons(): void
    {
        $jawab = $this->getJson('/api/v1/profil-desa')->assertOk();

        $korelasi = $jawab->headers->get(PengenalKorelasi::TAJUK);

        $this->assertNotEmpty($korelasi);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $korelasi);
    }

    public function test_pengenal_dari_pemanggil_dipakai_ulang_agar_dapat_ditelusuri(): void
    {
        $milik = 'peladen-depan-8f3a21b7';

        $jawab = $this->withHeader(PengenalKorelasi::TAJUK, $milik)
            ->getJson('/api/v1/profil-desa')
            ->assertOk();

        $this->assertSame($milik, $jawab->headers->get(PengenalKorelasi::TAJUK));
    }

    public function test_pengenal_kiriman_yang_tidak_wajar_diganti(): void
    {
        // Nilai bebas dari luar tidak boleh masuk ke berkas log apa adanya.
        $jahat = "baris-pertama\nWARNING: baris palsu yang disisipkan penyerang";

        $jawab = $this->withHeader(PengenalKorelasi::TAJUK, $jahat)
            ->getJson('/api/v1/profil-desa')
            ->assertOk();

        $dipakai = (string) $jawab->headers->get(PengenalKorelasi::TAJUK);

        $this->assertNotSame($jahat, $dipakai);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $dipakai);

        foreach (['x', str_repeat('a', 65), 'pendek'] as $tidakWajar) {
            $lain = $this->withHeader(PengenalKorelasi::TAJUK, $tidakWajar)
                ->getJson('/api/v1/profil-desa')
                ->headers->get(PengenalKorelasi::TAJUK);

            $this->assertNotSame($tidakWajar, $lain);
        }
    }

    public function test_korelasi_pada_badan_galat_sama_dengan_yang_di_tajuk(): void
    {
        $jawab = $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();

        // Warga menyebut kode yang tampil di layar; petugas mencarinya di log.
        $this->assertSame(
            $jawab->headers->get(PengenalKorelasi::TAJUK),
            $jawab->json('korelasi'),
        );
    }
}
