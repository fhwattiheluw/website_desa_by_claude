<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Konten;
use App\Models\Permohonan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** REQ-F-USR-012, REQ-F-KNT-014, REQ-F-ADM-005; BR-03, BR-13. */
class OtorisasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
        Storage::fake('local');
    }

    public function test_warga_tidak_dapat_membuka_panel_administrasi(): void
    {
        $this->actingAs($this->buatWarga())
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('izin_dibutuhkan.0', 'dashboard.lihat');
    }

    public function test_operator_tidak_dapat_menyetujui_atau_menandatangani(): void
    {
        $warga = $this->buatWarga();
        $operator = $this->buatPengguna('operator');
        $layanan = $this->layanan('SKD');

        $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'data_formulir' => $this->dataFormulir($layanan),
        ])->assertCreated();

        $permohonan = Permohonan::firstOrFail();

        $this->actingAs($operator)->postJson("/api/v1/admin/permohonan/{$permohonan->id}/verifikasi")->assertOk();
        $this->actingAs($operator)->postJson("/api/v1/admin/permohonan/{$permohonan->id}/setujui")->assertForbidden();
        $this->actingAs($operator)->postJson("/api/v1/admin/permohonan/{$permohonan->id}/tanda-tangani")->assertForbidden();
    }

    /** BR-03: petugas loket tidak boleh menyetujui permohonan buatannya sendiri. */
    public function test_pembuat_permohonan_loket_tidak_dapat_menyetujuinya(): void
    {
        $warga = $this->buatWarga();
        $sekdes = $this->buatPengguna('sekdes');
        $verifikator = $this->buatPengguna('verifikator');
        $layanan = $this->layanan('SKD');

        $this->actingAs($sekdes)->postJson('/api/v1/admin/permohonan/loket', [
            'pemohon_id' => $warga->id,
            'layanan' => $layanan->slug,
            'data_formulir' => $this->dataFormulir($layanan),
        ])->assertCreated();

        $permohonan = Permohonan::firstOrFail();
        $this->assertSame('loket', $permohonan->kanal);

        $this->actingAs($verifikator)->postJson("/api/v1/admin/permohonan/{$permohonan->id}/verifikasi")->assertOk();

        $this->actingAs($sekdes)->postJson("/api/v1/admin/permohonan/{$permohonan->id}/setujui")
            ->assertStatus(422)
            ->assertJsonPath('kode', 'ALUR_TIDAK_VALID');

        $this->actingAs($verifikator)->postJson("/api/v1/admin/permohonan/{$permohonan->id}/setujui")->assertOk();
    }

    /** REQ-F-KNT-014: penulis tidak boleh menerbitkan tulisannya sendiri. */
    public function test_penulis_tidak_dapat_menerbitkan_kontennya_sendiri(): void
    {
        $verifikator = $this->buatPengguna('verifikator');

        $this->actingAs($verifikator)->postJson('/api/v1/admin/konten', [
            'tipe' => 'berita',
            'judul' => 'Perbaikan Jalan Dusun Mekar Dimulai Pekan Ini',
            'isi' => '<p>Isi berita.</p>',
        ])->assertCreated();

        $konten = Konten::firstOrFail();

        $this->actingAs($verifikator)
            ->postJson("/api/v1/admin/konten/{$konten->id}/status", ['status' => 'terbit'])
            ->assertForbidden();

        $peninjau = $this->buatPengguna('sekdes');

        $this->actingAs($peninjau)
            ->postJson("/api/v1/admin/konten/{$konten->id}/status", ['status' => 'terbit'])
            ->assertOk();

        $this->assertSame('terbit', $konten->refresh()->status);
        $this->assertNotNull($konten->terbit_pada);
    }

    public function test_operator_tidak_dapat_menerbitkan_konten_karena_tanpa_izin(): void
    {
        $operator = $this->buatPengguna('operator');

        $this->actingAs($operator)->postJson('/api/v1/admin/konten', [
            'tipe' => 'berita',
            'judul' => 'Kegiatan Posyandu Bulan Ini Berjalan Lancar',
            'isi' => '<p>Isi.</p>',
        ])->assertCreated();

        $this->actingAs($operator)
            ->postJson('/api/v1/admin/konten/'.Konten::firstOrFail()->id.'/status', ['status' => 'terbit'])
            ->assertForbidden();
    }

    /** BR-13 & REQ-F-ADM-004: perubahan peran hanya oleh admin dan selalu tercatat. */
    public function test_perubahan_peran_hanya_oleh_admin_dan_tercatat_di_audit_log(): void
    {
        $warga = $this->buatWarga();

        $this->actingAs($this->buatPengguna('sekdes'))
            ->postJson("/api/v1/admin/pengguna/{$warga->id}/peran", ['role' => 'operator'])
            ->assertForbidden();

        $this->actingAs($this->buatPengguna('admin'))
            ->postJson("/api/v1/admin/pengguna/{$warga->id}/peran", ['role' => 'operator'])
            ->assertOk();

        $this->assertSame('operator', $warga->refresh()->role->kode);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'ubah_peran', 'entitas' => 'User', 'entitas_id' => $warga->id]);
    }

    /** REQ-F-ADM-005: audit log tidak dapat diubah maupun dihapus. */
    public function test_audit_log_bersifat_hanya_baca(): void
    {
        $admin = $this->buatPengguna('admin');

        $this->actingAs($admin)->getJson('/api/v1/admin/audit-log')->assertOk();

        $log = AuditLog::create([
            'aktor_id' => $admin->id, 'aksi' => 'login', 'entitas' => 'User', 'created_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $log->update(['aksi' => 'diubah']);
    }

    public function test_akun_nonaktif_kehilangan_akses_seketika(): void
    {
        $operator = $this->buatPengguna('operator');
        $admin = $this->buatPengguna('admin');

        $this->actingAs($operator)->getJson('/api/v1/admin/dashboard')->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/pengguna/{$operator->id}/status", ['status_akun' => 'nonaktif'])
            ->assertOk();

        $this->actingAs($operator->refresh())->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }
}
