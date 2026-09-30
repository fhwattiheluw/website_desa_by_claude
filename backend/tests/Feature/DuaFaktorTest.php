<?php

namespace Tests\Feature;

use App\Models\NotifikasiLog;
use App\Models\Role;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** REQ-F-USR-009: otentikasi dua faktor wajib bagi peran berwenang. */
class DuaFaktorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    /** @return array{tantangan: string, kode: string} */
    private function masuk(string $peran): array
    {
        $petugas = $this->buatPengguna($peran);

        $jawab = $this->postJson('/api/v1/auth/masuk', [
            'email' => $petugas->email,
            'password' => 'password',
        ])->assertStatus(202);

        // Kode hanya ada pada pesan yang dikirim; uji membacanya dari sana,
        // persis seperti petugas membacanya dari surel.
        $log = NotifikasiLog::where('templat', 'kode_masuk')->latest('id')->firstOrFail();

        return ['tantangan' => $jawab->json('tantangan'), 'kode' => $log->data['kode']];
    }

    public function test_peran_berwenang_belum_memperoleh_token_dari_kata_sandi_saja(): void
    {
        foreach (OtpService::PERAN_WAJIB as $peran) {
            $petugas = $this->buatPengguna($peran);

            $jawab = $this->postJson('/api/v1/auth/masuk', [
                'email' => $petugas->email,
                'password' => 'password',
            ])->assertStatus(202);

            $jawab->assertJson(['perlu_otp' => true]);
            // Tidak ada token sebelum langkah kedua dilewati.
            $this->assertNull($jawab->json('token'));
            $this->assertSame(0, $petugas->tokens()->count());
        }
    }

    public function test_peran_lain_tetap_masuk_dalam_satu_langkah(): void
    {
        foreach ([Role::WARGA, Role::OPERATOR, Role::VERIFIKATOR] as $peran) {
            $pengguna = $peran === Role::WARGA ? $this->buatWarga() : $this->buatPengguna($peran);

            $this->postJson('/api/v1/auth/masuk', ['email' => $pengguna->email, 'password' => 'password'])
                ->assertOk()
                ->assertJsonStructure(['token']);
        }
    }

    public function test_kode_yang_benar_menerbitkan_sesi(): void
    {
        $tantangan = $this->masuk(Role::SEKDES);

        $this->postJson('/api/v1/auth/otp', $tantangan)
            ->assertOk()
            ->assertJsonStructure(['token', 'kedaluwarsa', 'pengguna' => ['izin']]);

        $this->assertDatabaseHas('audit_log', ['aksi' => 'login']);
    }

    public function test_kode_hanya_dapat_dipakai_sekali(): void
    {
        $tantangan = $this->masuk(Role::KADES);

        $this->postJson('/api/v1/auth/otp', $tantangan)->assertOk();
        $this->postJson('/api/v1/auth/otp', $tantangan)->assertUnprocessable();
    }

    public function test_kode_keliru_ditolak_dan_dibatasi_percobaannya(): void
    {
        $tantangan = $this->masuk(Role::ADMIN);
        $salah = ['tantangan' => $tantangan['tantangan'], 'kode' => '000000'];

        for ($percobaan = 0; $percobaan < OtpService::MAKS_PERCOBAAN; $percobaan++) {
            $this->postJson('/api/v1/auth/otp', $salah)->assertUnprocessable();
        }

        // Setelah batas percobaan, tantangan dihanguskan; kode yang benar pun
        // tidak lagi berlaku.
        $this->postJson('/api/v1/auth/otp', $tantangan)->assertUnprocessable();
        $this->assertDatabaseHas('audit_log', ['aksi' => 'otp_gagal']);
    }

    public function test_kode_tidak_pernah_tersimpan_apa_adanya(): void
    {
        $tantangan = $this->masuk(Role::SEKDES);
        $tersimpan = Cache::get('otp:'.$tantangan['tantangan']);

        $this->assertNotSame($tantangan['kode'], $tersimpan['kode']);
        $this->assertTrue(Hash::check($tantangan['kode'], $tersimpan['kode']));
    }

    public function test_tantangan_asing_ditolak(): void
    {
        $this->postJson('/api/v1/auth/otp', ['tantangan' => str_repeat('a', 40), 'kode' => '123456'])
            ->assertUnprocessable();
    }

    public function test_tujuan_pengiriman_disamarkan(): void
    {
        $petugas = $this->buatPengguna(Role::ADMIN, ['email' => 'administrator@sukamaju.desa.id']);

        $jawab = $this->postJson('/api/v1/auth/masuk', [
            'email' => $petugas->email,
            'password' => 'password',
        ])->assertStatus(202);

        // Layar masuk tidak boleh menjadi jalan memastikan alamat surel petugas.
        $this->assertSame('ad***********@sukamaju.desa.id', $jawab->json('tujuan'));
        $this->assertStringNotContainsString('administrator@', $jawab->getContent());
    }

    public function test_kata_sandi_keliru_tidak_mengirim_kode(): void
    {
        $petugas = $this->buatPengguna(Role::SEKDES);

        $this->postJson('/api/v1/auth/masuk', ['email' => $petugas->email, 'password' => 'keliru'])
            ->assertUnprocessable();

        $this->assertSame(0, NotifikasiLog::where('templat', 'kode_masuk')->count());
    }
}
