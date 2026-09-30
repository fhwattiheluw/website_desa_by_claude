<?php

namespace Tests\Feature;

use App\Models\FasilitasUmum;
use App\Models\Lembaga;
use App\Models\Pengaturan;
use App\Models\Pengurus;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** REQ-F-BRD-003: struktur organisasi disajikan sebagai bagan berjenjang. */
class BaganOrganisasiTest extends TestCase
{
    use RefreshDatabase;

    private function buatLembaga(string $nama = 'Pemerintah Desa Uji'): Lembaga
    {
        return Lembaga::create([
            'nama' => $nama,
            'slug' => str($nama)->slug()->toString(),
            'jenis' => 'pemerintah_desa',
            'urutan' => 0,
        ]);
    }

    private function buatPengurus(Lembaga $lembaga, string $nama, string $jabatan, ?Pengurus $atasan = null): Pengurus
    {
        return $lembaga->pengurus()->create([
            'nama' => $nama,
            'jabatan' => $jabatan,
            'atasan_id' => $atasan?->id,
            'masa_jabatan_mulai' => 2022,
            'masa_jabatan_selesai' => 2028,
            'urutan' => $lembaga->pengurus()->count(),
        ]);
    }

    public function test_bagan_menyusun_bawahan_di_bawah_atasannya(): void
    {
        $lembaga = $this->buatLembaga();
        $kades = $this->buatPengurus($lembaga, 'Hartono Wijaya', 'Kepala Desa');
        $sekdes = $this->buatPengurus($lembaga, 'Sri Rahayu', 'Sekretaris Desa', $kades);
        $this->buatPengurus($lembaga, 'Budi Santoso', 'Kasi Pemerintahan', $kades);
        $this->buatPengurus($lembaga, 'Lilis Suryani', 'Kaur Keuangan', $sekdes);

        $bagan = $this->getJson('/api/v1/lembaga')->assertOk()->json('data.0.bagan');

        // Satu akar: Kepala Desa.
        $this->assertCount(1, $bagan);
        $this->assertSame('Kepala Desa', $bagan[0]['jabatan']);
        $this->assertSame('2022–2028', $bagan[0]['masa_jabatan']);

        $this->assertCount(2, $bagan[0]['bawahan']);
        $this->assertSame('Sekretaris Desa', $bagan[0]['bawahan'][0]['jabatan']);
        $this->assertSame('Kasi Pemerintahan', $bagan[0]['bawahan'][1]['jabatan']);

        // Jenjang ketiga: Kaur berada di bawah Sekretaris Desa.
        $this->assertCount(1, $bagan[0]['bawahan'][0]['bawahan']);
        $this->assertSame('Kaur Keuangan', $bagan[0]['bawahan'][0]['bawahan'][0]['jabatan']);
        $this->assertSame([], $bagan[0]['bawahan'][1]['bawahan']);
    }

    public function test_pengurus_tanpa_atasan_yang_sah_tetap_muncul_sebagai_akar(): void
    {
        $lembaga = $this->buatLembaga();
        $ketua = $this->buatPengurus($lembaga, 'Asep Mulyana', 'Ketua');

        $lain = $this->buatLembaga('Karang Taruna Uji');
        $ketuaLain = $this->buatPengurus($lain, 'Agus Priyanto', 'Ketua Karang Taruna');

        // Atasan menunjuk pengurus di lembaga lain: tidak boleh menghilang dari
        // bagan lembaganya sendiri.
        $nyasar = $this->buatPengurus($lembaga, 'Nurhayati', 'Sekretaris', $ketuaLain);

        $bagan = collect($this->getJson('/api/v1/lembaga')->assertOk()->json('data'))
            ->firstWhere('slug', $lembaga->slug)['bagan'];

        $this->assertCount(2, $bagan);
        $this->assertEqualsCanonicalizing(
            [$ketua->id, $nyasar->id],
            array_column($bagan, 'id'),
        );
    }

