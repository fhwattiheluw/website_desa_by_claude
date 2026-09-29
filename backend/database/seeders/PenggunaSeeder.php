<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun demonstrasi untuk setiap peran.
 *
 * Kata sandi bawaan hanya untuk lingkungan pengembangan dan WAJIB diganti
 * sebelum sistem dipakai (lihat daftar periksa prarilis, Lampiran C SRS).
 */
class PenggunaSeeder extends Seeder
{
    private const SANDI_DEMO = 'sidesa2026';

    public function run(): void
    {
        $akun = [
            ['kades', 'Hartono Wijaya', 'kades@sukamaju.desa.id', Role::KADES],
            ['sekdes', 'Sri Rahayu', 'sekdes@sukamaju.desa.id', Role::SEKDES],
            ['verifikator', 'Budi Santoso', 'kasi@sukamaju.desa.id', Role::VERIFIKATOR],
            ['operator', 'Dedi Kurniawan', 'operator@sukamaju.desa.id', Role::OPERATOR],
            ['admin', 'Administrator SIDESA', 'admin@sukamaju.desa.id', Role::ADMIN],
        ];

        foreach ($akun as [$label, $nama, $email, $peran]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $nama,
                'role_id' => Role::where('kode', $peran)->value('id'),
                'password' => self::SANDI_DEMO,
                'telepon' => '0812'.str_pad((string) crc32($label) % 100000000, 8, '0', STR_PAD_LEFT),
                'status_akun' => User::AKTIF,
                'verifikasi_nik_at' => now(),
                'consent_at' => now(),
            ]);
        }

        // Warga terverifikasi (dapat langsung mengajukan layanan).
        User::updateOrCreate(['email' => 'sari.wulandari@contoh.id'], [
            'name' => 'Sari Wulandari',
            'role_id' => Role::where('kode', Role::WARGA)->value('id'),
            'password' => self::SANDI_DEMO,
            'nik' => '3203014507840003',
            'telepon' => '081234567890',
            'alamat' => 'Dusun Mekar RT 002 RW 001',
            'tempat_lahir' => 'Cianjur',
            'tanggal_lahir' => '1984-07-05',
            'jenis_kelamin' => 'P',
            'pekerjaan' => 'Ibu Rumah Tangga',
            'status_akun' => User::AKTIF,
            'verifikasi_nik_at' => now(),
            'consent_at' => now(),
        ]);

        // Warga yang masih menunggu validasi NIK oleh operator (REQ-F-USR-003).
        User::updateOrCreate(['email' => 'rina.pertiwi@contoh.id'], [
            'name' => 'Rina Pertiwi',
            'role_id' => Role::where('kode', Role::WARGA)->value('id'),
            'password' => self::SANDI_DEMO,
            'nik' => '3203012209910007',
            'telepon' => '081298765432',
            'alamat' => 'Dusun Sukasari RT 004 RW 003',
            'status_akun' => User::BELUM_VERIFIKASI,
            'consent_at' => now(),
        ]);
    }
}
