<?php

namespace Database\Seeders;

use App\Models\JenisLayanan;
use Illuminate\Database\Seeder;

/** Sepuluh layanan Fase 1 sesuai tabel 3.6.1 SRS. */
class JenisLayananSeeder extends Seeder
{
    public function run(): void
    {
        $layanan = [
            [
                'kode' => 'SKD', 'nama' => 'Surat Keterangan Domisili', 'sla' => 1,
                'deskripsi' => 'Keterangan resmi bahwa pemohon berdomisili di wilayah desa.',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga'],
                'tambahan' => [
                    ['nama' => 'lama_tinggal', 'label' => 'Lama Tinggal', 'tipe' => 'teks'],
                    ['nama' => 'keperluan', 'label' => 'Keperluan Surat', 'tipe' => 'teks'],
                ],
            ],
            [
                'kode' => 'SKTM', 'nama' => 'Surat Keterangan Tidak Mampu', 'sla' => 2,
                'deskripsi' => 'Keterangan kondisi ekonomi untuk keperluan beasiswa, kesehatan, atau bantuan sosial.',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga', 'Surat pengantar RT/RW'],
                'tambahan' => [
                    ['nama' => 'penghasilan', 'label' => 'Penghasilan per Bulan (Rp)', 'tipe' => 'angka'],
                    ['nama' => 'tanggungan', 'label' => 'Jumlah Tanggungan', 'tipe' => 'angka'],
                    ['nama' => 'keperluan', 'label' => 'Keperluan Surat', 'tipe' => 'teks'],
                ],
            ],
            [
                'kode' => 'SKU', 'nama' => 'Surat Keterangan Usaha', 'sla' => 2,
                'deskripsi' => 'Keterangan bahwa pemohon menjalankan usaha di wilayah desa.',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga', 'Foto tempat usaha'],
                'tambahan' => [
                    ['nama' => 'nama_usaha', 'label' => 'Nama Usaha', 'tipe' => 'teks'],
                    ['nama' => 'jenis_usaha', 'label' => 'Jenis Usaha', 'tipe' => 'teks'],
                    ['nama' => 'alamat_usaha', 'label' => 'Alamat Usaha', 'tipe' => 'teks'],
                    ['nama' => 'lama_usaha', 'label' => 'Lama Usaha Berjalan', 'tipe' => 'teks'],
                    ['nama' => 'keperluan', 'label' => 'Keperluan Surat', 'tipe' => 'teks'],
                ],
            ],
            [
                'kode' => 'SPN', 'nama' => 'Surat Pengantar Nikah', 'sla' => 2,
                'deskripsi' => 'Pengantar administrasi pernikahan (model N1–N4) ke KUA.',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga', 'Fotokopi akta kelahiran'],
                'tambahan' => [
                    ['nama' => 'status_perkawinan', 'label' => 'Status Perkawinan', 'tipe' => 'pilihan',
                        'pilihan' => ['Belum Kawin', 'Cerai Hidup', 'Cerai Mati']],
                    ['nama' => 'nama_pasangan', 'label' => 'Nama Calon Pasangan', 'tipe' => 'teks'],
                    ['nama' => 'rencana_tanggal', 'label' => 'Rencana Tanggal Akad', 'tipe' => 'teks'],
                ],
            ],
            [
                'kode' => 'SKL', 'nama' => 'Surat Keterangan Kelahiran', 'sla' => 1,
                'deskripsi' => 'Keterangan kelahiran sebagai dasar pengurusan akta kelahiran.',
                'syarat' => ['Fotokopi KTP orang tua', 'Fotokopi Kartu Keluarga', 'Surat keterangan bidan/rumah sakit'],
                'tambahan' => [
                    ['nama' => 'nama_anak', 'label' => 'Nama Anak', 'tipe' => 'teks'],
                    ['nama' => 'tanggal_lahir_anak', 'label' => 'Tanggal Lahir Anak', 'tipe' => 'tanggal'],
                    ['nama' => 'tempat_lahir_anak', 'label' => 'Tempat Lahir Anak', 'tipe' => 'teks'],
                    ['nama' => 'jenis_kelamin_anak', 'label' => 'Jenis Kelamin Anak', 'tipe' => 'pilihan',
                        'pilihan' => ['Laki-laki', 'Perempuan']],
                ],
            ],
            [
                'kode' => 'SKM', 'nama' => 'Surat Keterangan Kematian', 'sla' => 1,
                'deskripsi' => 'Keterangan kematian warga sebagai dasar pengurusan akta kematian.',
                'syarat' => ['Fotokopi KTP almarhum', 'Fotokopi Kartu Keluarga', 'Surat keterangan kematian'],
                'tambahan' => [
                    ['nama' => 'nama_almarhum', 'label' => 'Nama Almarhum/Almarhumah', 'tipe' => 'teks'],
                    ['nama' => 'tanggal_meninggal', 'label' => 'Tanggal Meninggal', 'tipe' => 'tanggal'],
                    ['nama' => 'tempat_meninggal', 'label' => 'Tempat Meninggal', 'tipe' => 'teks'],
                    ['nama' => 'sebab_meninggal', 'label' => 'Sebab Kematian', 'tipe' => 'teks'],
                ],
            ],
            [
                'kode' => 'SPS', 'nama' => 'Surat Pengantar SKCK', 'sla' => 1,
                'deskripsi' => 'Pengantar permohonan Surat Keterangan Catatan Kepolisian.',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga', 'Pas foto berwarna 4x6'],
                'tambahan' => [
                    ['nama' => 'keperluan', 'label' => 'Keperluan SKCK', 'tipe' => 'teks'],
                ],
            ],
            [
                'kode' => 'SKAW', 'nama' => 'Surat Keterangan Ahli Waris', 'sla' => 3,
                'deskripsi' => 'Keterangan para ahli waris yang sah dari almarhum.',
                'syarat' => ['Fotokopi KTP seluruh ahli waris', 'Fotokopi Kartu Keluarga', 'Akta kematian', 'Surat pernyataan ahli waris'],
                'tambahan' => [
                    ['nama' => 'nama_pewaris', 'label' => 'Nama Pewaris', 'tipe' => 'teks'],
                    ['nama' => 'tanggal_meninggal', 'label' => 'Tanggal Meninggal Pewaris', 'tipe' => 'tanggal'],
                    ['nama' => 'jumlah_ahli_waris', 'label' => 'Jumlah Ahli Waris', 'tipe' => 'angka'],
                    ['nama' => 'daftar_ahli_waris', 'label' => 'Nama Ahli Waris (pisahkan dengan koma)', 'tipe' => 'teks_panjang'],
                ],
            ],
            [
                'kode' => 'SKBM', 'nama' => 'Surat Keterangan Belum Menikah', 'sla' => 1,
                'deskripsi' => 'Keterangan status belum pernah menikah.',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga'],
                'tambahan' => [
                    ['nama' => 'keperluan', 'label' => 'Keperluan Surat', 'tipe' => 'teks'],
                ],
            ],
            [
                'kode' => 'SPPD', 'nama' => 'Surat Pengantar Pindah Domisili', 'sla' => 2,
                'deskripsi' => 'Pengantar perpindahan domisili keluar wilayah desa.',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga'],
                'tambahan' => [
                    ['nama' => 'alamat_tujuan', 'label' => 'Alamat Tujuan Pindah', 'tipe' => 'teks_panjang'],
                    ['nama' => 'alasan_pindah', 'label' => 'Alasan Pindah', 'tipe' => 'teks'],
                    ['nama' => 'jumlah_pengikut', 'label' => 'Jumlah Anggota Keluarga yang Ikut', 'tipe' => 'angka'],
                ],
            ],
        ];

        foreach ($layanan as $urutan => $item) {
            JenisLayanan::updateOrCreate(['kode' => $item['kode']], [
                'nama' => $item['nama'],
                'slug' => str($item['nama'])->slug()->toString(),
                'deskripsi' => $item['deskripsi'],
                'persyaratan' => $item['syarat'],
                'kolom_formulir' => [...$this->kolomDasar(), ...$this->lengkapi($item['tambahan']), ...$this->pernyataan()],
                'sla_hari_kerja' => $item['sla'],
                'format_nomor' => '{urut}/{kode}/{romawi_bulan}/{tahun}',
                'templat' => 'surat.umum',
                'aktif' => true,
                'urutan' => $urutan,
            ]);
        }
    }

