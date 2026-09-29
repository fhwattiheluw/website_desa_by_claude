<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-ADM-004..005, REQ-F-NOT-003..004, REQ-NF-SEC-013. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aktor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 60)->index();
            $table->string('entitas', 60)->index();
            $table->string('entitas_id', 64)->nullable();
            $table->json('sebelum')->nullable();
            $table->json('sesudah')->nullable();
            $table->string('alamat_ip', 45)->nullable();
            $table->string('agen', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('notifikasi_log', function (Blueprint $table) {
            $table->id();
            $table->string('kanal', 20)->index();   // email|whatsapp|dalam_aplikasi
            $table->string('tujuan', 255);
            $table->string('templat', 80);
            $table->json('data')->nullable();
            $table->string('status', 20)->default('antre')->index();
            $table->unsignedTinyInteger('percobaan')->default(0);
            $table->text('galat')->nullable();
            $table->timestamp('terkirim_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_log');
        Schema::dropIfExists('audit_log');
    }
};
