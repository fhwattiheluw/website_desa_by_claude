<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-KNT-001..014. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('kategori')->nullOnDelete();
            $table->string('nama', 120);
            $table->string('slug', 140)->unique();
            $table->string('deskripsi', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('konten', function (Blueprint $table) {
            $table->id();
            $table->string('tipe', 20)->index();          // berita|artikel|pengumuman|agenda
            $table->foreignId('kategori_id')->nullable()->constrained('kategori')->nullOnDelete();
            $table->foreignId('penulis_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gambar_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('judul', 200);
            $table->string('slug', 220)->unique();
            $table->string('ringkasan', 500)->nullable();
            $table->longText('isi')->nullable();
            $table->string('status', 20)->default('draf')->index();  // draf|review|terbit|arsip
            $table->boolean('sorotan')->default(false);
            $table->unsignedBigInteger('dibaca')->default(0);
            $table->json('tag')->nullable();
            $table->timestamp('terbit_pada')->nullable()->index();
            $table->timestamp('kedaluwarsa_pada')->nullable();
            $table->timestamp('mulai_pada')->nullable();   // agenda
            $table->timestamp('selesai_pada')->nullable(); // agenda
            $table->string('lokasi', 200)->nullable();     // agenda
            $table->string('penyelenggara', 150)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('konten_versi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('konten_id')->constrained('konten')->cascadeOnDelete();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('versi');
            $table->string('judul', 200);
            $table->longText('isi')->nullable();
            $table->timestamps();
            $table->unique(['konten_id', 'versi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konten_versi');
        Schema::dropIfExists('konten');
        Schema::dropIfExists('kategori');
    }
};
