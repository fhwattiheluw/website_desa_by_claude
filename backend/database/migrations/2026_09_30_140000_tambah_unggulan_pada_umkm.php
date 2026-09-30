<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-POT-007: produk unggulan desa yang tampil bergilir pada beranda. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umkm', function (Blueprint $tabel) {
            $tabel->boolean('unggulan')->default(false)->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('umkm', function (Blueprint $tabel) {
            $tabel->dropColumn('unggulan');
        });
    }
};
