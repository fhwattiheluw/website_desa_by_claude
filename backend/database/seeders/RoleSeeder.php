<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/** Peran dan izin sesuai matriks RBAC pada Bab 2.3 SRS. */
class RoleSeeder extends Seeder
{
    /** @var array<string, array{nama: string, grup: string}> */
    private const IZIN = [
        'dashboard.lihat' => ['nama' => 'Melihat dasbor', 'grup' => 'umum'],
        'konten.kelola' => ['nama' => 'Membuat dan menyunting konten', 'grup' => 'konten'],
        'konten.terbit' => ['nama' => 'Menerbitkan konten', 'grup' => 'konten'],
        'permohonan.lihat' => ['nama' => 'Melihat antrean permohonan', 'grup' => 'layanan'],
        'permohonan.verifikasi' => ['nama' => 'Memverifikasi berkas permohonan', 'grup' => 'layanan'],
        'permohonan.setujui' => ['nama' => 'Menyetujui permohonan', 'grup' => 'layanan'],
        'permohonan.tanda_tangan' => ['nama' => 'Menandatangani surat', 'grup' => 'layanan'],
        'permohonan.buat_loket' => ['nama' => 'Membuat permohonan atas nama warga', 'grup' => 'layanan'],
        'surat.batalkan' => ['nama' => 'Membatalkan surat terbit', 'grup' => 'layanan'],
        'laporan.lihat' => ['nama' => 'Melihat laporan kinerja layanan', 'grup' => 'laporan'],
        'pengaduan.lihat' => ['nama' => 'Melihat pengaduan', 'grup' => 'pengaduan'],
        'pengaduan.kelola' => ['nama' => 'Menanggapi dan mengubah status pengaduan', 'grup' => 'pengaduan'],
        'pengaduan.disposisi' => ['nama' => 'Mendisposisi pengaduan', 'grup' => 'pengaduan'],
        'pengaduan.moderasi' => ['nama' => 'Memoderasi publikasi pengaduan', 'grup' => 'pengaduan'],
        'apbdes.kelola' => ['nama' => 'Mengelola data APBDes', 'grup' => 'transparansi'],
        'apbdes.publikasi' => ['nama' => 'Mempublikasikan APBDes', 'grup' => 'transparansi'],
        'statistik.kelola' => ['nama' => 'Mengelola statistik desa', 'grup' => 'transparansi'],
        'bumdes.kelola' => ['nama' => 'Mengelola unit usaha dan kinerja BUMDes', 'grup' => 'transparansi'],
        'bumdes.publikasi' => ['nama' => 'Mempublikasikan kinerja BUMDes', 'grup' => 'transparansi'],
        'pengguna.lihat' => ['nama' => 'Melihat daftar pengguna', 'grup' => 'pengguna'],
        'pengguna.verifikasi' => ['nama' => 'Memvalidasi NIK warga', 'grup' => 'pengguna'],
        'pengguna.kelola' => ['nama' => 'Mengelola akun dan peran', 'grup' => 'pengguna'],
        'data_pribadi.kelola' => ['nama' => 'Menangani permintaan hak subjek data', 'grup' => 'pengguna'],
        'pengaturan.kelola' => ['nama' => 'Mengubah pengaturan situs', 'grup' => 'sistem'],
        'audit.lihat' => ['nama' => 'Melihat audit log', 'grup' => 'sistem'],
    ];

    /** @var array<string, array{nama: string, deskripsi: string, izin: array<int, string>|string}> */
    private const PERAN = [
        Role::WARGA => [
            'nama' => 'Warga Terdaftar',
            'deskripsi' => 'Penduduk desa yang dapat mengajukan layanan dan mengirim pengaduan.',
            'izin' => [],
        ],
        Role::OPERATOR => [
            'nama' => 'Operator Desa',
            'deskripsi' => 'Mengelola konten serta memverifikasi berkas permohonan.',
            'izin' => [
                'dashboard.lihat', 'konten.kelola', 'permohonan.lihat', 'permohonan.verifikasi',
                'permohonan.buat_loket', 'pengaduan.lihat', 'apbdes.kelola', 'statistik.kelola',
                'bumdes.kelola', 'pengguna.lihat', 'pengguna.verifikasi',
            ],
        ],
        Role::VERIFIKATOR => [
            'nama' => 'Verifikator (Kasi/Kaur)',
            'deskripsi' => 'Memeriksa substansi permohonan dan mendisposisi pengaduan.',
            'izin' => [
                'dashboard.lihat', 'konten.kelola', 'konten.terbit', 'permohonan.lihat',
                'permohonan.verifikasi', 'permohonan.setujui', 'permohonan.buat_loket',
                'pengaduan.lihat', 'pengaduan.kelola', 'pengaduan.disposisi', 'laporan.lihat',
            ],
        ],
        Role::SEKDES => [
            'nama' => 'Sekretaris Desa',
            'deskripsi' => 'Penyetuju akhir permohonan, pengelola PPID dan transparansi anggaran.',
            'izin' => [
                'dashboard.lihat', 'konten.kelola', 'konten.terbit', 'permohonan.lihat',
                'permohonan.verifikasi', 'permohonan.setujui', 'permohonan.tanda_tangan',
                'permohonan.buat_loket', 'surat.batalkan', 'laporan.lihat', 'pengaduan.lihat', 'pengaduan.kelola',
                'pengaduan.disposisi', 'pengaduan.moderasi', 'apbdes.kelola', 'apbdes.publikasi',
                'statistik.kelola', 'bumdes.kelola', 'bumdes.publikasi',
                'pengguna.lihat', 'pengguna.verifikasi', 'data_pribadi.kelola', 'audit.lihat',
            ],
        ],
        Role::KADES => [
            'nama' => 'Kepala Desa',
            'deskripsi' => 'Penandatangan surat dan pemantau kinerja layanan.',
            'izin' => [
                'dashboard.lihat', 'permohonan.lihat', 'permohonan.setujui', 'permohonan.tanda_tangan',
                'permohonan.buat_loket', 'surat.batalkan', 'laporan.lihat', 'pengaduan.lihat', 'pengaduan.disposisi',
                'pengaduan.kelola', 'audit.lihat',
            ],
        ],
        Role::ADMIN => [
            'nama' => 'Administrator Sistem',
            'deskripsi' => 'Pengelola teknis: pengguna, peran, pengaturan, dan audit.',
            'izin' => '*',
        ],
    ];

    public function run(): void
    {
        foreach (self::IZIN as $kode => $atribut) {
            Permission::updateOrCreate(['kode' => $kode], $atribut);
        }

        foreach (self::PERAN as $kode => $atribut) {
            $peran = Role::updateOrCreate(['kode' => $kode], [
                'nama' => $atribut['nama'],
                'deskripsi' => $atribut['deskripsi'],
                'bawaan' => true,
            ]);

            $izin = $atribut['izin'] === '*'
                ? Permission::pluck('id')
                : Permission::whereIn('kode', $atribut['izin'])->pluck('id');

            $peran->permissions()->sync($izin);
        }
    }
}
