<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Otorisasi diberlakukan di sisi server pada setiap titik akhir
 * (REQ-F-USR-012) — menyembunyikan menu di antarmuka saja tidak memadai.
 */
class PastikanIzin
{
    public function handle(Request $request, Closure $next, string ...$izin): Response
    {
        $pengguna = $request->user();

        if (! $pengguna) {
            return response()->json(['pesan' => 'Anda harus masuk terlebih dahulu.'], 401);
        }

        if ($pengguna->status_akun === User::NONAKTIF) {
            return response()->json(['pesan' => 'Akun Anda dinonaktifkan. Hubungi administrator desa.'], 403);
        }

        $pengguna->loadMissing('role.permissions');

        foreach ($izin as $kode) {
            if ($pengguna->punyaIzin($kode)) {
                return $next($request);
            }
        }

        return response()->json([
            'pesan' => 'Anda tidak memiliki hak akses untuk tindakan ini.',
            'izin_dibutuhkan' => $izin,
        ], 403);
    }
}
