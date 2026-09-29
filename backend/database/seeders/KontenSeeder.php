<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Konten;
use App\Models\User;
use Illuminate\Database\Seeder;

class KontenSeeder extends Seeder
{
    public function run(): void
    {
        $penulis = User::where('email', 'operator@sukamaju.desa.id')->first();

        $kategori = collect([
            'Pembangunan', 'Kesehatan', 'Pendidikan', 'Ekonomi', 'Sosial Budaya', 'Pemerintahan',
        ])->mapWithKeys(fn (string $nama) => [
            $nama => Kategori::updateOrCreate(['slug' => str($nama)->slug()->toString()], ['nama' => $nama])->id,
        ]);

        $berita = [
            ['Musyawarah Desa Bahas Prioritas Pembangunan 2027', 'Pemerintahan',
                'Pemerintah Desa Sukamaju bersama BPD menggelar musyawarah desa untuk menyusun prioritas pembangunan tahun anggaran 2027.'],
            ['Posyandu Balita Melayani 180 Anak pada Oktober', 'Kesehatan',
                'Kegiatan Posyandu rutin bulan ini melayani penimbangan, imunisasi, dan pemberian makanan tambahan bagi 180 balita.'],
            ['Jalan Poros Dusun Mekar Selesai Diperbaiki', 'Pembangunan',
                'Perbaikan jalan sepanjang 1,2 kilometer telah rampung dan kini memperlancar akses warga menuju pasar kecamatan.'],
            ['Pelatihan Pemasaran Digital untuk 40 Pelaku UMKM', 'Ekonomi',
                'BUMDes bekerja sama dengan pendamping desa menggelar pelatihan pemasaran digital bagi pelaku usaha mikro.'],
            ['Beasiswa Desa Dibuka untuk 25 Pelajar Berprestasi', 'Pendidikan',
                'Pemerintah desa membuka pendaftaran beasiswa bagi pelajar berprestasi dari keluarga prasejahtera.'],
            ['Gotong Royong Bersihkan Saluran Irigasi', 'Sosial Budaya',
                'Warga empat dusun bergotong royong membersihkan saluran irigasi menjelang musim tanam.'],
        ];

        foreach ($berita as $urutan => [$judul, $kat, $ringkasan]) {
            Konten::updateOrCreate(['slug' => str($judul)->slug()->toString()], [
                'tipe' => 'berita',
                'judul' => $judul,
                'ringkasan' => $ringkasan,
                'isi' => "<p>{$ringkasan}</p><p>Kegiatan ini merupakan bagian dari program kerja Pemerintah Desa Sukamaju "
                    .'yang disusun bersama masyarakat melalui musyawarah desa. Seluruh tahapan pelaksanaan dilaporkan '
                    .'secara terbuka dan dapat dipantau warga melalui laman transparansi anggaran.</p>',
                'kategori_id' => $kategori[$kat],
                'penulis_id' => $penulis?->id,
                'status' => 'terbit',
                'sorotan' => $urutan < 3,
                'terbit_pada' => now()->subDays($urutan * 4 + 1),
                'tag' => [strtolower($kat), 'desa sukamaju'],
            ]);
        }

        $pengumuman = [
            ['Jadwal Pelayanan Administrasi Selama Hari Libur Nasional',
                'Pelayanan administrasi kantor desa diliburkan pada tanggal merah. Pengajuan surat tetap dapat dilakukan daring melalui laman layanan.', 30],
            ['Pemutakhiran Data Penerima Bantuan Sosial',
                'Warga penerima bantuan sosial diminta memverifikasi data kependudukan di kantor desa paling lambat akhir bulan ini.', 21],
            ['Pendaftaran Peserta Pelatihan Kewirausahaan Dibuka',
                'Pendaftaran pelatihan kewirausahaan gelombang kedua dibuka untuk 30 peserta. Kuota terbatas.', 14],
        ];

        foreach ($pengumuman as $urutan => [$judul, $isi, $hari]) {
            Konten::updateOrCreate(['slug' => str($judul)->slug()->toString()], [
                'tipe' => 'pengumuman',
                'judul' => $judul,
                'ringkasan' => $isi,
                'isi' => "<p>{$isi}</p>",
                'penulis_id' => $penulis?->id,
                'status' => 'terbit',
                'terbit_pada' => now()->subDays($urutan + 1),
                'kedaluwarsa_pada' => now()->addDays($hari),
            ]);
        }

        $agenda = [
            ['Musyawarah Rencana Pembangunan Desa', 'Aula Kantor Desa Sukamaju', 'Pemerintah Desa', 5],
            ['Posyandu Balita Dusun Mekar', 'Balai Dusun Mekar', 'TP PKK', 9],
            ['Pelatihan Pemasaran Digital UMKM', 'Gedung Serbaguna', 'BUMDes Sukamaju', 14],
            ['Kerja Bakti Bersih Desa', 'Seluruh Dusun', 'Karang Taruna', 20],
        ];

        foreach ($agenda as [$judul, $lokasi, $penyelenggara, $hari]) {
            Konten::updateOrCreate(['slug' => str($judul)->slug()->toString()], [
                'tipe' => 'agenda',
                'judul' => $judul,
                'ringkasan' => "Kegiatan {$judul} diselenggarakan oleh {$penyelenggara} di {$lokasi}.",
                'isi' => "<p>Seluruh warga diundang untuk menghadiri kegiatan {$judul}.</p>",
                'penulis_id' => $penulis?->id,
                'status' => 'terbit',
                'terbit_pada' => now()->subDay(),
                'mulai_pada' => now()->addDays($hari)->setTime(9, 0),
                'selesai_pada' => now()->addDays($hari)->setTime(12, 0),
                'lokasi' => $lokasi,
                'penyelenggara' => $penyelenggara,
            ]);
        }
    }
}
