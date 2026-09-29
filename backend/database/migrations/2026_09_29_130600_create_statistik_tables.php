<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-STA-001..006, BR-15. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_statistik', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 80);
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('sumber_data', 150)->nullable();
            $table->boolean('aktif')->default(false);
            $table->timestamps();
        });

        Schema::create('item_statistik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_statistik_id')->constrained('periode_statistik')->cascadeOnDelete();
            $table->string('kelompok', 40)->index(); // jenis_kelamin|usia|pendidikan|pekerjaan|agama|dusun
            $table->string('label', 120);
            $table->unsignedInteger('jumlah')->default(0);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_statistik');
        Schema::dropIfExists('periode_statistik');
    }
};
