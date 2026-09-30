<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Otentikasi dua faktor berbasis kode sekali pakai (REQ-F-USR-009).
 *
 * Peran yang dapat menandatangani surat, membatalkan surat, atau mengubah hak
 * akses memegang kewenangan yang merugikan bila akunnya diambil alih. Kata
 * sandi saja tidak memadai untuk itu, sehingga peran tersebut wajib melewati
 * satu langkah lagi sebelum sesinya terbit.
 *
 * Kode dikirim melalui surel, bukan aplikasi pengotentikasi: perangkat desa
 * belum tentu memilikinya, sedangkan surel sudah diverifikasi saat pendaftaran.
 */
class OtpService
{
    /** Peran yang wajib melewati dua faktor. */
    public const PERAN_WAJIB = [Role::ADMIN, Role::SEKDES, Role::KADES];

    public const MASA_BERLAKU_MENIT = 10;

    public const MAKS_PERCOBAAN = 5;

    private const PREFIKS = 'otp:';

    public function __construct(
        private readonly NotifikasiService $notifikasi,
        private readonly AuditLogger $audit,
    ) {}

    public function wajibBagi(User $pengguna): bool
    {
        return in_array($pengguna->role?->kode, self::PERAN_WAJIB, true);
    }

    /**
     * Menerbitkan tantangan dan mengirim kodenya.
     *
     * @return array{tantangan: string, kedaluwarsa: string, tujuan: string}
     */
    public function mulai(User $pengguna): array
    {
        $tantangan = Str::random(40);
        $kode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::PREFIKS.$tantangan, [
            'pengguna_id' => $pengguna->id,
            // Disimpan sebagai hash: singgahan bukan tempat menyimpan kode apa adanya.
            'kode' => Hash::make($kode),
            'percobaan' => 0,
        ], now()->addMinutes(self::MASA_BERLAKU_MENIT));

        $this->notifikasi->kirim('email', $pengguna->email, 'kode_masuk', [
            'nama' => $pengguna->name,
            'kode' => $kode,
            'berlaku_menit' => self::MASA_BERLAKU_MENIT,
        ]);

        $this->audit->catat('otp_dikirim', 'User', $pengguna->id);

        return [
            'tantangan' => $tantangan,
            'kedaluwarsa' => now()->addMinutes(self::MASA_BERLAKU_MENIT)->toIso8601String(),
            'tujuan' => $this->samarkanSurel($pengguna->email),
        ];
    }

    /** Memeriksa kode dan mengembalikan penggunanya bila cocok. */
    public function periksa(string $tantangan, string $kode): User
    {
        $kunci = self::PREFIKS.$tantangan;
        $tersimpan = Cache::get($kunci);

        if (! is_array($tersimpan)) {
            throw ValidationException::withMessages([
                'kode' => 'Tantangan tidak ditemukan atau sudah kedaluwarsa. Silakan masuk kembali.',
            ]);
        }

        if ($tersimpan['percobaan'] >= self::MAKS_PERCOBAAN) {
            Cache::forget($kunci);

            throw ValidationException::withMessages([
                'kode' => 'Terlalu banyak percobaan. Silakan masuk kembali untuk memperoleh kode baru.',
            ]);
        }

        if (! Hash::check($kode, $tersimpan['kode'])) {
            // Sisa masa berlaku dipertahankan agar percobaan salah tidak
            // memperpanjang umur tantangan.
            Cache::put($kunci, [...$tersimpan, 'percobaan' => $tersimpan['percobaan'] + 1], now()->addMinutes(self::MASA_BERLAKU_MENIT));

            $this->audit->catat('otp_gagal', 'User', $tersimpan['pengguna_id']);

            throw ValidationException::withMessages(['kode' => 'Kode yang Anda masukkan tidak cocok.']);
        }

        Cache::forget($kunci);

        return User::with('role.permissions')->findOrFail($tersimpan['pengguna_id']);
    }

    /** Menampilkan tujuan pengiriman tanpa membocorkan alamat lengkapnya. */
    private function samarkanSurel(string $surel): string
    {
        [$nama, $ranah] = array_pad(explode('@', $surel, 2), 2, '');

        $awal = mb_substr($nama, 0, 2);

        return $awal.str_repeat('*', max(1, mb_strlen($nama) - 2)).'@'.$ranah;
    }
}
