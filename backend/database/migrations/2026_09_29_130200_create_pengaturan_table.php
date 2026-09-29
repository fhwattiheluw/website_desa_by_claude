<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-BRD-005, REQ-F-ADM-002: profil & pengaturan situs dapat disunting tanpa ubah kode. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 80)->unique();
            $table->text('nilai')->nullable();
            $table->string('grup', 40)->default('umum')->index();
            $table->string('tipe', 20)->default('teks');
            $table->string('label', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
