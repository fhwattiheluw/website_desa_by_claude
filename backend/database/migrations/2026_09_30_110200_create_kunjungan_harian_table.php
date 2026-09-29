<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hitungan kunjungan agregat (REQ-SW-006).
 *
 * Satu baris mewakili satu jalur laman pada satu tanggal. Tidak ada kolom yang
 * menunjuk pengunjung: tanpa alamat IP, tanpa pengenal sesi, tanpa perujuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungan_harian', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->date('tanggal');
            $tabel->string('jalur', 120);
            $tabel->unsignedBigInteger('jumlah')->default(0);
            $tabel->timestamps();

            $tabel->unique(['tanggal', 'jalur']);
            $tabel->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungan_harian');
    }
};
