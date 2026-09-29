<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-GAL-001..008. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('album', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('slug', 170)->unique();
            $table->text('deskripsi')->nullable();
            $table->date('tanggal_kegiatan')->nullable();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->nullable()->constrained('album')->nullOnDelete();
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_asli', 255);
            $table->string('path', 255);
            $table->string('disk', 40)->default('public');
            $table->string('mime', 100);
            $table->unsignedBigInteger('ukuran');
            $table->string('alt', 255)->nullable();
            $table->string('koleksi', 40)->default('umum')->index();
            $table->boolean('privat')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('album');
    }
};
