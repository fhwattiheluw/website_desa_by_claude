<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\VerifikasiSurelController;
use App\Models\NotifikasiLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** REQ-F-USR-002, REQ-F-USR-006. */
class PemulihanAkunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    public function test_permintaan_pemulihan_mengirim_tautan_berbatas_waktu(): void
    {
        $warga = $this->buatWarga(['email' => 'warga@contoh.id']);

        $this->postJson('/api/v1/auth/lupa-kata-sandi', ['email' => $warga->email])
            ->assertOk()
            ->assertJsonPath('pesan', fn ($pesan) => str_contains($pesan, '60 menit'));

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $warga->email]);

        $log = NotifikasiLog::where('templat', 'pemulihan_kata_sandi')->firstOrFail();

        $this->assertSame($warga->email, $log->tujuan);
        $this->assertStringContainsString('/atur-ulang-kata-sandi?token=', $log->data['tautan']);
    }

    /**
     * Jawaban untuk surel yang tidak terdaftar harus sama persis dengan yang
     * terdaftar, agar tidak dapat dipakai memetakan akun yang ada.
     */
    public function test_surel_tak_dikenal_memperoleh_jawaban_yang_sama(): void
    {
        $warga = $this->buatWarga(['email' => 'ada@contoh.id']);

        $terdaftar = $this->postJson('/api/v1/auth/lupa-kata-sandi', ['email' => $warga->email])->assertOk();
        $asing = $this->postJson('/api/v1/auth/lupa-kata-sandi', ['email' => 'tidakada@contoh.id'])->assertOk();

        $this->assertSame($terdaftar->json('pesan'), $asing->json('pesan'));
        $this->assertSame(1, NotifikasiLog::where('templat', 'pemulihan_kata_sandi')->count());
    }

    public function test_tautan_pemulihan_mengubah_kata_sandi_dan_mencabut_sesi_lama(): void
    {
        $warga = $this->buatWarga(['email' => 'warga@contoh.id']);
        $warga->createToken('sesi-lama');

        $token = Password::createToken($warga);

        $this->postJson('/api/v1/auth/atur-ulang-kata-sandi', [
            'token' => $token,
            'email' => $warga->email,
            'password' => 'SandiBaru2026',
            'password_confirmation' => 'SandiBaru2026',
        ])->assertOk();

        $this->assertSame(0, $warga->tokens()->count(), 'Sesi lama wajib dicabut setelah kata sandi diganti.');

        $this->postJson('/api/v1/auth/masuk', ['email' => $warga->email, 'password' => 'SandiBaru2026'])
            ->assertOk();
    }

    public function test_tautan_pemulihan_hanya_berlaku_sekali(): void
    {
        $warga = $this->buatWarga(['email' => 'warga@contoh.id']);
        $token = Password::createToken($warga);

        $muatan = [
            'token' => $token,
            'email' => $warga->email,
            'password' => 'SandiBaru2026',
            'password_confirmation' => 'SandiBaru2026',
        ];

        $this->postJson('/api/v1/auth/atur-ulang-kata-sandi', $muatan)->assertOk();

        $this->postJson('/api/v1/auth/atur-ulang-kata-sandi', $muatan)
            ->assertStatus(422)
            ->assertJsonValidationErrors('token');
    }

    public function test_kata_sandi_baru_tetap_tunduk_aturan_kekuatan(): void
    {
        $warga = $this->buatWarga(['email' => 'warga@contoh.id']);

        $this->postJson('/api/v1/auth/atur-ulang-kata-sandi', [
            'token' => Password::createToken($warga),
            'email' => $warga->email,
            'password' => 'semuahuruf',
            'password_confirmation' => 'semuahuruf',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_pendaftaran_mengirim_tautan_verifikasi_surel(): void
    {
        $this->postJson('/api/v1/auth/daftar', [
            'name' => 'Sari Wulandari',
            'email' => 'sari@contoh.id',
            'nik' => '3203014507840003',
            'telepon' => '081234567890',
            'password' => 'Rahasia2026',
            'password_confirmation' => 'Rahasia2026',
            'persetujuan' => true,
        ])->assertCreated()->assertJsonPath('pengguna.surel_terverifikasi', false);

        $log = NotifikasiLog::where('templat', 'verifikasi_surel')->firstOrFail();

        $this->assertStringContainsString('/api/v1/auth/verifikasi-surel/', $log->data['tautan']);
    }

    public function test_membuka_tautan_verifikasi_menandai_surel_terverifikasi(): void
    {
        $warga = $this->buatWarga(['email' => 'warga@contoh.id', 'email_verified_at' => null]);

        $this->get(VerifikasiSurelController::tautanUntuk($warga))
            ->assertRedirectContains('verifikasi=berhasil');

        $this->assertNotNull($warga->refresh()->email_verified_at);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'verifikasi_surel', 'entitas_id' => $warga->id]);
    }

    public function test_tautan_verifikasi_tanpa_tanda_tangan_ditolak(): void
    {
        $warga = $this->buatWarga(['email' => 'warga@contoh.id', 'email_verified_at' => null]);

        $this->get("/api/v1/auth/verifikasi-surel/{$warga->id}/".sha1($warga->email))
            ->assertForbidden();

        $this->assertNull($warga->refresh()->email_verified_at);
    }

    public function test_verifikasi_surel_bukan_pengganti_verifikasi_nik(): void
    {
        $warga = $this->buatWarga([
            'email' => 'warga@contoh.id',
            'email_verified_at' => null,
            'status_akun' => User::BELUM_VERIFIKASI,
            'verifikasi_nik_at' => null,
        ]);

        $this->get(VerifikasiSurelController::tautanUntuk($warga));

        // Surel terbukti dimiliki, tetapi kewenangan mengajukan layanan tetap
        // menunggu validasi NIK oleh petugas (BR-01).
        $this->assertNotNull($warga->refresh()->email_verified_at);
        $this->assertFalse($warga->bolehMengajukanLayanan());
    }
}
