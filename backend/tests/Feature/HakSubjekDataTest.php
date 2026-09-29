<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pengaduan;
use App\Models\PermintaanDataPribadi;
use App\Models\Permohonan;
use App\Models\Role;
use App\Models\User;
use App\Services\DataPribadiService;
use App\Services\RetensiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** REQ-F-USR-013 dan REQ-NF-CMP-004: hak subjek data serta kebijakan retensi. */
class HakSubjekDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    private function buatPengaduan(User $pelapor, array $ubah = []): Pengaduan
    {
        return Pengaduan::create([
            'nomor_tiket' => 'ADU/202609/'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'kode_lacak' => strtoupper(str()->random(8)),
            'pelapor_id' => $pelapor->id,
            'nama_pelapor' => $pelapor->name,
            'kontak_pelapor' => $pelapor->email,
            'kategori' => 'infrastruktur',
            'judul' => 'Jalan rusak',
            'uraian' => 'Jalan penghubung dusun berlubang dan menggenang setiap kali hujan turun.',
            'status' => Pengaduan::BARU,
            ...$ubah,
        ]);
    }

    public function test_warga_dapat_mengunduh_salinan_data_pribadinya(): void
    {
        $warga = $this->buatWarga(['name' => 'Rahmat Hidayat', 'nik' => '3203014507840003']);

        Permohonan::create([
            'nomor_tiket' => 'SKTM/202609/00001',
            'pemohon_id' => $warga->id,
            'jenis_layanan_id' => $this->layanan()->id,
            'data_formulir' => ['keperluan' => 'Beasiswa'],
            'status' => Permohonan::SELESAI,
            'kanal' => 'daring',
        ]);

        $jawab = $this->actingAs($warga)->get('/api/v1/auth/data-pribadi/unduh')->assertOk();
        $berkas = json_decode($jawab->streamedContent(), true);

        $this->assertSame('Rahmat Hidayat', $berkas['identitas']['nama']);
        // Pemilik data berhak melihat NIK-nya sendiri secara utuh.
        $this->assertSame('3203014507840003', $berkas['identitas']['nik']);
        $this->assertSame('SKTM/202609/00001', $berkas['permohonan_layanan'][0]['nomor_tiket']);
        $this->assertNotEmpty($berkas['catatan_retensi']);
        // Pengunduhan data pribadi sendiri pun meninggalkan jejak audit.
        $this->assertDatabaseHas('audit_log', ['aksi' => 'unduh_data_pribadi', 'entitas_id' => (string) $warga->id]);
    }

    public function test_berkas_unduhan_hanya_memuat_data_pemilik_akun(): void
    {
        $warga = $this->buatWarga();
        $orangLain = $this->buatWarga(['name' => 'Siti Aminah']);

        $this->buatPengaduan($orangLain, ['judul' => 'Milik orang lain']);

        $isi = $this->actingAs($warga)->get('/api/v1/auth/data-pribadi/unduh')->assertOk()->streamedContent();

        $this->assertStringNotContainsString('Siti Aminah', $isi);
        $this->assertStringNotContainsString('Milik orang lain', $isi);
    }

    public function test_tamu_tidak_dapat_mengunduh_data_pribadi(): void
    {
        $this->getJson('/api/v1/auth/data-pribadi/unduh')->assertUnauthorized();
    }

    public function test_permintaan_penghapusan_perlu_konfirmasi_dan_tidak_boleh_ganda(): void
    {
        $warga = $this->buatWarga();

        $this->actingAs($warga)
            ->postJson('/api/v1/auth/data-pribadi/penghapusan', ['alasan' => 'Pindah domisili'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('konfirmasi');

        $this->actingAs($warga)->postJson('/api/v1/auth/data-pribadi/penghapusan', [
            'alasan' => 'Pindah domisili',
            'konfirmasi' => true,
        ])->assertCreated();

        $this->actingAs($warga)->postJson('/api/v1/auth/data-pribadi/penghapusan', [
            'konfirmasi' => true,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('permintaan_data_pribadi', 1);
        $this->assertNotNull(PermintaanDataPribadi::first()->tenggat_jawaban);
    }

    public function test_petugas_tanpa_izin_tidak_dapat_menangani_permintaan(): void
    {
        $warga = $this->buatWarga();
        $permintaan = app(DataPribadiService::class)->ajukanPenghapusan($warga, null);

        $this->actingAs($this->buatPengguna(Role::OPERATOR))
            ->getJson('/api/v1/admin/permintaan-data')
            ->assertForbidden();

        $this->actingAs($this->buatPengguna(Role::OPERATOR))
            ->postJson("/api/v1/admin/permintaan-data/{$permintaan->id}/tindak", ['keputusan' => 'setujui'])
            ->assertForbidden();
    }

    public function test_persetujuan_menghapus_data_pribadi_namun_menyimpan_arsip_surat(): void
    {
        $warga = $this->buatWarga(['name' => 'Rahmat Hidayat', 'email' => 'rahmat@contoh.id']);

        $draf = Permohonan::create([
            'nomor_tiket' => 'SKTM/202609/00002',
            'pemohon_id' => $warga->id,
            'jenis_layanan_id' => $this->layanan()->id,
            'data_formulir' => ['keperluan' => 'Beasiswa'],
            'status' => Permohonan::DRAF,
            'kanal' => 'daring',
        ]);

        $selesai = Permohonan::create([
            'nomor_tiket' => 'SKTM/202609/00003',
            'pemohon_id' => $warga->id,
            'jenis_layanan_id' => $this->layanan()->id,
            'data_formulir' => ['keperluan' => 'Bantuan'],
            'status' => Permohonan::SELESAI,
            'kanal' => 'daring',
        ]);

        $pengaduan = $this->buatPengaduan($warga, [
            'nama_pelapor' => 'Rahmat Hidayat',
            'kontak_pelapor' => 'rahmat@contoh.id',
        ]);

        $permintaan = app(DataPribadiService::class)->ajukanPenghapusan($warga, 'Tidak lagi berdomisili di desa');

        $this->actingAs($this->buatPengguna(Role::SEKDES))
            ->postJson("/api/v1/admin/permintaan-data/{$permintaan->id}/tindak", ['keputusan' => 'setujui'])
            ->assertOk()
            ->assertJson(['status' => PermintaanDataPribadi::DISETUJUI]);

        $warga->refresh();

        $this->assertSame(DataPribadiService::NAMA_ANONIM, $warga->name);
        $this->assertNull($warga->nik);
        $this->assertNull($warga->getRawOriginal('nik_hash'));
        $this->assertNull($warga->telepon);
        $this->assertSame(User::NONAKTIF, $warga->status_akun);
        $this->assertNotSame('rahmat@contoh.id', $warga->email);

        // Identitas pelapor terhapus, isi pengaduan tetap dapat ditindaklanjuti.
        $pengaduan->refresh();
        $this->assertNull($pengaduan->nama_pelapor);
        $this->assertSame(DataPribadiService::KONTAK_ANONIM, $pengaduan->kontak_pelapor);
        $this->assertTrue($pengaduan->anonim);

        // Draf tidak punya nilai arsip; surat yang sudah terbit wajib disimpan.
        $this->assertDatabaseMissing('permohonan', ['id' => $draf->id]);
        $this->assertDatabaseHas('permohonan', ['id' => $selesai->id]);

        $this->assertDatabaseHas('audit_log', ['aksi' => 'setujui_hapus_data']);
        // Seluruh sesi warga dicabut agar akun tidak lagi dapat dipakai.
        $this->assertSame(0, $warga->tokens()->count());
    }

    public function test_penolakan_wajib_beralasan_dan_tidak_mengubah_data(): void
    {
        $warga = $this->buatWarga(['name' => 'Rahmat Hidayat']);
        $permintaan = app(DataPribadiService::class)->ajukanPenghapusan($warga, null);
        $sekdes = $this->buatPengguna(Role::SEKDES);

        $this->actingAs($sekdes)
            ->postJson("/api/v1/admin/permintaan-data/{$permintaan->id}/tindak", ['keputusan' => 'tolak'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('catatan');

        $this->actingAs($sekdes)->postJson("/api/v1/admin/permintaan-data/{$permintaan->id}/tindak", [
            'keputusan' => 'tolak',
            'catatan' => 'Masih terdapat permohonan layanan yang berjalan atas nama Anda.',
        ])->assertOk();

        $this->assertSame('Rahmat Hidayat', $warga->refresh()->name);
        $this->assertSame(PermintaanDataPribadi::DITOLAK, $permintaan->refresh()->status);

        // Permintaan yang sudah ditindak tidak dapat ditindak ulang.
        $this->actingAs($sekdes)->postJson("/api/v1/admin/permintaan-data/{$permintaan->id}/tindak", [
            'keputusan' => 'setujui',
        ])->assertUnprocessable();
    }

    public function test_warga_melihat_status_permintaannya(): void
    {
        $warga = $this->buatWarga();
        app(DataPribadiService::class)->ajukanPenghapusan($warga, 'Alasan pribadi');

        $this->actingAs($warga)->getJson('/api/v1/auth/data-pribadi')
            ->assertOk()
            ->assertJson([
                'ada_permintaan_tertunda' => true,
                'tenggat_jawaban_hari' => DataPribadiService::TENGGAT_JAWABAN_HARI,
            ])
            ->assertJsonPath('data.0.alasan', 'Alasan pribadi');
    }

    public function test_jejak_audit_melewati_24_bulan_dihapus(): void
    {
        $warga = $this->buatWarga();

        DB::table('audit_log')->insert([
            ['aktor_id' => $warga->id, 'aksi' => 'login', 'entitas' => 'User', 'created_at' => now()->subMonths(25)],
            ['aktor_id' => $warga->id, 'aksi' => 'login', 'entitas' => 'User', 'created_at' => now()->subMonths(23)],
        ]);

        $dihapus = app(RetensiService::class)->bersihkanAuditLog();

        $this->assertSame(1, $dihapus);
        $this->assertSame(0, AuditLog::where('created_at', '<', now()->subMonths(24))->count());
        // Pemangkasan itu sendiri tercatat agar penghapusan tetap dapat diperiksa.
        $this->assertDatabaseHas('audit_log', ['aksi' => 'retensi_audit_log']);
    }

    public function test_akun_tidak_aktif_36_bulan_ditandai_untuk_ditinjau(): void
    {
        $lama = $this->buatWarga(['last_login_at' => now()->subMonths(37)]);
        $baru = $this->buatWarga(['last_login_at' => now()->subMonths(10)]);
        $petugas = $this->buatPengguna(Role::OPERATOR, ['last_login_at' => now()->subMonths(40)]);

        $ditandai = app(RetensiService::class)->tinjauAkunTidakAktif();

        $this->assertSame(1, $ditandai);
        $this->assertNotNull($lama->refresh()->tinjauan_akun_pada);
        $this->assertNull($baru->refresh()->tinjauan_akun_pada);
        // Akun petugas dikelola administrator, bukan oleh penjadwal ini.
        $this->assertNull($petugas->refresh()->tinjauan_akun_pada);
        // Status akun tidak diubah otomatis; keputusan tetap pada petugas.
        $this->assertSame(User::AKTIF, $lama->status_akun);

        // Penjadwal bulanan tidak menandai akun yang sama berulang kali.
        $this->assertSame(0, app(RetensiService::class)->tinjauAkunTidakAktif());
    }
}
