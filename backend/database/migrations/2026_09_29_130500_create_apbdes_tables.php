<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-APB-001..009, BR-08. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahun_anggaran', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun')->unique();
            $table->boolean('dipublikasikan')->default(false)->index();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dipublikasikan_pada')->nullable();
            $table->string('catatan', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('item_apbdes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_anggaran_id')->constrained('tahun_anggaran')->cascadeOnDelete();
            $table->string('jenis', 20)->index();       // pendapatan|belanja|pembiayaan
            $table->string('bidang', 150);
            $table->string('kegiatan', 200)->nullable();
            $table->decimal('pagu', 18, 2)->default(0);
            $table->decimal('realisasi', 18, 2)->default(0);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('dokumen_anggaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_anggaran_id')->constrained('tahun_anggaran')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('judul', 200);
            $table->string('jenis_dokumen', 60); // apbdes|apbdes_perubahan|lpj|realisasi
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_anggaran');
        Schema::dropIfExists('item_apbdes');
        Schema::dropIfExists('tahun_anggaran');
    }
};
