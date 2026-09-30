<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-GAL-006: penyematan video dari penyedia eksternal melalui URL. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_album', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('album_id')->constrained('album')->cascadeOnDelete();
            $tabel->string('judul', 160);
            $tabel->string('penyedia', 20);
            // Pengenal video pada penyedia, bukan alamat lengkap. Alamat sematan
            // dibentuk ulang dari sini agar tidak ada masukan pengguna yang
            // langsung menjadi atribut src.
            $tabel->string('id_video', 64);
            $tabel->string('url_asli', 255);
            $tabel->string('thumbnail_path', 255)->nullable();
            $tabel->unsignedSmallInteger('urutan')->default(0);
            $tabel->timestamps();

            $tabel->index(['album_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_album');
    }
};
