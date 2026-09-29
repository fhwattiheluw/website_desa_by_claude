<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-F-USR-001..003, REQ-NF-SEC-006, BR-12. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained()->nullOnDelete();
            // NIK disimpan terenkripsi; kolom hash dipakai untuk pencarian & keunikan (BR-12).
            $table->text('nik')->nullable()->after('email');
            $table->string('nik_hash', 64)->nullable()->unique()->after('nik');
            $table->string('telepon', 20)->nullable()->after('nik_hash');
            $table->string('alamat', 255)->nullable()->after('telepon');
            $table->string('tempat_lahir', 100)->nullable()->after('alamat');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable()->after('tanggal_lahir');
            $table->string('pekerjaan', 80)->nullable()->after('jenis_kelamin');
            $table->string('status_akun', 20)->default('belum_verifikasi')->index()->after('pekerjaan');
            $table->timestamp('verifikasi_nik_at')->nullable()->after('status_akun');
            $table->timestamp('consent_at')->nullable()->after('verifikasi_nik_at');
            $table->timestamp('last_login_at')->nullable()->after('consent_at');
            $table->unsignedTinyInteger('gagal_masuk')->default(0)->after('last_login_at');
            $table->timestamp('terkunci_sampai')->nullable()->after('gagal_masuk');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn([
                'nik', 'nik_hash', 'telepon', 'alamat', 'tempat_lahir', 'tanggal_lahir',
                'jenis_kelamin', 'pekerjaan', 'status_akun', 'verifikasi_nik_at',
                'consent_at', 'last_login_at', 'gagal_masuk', 'terkunci_sampai',
            ]);
        });
    }
};
