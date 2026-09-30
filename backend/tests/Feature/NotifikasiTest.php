<?php

namespace Tests\Feature;

use App\Models\NotifikasiLog;
use App\Models\NotifikasiPetugas;
use App\Models\Permohonan;
use App\Models\Role;
use App\Models\User;
use App\Services\PermohonanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * REQ-F-NOT-005: lonceng pekerjaan baru bagi petugas.
 * REQ-F-NOT-006: preferensi kanal notifikasi bagi warga.
 */
class NotifikasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    private function ajukan(?User $warga = null): Permohonan
    {
        $warga ??= $this->buatWarga();
        $layanan = $this->layanan('SKTM');

        // Jalur pengajuan mandiri warga: kanal daring, tanpa petugas loket.
        return app(PermohonanService::class)->buat($warga, $layanan, $this->dataFormulir($layanan));
    }

    public function test_permohonan_baru_membunyikan_lonceng_verifikator_saja(): void
    {
        $operator = $this->buatPengguna(Role::OPERATOR);   // punya permohonan.verifikasi
        $kades = $this->buatPengguna(Role::KADES);         // tidak punya permohonan.verifikasi
        $warga = $this->buatWarga();

        $this->ajukan($warga);

        $this->assertSame(1, NotifikasiPetugas::where('pengguna_id', $operator->id)->count());
        $this->assertSame(0, NotifikasiPetugas::where('pengguna_id', $kades->id)->count());
        // Warga bukan petugas; loncengnya tidak berlaku baginya.
        $this->assertSame(0, NotifikasiPetugas::where('pengguna_id', $warga->id)->count());
    }

    public function test_giliran_kerja_berpindah_ke_peran_berikutnya(): void
    {
        $operator = $this->buatPengguna(Role::OPERATOR);
        $verifikator = $this->buatPengguna(Role::VERIFIKATOR);  // permohonan.setujui
        $kades = $this->buatPengguna(Role::KADES);              // permohonan.tanda_tangan

        $permohonan = $this->ajukan();
        $layanan = app(PermohonanService::class);

        $layanan->transisi($permohonan, Permohonan::DIVERIFIKASI, $operator);
        $this->assertSame(
            'Permohonan menunggu persetujuan',
            NotifikasiPetugas::where('pengguna_id', $verifikator->id)->latest('id')->first()?->judul,
        );

        $layanan->transisi($permohonan->refresh(), Permohonan::DISETUJUI, $verifikator);
        $this->assertSame(
            'Permohonan menunggu tanda tangan',
            NotifikasiPetugas::where('pengguna_id', $kades->id)->latest('id')->first()?->judul,
        );
    }

    public function test_status_akhir_tidak_menyisakan_pekerjaan_sehingga_tidak_berbunyi(): void
    {
        $operator = $this->buatPengguna(Role::OPERATOR);
        $permohonan = $this->ajukan();

        $sebelum = NotifikasiPetugas::count();

        app(PermohonanService::class)->transisi(
            $permohonan,
            Permohonan::DITOLAK,
            $operator,
            'Berkas persyaratan tidak dapat dibaca dan tidak diperbaiki.',
        );

        $this->assertSame($sebelum, NotifikasiPetugas::count());
    }

    public function test_petugas_hanya_melihat_dan_membaca_notifikasinya_sendiri(): void
    {
        $operator = $this->buatPengguna(Role::OPERATOR);
        $lain = $this->buatPengguna(Role::OPERATOR);
        $this->ajukan();

        $jawab = $this->actingAs($operator)->getJson('/api/v1/admin/notifikasi')->assertOk();
        $this->assertSame(1, $jawab->json('jumlah_belum_dibaca'));
        $this->assertCount(1, $jawab->json('data'));

        $milikLain = NotifikasiPetugas::where('pengguna_id', $lain->id)->firstOrFail();

        $this->actingAs($operator)
            ->postJson("/api/v1/admin/notifikasi/{$milikLain->id}/baca")
            ->assertNotFound();

        $this->assertNull($milikLain->refresh()->dibaca_pada);
    }

    public function test_menandai_terbaca_menurunkan_jumlah_belum_dibaca(): void
    {
        $operator = $this->buatPengguna(Role::OPERATOR);
        $this->ajukan();
        $this->ajukan();

        $milik = NotifikasiPetugas::where('pengguna_id', $operator->id)->firstOrFail();

        $this->actingAs($operator)->postJson("/api/v1/admin/notifikasi/{$milik->id}/baca")->assertOk();
        $this->actingAs($operator)->getJson('/api/v1/admin/notifikasi')
            ->assertOk()->assertJsonPath('jumlah_belum_dibaca', 1);

        $this->actingAs($operator)->postJson('/api/v1/admin/notifikasi/baca-semua')->assertOk();
        $this->actingAs($operator)->getJson('/api/v1/admin/notifikasi')
            ->assertOk()->assertJsonPath('jumlah_belum_dibaca', 0);
    }

    public function test_warga_dapat_mematikan_kanal_whatsapp(): void
    {
        $warga = $this->buatWarga(['telepon' => '081234567890']);

        $this->actingAs($warga)->putJson('/api/v1/auth/preferensi-notifikasi', [
            'email' => true,
            'whatsapp' => false,
            'pengumuman' => true,
        ])->assertOk()->assertJsonPath('preferensi.whatsapp', false);

        $this->ajukan($warga);

        $this->assertSame(1, NotifikasiLog::where('kanal', 'email')->count());
        $this->assertSame(0, NotifikasiLog::where('kanal', 'whatsapp')->count());
    }

    public function test_pemberitahuan_transaksional_tetap_sampai_meski_semua_kanal_dimatikan(): void
    {
        $warga = $this->buatWarga(['telepon' => '081234567890']);

        $this->actingAs($warga)->putJson('/api/v1/auth/preferensi-notifikasi', [
            'email' => false,
            'whatsapp' => false,
            'pengumuman' => false,
        ])->assertOk();

        $this->ajukan($warga);

        // Warga yang mematikan semuanya lalu tidak pernah tahu suratnya selesai
        // bukan pilihan yang benar-benar ia maksud.
        $this->assertSame(1, NotifikasiLog::where('kanal', 'email')->count());
        $this->assertSame(0, NotifikasiLog::where('kanal', 'whatsapp')->count());
    }

    public function test_preferensi_bawaan_menyalakan_seluruh_kanal(): void
    {
        $warga = $this->buatWarga();

        $this->actingAs($warga)->getJson('/api/v1/auth/preferensi-notifikasi')
            ->assertOk()
            ->assertJson(['preferensi' => User::PREFERENSI_BAWAAN]);
    }

    public function test_perubahan_preferensi_tercatat_pada_audit(): void
    {
        $warga = $this->buatWarga();

        $this->actingAs($warga)->putJson('/api/v1/auth/preferensi-notifikasi', [
            'email' => true, 'whatsapp' => false, 'pengumuman' => false,
        ])->assertOk();

        $this->assertDatabaseHas('audit_log', [
            'aksi' => 'ubah_preferensi_notifikasi',
            'entitas_id' => (string) $warga->id,
        ]);
    }
}
