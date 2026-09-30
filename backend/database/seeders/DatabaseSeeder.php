<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PengaturanSeeder::class,
            JenisLayananSeeder::class,
            HariLiburSeeder::class,
            PenggunaSeeder::class,
            LembagaSeeder::class,
            FasilitasUmumSeeder::class,
            KontenSeeder::class,
            TransparansiSeeder::class,
        ]);
    }
}
