<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotifikasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Pembuktian kepemilikan kontak melalui tautan verifikasi surel
 * (REQ-F-USR-002). Tautan ditandatangani digital dan berlaku 24 jam.
 */
class VerifikasiSurelController extends Controller
{
    public const JAM_BERLAKU = 24;

    public function __construct(
        private readonly NotifikasiService $notifikasi,
        private readonly AuditLogger $audit,
    ) {}

    public static function tautanUntuk(User $pengguna): string
    {
        return URL::temporarySignedRoute('verifikasi.surel', now()->addHours(self::JAM_BERLAKU), [
            'pengguna' => $pengguna->id,
            'sidik' => sha1($pengguna->email),
        ]);
    }

    public function kirim(User $pengguna): void
    {
        $this->notifikasi->kirim('email', $pengguna->email, 'verifikasi_surel', [
            'nama' => $pengguna->name,
            'tautan' => self::tautanUntuk($pengguna),
            'berlaku_jam' => self::JAM_BERLAKU,
        ]);
    }

    /** Dipanggil dari tautan pada surel; mengarahkan kembali ke antarmuka. */
    public function verifikasi(Request $request, User $pengguna, string $sidik): RedirectResponse
    {
        $depan = rtrim((string) config('app.frontend_url'), '/');

        if (! hash_equals(sha1($pengguna->email), $sidik)) {
            return redirect()->away($depan.'/masuk?verifikasi=gagal');
        }

        if ($pengguna->email_verified_at === null) {
            $pengguna->forceFill(['email_verified_at' => now()])->save();
            $this->audit->catat('verifikasi_surel', 'User', $pengguna->id);
        }

        return redirect()->away($depan.'/masuk?verifikasi=berhasil');
    }

    /** Pengiriman ulang bila surel pertama tidak sampai. */
    public function kirimUlang(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $pengguna = User::where('email', $data['email'])->first();

        if ($pengguna && $pengguna->email_verified_at === null) {
            $this->kirim($pengguna);
        }

        return response()->json([
            'pesan' => 'Bila surel tersebut terdaftar dan belum terverifikasi, tautan verifikasi baru telah dikirim.',
        ]);
    }
}
