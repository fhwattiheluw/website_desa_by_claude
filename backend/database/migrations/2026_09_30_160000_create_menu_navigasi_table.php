<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-ADM-003: menu navigasi yang dapat diatur tanpa mengubah kode. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_navigasi', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('induk_id')->nullable()->constrained('menu_navigasi')->cascadeOnDelete();
            $tabel->string('label', 60);
            // Butir bertingkat pertama boleh tanpa tautan bila hanya menaungi anak.
            $tabel->string('tautan', 255)->nullable();
            $tabel->unsignedSmallInteger('urutan')->default(0);
            $tabel->boolean('aktif')->default(true);
            $tabel->timestamps();

            $tabel->index(['induk_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_navigasi');
    }
};
