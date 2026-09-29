<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotifikasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Pemulihan kata sandi mandiri (REQ-F-USR-006).
 *
 * Tautan bersifat sekali pakai dan kedaluwarsa dalam 60 menit sesuai
 * pengaturan broker kata sandi pada config/auth.php.
 */
class KataSandiController extends Controller
{
    public function __construct(
        private readonly NotifikasiService $notifikasi,
        private readonly AuditLogger $audit,
    ) {}

    public function kirimTautan(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $pengguna = User::where('email', $data['email'])->first();

        if ($pengguna && $pengguna->status_akun !== User::NONAKTIF) {
            $token = Password::createToken($pengguna);
            $tautan = rtrim((string) config('app.frontend_url'), '/')
                .'/atur-ulang-kata-sandi?token='.$token.'&email='.urlencode($pengguna->email);

            $this->notifikasi->kirim('email', $pengguna->email, 'pemulihan_kata_sandi', [
                'nama' => $pengguna->name,
                'tautan' => $tautan,
                'berlaku_menit' => config('auth.passwords.users.expire', 60),
            ]);

            $this->audit->catat('minta_pemulihan_sandi', 'User', $pengguna->id);
        }

        // Jawaban dibuat seragam agar penyerang tidak dapat memetakan surel
        // mana yang terdaftar (REQ-NF-SEC-001).
        return response()->json([
            'pesan' => 'Bila surel tersebut terdaftar, kami telah mengirimkan tautan pemulihan kata sandi. '
                .'Tautan berlaku '.config('auth.passwords.users.expire', 60).' menit.',
        ]);
    }

    public function aturUlang(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
        ], [
            'password.regex' => 'Kata sandi harus memuat huruf dan angka.',
        ]);

        $hasil = Password::reset($data, function (User $pengguna, string $kataSandi) {
            $pengguna->forceFill([
                'password' => $kataSandi,
                'gagal_masuk' => 0,
                'terkunci_sampai' => null,
            ])->save();

            // Seluruh sesi lama dicabut agar kredensial lama tidak dapat dipakai lagi.
            $pengguna->tokens()->delete();

            $this->audit->catat('pemulihan_sandi_selesai', 'User', $pengguna->id);
        });

        if ($hasil !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'Tautan pemulihan tidak berlaku atau sudah kedaluwarsa. Silakan ajukan permintaan baru.',
            ]);
        }

        return response()->json(['pesan' => 'Kata sandi berhasil diubah. Silakan masuk dengan kata sandi baru.']);
    }
}
