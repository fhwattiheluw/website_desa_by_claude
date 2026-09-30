<?php

namespace Database\Seeders;

use App\Models\MenuNavigasi;
use Illuminate\Database\Seeder;

/**
 * Menu bawaan (REQ-F-ADM-003).
 *
 * Isinya persis susunan yang sebelumnya tertulis di kode antarmuka, agar desa
 * yang tidak menyentuh pengelola menu tetap memperoleh navigasi yang sama.
 */
class MenuNavigasiSeeder extends Seeder
{
    private const BAWAAN = [
        ['label' => 'Beranda', 'tautan' => '/'],
        ['label' => 'Profil Desa', 'tautan' => '/profil'],
        // Butir yang menaungi anak tidak diberi tautan sendiri: tautannya akan
        // selalu sama dengan anak pertama, sehingga daftar hanya berisi dua
        // butir yang menuju halaman yang sama.
        ['label' => 'Informasi', 'anak' => [
            ['label' => 'Berita Desa', 'tautan' => '/berita'],
            ['label' => 'Pengumuman', 'tautan' => '/pengumuman'],
            ['label' => 'Agenda Kegiatan', 'tautan' => '/agenda'],
            ['label' => 'Galeri', 'tautan' => '/galeri'],
        ]],
        ['label' => 'Transparansi', 'anak' => [
            ['label' => 'APBDes', 'tautan' => '/transparansi/apbdes'],
            ['label' => 'Statistik Desa', 'tautan' => '/transparansi/statistik'],
            ['label' => 'Produk Hukum', 'tautan' => '/transparansi/produk-hukum'],
            ['label' => 'PPID', 'tautan' => '/ppid'],
        ]],
        ['label' => 'Layanan', 'tautan' => '/layanan'],
        ['label' => 'Partisipasi', 'anak' => [
            ['label' => 'Pengaduan Warga', 'tautan' => '/pengaduan'],
            ['label' => 'Lacak Pengaduan', 'tautan' => '/pengaduan/lacak'],
            ['label' => 'Permohonan Informasi', 'tautan' => '/ppid/permohonan'],
        ]],
        ['label' => 'Potensi Desa', 'anak' => [
            ['label' => 'UMKM', 'tautan' => '/potensi/umkm'],
            ['label' => 'Wisata', 'tautan' => '/potensi/wisata'],
            ['label' => 'BUMDes', 'tautan' => '/potensi/bumdes'],
        ]],
    ];

    public function run(): void
    {
        if (MenuNavigasi::exists()) {
            return;
        }

        foreach (self::BAWAAN as $urutan => $butir) {
            $induk = MenuNavigasi::create([
                'label' => $butir['label'],
                'tautan' => $butir['tautan'] ?? null,
                'urutan' => $urutan,
            ]);

            foreach ($butir['anak'] ?? [] as $urutanAnak => $anak) {
                MenuNavigasi::create([
                    'induk_id' => $induk->id,
                    'label' => $anak['label'],
                    'tautan' => $anak['tautan'],
                    'urutan' => $urutanAnak,
                ]);
            }
        }
    }
}
