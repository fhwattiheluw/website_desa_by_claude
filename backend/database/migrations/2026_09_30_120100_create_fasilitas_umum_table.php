<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titik fasilitas umum desa untuk ditandai pada peta wilayah (REQ-F-BRD-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fasilitas_umum', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('nama', 150);
            // Kantor, pendidikan, kesehatan, ibadah, olahraga, ekonomi, lainnya.
            $tabel->string('jenis', 40);
            $tabel->string('alamat', 250)->nullable();
            $tabel->string('keterangan', 250)->nullable();
            // Presisi tujuh angka di belakang koma cukup untuk ketelitian
            // sekitar satu sentimeter, jauh melebihi kebutuhan penanda peta.
            $tabel->decimal('lat', 10, 7);
            $tabel->decimal('lng', 10, 7);
            $tabel->unsignedSmallInteger('urutan')->default(0);
            $tabel->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fasilitas_umum');
    }
};
