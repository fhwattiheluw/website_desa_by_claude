<?php

namespace Tests\Feature;

use App\Models\KeberatanInformasi;
use App\Models\Komentar;
use App\Models\Konten;
use App\Models\PermohonanInformasi;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * REQ-F-KNT-013: komentar wajib dimoderasi sebelum tayang.
 * REQ-F-PID-007: keberatan atas penolakan permohonan informasi publik.
 */
class PartisipasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    private function artikel(): Konten
    {
        return Konten::create([
            'tipe' => 'artikel',
            'judul' => 'Membaca APBDes Desa Sendiri',
            'slug' => 'membaca-apbdes-desa-sendiri',
            'ringkasan' => 'Panduan singkat membaca dokumen anggaran desa.',
            'isi' => '<p>Isi artikel.</p>',
            'status' => 'terbit',
            'terbit_pada' => now()->subDay(),
        ]);
    }

    private function permohonanInformasi(array $ubah = []): PermohonanInformasi
    {
        return PermohonanInformasi::create([
            'nomor_tiket' => 'PPID/202609/00001',
            'kode_lacak' => 'UJIKODE1',
            'nama' => 'Siti Aminah',
            'kontak' => 'siti@contoh.id',
            'informasi_diminta' => 'Salinan laporan realisasi APBDes tahun anggaran berjalan.',
            'status' => 'diajukan',
            'tenggat_jawaban' => now()->addDays(10),
            ...$ubah,
        ]);
    }

    public function test_komentar_baru_tidak_pernah_langsung_tayang(): void
    {
        $artikel = $this->artikel();

        $this->postJson("/api/v1/konten/artikel/{$artikel->slug}/komentar", [
            'nama' => 'Agus Priyanto',
            'isi' => 'Terima kasih, penjelasannya membantu saya memahami pos belanja desa.',
        ])->assertCreated();

        $this->assertSame(Komentar::MENUNGGU, Komentar::firstOrFail()->status);

        // Laman publik hanya menampilkan yang sudah lolos moderasi.
        $this->getJson("/api/v1/konten/artikel/{$artikel->slug}/komentar")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_komentar_tayang_setelah_disetujui_petugas(): void
    {
        $artikel = $this->artikel();
        $komentar = $artikel->komentar()->create([
            'nama' => 'Agus Priyanto',
            'isi' => 'Terima kasih, penjelasannya membantu.',
            'status' => Komentar::MENUNGGU,
        ]);

        $this->actingAs($this->buatPengguna(Role::OPERATOR))
            ->postJson("/api/v1/admin/komentar/{$komentar->id}/moderasi", ['keputusan' => 'setujui'])
            ->assertOk();

        $this->getJson("/api/v1/konten/artikel/{$artikel->slug}/komentar")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Agus Priyanto');
    }

    public function test_penolakan_komentar_wajib_beralasan_dan_tetap_tersembunyi(): void
    {
        $artikel = $this->artikel();
        $komentar = $artikel->komentar()->create([
            'nama' => 'Anonim',
            'isi' => 'Komentar yang tidak layak tayang.',
            'status' => Komentar::MENUNGGU,
        ]);
        $operator = $this->buatPengguna(Role::OPERATOR);

        $this->actingAs($operator)
            ->postJson("/api/v1/admin/komentar/{$komentar->id}/moderasi", ['keputusan' => 'tolak'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('alasan');

        $this->actingAs($operator)->postJson("/api/v1/admin/komentar/{$komentar->id}/moderasi", [
            'keputusan' => 'tolak',
            'alasan' => 'Memuat serangan pribadi terhadap perangkat desa.',
        ])->assertOk();

        $this->assertSame(Komentar::DITOLAK, $komentar->refresh()->status);
        $this->getJson("/api/v1/konten/artikel/{$artikel->slug}/komentar")->assertJsonCount(0, 'data');
    }

    public function test_alamat_ip_pengomentar_tidak_pernah_tampil_publik(): void
    {
        $artikel = $this->artikel();

        $this->postJson("/api/v1/konten/artikel/{$artikel->slug}/komentar", [
            'nama' => 'Agus Priyanto',
            'isi' => 'Terima kasih, penjelasannya membantu saya memahami pos belanja desa.',
        ])->assertCreated();

        $komentar = Komentar::firstOrFail();
        $komentar->update(['status' => Komentar::DISETUJUI, 'dimoderasi_pada' => now()]);

        // Alamat IP dicatat untuk membatasi penyalahgunaan, bukan untuk dibaca siapa pun.
        $this->assertNotNull($komentar->alamat_ip);
        $isi = $this->getJson("/api/v1/konten/artikel/{$artikel->slug}/komentar")->getContent();
        $this->assertStringNotContainsString($komentar->alamat_ip, $isi);
    }

    public function test_komentar_hanya_pada_berita_dan_artikel(): void
    {
        $pengumuman = Konten::create([
            'tipe' => 'pengumuman',
            'judul' => 'Pengumuman Resmi',
            'slug' => 'pengumuman-resmi',
            'isi' => '<p>Isi.</p>',
            'status' => 'terbit',
            'terbit_pada' => now()->subDay(),
        ]);

        $this->postJson("/api/v1/konten/pengumuman/{$pengumuman->slug}/komentar", [
            'nama' => 'Agus', 'isi' => 'Komentar pada pengumuman resmi desa.',
        ])->assertNotFound();
    }

    public function test_keberatan_belum_dapat_diajukan_sebelum_permohonan_dijawab(): void
    {
        $permohonan = $this->permohonanInformasi();

        $this->getJson("/api/v1/permohonan-informasi/lacak/{$permohonan->kode_lacak}")
            ->assertOk()
            ->assertJsonPath('dapat_mengajukan_keberatan', false);

        $this->postJson("/api/v1/permohonan-informasi/lacak/{$permohonan->kode_lacak}/keberatan", [
            'alasan' => 'Saya keberatan karena permohonan belum juga dijawab hingga hari ini.',
        ])->assertUnprocessable();
    }

    public function test_keberatan_dapat_diajukan_setelah_permohonan_ditolak(): void
    {
        $permohonan = $this->permohonanInformasi(['status' => 'ditolak', 'jawaban' => 'Termasuk informasi dikecualikan.']);

        $this->getJson("/api/v1/permohonan-informasi/lacak/{$permohonan->kode_lacak}")
            ->assertOk()
            ->assertJsonPath('dapat_mengajukan_keberatan', true);

        $this->postJson("/api/v1/permohonan-informasi/lacak/{$permohonan->kode_lacak}/keberatan", [
            'alasan' => 'Informasi yang saya minta adalah dokumen anggaran yang wajib tersedia setiap saat.',
        ])->assertCreated();

        $keberatan = KeberatanInformasi::firstOrFail();

        $this->assertSame(KeberatanInformasi::DIAJUKAN, $keberatan->status);
        $this->assertNotNull($keberatan->tenggat_tanggapan);

        // Satu permohonan hanya menerima satu keberatan.
        $this->postJson("/api/v1/permohonan-informasi/lacak/{$permohonan->kode_lacak}/keberatan", [
            'alasan' => 'Saya mengajukan keberatan kedua atas permohonan yang sama.',
        ])->assertUnprocessable();
    }

    public function test_keberatan_dapat_diajukan_bila_tenggat_jawaban_terlampaui(): void
    {
        $permohonan = $this->permohonanInformasi(['tenggat_jawaban' => now()->subDay()]);

        $this->getJson("/api/v1/permohonan-informasi/lacak/{$permohonan->kode_lacak}")
            ->assertOk()
            ->assertJsonPath('melampaui_tenggat', true)
            ->assertJsonPath('dapat_mengajukan_keberatan', true);
    }

    public function test_tanggapan_keberatan_wajib_beralasan_dan_terbaca_pemohon(): void
    {
        $permohonan = $this->permohonanInformasi(['status' => 'ditolak']);
        $keberatan = $permohonan->keberatan()->create([
            'alasan' => 'Dokumen yang saya minta termasuk informasi yang wajib tersedia setiap saat.',
            'status' => KeberatanInformasi::DIAJUKAN,
            'tenggat_tanggapan' => now()->addDays(30),
        ]);

        $sekdes = $this->buatPengguna(Role::SEKDES);

        $this->actingAs($sekdes)
            ->postJson("/api/v1/admin/keberatan-informasi/{$keberatan->id}/tanggapi", ['tanggapan' => 'Singkat.'])
            ->assertUnprocessable();

        $this->actingAs($sekdes)->postJson("/api/v1/admin/keberatan-informasi/{$keberatan->id}/tanggapi", [
            'tanggapan' => 'Keberatan diterima. Dokumen akan kami serahkan paling lambat pekan depan melalui PPID.',
        ])->assertOk();

        $this->getJson("/api/v1/permohonan-informasi/lacak/{$permohonan->kode_lacak}")
            ->assertOk()
            ->assertJsonPath('keberatan.0.status', KeberatanInformasi::DITANGGAPI)
            ->assertJsonPath('keberatan.0.tanggapan', 'Keberatan diterima. Dokumen akan kami serahkan paling lambat pekan depan melalui PPID.');
    }

    public function test_moderasi_dan_keberatan_menuntut_izin_yang_tepat(): void
    {
        $artikel = $this->artikel();
        $komentar = $artikel->komentar()->create(['nama' => 'A', 'isi' => 'Isi komentar.', 'status' => Komentar::MENUNGGU]);

        $this->actingAs($this->buatWarga())
            ->getJson('/api/v1/admin/komentar')
            ->assertForbidden();

        $this->actingAs($this->buatWarga())
            ->postJson("/api/v1/admin/komentar/{$komentar->id}/moderasi", ['keputusan' => 'setujui'])
            ->assertForbidden();

        // Operator boleh memoderasi komentar, tetapi keberatan informasi adalah
        // kewenangan atasan PPID.
        $this->actingAs($this->buatPengguna(Role::OPERATOR))
            ->getJson('/api/v1/admin/keberatan-informasi')
            ->assertForbidden();
    }
}
