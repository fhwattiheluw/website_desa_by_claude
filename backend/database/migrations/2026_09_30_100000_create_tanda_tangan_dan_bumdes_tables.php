<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fase 4: tanda tangan elektronik (REQ-F-SRT-017) dan profil BUMDes (REQ-F-POT-003). */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Spesimen tanda tangan hanya boleh dimiliki pejabat penanda tangan.
         * Berkasnya disimpan pada disk privat, tidak pernah disajikan publik.
         */
        Schema::create('spesimen_tanda_tangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('disk', 40)->default('local');
            $table->char('hash_berkas', 64);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::table('surat_terbit', function (Blueprint $table) {
            // internal = spesimen tanda tangan; tte = penyedia tersertifikasi.
            $table->string('metode_tanda_tangan', 20)->default('internal')->after('penandatangan_id');
            $table->json('bukti_tte')->nullable()->after('metode_tanda_tangan');
        });

        Schema::create('unit_usaha', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('nama', 150);
            $table->string('slug', 170)->unique();
            $table->text('deskripsi')->nullable();
            $table->string('penanggung_jawab', 150)->nullable();
            $table->string('kontak', 60)->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('kinerja_bumdes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun')->unique();
            $table->decimal('pendapatan', 18, 2)->default(0);
            $table->decimal('laba_bersih', 18, 2)->default(0);
            $table->decimal('kontribusi_pades', 18, 2)->default(0);
            $table->string('catatan', 255)->nullable();
            $table->boolean('dipublikasikan')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kinerja_bumdes');
        Schema::dropIfExists('unit_usaha');

        Schema::table('surat_terbit', function (Blueprint $table) {
            $table->dropColumn(['metode_tanda_tangan', 'bukti_tte']);
        });

        Schema::dropIfExists('spesimen_tanda_tangan');
    }
};
