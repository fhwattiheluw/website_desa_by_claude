<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-SRT-001..027, BR-01..BR-07. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_layanan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 15)->unique();
            $table->string('nama', 150);
            $table->string('slug', 170)->unique();
            $table->text('deskripsi')->nullable();
            $table->json('persyaratan');          // daftar berkas wajib
            $table->json('kolom_formulir');       // definisi kolom dinamis (REQ-F-SRT-003)
            $table->unsignedTinyInteger('sla_hari_kerja')->default(1);
            $table->string('format_nomor', 120)->default('{urut}/{kode}/{romawi_bulan}/{tahun}');
            $table->string('templat', 60)->default('surat.umum');
            $table->boolean('aktif')->default(true)->index();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('permohonan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_tiket', 40)->unique();
            $table->foreignId('pemohon_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('jenis_layanan_id')->constrained('jenis_layanan');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete(); // kanal loket
            $table->foreignId('verifikator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('penyetuju_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data_formulir');
            $table->string('status', 20)->default('draf')->index();
            $table->string('kanal', 10)->default('daring');
            $table->text('alasan')->nullable();
            $table->timestamp('tenggat_sla')->nullable()->index();
            $table->timestamp('diajukan_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamp('lampiran_dibersihkan_pada')->nullable(); // REQ-F-SRT-026
            $table->unsignedTinyInteger('kepuasan')->nullable();        // REQ-F-SRT-027
            $table->timestamps();
            $table->index(['status', 'tenggat_sla']);
        });

        Schema::create('riwayat_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $table->foreignId('aktor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dari', 20)->nullable();
            $table->string('ke', 20);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('lampiran', function (Blueprint $table) {
            $table->id();
            $table->morphs('lampiranable');
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('label', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('surat_terbit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permohonan_id')->unique()->constrained('permohonan')->cascadeOnDelete();
            $table->foreignId('penandatangan_id')->constrained('users');
            $table->string('nomor_surat', 60)->unique();
            $table->date('tanggal_terbit');
            $table->string('path_pdf', 255);
            $table->char('hash_dokumen', 64);
            $table->string('kode_verifikasi', 32)->unique();
            $table->string('status_keabsahan', 15)->default('sah');
            $table->text('alasan_pembatalan')->nullable();
            $table->timestamps();
        });

        // Pencacah nomor urut, dikunci saat transaksi agar tidak duplikat (REQ-F-SRT-014).
        Schema::create('urutan_nomor', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 80)->unique();
            $table->unsignedInteger('urut')->default(0);
            $table->timestamps();
        });

        Schema::create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->string('keterangan', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hari_libur');
        Schema::dropIfExists('urutan_nomor');
        Schema::dropIfExists('surat_terbit');
        Schema::dropIfExists('lampiran');
        Schema::dropIfExists('riwayat_status');
        Schema::dropIfExists('permohonan');
        Schema::dropIfExists('jenis_layanan');
    }
};
