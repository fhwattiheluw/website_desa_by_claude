<?php

namespace Tests\Feature;

use App\Models\NotifikasiLog;
use App\Models\Role;
use App\Services\DeteksiInsidenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** REQ-NF-CMP-005: indikasi kebocoran data terdeteksi dan dilaporkan. */
class DeteksiInsidenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    private function catatJejak(string $aksi, int $kali, ?int $aktor = null, ?string $ip = null, int $umurJam = 1): void
    {
        $baris = [];

        for ($i = 0; $i < $kali; $i++) {
            $baris[] = [
                'aktor_id' => $aktor,
                'aksi' => $aksi,
                'entitas' => 'User',
                'alamat_ip' => $ip,
                'created_at' => now()->subHours($umurJam),
            ];
        }

        DB::table('audit_log')->insert($baris);
    }

    public function test_tidak_ada_temuan_pada_aktivitas_wajar(): void
    {
        $this->catatJejak('login_gagal', 3, null, '203.0.113.9');
        $this->catatJejak('ekspor_laporan', 2, $this->buatPengguna(Role::SEKDES)->id);

        $this->assertSame([], app(DeteksiInsidenService::class)->periksa());
        $this->assertDatabaseMissing('audit_log', ['aksi' => 'insiden_terdeteksi']);
    }

    public function test_percobaan_masuk_gagal_beruntun_dari_satu_ip_terdeteksi(): void
    {
        $this->catatJejak('login_gagal', DeteksiInsidenService::BATAS_GAGAL_MASUK + 1, null, '198.51.100.7');

        $temuan = app(DeteksiInsidenService::class)->periksa();

        $this->assertCount(1, $temuan);
        $this->assertSame('login_gagal', $temuan[0]['indikasi']);
        $this->assertSame('198.51.100.7', $temuan[0]['nilai']);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'insiden_terdeteksi']);
    }

    public function test_pengunduhan_data_pribadi_berlebihan_memicu_peringatan_ke_administrator(): void
    {
        $admin = $this->buatPengguna(Role::ADMIN, ['email' => 'admin@desa.test']);
        $pelaku = $this->buatPengguna(Role::OPERATOR);

        $this->catatJejak('unduh_data_pribadi', DeteksiInsidenService::BATAS_UNDUH_DATA_PRIBADI + 5, $pelaku->id);

        $temuan = app(DeteksiInsidenService::class)->periksa();

        $this->assertCount(1, $temuan);
        $this->assertSame((string) $pelaku->id, $temuan[0]['nilai']);

        // Administrator diberi tahu agar tenggat pelaporan 3x24 jam dapat dipenuhi.
        $this->assertDatabaseHas('notifikasi_log', ['tujuan' => $admin->email, 'templat' => 'insiden_keamanan']);
        $this->assertNotEmpty(NotifikasiLog::where('templat', 'insiden_keamanan')->first()->data);
    }

    public function test_ekspor_laporan_massal_terdeteksi_dan_tercatat_pada_audit(): void
    {
        $sekdes = $this->buatPengguna(Role::SEKDES);

        // Satu ekspor wajar tidak memicu apa pun.
        $this->actingAs($sekdes)->get('/api/v1/admin/permohonan/laporan/csv')->assertOk();
        $this->assertDatabaseHas('audit_log', ['aksi' => 'ekspor_laporan', 'aktor_id' => $sekdes->id]);
        $this->assertSame([], app(DeteksiInsidenService::class)->periksa());

        $this->catatJejak('ekspor_laporan', DeteksiInsidenService::BATAS_EKSPOR_LAPORAN, $sekdes->id);

        $temuan = app(DeteksiInsidenService::class)->periksa();

        $this->assertCount(1, $temuan);
        $this->assertSame('ekspor_laporan', $temuan[0]['indikasi']);
    }

    public function test_jejak_di_luar_jendela_pengamatan_diabaikan(): void
    {
        $this->catatJejak(
            'login_gagal',
            DeteksiInsidenService::BATAS_GAGAL_MASUK + 10,
            null,
            '198.51.100.7',
            DeteksiInsidenService::JENDELA_JAM + 2,
        );

        $this->assertSame([], app(DeteksiInsidenService::class)->periksa());
    }

    public function test_perintah_pemantauan_dapat_dijalankan_penjadwal(): void
    {
        $this->catatJejak('ubah_peran', DeteksiInsidenService::BATAS_UBAH_PERAN + 1, $this->buatPengguna(Role::ADMIN)->id);

        $this->artisan('sidesa:pantau-anomali')
            ->expectsOutputToContain('Perubahan peran akun')
            ->assertExitCode(0);
    }
}