    public function test_bagan_dalam_tetap_satu_kueri_pengurus(): void
    {
        $lembaga = $this->buatLembaga();
        $atasan = null;

        foreach (range(1, 6) as $tingkat) {
            $atasan = $this->buatPengurus($lembaga, "Perangkat {$tingkat}", "Jabatan {$tingkat}", $atasan);
        }

        DB::enableQueryLog();
        $bagan = $this->getJson('/api/v1/lembaga')->assertOk()->json('data.0.bagan');
        $kueriPengurus = collect(DB::getQueryLog())
            ->filter(fn (array $baris) => str_contains($baris['query'], 'pengurus'))
            ->count();
        DB::disableQueryLog();

        // Enam tingkat tidak boleh berarti enam kueri (REQ-NF-PRF-002).
        $this->assertLessThanOrEqual(1, $kueriPengurus);

        $simpul = $bagan[0];
        $kedalaman = 1;

        while ($simpul['bawahan'] !== []) {
            $simpul = $simpul['bawahan'][0];
            $kedalaman++;
        }

        $this->assertSame(6, $kedalaman);
    }

    /** REQ-F-BRD-004: titik peta wilayah disajikan bersama profil desa. */
    public function test_fasilitas_umum_tersaji_dengan_koordinatnya(): void
    {
        $this->seed(PengaturanSeeder::class);

        FasilitasUmum::create([
            'nama' => 'Kantor Desa Uji',
            'jenis' => 'kantor',
            'alamat' => 'Jalan Raya Uji No. 1',
            'keterangan' => 'Pusat pelayanan administrasi desa',
            'lat' => -6.9147,
            'lng' => 107.1425,
            'urutan' => 0,
        ]);

        FasilitasUmum::create([
            'nama' => 'Posyandu Uji',
            'jenis' => 'kesehatan',
            'lat' => -6.9185,
            'lng' => 107.1378,
            'urutan' => 1,
        ]);

        $this->getJson('/api/v1/profil-desa')
            ->assertOk()
            ->assertJsonCount(2, 'fasilitas_umum')
            ->assertJsonPath('fasilitas_umum.0.nama', 'Kantor Desa Uji')
            ->assertJsonPath('fasilitas_umum.0.jenis', 'kantor')
            // Koordinat dikirim sebagai angka, bukan teks, agar peta tidak
            // perlu menebak-nebak bentuknya.
            ->assertJsonPath('fasilitas_umum.0.koordinat.lat', -6.9147)
            ->assertJsonPath('fasilitas_umum.1.koordinat.lng', 107.1378);
    }

    /** REQ-F-BRD-007: sambutan diambil dari puncak bagan, bukan cocokan teks. */
    public function test_sambutan_menunjuk_pengurus_di_puncak_bagan(): void
    {
        $this->seed(PengaturanSeeder::class);

        $lembaga = $this->buatLembaga();
        $kades = $this->buatPengurus($lembaga, 'Hartono Wijaya', 'Kepala Desa');
        $this->buatPengurus($lembaga, 'Sri Rahayu', 'Sekretaris Desa', $kades);

        $this->getJson('/api/v1/beranda')
            ->assertOk()
            ->assertJsonPath('sambutan.nama', 'Hartono Wijaya')
            ->assertJsonPath('sambutan.jabatan', 'Kepala Desa');

        // Jabatan boleh dituliskan berbeda; yang menentukan tetap posisinya
        // pada bagan.
        $kades->update(['jabatan' => 'Pj. Kepala Desa Sukamaju']);

        $this->getJson('/api/v1/beranda')
            ->assertOk()
            ->assertJsonPath('sambutan.jabatan', 'Pj. Kepala Desa Sukamaju');
    }

    public function test_sambutan_tidak_tampil_bila_kutipannya_kosong(): void
    {
        $this->seed(PengaturanSeeder::class);
        Pengaturan::where('kunci', 'sambutan_kepala_desa')->delete();

        $this->getJson('/api/v1/beranda')->assertOk()->assertJsonPath('sambutan', null);
    }

    public function test_data_pribadi_aparatur_tidak_ikut_terbawa(): void
    {
        $lembaga = $this->buatLembaga();
        $this->buatPengurus($lembaga, 'Hartono Wijaya', 'Kepala Desa');

        $simpul = $this->getJson('/api/v1/lembaga')->assertOk()->json('data.0.bagan.0');

        // REQ-F-LMB-004: bagan hanya memuat keterangan jabatan, bukan data
        // pribadi perangkat desa.
        $this->assertSame(
            ['id', 'nama', 'jabatan', 'wilayah', 'masa_jabatan', 'tugas_pokok', 'foto', 'bawahan'],
            array_keys($simpul),
        );
    }
}
