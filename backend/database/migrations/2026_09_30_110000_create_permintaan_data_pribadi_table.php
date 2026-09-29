<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hak subjek data atas data pribadinya (REQ-F-USR-013, UU 27/2022).
 *
 * Pengunduhan dilayani seketika, sedangkan penghapusan melewati peninjauan
 * petugas karena sebagian arsip layanan wajib disimpan menurut ketentuan
 * kearsipan. Tabel ini menjadi berkas resmi permintaan beserta keputusannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_data_pribadi', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('pengguna_id')->constrained('users')->cascadeOnDelete();
            $tabel->string('jenis', 20)->default('hapus');
            $tabel->string('status', 20)->default('menunggu');
            $tabel->text('alasan')->nullable();
            $tabel->text('catatan_petugas')->nullable();
            $tabel->foreignId('ditindak_oleh')->nullable()->constrained('users')->nullOnDelete();
            $tabel->timestamp('ditindak_pada')->nullable();
            // Tenggat jawaban pengendali data kepada subjek data.
            $tabel->timestamp('tenggat_jawaban')->nullable();
            $tabel->timestamps();

            $tabel->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_data_pribadi');
    }
};
