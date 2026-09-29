<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-ADU-001..011, BR-10, BR-11. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaduan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_tiket', 40)->unique();
            $table->string('kode_lacak', 20)->unique();
            $table->foreignId('pelapor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_pelapor', 150)->nullable();
            $table->text('kontak_pelapor');   // terenkripsi
            $table->string('kategori', 60)->index();
            $table->string('judul', 200);
            $table->text('uraian');
            $table->string('lokasi', 200)->nullable();
            $table->date('tanggal_kejadian')->nullable();
            $table->string('status', 20)->default('baru')->index();
            $table->foreignId('didisposisi_ke')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_disposisi')->nullable();
            $table->timestamp('tenggat_tanggapan')->nullable()->index();
            $table->boolean('tampil_publik')->default(false);
            $table->boolean('anonim')->default(false);
            $table->timestamps();
        });

        Schema::create('tanggapan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengaduan_id')->constrained('pengaduan')->cascadeOnDelete();
            $table->foreignId('aktor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('isi');
            $table->boolean('internal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanggapan');
        Schema::dropIfExists('pengaduan');
    }
};
