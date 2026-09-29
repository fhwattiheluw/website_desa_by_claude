<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use Illuminate\Database\Seeder;

/** Profil desa contoh — seluruhnya dapat disunting lewat panel (REQ-F-BRD-005). */
class PengaturanSeeder extends Seeder
{
    public function run(): void
    {
        $nilai = [
            'identitas' => [
                'nama_desa' => 'Sukamaju',
                'kecamatan' => 'Cibeber',
                'kabupaten' => 'Cianjur',
                'provinsi' => 'Jawa Barat',
                'kode_pos' => '43262',
                'tagline' => 'Desa Digital, Pelayanan Cepat dan Transparan',
            ],
            'kontak' => [
                'alamat' => 'Jalan Raya Sukamaju Nomor 12, Sukamaju, Cibeber',
                'telepon' => '(0263) 123456',
                'email' => 'pemerintah@sukamaju.desa.id',
                'jam_pelayanan' => 'Senin–Jumat, 08.00–15.00 WIB',
                'facebook' => 'https://facebook.com/desasukamaju',
                'instagram' => 'https://instagram.com/desasukamaju',
            ],
            'profil' => [
                'sejarah' => 'Desa Sukamaju dibentuk pada tahun 1952 sebagai hasil pemekaran dari Desa Cibeber. '
                    .'Nama Sukamaju diambil dari cita-cita para pendiri desa agar warganya senantiasa maju dan sejahtera.',
                'visi' => 'Terwujudnya Desa Sukamaju yang mandiri, sejahtera, dan melayani dengan tata kelola '
                    .'pemerintahan yang bersih, transparan, dan partisipatif.',
                'misi' => "Meningkatkan kualitas pelayanan publik berbasis teknologi informasi\n"
                    ."Mengembangkan ekonomi desa melalui penguatan BUMDes dan UMKM\n"
                    ."Membangun infrastruktur desa yang merata dan berkelanjutan\n"
                    ."Meningkatkan partisipasi masyarakat dalam pembangunan desa\n"
                    .'Mewujudkan pengelolaan keuangan desa yang akuntabel dan transparan',
                'luas_wilayah' => '1.245 hektar',
                'batas_utara' => 'Desa Cimanggu',
                'batas_selatan' => 'Desa Karangtengah',
                'batas_timur' => 'Desa Sukasari',
                'batas_barat' => 'Desa Cibeber',
                'jumlah_dusun' => '4',
                'jumlah_rw' => '8',
                'jumlah_rt' => '24',
                'lat' => '-6.9147',
                'lng' => '107.1425',
            ],
            'bumdes' => [
                'bumdes_nama' => 'BUMDes Sukamaju Mandiri',
                'bumdes_deskripsi' => 'Badan Usaha Milik Desa yang mengelola potensi ekonomi desa untuk '
                    .'meningkatkan pendapatan asli desa dan kesejahteraan masyarakat.',
                'bumdes_tahun_berdiri' => '2019',
                'bumdes_dasar_hukum' => 'Peraturan Desa Nomor 03 Tahun '.(now()->year - 1),
                'bumdes_alamat' => 'Jalan Raya Sukamaju Nomor 14, Sukamaju, Cibeber',
                'bumdes_kontak' => '081234511122',
            ],
            'ppid' => [
                'ppid_nama' => 'PPID Pembantu Desa Sukamaju',
                'ppid_penanggung_jawab' => 'Sekretaris Desa',
                'ppid_maklumat' => 'Pemerintah Desa Sukamaju berkomitmen menyelenggarakan pelayanan informasi publik '
                    .'secara cepat, tepat waktu, berbiaya ringan, dan sederhana sesuai Undang-Undang Nomor 14 Tahun 2008.',
            ],
        ];

        foreach ($nilai as $grup => $butir) {
            foreach ($butir as $kunci => $isi) {
                Pengaturan::updateOrCreate(['kunci' => $kunci], ['nilai' => $isi, 'grup' => $grup]);
            }
        }
    }
}
