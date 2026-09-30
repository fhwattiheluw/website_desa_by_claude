<?php

namespace Tests\Feature;

use App\Models\Permohonan;
use App\Models\Role;
use App\Models\SuratTerbit;
use App\Models\User;
use App\Services\SuratService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** REQ-F-SRT-002..022; BR-01..BR-05. */
class PermohonanSuratTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function ajukan(User $warga, string $kode = 'SKTM'): Permohonan
    {
        $layanan = $this->layanan($kode);

        $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'data_formulir' => $this->dataFormulir($layanan),
        ])->assertCreated();

        return Permohonan::latest('id')->firstOrFail();
    }

    public function test_warga_terverifikasi_dapat_mengajukan_dan_memperoleh_nomor_tiket(): void
    {
        $warga = $this->buatWarga();
        $layanan = $this->layanan('SKTM');

        $respons = $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'data_formulir' => $this->dataFormulir($layanan),
            'lampiran' => [UploadedFile::fake()->image('ktp.jpg', 800, 500)],
        ])->assertCreated();

        $permohonan = Permohonan::firstOrFail();

        $this->assertSame(Permohonan::DIAJUKAN, $permohonan->status);
        $this->assertMatchesRegularExpression('#^DESA/SKTM/\d{6}/\d{5}$#', $permohonan->nomor_tiket);
        $this->assertNotNull($permohonan->tenggat_sla);
        $this->assertCount(1, $permohonan->lampiran);
        $this->assertTrue($permohonan->lampiran->first()->media->privat, 'Lampiran identitas wajib disimpan privat.');
        $respons->assertJsonPath('data.status', Permohonan::DIAJUKAN);
    }

    public function test_warga_belum_terverifikasi_tidak_dapat_mengajukan(): void
    {
        $warga = $this->buatWarga(['status_akun' => User::BELUM_VERIFIKASI, 'verifikasi_nik_at' => null]);
        $layanan = $this->layanan('SKTM');

        $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'data_formulir' => $this->dataFormulir($layanan),
        ])->assertStatus(422)->assertJsonPath('kode', 'ALUR_TIDAK_VALID');

        $this->assertDatabaseCount('permohonan', 0);
    }

    public function test_formulir_dengan_nik_tidak_valid_ditolak(): void
    {
        $warga = $this->buatWarga();
        $layanan = $this->layanan('SKTM');

        $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'data_formulir' => [...$this->dataFormulir($layanan), 'nik' => '123'],
        ])->assertStatus(422)->assertJsonValidationErrors('data_formulir.nik');
    }

    public function test_pengajuan_ganda_untuk_layanan_yang_sama_dicegah(): void
    {
        $warga = $this->buatWarga();
        $this->ajukan($warga);

        $layanan = $this->layanan('SKTM');

        $this->actingAs($warga)->postJson('/api/v1/permohonan', [
            'layanan' => $layanan->slug,
            'data_formulir' => $this->dataFormulir($layanan),
        ])->assertStatus(422);

        $this->assertDatabaseCount('permohonan', 1);
    }

    public function test_alur_lengkap_sampai_surat_terbit(): void
    {
        $warga = $this->buatWarga();
        $operator = $this->buatPengguna('operator');
        $sekdes = $this->buatPengguna('sekdes');
        $kades = $this->buatPengguna('kades');

        $permohonan = $this->ajukan($warga);

        $this->actingAs($operator)->postJson("/api/v1/admin/permohonan/{$permohonan->id}/verifikasi")->assertOk();
        $this->assertSame(Permohonan::DIVERIFIKASI, $permohonan->refresh()->status);

        $this->actingAs($sekdes)->postJson("/api/v1/admin/permohonan/{$permohonan->id}/setujui")->assertOk();
        $this->assertSame(Permohonan::DISETUJUI, $permohonan->refresh()->status);

        $this->actingAs($kades)->postJson("/api/v1/admin/permohonan/{$permohonan->id}/tanda-tangani")
            ->assertOk()
            ->assertJsonStructure(['nomor_surat', 'kode_verifikasi']);

        $permohonan->refresh();
        $surat = SuratTerbit::firstOrFail();

        $this->assertSame(Permohonan::SELESAI, $permohonan->status);
        $this->assertMatchesRegularExpression('#^\d{3}/SKTM/[IVX]+/\d{4}$#', $surat->nomor_surat);
        $this->assertTrue(Storage::disk(SuratService::DISK)->exists($surat->path_pdf));
        $this->assertSame(64, strlen($surat->hash_dokumen));

        // Seluruh transisi status terekam (REQ-F-SRT-021).
        $this->assertSame(
            ['diajukan', 'diverifikasi', 'disetujui', 'ditandatangani', 'selesai'],
            $permohonan->riwayat()->pluck('ke')->all(),
        );
        $this->assertDatabaseHas('audit_log', ['entitas' => 'SuratTerbit', 'aksi' => 'terbit_surat']);
    }

    public function test_persetujuan_tanpa_verifikasi_ditolak(): void
    {
        $warga = $this->buatWarga();
        $sekdes = $this->buatPengguna('sekdes');
        $permohonan = $this->ajukan($warga);

        // BR-02: status disetujui hanya dapat dicapai dari diverifikasi.
        $this->actingAs($sekdes)->postJson("/api/v1/admin/permohonan/{$permohonan->id}/setujui")
            ->assertStatus(422);

        $this->assertSame(Permohonan::DIAJUKAN, $permohonan->refresh()->status);
    }

    public function test_pengembalian_wajib_disertai_alasan_memadai(): void
    {
        $warga = $this->buatWarga();
        $operator = $this->buatPengguna('operator');
        $permohonan = $this->ajukan($warga);

        $this->actingAs($operator)
            ->postJson("/api/v1/admin/permohonan/{$permohonan->id}/kembalikan", ['alasan' => 'kurang'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('alasan');

        $this->actingAs($operator)
            ->postJson("/api/v1/admin/permohonan/{$permohonan->id}/kembalikan", [
                'alasan' => 'Foto Kartu Keluarga tidak terbaca, mohon unggah ulang dengan pencahayaan cukup.',
            ])->assertOk();

        $this->assertSame(Permohonan::DIKEMBALIKAN, $permohonan->refresh()->status);
    }

    public function test_pemohon_dapat_memperbaiki_dan_mengirim_ulang(): void
    {
        $warga = $this->buatWarga();
        $operator = $this->buatPengguna('operator');
        $permohonan = $this->ajukan($warga);
        $layanan = $permohonan->jenisLayanan;

        $this->actingAs($operator)->postJson("/api/v1/admin/permohonan/{$permohonan->id}/kembalikan", [
            'alasan' => 'Berkas surat pengantar RT/RW belum dilampirkan pada permohonan ini.',
        ])->assertOk();

        $this->actingAs($warga)->postJson("/api/v1/permohonan/{$permohonan->id}/kirim-ulang", [
            'data_formulir' => [...$this->dataFormulir($layanan), 'pekerjaan' => 'Pedagang'],
        ])->assertOk();

        $permohonan->refresh();

        $this->assertSame(Permohonan::DIAJUKAN, $permohonan->status);
        $this->assertNull($permohonan->alasan);
        $this->assertSame('Pedagang', $permohonan->data_formulir['pekerjaan']);
    }

    public function test_warga_tidak_dapat_melihat_permohonan_milik_orang_lain(): void
    {
        $permohonan = $this->ajukan($this->buatWarga());

        $this->actingAs($this->buatWarga())
            ->getJson("/api/v1/permohonan/{$permohonan->id}")
            ->assertForbidden();
    }

    public function test_tautan_unduh_surat_berbatas_waktu_dan_bertanda_tangan(): void
    {
        $warga = $this->buatWarga();
        $permohonan = $this->prosesSampaiSelesai($warga);

        $tautan = $this->actingAs($warga)
            ->getJson("/api/v1/permohonan/{$permohonan->id}/tautan-surat")
            ->assertOk()
            ->json('tautan');

        $this->get($tautan)->assertOk()->assertHeader('content-type', 'application/pdf');

        // Tanpa tanda tangan yang sah, berkas tidak dapat diakses (REQ-NF-SEC-007).
        $this->get('/api/v1/surat/'.$permohonan->surat->id.'/unduh')->assertForbidden();
    }

    public function test_verifikasi_publik_menampilkan_status_keabsahan_tanpa_data_pribadi(): void
    {
        $permohonan = $this->prosesSampaiSelesai($this->buatWarga());
        $surat = $permohonan->surat;

        $respons = $this->getJson("/api/v1/surat/verifikasi/{$surat->kode_verifikasi}")
            ->assertOk()
            ->assertJsonPath('sah', true)
            ->assertJsonPath('nomor_surat', $surat->nomor_surat);

        $this->assertArrayNotHasKey('data_formulir', $respons->json());
        $this->assertArrayNotHasKey('nik', $respons->json());

        $this->getJson('/api/v1/surat/verifikasi/TIDAKADA')->assertNotFound();
    }

    public function test_surat_yang_dibatalkan_ditandai_tidak_berlaku(): void
    {
        $permohonan = $this->prosesSampaiSelesai($this->buatWarga());

        $this->actingAs($this->buatPengguna('sekdes'))
            ->postJson("/api/v1/admin/permohonan/{$permohonan->id}/batalkan-surat", [
                'alasan' => 'Terdapat kekeliruan penulisan nama pemohon pada dokumen yang telah terbit.',
            ])->assertOk();

        $this->getJson("/api/v1/surat/verifikasi/{$permohonan->surat->kode_verifikasi}")
            ->assertOk()
            ->assertJsonPath('sah', false)
            ->assertJsonPath('status_keabsahan', 'dibatalkan');
    }

    /** REQ-F-SRT-017: jabatan pada blok tanda tangan mengikuti peran penandatangan. */
    public function test_jabatan_penandatangan_mengikuti_peran(): void
    {
        $surat = app(SuratService::class);

        $this->assertSame(
            'Kepala Desa Sukamaju',
            $surat->jabatanPenandatangan($this->buatPengguna('kades')->load('role')),
        );

        $this->assertStringContainsString(
            'a.n. Kepala Desa Sukamaju',
            $surat->jabatanPenandatangan($this->buatPengguna('sekdes')->load('role')),
        );
    }

    private function prosesSampaiSelesai(User $warga): Permohonan
    {
        $permohonan = $this->ajukan($warga);

        $this->actingAs($this->buatPengguna('operator'))
            ->postJson("/api/v1/admin/permohonan/{$permohonan->id}/verifikasi")->assertOk();
        $this->actingAs($this->buatPengguna('sekdes'))
            ->postJson("/api/v1/admin/permohonan/{$permohonan->id}/setujui")->assertOk();
        $this->actingAs($this->buatPengguna('kades'))
            ->postJson("/api/v1/admin/permohonan/{$permohonan->id}/tanda-tangani")->assertOk();

        return $permohonan->fresh('surat');
    }

    /** REQ-F-SRT-023: pengunduhan massal surat yang telah terbit. */
    public function test_unduh_massal_membentuk_arsip_berisi_surat_terpilih(): void
    {
        $satu = $this->prosesSampaiSelesai($this->buatWarga());
        $dua = $this->prosesSampaiSelesai($this->buatWarga());

        $jawab = $this->actingAs($this->buatPengguna(Role::OPERATOR))
            ->post('/api/v1/admin/permohonan/unduh-massal', [
                'permohonan' => [$satu->id, $dua->id],
            ])->assertOk();

        $berkas = tempnam(sys_get_temp_dir(), 'uji');
        file_put_contents($berkas, $jawab->streamedContent());

        $arsip = new \ZipArchive;
        $this->assertTrue($arsip->open($berkas) === true);
        $this->assertSame(2, $arsip->numFiles);
        $this->assertStringStartsWith('%PDF', (string) $arsip->getFromIndex(0));
        $arsip->close();
        unlink($berkas);

        $this->assertDatabaseHas('audit_log', ['aksi' => 'unduh_massal_surat']);
    }

    /** Surat yang dibatalkan tidak boleh ikut terarsip (REQ-F-SRT-023). */
    public function test_unduh_massal_menolak_permohonan_tanpa_surat_sah(): void
    {
        $tanpaSurat = $this->ajukan($this->buatWarga());

        $this->actingAs($this->buatPengguna(Role::OPERATOR))
            ->post('/api/v1/admin/permohonan/unduh-massal', ['permohonan' => [$tanpaSurat->id]])
            ->assertStatus(422);
    }

    /** Warga tidak boleh mengunduh berkas permohonan orang lain (REQ-NF-SEC-004). */
    public function test_unduh_massal_tertutup_bagi_warga(): void
    {
        $permohonan = $this->prosesSampaiSelesai($this->buatWarga());

        $this->actingAs($this->buatWarga())
            ->postJson('/api/v1/admin/permohonan/unduh-massal', ['permohonan' => [$permohonan->id]])
            ->assertForbidden();
    }
}
