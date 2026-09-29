<?php

namespace Database\Seeders;

use App\Models\InformasiPublik;
use App\Models\ItemStatistik;
use App\Models\KinerjaBumdes;
use App\Models\PeriodeStatistik;
use App\Models\ProdukHukum;
use App\Models\TahunAnggaran;
use App\Models\Umkm;
use App\Models\UnitUsaha;
use App\Models\User;
use App\Models\Wisata;
use Illuminate\Database\Seeder;

/** Data transparansi anggaran, statistik, produk hukum, dan potensi desa. */
class TransparansiSeeder extends Seeder
{
    public function run(): void
    {
        $this->apbdes();
        $this->statistik();
        $this->produkHukum();
        $this->informasiPublik();
        $this->potensi();
        $this->bumdes();
    }

    private function bumdes(): void
    {
        $unit = [
            ['Toko Sembako Desa', 'Penyediaan kebutuhan pokok dengan harga terjangkau bagi warga.', 'Asep Saepudin', '081234511133'],
            ['Pengelolaan Air Bersih', 'Distribusi air bersih untuk 420 sambungan rumah di empat dusun.', 'Wawan Setiawan', '081234511144'],
            ['Wisata Curug Sukamaju', 'Pengelolaan kawasan wisata air terjun beserta kios oleh-oleh.', 'Dewi Anggraeni', '081234511155'],
            ['Simpan Pinjam Perempuan', 'Layanan keuangan mikro bagi kelompok usaha perempuan desa.', 'Yuyun Yuningsih', null],
        ];

        foreach ($unit as $urutan => [$nama, $deskripsi, $penanggungJawab, $kontak]) {
            UnitUsaha::updateOrCreate(['slug' => str($nama)->slug()->toString()], [
                'nama' => $nama,
                'deskripsi' => $deskripsi,
                'penanggung_jawab' => $penanggungJawab,
                'kontak' => $kontak,
                'aktif' => true,
                'urutan' => $urutan,
            ]);
        }

        $kinerja = [
            [now()->year - 1, 486_000_000, 92_400_000, 27_700_000, 'Audit internal selesai.'],
            [now()->year - 2, 402_500_000, 71_800_000, 21_500_000, 'Tahun pertama unit air bersih beroperasi penuh.'],
        ];

        foreach ($kinerja as [$tahun, $pendapatan, $laba, $kontribusi, $catatan]) {
            KinerjaBumdes::updateOrCreate(['tahun' => $tahun], [
                'pendapatan' => $pendapatan,
                'laba_bersih' => $laba,
                'kontribusi_pades' => $kontribusi,
                'catatan' => $catatan,
                'dipublikasikan' => true,
            ]);
        }
    }

    private function apbdes(): void
    {
        $sekdes = User::where('email', 'sekdes@sukamaju.desa.id')->first();

        $rincian = [
            'pendapatan' => [
                ['Dana Desa', null, 1_150_000_000, 1_035_000_000],
                ['Alokasi Dana Desa', null, 520_000_000, 468_000_000],
                ['Bagi Hasil Pajak dan Retribusi', null, 85_000_000, 82_400_000],
                ['Pendapatan Asli Desa', 'Hasil usaha BUMDes', 120_000_000, 96_500_000],
            ],
            'belanja' => [
                ['Penyelenggaraan Pemerintahan Desa', 'Penghasilan tetap dan operasional', 610_000_000, 548_000_000],
                ['Pelaksanaan Pembangunan Desa', 'Rabat beton jalan dusun', 780_000_000, 702_000_000],
                ['Pelaksanaan Pembangunan Desa', 'Rehabilitasi saluran irigasi', 180_000_000, 175_400_000],
                ['Pembinaan Kemasyarakatan', 'Posyandu, PKK, dan Karang Taruna', 145_000_000, 128_300_000],
                ['Pemberdayaan Masyarakat', 'Pelatihan UMKM dan penguatan BUMDes', 160_000_000, 141_200_000],
                ['Penanggulangan Bencana dan Mendesak', null, 60_000_000, 22_000_000],
            ],
            'pembiayaan' => [
                ['Silpa Tahun Sebelumnya', null, 95_000_000, 95_000_000],
                ['Penyertaan Modal BUMDes', null, 75_000_000, 75_000_000],
            ],
        ];

        foreach ([now()->year, now()->year - 1] as $indeks => $tahun) {
            $anggaran = TahunAnggaran::updateOrCreate(['tahun' => $tahun], [
                'dipublikasikan' => true,
                'disetujui_oleh' => $sekdes?->id,
                'dipublikasikan_pada' => now()->subDays($indeks * 180 + 10),
                'catatan' => $indeks === 0 ? 'Data realisasi berjalan, diperbarui setiap triwulan.' : 'Realisasi final.',
            ]);

            $anggaran->item()->delete();
            $urutan = 0;

            foreach ($rincian as $jenis => $baris) {
                foreach ($baris as [$bidang, $kegiatan, $pagu, $realisasi]) {
                    $faktor = $indeks === 0 ? 1 : 0.92;

                    $anggaran->item()->create([
                        'jenis' => $jenis,
                        'bidang' => $bidang,
                        'kegiatan' => $kegiatan,
                        'pagu' => round($pagu * $faktor),
                        'realisasi' => round(($indeks === 0 ? $realisasi : $pagu) * $faktor),
                        'urutan' => $urutan++,
                    ]);
                }
            }
        }
    }

