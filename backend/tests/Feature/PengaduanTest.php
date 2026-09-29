<?php

namespace Tests\Feature;

use App\Models\Pengaduan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** REQ-F-ADU-001..011; BR-10, BR-11. */
class PengaduanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    private function kirim(array $ubah = []): array
    {
        return $this->postJson('/api/v1/pengaduan', [
            'nama_pelapor' => 'Agus Priyanto',
            'kontak_pelapor' => 'agus@contoh.id',
            'kategori' => 'infrastruktur',
            'judul' => 'Lampu penerangan jalan mati',
            'uraian' => 'Lampu penerangan jalan di RT 003 RW 002 sudah mati sejak dua pekan lalu dan rawan kecelakaan.',
            'lokasi' => 'Jalan Dusun Mekar RT 003',
            ...$ubah,
        ])->assertCreated()->json();
    }

    public function test_warga_dapat_mengirim_pengaduan_dan_menerima_kode_lacak(): void
    {
        $hasil = $this->kirim();

        $this->assertMatchesRegularExpression('#^ADU/\d{6}/\d{5}$#', $hasil['nomor_tiket']);
        $this->assertNotEmpty($hasil['kode_lacak']);

        $pengaduan = Pengaduan::firstOrFail();

        $this->assertSame(Pengaduan::BARU, $pengaduan->status);
        $this->assertNotNull($pengaduan->tenggat_tanggapan);
        // Kontak pelapor tergolong data pribadi dan wajib tersimpan terenkripsi.
        $this->assertNotSame('agus@contoh.id', $pengaduan->getRawOriginal('kontak_pelapor'));
        $this->assertSame('agus@contoh.id', $pengaduan->kontak_pelapor);
    }

    public function test_pengaduan_anonim_tetap_mewajibkan_kanal_kontak(): void
    {
        $this->postJson('/api/v1/pengaduan', [
            'kategori' => 'pelayanan',
            'judul' => 'Antrean layanan terlalu lama',
            'uraian' => 'Antrean pelayanan surat di kantor desa memakan waktu lebih dari dua jam pada hari Senin.',
            'anonim' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('kontak_pelapor');

        $this->kirim(['anonim' => true, 'nama_pelapor' => null]);

        $this->assertTrue(Pengaduan::firstOrFail()->anonim);
    }

    public function test_pelacakan_publik_tidak_membocorkan_identitas_pelapor(): void
    {
        $kode = $this->kirim()['kode_lacak'];

        $respons = $this->getJson("/api/v1/pengaduan/lacak/{$kode}")->assertOk();

        $this->assertSame('Agus P.', $respons->json('data.pelapor'));
        $this->assertNull($respons->json('data.kontak_pelapor'));
        $this->assertNull($respons->json('data.kode_lacak'));
    }

    public function test_alur_penanganan_pengaduan_sampai_selesai(): void
    {
        $kode = $this->kirim()['kode_lacak'];
        $pengaduan = Pengaduan::firstOrFail();

        $verifikator = $this->buatPengguna('verifikator');
        $sekdes = $this->buatPengguna('sekdes');
        $operator = $this->buatPengguna('operator');

        $this->actingAs($verifikator)
            ->postJson("/api/v1/admin/pengaduan/{$pengaduan->id}/status", ['status' => Pengaduan::DIVERIFIKASI])
            ->assertOk();

        $this->actingAs($sekdes)
            ->postJson("/api/v1/admin/pengaduan/{$pengaduan->id}/disposisi", [
                'petugas_id' => $operator->id,
                'catatan' => 'Mohon ditindaklanjuti bersama petugas teknis.',
            ])->assertOk();

        $this->assertSame(Pengaduan::DIDISPOSISI, $pengaduan->refresh()->status);
        $this->assertSame($operator->id, $pengaduan->didisposisi_ke);

        $this->actingAs($verifikator)
            ->postJson("/api/v1/admin/pengaduan/{$pengaduan->id}/status", ['status' => Pengaduan::PROSES])
            ->assertOk();

        $this->actingAs($verifikator)
            ->postJson("/api/v1/admin/pengaduan/{$pengaduan->id}/tanggapan", [
                'isi' => 'Lampu penerangan jalan telah diganti pada hari Rabu dan kembali berfungsi normal.',
            ])->assertOk();

        $this->actingAs($verifikator)
            ->postJson("/api/v1/admin/pengaduan/{$pengaduan->id}/status", ['status' => Pengaduan::SELESAI])
            ->assertOk();

        $this->getJson("/api/v1/pengaduan/lacak/{$kode}")
            ->assertOk()
            ->assertJsonPath('data.status', Pengaduan::SELESAI)
            ->assertJsonCount(1, 'data.tanggapan');
    }

    public function test_transisi_status_yang_melompat_ditolak(): void
    {
        $this->kirim();
        $pengaduan = Pengaduan::firstOrFail();

        $this->actingAs($this->buatPengguna('verifikator'))
            ->postJson("/api/v1/admin/pengaduan/{$pengaduan->id}/status", ['status' => Pengaduan::SELESAI])
            ->assertStatus(422)
            ->assertJsonPath('kode', 'ALUR_TIDAK_VALID');
    }

    /** BR-10: penolakan wajib beralasan. */
    public function test_penolakan_pengaduan_wajib_beralasan(): void
    {
        $this->kirim();
        $pengaduan = Pengaduan::firstOrFail();
        $verifikator = $this->buatPengguna('verifikator');

        $this->actingAs($verifikator)
            ->postJson("/api/v1/admin/pengaduan/{$pengaduan->id}/status", [
                'status' => Pengaduan::DITOLAK, 'catatan' => 'spam',
            ])->assertStatus(422);

        $this->actingAs($verifikator)
            ->postJson("/api/v1/admin/pengaduan/{$pengaduan->id}/status", [
                'status' => Pengaduan::DITOLAK,
                'catatan' => 'Laporan memuat ujaran kebencian sehingga tidak dapat ditindaklanjuti.',
            ])->assertOk();

        $this->assertSame(Pengaduan::DITOLAK, $pengaduan->refresh()->status);
    }

    /** REQ-F-ADU-008 & BR-11: publikasi menyamarkan identitas pelapor. */
    public function test_pengaduan_publik_menyamarkan_pelapor(): void
    {
        $this->kirim();
        $pengaduan = Pengaduan::firstOrFail();
        $verifikator = $this->buatPengguna('verifikator');
        $sekdes = $this->buatPengguna('sekdes');

        $this->getJson('/api/v1/pengaduan/publik')->assertOk()->assertJsonCount(0, 'data');

        foreach ([Pengaduan::DIVERIFIKASI, Pengaduan::DIDISPOSISI, Pengaduan::PROSES] as $status) {
            $this->actingAs($verifikator)
                ->postJson("/api/v1/admin/pengaduan/{$pengaduan->id}/status", ['status' => $status])
                ->assertOk();
        }

        $this->actingAs($sekdes)
            ->postJson("/api/v1/admin/pengaduan/{$pengaduan->id}/publikasi", ['tampil_publik' => true])
            ->assertOk();

        $this->getJson('/api/v1/pengaduan/publik')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.pelapor', 'Agus P.');
    }

    public function test_formulir_publik_dibatasi_lajunya(): void
    {
        foreach (range(1, 3) as $percobaan) {
            $this->kirim(['judul' => "Laporan ke-{$percobaan} tentang jalan rusak"]);
        }

        $this->postJson('/api/v1/pengaduan', [
            'kontak_pelapor' => 'agus@contoh.id',
            'kategori' => 'infrastruktur',
            'judul' => 'Laporan keempat',
            'uraian' => 'Uraian laporan keempat yang seharusnya tertahan oleh pembatas laju permintaan.',
        ])->assertStatus(429);
    }
}
