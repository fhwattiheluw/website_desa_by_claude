<?php

namespace Database\Seeders;

use App\Models\FasilitasUmum;
use Illuminate\Database\Seeder;

/** REQ-F-BRD-004: titik fasilitas umum utama Desa Sukamaju. */
class FasilitasUmumSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['Kantor Desa Sukamaju', 'kantor', 'Jalan Raya Sukamaju No. 1', 'Pusat pelayanan administrasi desa', -6.9147, 107.1425],
            ['Balai Desa Sukamaju', 'kantor', 'Jalan Raya Sukamaju No. 1', 'Ruang musyawarah dan kegiatan warga', -6.9151, 107.1428],
            ['SD Negeri Sukamaju 01', 'pendidikan', 'Jalan Pendidikan No. 12', null, -6.9132, 107.1461],
            ['SMP Negeri 2 Cibeber', 'pendidikan', 'Jalan Pendidikan No. 40', null, -6.9108, 107.1489],
            ['Puskesmas Pembantu Sukamaju', 'kesehatan', 'Jalan Raya Sukamaju No. 25', 'Layanan kesehatan dasar, Senin–Sabtu', -6.9163, 107.1402],
            ['Posyandu Melati', 'kesehatan', 'Dusun Mekar RT 003 RW 002', 'Penimbangan balita setiap tanggal 10', -6.9185, 107.1378],
            ['Masjid Jami Al-Ikhlas', 'ibadah', 'Jalan Raya Sukamaju No. 8', null, -6.9155, 107.1441],
            ['Lapangan Desa Sukamaju', 'olahraga', 'Dusun Mekar', 'Lapangan sepak bola dan kegiatan desa', -6.9172, 107.1447],
            ['Pasar Desa Sukamaju', 'ekonomi', 'Jalan Pasar Lama', 'Beroperasi setiap hari, 05.00–11.00 WIB', -6.9121, 107.1413],
        ];

        foreach ($data as $urutan => [$nama, $jenis, $alamat, $keterangan, $lat, $lng]) {
            FasilitasUmum::updateOrCreate(
                ['nama' => $nama],
                compact('jenis', 'alamat', 'keterangan', 'lat', 'lng') + ['urutan' => $urutan],
            );
        }
    }
}
