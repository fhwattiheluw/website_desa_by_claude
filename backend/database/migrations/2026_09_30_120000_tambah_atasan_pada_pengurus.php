<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atasan langsung setiap perangkat desa (REQ-F-BRD-003).
 *
 * Struktur organisasi perlu ditampilkan sebagai bagan, dan bagan memerlukan
 * hubungan atasan–bawahan yang dinyatakan tegas. Menyimpulkannya dari teks
 * jabatan akan rapuh: setiap desa menamai jabatannya sedikit berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengurus', function (Blueprint $tabel) {
            // Dikosongkan saat atasan dihapus agar bagan tidak menggantung pada
            // baris yang sudah tidak ada.
            $tabel->foreignId('atasan_id')->nullable()->after('lembaga_id')
                ->constrained('pengurus')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pengurus', function (Blueprint $tabel) {
            $tabel->dropConstrainedForeignId('atasan_id');
        });
    }
};
