<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/** REQ-F-USR-001..008, 014; BR-12. */
class AutentikasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    private function dataPendaftaran(array $ubah = []): array
    {
        return [
            'name' => 'Sari Wulandari',
            'email' => 'sari@contoh.id',
            'nik' => '3203014507840003',
            'telepon' => '081234567890',
            'password' => 'Rahasia2026',
            'password_confirmation' => 'Rahasia2026',
            'persetujuan' => true,
            ...$ubah,
        ];
    }

    public function test_warga_baru_terdaftar_dengan_status_menunggu_verifikasi_nik(): void
    {
        $respons = $this->postJson('/api/v1/auth/daftar', $this->dataPendaftaran());

        $respons->assertCreated()
            ->assertJsonPath('pengguna.status_akun', User::BELUM_VERIFIKASI)
            ->assertJsonPath('pengguna.boleh_mengajukan', false);

        $pengguna = User::where('email', 'sari@contoh.id')->firstOrFail();

        $this->assertSame('warga', $pengguna->role->kode);
        $this->assertNotNull($pengguna->consent_at, 'Persetujuan pemrosesan data pribadi wajib tercatat.');
        // NIK tidak boleh tersimpan dalam bentuk terbaca (REQ-NF-SEC-006).
        $this->assertNotSame('3203014507840003', $pengguna->getRawOriginal('nik'));
        $this->assertSame('3203014507840003', $pengguna->nik);
    }

    public function test_pendaftaran_ditolak_tanpa_persetujuan_data_pribadi(): void
    {
        $this->postJson('/api/v1/auth/daftar', $this->dataPendaftaran(['persetujuan' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('persetujuan');
    }

    public function test_kata_sandi_lemah_ditolak(): void
    {
        $this->postJson('/api/v1/auth/daftar', $this->dataPendaftaran([
            'password' => 'password',
            'password_confirmation' => 'password',
        ]))->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_satu_nik_hanya_untuk_satu_akun(): void
    {
        $this->postJson('/api/v1/auth/daftar', $this->dataPendaftaran())->assertCreated();

        $this->postJson('/api/v1/auth/daftar', $this->dataPendaftaran(['email' => 'lain@contoh.id']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nik');
    }

    public function test_akun_terkunci_setelah_lima_kali_gagal_masuk(): void
    {
        $warga = $this->buatWarga(['email' => 'warga@contoh.id']);

        foreach (range(1, 5) as $percobaan) {
            $this->postJson('/api/v1/auth/masuk', ['email' => $warga->email, 'password' => 'salah'])
                ->assertStatus(422);
        }

        $this->assertNotNull($warga->refresh()->terkunci_sampai);

        // Kredensial yang benar pun ditolak selama masa penguncian. Pembatas laju
        // dilepas agar yang diuji murni penguncian akun, bukan throttling.
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->postJson('/api/v1/auth/masuk', ['email' => $warga->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', fn ($pesan) => str_contains($pesan, 'terkunci'));
    }

    /** REQ-NF-SEC-009: pembatasan laju per akun pada titik akhir masuk. */
    public function test_pembatas_laju_per_akun_menghentikan_percobaan_beruntun(): void
    {
        $warga = $this->buatWarga(['email' => 'target@contoh.id']);

        foreach (range(1, 5) as $percobaan) {
            $this->postJson('/api/v1/auth/masuk', ['email' => $warga->email, 'password' => 'salah']);
        }

        $this->postJson('/api/v1/auth/masuk', ['email' => $warga->email, 'password' => 'salah'])
            ->assertStatus(429);
    }

    /**
     * Batas per alamat IP tidak boleh menjegal kantor desa yang berbagi satu
     * koneksi: lima petugas berbeda tetap dapat masuk berurutan.
     */
    public function test_beberapa_akun_dari_satu_alamat_ip_tetap_dapat_masuk(): void
    {
        foreach (['operator', 'verifikator', 'sekdes', 'kades', 'admin'] as $peran) {
            $petugas = $this->buatPengguna($peran);

            $this->postJson('/api/v1/auth/masuk', ['email' => $petugas->email, 'password' => 'password'])
                ->assertOk();
        }
    }

    public function test_masuk_berhasil_mengembalikan_token_dan_daftar_izin(): void
    {
        $sekdes = $this->buatPengguna('sekdes');

        $respons = $this->postJson('/api/v1/auth/masuk', ['email' => $sekdes->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'kedaluwarsa', 'pengguna' => ['izin']]);

        $this->assertContains('permohonan.setujui', $respons->json('pengguna.izin'));
    }

    public function test_akun_nonaktif_tidak_dapat_masuk(): void
    {
        $warga = $this->buatWarga(['status_akun' => User::NONAKTIF]);

        $this->postJson('/api/v1/auth/masuk', ['email' => $warga->email, 'password' => 'password'])
            ->assertStatus(422);
    }

    public function test_titik_akhir_terlindungi_menolak_permintaan_tanpa_token(): void
    {
        $this->getJson('/api/v1/auth/saya')->assertUnauthorized();
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }
}