    /** Kolom identitas yang selalu diminta pada setiap layanan. */
    private function kolomDasar(): array
    {
        return [
            ['nama' => 'nama_lengkap', 'label' => 'Nama Lengkap', 'tipe' => 'teks', 'wajib' => true],
            ['nama' => 'nik', 'label' => 'NIK', 'tipe' => 'nik', 'wajib' => true],
            ['nama' => 'nomor_kk', 'label' => 'Nomor Kartu Keluarga', 'tipe' => 'kk', 'wajib' => true],
            ['nama' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'tipe' => 'teks', 'wajib' => true],
            ['nama' => 'tanggal_lahir', 'label' => 'Tanggal Lahir', 'tipe' => 'tanggal', 'wajib' => true],
            ['nama' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'tipe' => 'pilihan', 'wajib' => true,
                'pilihan' => ['Laki-laki', 'Perempuan']],
            ['nama' => 'alamat', 'label' => 'Alamat (Dusun/RT/RW)', 'tipe' => 'teks', 'wajib' => true],
            ['nama' => 'pekerjaan', 'label' => 'Pekerjaan', 'tipe' => 'teks', 'wajib' => true],
        ];
    }

    private function pernyataan(): array
    {
        return [[
            'nama' => 'pernyataan_benar',
            'label' => 'Saya menyatakan data yang saya isikan benar dan dapat dipertanggungjawabkan',
            'tipe' => 'centang',
            'wajib' => true,
            'di_surat' => false,
        ]];
    }

    private function lengkapi(array $kolom): array
    {
        return array_map(fn (array $k) => [...$k, 'wajib' => $k['wajib'] ?? true], $kolom);
    }
}