    private function statistik(): void
    {
        $periode = PeriodeStatistik::updateOrCreate(
            ['nama' => 'Semester I '.now()->year],
            ['tahun' => now()->year, 'sumber_data' => 'Pendataan Profil Desa dan Kelurahan', 'aktif' => true],
        );

        $periode->item()->delete();

        $data = [
            'jenis_kelamin' => ['Laki-laki' => 2148, 'Perempuan' => 2076],
            'kepala_keluarga' => ['Kepala Keluarga' => 1342],
            'usia' => ['0–5 tahun' => 361, '6–17 tahun' => 892, '18–40 tahun' => 1523, '41–60 tahun' => 989, 'di atas 60 tahun' => 459],
            'pendidikan' => ['Tidak/Belum Sekolah' => 412, 'SD Sederajat' => 1287, 'SMP Sederajat' => 984,
                'SMA Sederajat' => 1105, 'Diploma' => 218, 'Sarjana' => 218],
            'pekerjaan' => ['Petani' => 1186, 'Buruh Harian' => 742, 'Wiraswasta' => 623, 'Karyawan Swasta' => 481,
                'Pelajar/Mahasiswa' => 754, 'Ibu Rumah Tangga' => 318, 'ASN/TNI/Polri' => 87, 'Lainnya' => 33],
            'agama' => ['Islam' => 4102, 'Kristen' => 78, 'Katolik' => 31, 'Hindu' => 9, 'Buddha' => 4],
            'dusun' => ['Dusun Mekar' => 1184, 'Dusun Sukasari' => 1093, 'Dusun Cibodas' => 1047, 'Dusun Tegalsari' => 900],
        ];

        $urutan = 0;

        foreach ($data as $kelompok => $butir) {
            foreach ($butir as $label => $jumlah) {
                ItemStatistik::create([
                    'periode_statistik_id' => $periode->id,
                    'kelompok' => $kelompok,
                    'label' => $label,
                    'jumlah' => $jumlah,
                    'urutan' => $urutan++,
                ]);
            }
        }
    }

    private function produkHukum(): void
    {
        $data = [
            ['perdes', '01', now()->year, 'Anggaran Pendapatan dan Belanja Desa Tahun '.now()->year, 'APBDes tahun berjalan', true],
            ['perdes', '02', now()->year, 'Rencana Kerja Pemerintah Desa Tahun '.now()->year, 'RKPDes tahun berjalan', true],
            ['perdes', '03', now()->year - 1, 'Badan Usaha Milik Desa Sukamaju', 'Pendirian dan pengelolaan BUMDes', true],
            ['perdes', '01', now()->year - 1, 'Anggaran Pendapatan dan Belanja Desa Tahun '.(now()->year - 1), 'APBDes tahun lalu', false],
            ['perkades', '05', now()->year, 'Standar Pelayanan Administrasi Desa', 'Tata cara dan waktu layanan administrasi', true],
            ['sk_kades', '12', now()->year, 'Penetapan Operator Sistem Informasi Desa', 'Penunjukan pengelola website desa', true],
        ];

        foreach ($data as [$jenis, $nomor, $tahun, $judul, $tentang, $berlaku]) {
            ProdukHukum::updateOrCreate(
                ['jenis' => $jenis, 'nomor' => $nomor, 'tahun' => $tahun],
                ['judul' => $judul, 'tentang' => $tentang, 'berlaku' => $berlaku],
            );
        }
    }

