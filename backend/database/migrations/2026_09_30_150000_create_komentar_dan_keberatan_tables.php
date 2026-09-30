<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komentar artikel bermoderasi (REQ-F-KNT-013) dan keberatan atas penolakan
 * permohonan informasi publik (REQ-F-PID-007).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komentar', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('konten_id')->constrained('konten')->cascadeOnDelete();
            $tabel->foreignId('penulis_id')->nullable()->constrained('users')->nullOnDelete();
            $tabel->string('nama', 150);
            $tabel->text('isi');
            // Moderasi wajib: bawaannya menunggu, tidak pernah langsung tayang.
            $tabel->string('status', 20)->default('menunggu')->index();
            $tabel->foreignId('dimoderasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $tabel->timestamp('dimoderasi_pada')->nullable();
            $tabel->string('alasan_penolakan', 250)->nullable();
            // Dipakai membatasi penyalahgunaan, tidak pernah ditampilkan publik.
            $tabel->string('alamat_ip', 45)->nullable();
            $tabel->timestamps();

            $tabel->index(['konten_id', 'status']);
        });

        Schema::table('permohonan_informasi', function (Blueprint $tabel) {
            // Kode lacak menyusul pola pengaduan: pemohon dapat memeriksa
            // sendiri status permohonannya tanpa perlu berakun.
            $tabel->string('kode_lacak', 12)->nullable()->unique()->after('nomor_tiket');
        });

        Schema::create('keberatan_informasi', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('permohonan_informasi_id')->constrained('permohonan_informasi')->cascadeOnDelete();
            $tabel->text('alasan');
            $tabel->string('status', 20)->default('diajukan')->index();
            $tabel->text('tanggapan')->nullable();
            $tabel->foreignId('ditanggapi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $tabel->timestamp('ditanggapi_pada')->nullable();
            $tabel->timestamp('tenggat_tanggapan')->nullable();
            $tabel->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keberatan_informasi');

        Schema::table('permohonan_informasi', function (Blueprint $tabel) {
            $tabel->dropColumn('kode_lacak');
        });

        Schema::dropIfExists('komentar');
    }
};
