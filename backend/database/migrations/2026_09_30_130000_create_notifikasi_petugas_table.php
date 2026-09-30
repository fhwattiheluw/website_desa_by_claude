<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifikasi dalam aplikasi bagi petugas (REQ-F-NOT-005) dan preferensi kanal
 * notifikasi bagi warga (REQ-F-NOT-006).
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Satu baris per petugas, bukan satu baris peristiwa yang dibagi
         * bersama. Desa hanya memiliki segelintir petugas, sehingga penggandaan
         * baris jauh lebih murah daripada menghitung "belum dibaca" dari
         * perpotongan izin setiap kali lonceng dibuka.
         */
        Schema::create('notifikasi_petugas', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('pengguna_id')->constrained('users')->cascadeOnDelete();
            $tabel->string('jenis', 40);
            $tabel->string('judul', 150);
            $tabel->string('ringkasan', 250)->nullable();
            $tabel->string('tautan', 200)->nullable();
            $tabel->string('entitas', 40)->nullable();
            $tabel->string('entitas_id', 40)->nullable();
            $tabel->timestamp('dibaca_pada')->nullable();
            $tabel->timestamp('created_at')->nullable();

            $tabel->index(['pengguna_id', 'dibaca_pada']);
            $tabel->index('created_at');
        });

        Schema::table('users', function (Blueprint $tabel) {
            $tabel->json('preferensi_notifikasi')->nullable()->after('consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabel) {
            $tabel->dropColumn('preferensi_notifikasi');
        });

        Schema::dropIfExists('notifikasi_petugas');
    }
};
