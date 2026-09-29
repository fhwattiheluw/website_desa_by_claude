<?php

namespace Tests;

use App\Models\JenisLayanan;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\JenisLayananSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Menyiapkan peran, izin, pengaturan desa, dan katalog layanan. */
    protected function siapkanReferensi(): void
    {
        $this->seed([RoleSeeder::class, PengaturanSeeder::class, JenisLayananSeeder::class]);
    }

    protected function buatPengguna(string $peran, array $atribut = []): User
    {
        return User::factory()->create([
            'role_id' => Role::where('kode', $peran)->value('id'),
            'status_akun' => User::AKTIF,
            'verifikasi_nik_at' => now(),
            'consent_at' => now(),
            ...$atribut,
        ]);
    }

    protected function buatWarga(array $atribut = []): User
    {
        return $this->buatPengguna(Role::WARGA, [
            'nik' => (string) random_int(3200000000000000, 3299999999999999),
            ...$atribut,
        ]);
    }

    protected function layanan(string $kode = 'SKTM'): JenisLayanan
    {
        return JenisLayanan::where('kode', $kode)->firstOrFail();
    }

    /** Data formulir contoh yang memenuhi seluruh kolom wajib layanan. */
    protected function dataFormulir(JenisLayanan $layanan): array
    {
        $data = [];

        foreach ($layanan->kolom_formulir as $kolom) {
            $data[$kolom['nama']] = match ($kolom['tipe'] ?? 'teks') {
                'nik', 'kk' => '3203014507840003',
                'angka' => 1500000,
                'tanggal' => '1984-07-05',
                'centang' => true,
                'telepon' => '081234567890',
                'pilihan' => $kolom['pilihan'][0] ?? 'Lainnya',
                default => 'Contoh '.$kolom['label'],
            };
        }

        return $data;
    }
}
