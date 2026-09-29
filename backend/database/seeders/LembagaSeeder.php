<?php

namespace Database\Seeders;

use App\Models\Lembaga;
use Illuminate\Database\Seeder;

/** REQ-F-LMB-001..003: perangkat desa dan lembaga kemasyarakatan. */
class LembagaSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'nama' => 'Pemerintah Desa Sukamaju', 'jenis' => 'pemerintah_desa',
                'deskripsi' => 'Perangkat desa yang menyelenggarakan urusan pemerintahan dan pelayanan masyarakat.',
                'pengurus' => [
                    ['Hartono Wijaya', 'Kepala Desa', 'Memimpin penyelenggaraan pemerintahan desa'],
                    ['Sri Rahayu', 'Sekretaris Desa', 'Membantu Kepala Desa di bidang administrasi'],
                    ['Budi Santoso', 'Kasi Pemerintahan', 'Melaksanakan urusan pemerintahan dan kependudukan'],
                    ['Ani Kusmawati', 'Kasi Kesejahteraan', 'Melaksanakan pembangunan dan pemberdayaan masyarakat'],
                    ['Dedi Kurniawan', 'Kaur Umum dan Perencanaan', 'Mengelola administrasi umum dan perencanaan'],
                    ['Lilis Suryani', 'Kaur Keuangan', 'Mengelola keuangan dan aset desa'],
                ],
            ],
            [
                'nama' => 'Badan Permusyawaratan Desa', 'jenis' => 'bpd',
                'deskripsi' => 'Lembaga yang menampung aspirasi masyarakat dan mengawasi kinerja pemerintah desa.',
                'pengurus' => [
                    ['Asep Mulyana', 'Ketua BPD', null],
                    ['Nurhayati', 'Wakil Ketua', null],
                    ['Rudi Hermawan', 'Sekretaris', null],
                ],
            ],
            [
                'nama' => 'Tim Penggerak PKK', 'jenis' => 'pkk',
                'deskripsi' => 'Gerakan pemberdayaan dan kesejahteraan keluarga di tingkat desa.',
                'pengurus' => [
                    ['Yuliana Hartono', 'Ketua TP PKK', null],
                    ['Siti Maryam', 'Sekretaris', null],
                ],
            ],
            [
                'nama' => 'Karang Taruna Tunas Muda', 'jenis' => 'karang_taruna',
                'deskripsi' => 'Wadah pengembangan generasi muda desa di bidang sosial dan ekonomi kreatif.',
                'pengurus' => [
                    ['Agus Priyanto', 'Ketua', null],
                    ['Dewi Anggraeni', 'Bendahara', null],
                ],
            ],
            [
                'nama' => 'Lembaga Pemberdayaan Masyarakat', 'jenis' => 'lpm',
                'deskripsi' => 'Mitra pemerintah desa dalam perencanaan dan pelaksanaan pembangunan.',
                'pengurus' => [['Endang Suherman', 'Ketua LPM', null]],
            ],
        ];

        foreach ($data as $urutan => $item) {
            $lembaga = Lembaga::updateOrCreate(
                ['slug' => str($item['nama'])->slug()->toString()],
                ['nama' => $item['nama'], 'jenis' => $item['jenis'], 'deskripsi' => $item['deskripsi'], 'urutan' => $urutan],
            );

            $lembaga->pengurus()->delete();

            foreach ($item['pengurus'] as $no => [$nama, $jabatan, $tugas]) {
                $lembaga->pengurus()->create([
                    'nama' => $nama,
                    'jabatan' => $jabatan,
                    'tugas_pokok' => $tugas,
                    'masa_jabatan_mulai' => 2022,
                    'masa_jabatan_selesai' => 2028,
                    'urutan' => $no,
                ]);
            }
        }

        // Ketua RT/RW sebagai lembaga tersendiri (REQ-F-LMB-003).
        $rt = Lembaga::updateOrCreate(['slug' => 'rukun-tetangga-dan-rukun-warga'], [
            'nama' => 'Rukun Tetangga dan Rukun Warga',
            'jenis' => 'rt_rw',
            'deskripsi' => 'Daftar ketua RT dan RW beserta wilayah cakupannya.',
            'urutan' => 10,
        ]);

        $rt->pengurus()->delete();

        foreach (range(1, 8) as $nomor) {
            $rt->pengurus()->create([
                'nama' => 'Ketua RW '.str_pad((string) $nomor, 2, '0', STR_PAD_LEFT),
                'jabatan' => 'Ketua RW',
                'wilayah' => 'RW '.str_pad((string) $nomor, 3, '0', STR_PAD_LEFT),
                'urutan' => $nomor,
            ]);
        }
    }
}