    private function informasiPublik(): void
    {
        $data = [
            ['berkala', 'Laporan Realisasi APBDes Semester I', 'Ringkasan realisasi pendapatan dan belanja desa.', 'Setiap semester'],
            ['berkala', 'Laporan Penyelenggaraan Pemerintahan Desa', 'Laporan tahunan kinerja pemerintah desa.', 'Setiap tahun'],
            ['berkala', 'Rencana Kerja Pemerintah Desa', 'Dokumen perencanaan tahunan desa.', 'Setiap tahun'],
            ['serta_merta', 'Informasi Kebencanaan dan Kedaruratan', 'Informasi yang wajib diumumkan segera demi keselamatan warga.', 'Sewaktu-waktu'],
            ['setiap_saat', 'Daftar Aset dan Inventaris Desa', 'Daftar kekayaan milik desa.', 'Setiap saat'],
            ['setiap_saat', 'Daftar Penerima Bantuan Sosial', 'Data agregat penerima bantuan tanpa data pribadi.', 'Setiap saat'],
        ];

        foreach ($data as [$klasifikasi, $judul, $ringkasan, $periode]) {
            InformasiPublik::updateOrCreate(['judul' => $judul], [
                'klasifikasi' => $klasifikasi,
                'ringkasan' => $ringkasan,
                'penanggung_jawab' => 'PPID Pembantu Desa Sukamaju',
                'periode_terbit' => $periode,
            ]);
        }
    }

    private function potensi(): void
    {
        $umkm = [
            ['Keripik Singkong Bu Ani', 'Ani Rohaeti', 'Makanan Olahan', 'Keripik singkong aneka rasa produksi rumahan sejak 2015.', '081234500011'],
            ['Kopi Robusta Sukamaju', 'Asep Saepudin', 'Minuman', 'Kopi robusta hasil kebun warga, diolah secara tradisional.', '081234500022'],
            ['Anyaman Bambu Tegalsari', 'Wawan Setiawan', 'Kerajinan', 'Kerajinan bambu berupa besek, tampah, dan hiasan rumah.', '081234500033'],
            ['Batik Tulis Mekar Sari', 'Yuyun Yuningsih', 'Kerajinan', 'Batik tulis bermotif flora khas Sukamaju.', '081234500044'],
            ['Gula Aren Cibodas', 'Dadang Supriatna', 'Makanan Olahan', 'Gula aren cetak tanpa bahan pengawet.', null],
            ['Budidaya Lele Berkah', 'Hendra Gunawan', 'Perikanan', 'Pembesaran lele dengan sistem bioflok.', '081234500055'],
        ];

        foreach ($umkm as [$nama, $pemilik, $kategori, $deskripsi, $telepon]) {
            Umkm::updateOrCreate(['slug' => str($nama)->slug()->toString()], [
                'nama_usaha' => $nama,
                'pemilik' => $pemilik,
                'kategori' => $kategori,
                'deskripsi' => $deskripsi,
                'telepon' => $telepon,
                'alamat' => 'Desa Sukamaju, Kecamatan Cibeber',
                'consent_kontak' => $telepon !== null,
                'status' => 'disetujui',
            ]);
        }

        $wisata = [
            ['Curug Sukamaju', 'Air terjun setinggi 18 meter dengan kolam alami di kaki bukit.', '08.00–17.00 WIB', 'Rp5.000 per orang'],
            ['Kebun Teh Cibodas', 'Hamparan kebun teh dengan jalur trekking dan gardu pandang.', '07.00–17.00 WIB', 'Gratis'],
            ['Saung Budaya Tegalsari', 'Sanggar seni tradisional yang menampilkan pertunjukan setiap akhir pekan.', '09.00–16.00 WIB', 'Sukarela'],
        ];

        foreach ($wisata as [$nama, $deskripsi, $jam, $tarif]) {
            Wisata::updateOrCreate(['slug' => str($nama)->slug()->toString()], [
                'nama' => $nama,
                'deskripsi' => $deskripsi,
                'jam_operasional' => $jam,
                'tarif' => $tarif,
                'alamat' => 'Desa Sukamaju, Kecamatan Cibeber',
                'lat' => -6.9147,
                'lng' => 107.1425,
            ]);
        }
    }
}
