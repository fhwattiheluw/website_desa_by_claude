<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-PID-*, REQ-F-POT-*, REQ-F-LMB-*. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produk_hukum', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 40)->index();  // perdes|perkades|sk_kades
            $table->string('nomor', 40);
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('judul', 250);
            $table->string('tentang', 250)->nullable();
            $table->boolean('berlaku')->default(true);
            $table->foreignId('dicabut_oleh_id')->nullable()->constrained('produk_hukum')->nullOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
            $table->unique(['jenis', 'nomor', 'tahun']);
        });

        Schema::create('informasi_publik', function (Blueprint $table) {
            $table->id();
            $table->string('klasifikasi', 20)->index(); // berkala|serta_merta|setiap_saat
            $table->string('judul', 250);
            $table->text('ringkasan')->nullable();
            $table->string('penanggung_jawab', 150)->nullable();
            $table->string('periode_terbit', 60)->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('permohonan_informasi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_tiket', 40)->unique();
            $table->foreignId('pemohon_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama', 150);
            $table->text('kontak');
            $table->text('informasi_diminta');
            $table->string('tujuan_penggunaan', 250)->nullable();
            $table->string('status', 20)->default('baru')->index();
            $table->text('jawaban')->nullable();
            $table->timestamp('tenggat_jawaban')->nullable();
            $table->timestamps();
        });

        Schema::create('umkm', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_usaha', 150);
            $table->string('slug', 170)->unique();
            $table->string('pemilik', 150);
            $table->string('kategori', 60)->index();
            $table->text('deskripsi')->nullable();
            $table->string('telepon', 20)->nullable();
            $table->string('alamat', 250)->nullable();
            $table->boolean('consent_kontak')->default(false); // BR-16
            $table->string('status', 20)->default('menunggu')->index();
            $table->timestamps();
        });

        Schema::create('wisata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('nama', 150);
            $table->string('slug', 170)->unique();
            $table->text('deskripsi')->nullable();
            $table->string('jam_operasional', 100)->nullable();
            $table->string('tarif', 100)->nullable();
            $table->string('alamat', 250)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('lembaga', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('slug', 170)->unique();
            $table->string('jenis', 40)->index(); // pemerintah_desa|bpd|lpm|pkk|karang_taruna|rt_rw|posyandu|linmas
            $table->text('deskripsi')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('pengurus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('foto_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('nama', 150);
            $table->string('jabatan', 120);
            $table->string('wilayah', 100)->nullable();  // RT/RW
            $table->year('masa_jabatan_mulai')->nullable();
            $table->year('masa_jabatan_selesai')->nullable();
            $table->text('tugas_pokok')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengurus');
        Schema::dropIfExists('lembaga');
        Schema::dropIfExists('wisata');
        Schema::dropIfExists('umkm');
        Schema::dropIfExists('permohonan_informasi');
        Schema::dropIfExists('informasi_publik');
        Schema::dropIfExists('produk_hukum');
    }
};
