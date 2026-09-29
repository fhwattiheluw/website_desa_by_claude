<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda peninjauan akun tidak aktif (REQ-NF-CMP-004): akun yang tidak dipakai
 * 36 bulan ditandai untuk ditinjau petugas, bukan langsung dinonaktifkan, agar
 * warga yang jarang memakai portal tidak kehilangan akses tanpa pemeriksaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabel) {
            $tabel->timestamp('tinjauan_akun_pada')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabel) {
            $tabel->dropColumn('tinjauan_akun_pada');
        });
    }
};
